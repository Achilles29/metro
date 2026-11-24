<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Verification extends MY_Controller
{
    private function request_for($id,$permission='requests.view')
    {
        $user=$this->require_permission($permission);
        $request=$this->metro->request((int)$id);
        if (!$request) show_404();
        if ($this->input->method()==='post' && $request->submission_source==='historical')
            show_error('Arsip SKHP 2025 hanya dapat dibaca.',403);
        if (!$this->metro->can_view_request($user->id,$request->id)) show_error('Akses ditolak.',403);
        if (in_array($permission,array('requests.verify','requests.order_edit'),TRUE)
            && !$this->metro->can($user->id,'requests.view_all')) show_error('Tindakan ini memerlukan akses seluruh permohonan.',403);
        return array($user,$request);
    }

    private function overall($request,$actor,$note=NULL)
    {
        $statuses=array_column($this->db->select('item_status')->get_where('request_items',array('service_request_id'=>$request->id))->result_array(),'item_status');
        $overall=count(array_unique($statuses))===1 ? $statuses[0] : 'in_progress';
        if ($overall!==$request->request_status) {
            $this->db->where('id',$request->id)->update('service_requests',array('request_status'=>$overall,'updated_at'=>date('Y-m-d H:i:s')));
            $this->db->insert('request_status_history',array('service_request_id'=>$request->id,
                'from_status'=>$request->request_status,'to_status'=>$overall,'actor_user_id'=>$actor->id,'note'=>$note));
        }
    }

    public function start($id)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.verify');
        if (!$request->penera_option_id) show_error('Pilih penera sebelum memulai verifikasi.',400);
        $items=$this->metro->items($request->id);$eligible=array_filter($items,function($item){return $item->item_status==='submitted';});
        if (!$eligible || count($eligible)!==count($items)) show_error('Permohonan tidak berada pada tahap awal verifikasi.',400);
        $this->db->trans_begin();
        foreach ($eligible as $item) {
            $this->db->where('id',$item->id)->where('item_status','submitted')->update('request_items',array('item_status'=>'verification'));
            if ($this->db->affected_rows()!==1) {$this->db->trans_rollback();show_error('Status berubah. Muat ulang halaman.',409);}
            $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>'submitted',
                'to_status'=>'verification','actor_user_id'=>$actor->id,'note'=>'Verifikasi permohonan dimulai'));
        }
        $this->overall($request,$actor,'Verifikasi permohonan dimulai');
        $this->metro->audit($actor->id,'verification_start','service_requests',$request->id);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Verifikasi gagal dimulai.',500);}
        $this->db->trans_commit();
        $this->message('Verifikasi dimulai. Isi rincian pemeriksaan.','petugas/permohonan/'.$request->id.'?tab=verification');
    }

    public function review($id)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.verify');
        if (!$request->penera_option_id || $request->verification_finalized_at)
            show_error('Pilih penera terlebih dahulu atau kaji ulang sudah disimpan.',400);
        $checks=$this->db->where('is_active',1)->order_by('sort_order')->order_by('id')->get('verification_checks')->result();
        $items=$this->metro->items($request->id);
        if (!$checks || !$items) show_error('Unsur kaji ulang dan UTTP harus tersedia.',400);
        $suitable=(array)$this->input->post('suitable');
        $unsuitable=(array)$this->input->post('unsuitable');
        $item_ids=array_map(function($item){return (int)$item->id;},$items);
        $decisions=array();
        foreach ($checks as $check) {
            $selected=array_map('intval',is_array($unsuitable[$check->id]??NULL)?$unsuitable[$check->id]:array());
            $selected=array_values(array_unique($selected));
            if (array_diff($selected,$item_ids)) show_error('UTTP kaji ulang tidak valid.',400);
            if (!empty($suitable[$check->id])) {
                if ($selected) show_error('Unsur yang sesuai tidak boleh memiliki UTTP tidak sesuai.',400);
                $decisions[$check->id]=array('result'=>'ok','items'=>array());
            } else {
                if (!$selected) show_error('Pilih UTTP yang tidak sesuai pada setiap unsur yang tidak dicentang.',400);
                $decisions[$check->id]=array('result'=>count($selected)===count($items)?'rejected':'partial','items'=>$selected);
            }
        }
        $note=mb_substr(trim((string)$this->input->post('note',TRUE)),0,1000);
        $has_letter=$this->db->where('service_request_id',$request->id)->where('document_type','application_letter')
            ->where('is_current',1)->count_all_results('service_documents')>0;
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $locked=$this->db->get_where('service_requests',array('id'=>$request->id))->row();
        if ($locked->verification_finalized_at) {$this->db->trans_rollback();show_error('Kaji ulang sudah disimpan.',409);}
        foreach ($items as $item) if (!in_array($item->item_status,array('submitted','verification'),TRUE)) {
            $this->db->trans_rollback();show_error('Status UTTP berubah. Muat ulang halaman.',409);
        }
        foreach ($checks as $check) {
            $decision=$decisions[$check->id];
            $this->db->query('INSERT INTO request_check_results (request_id,check_id,result,note,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE result=VALUES(result),note=VALUES(note),reviewed_by=VALUES(reviewed_by),reviewed_at=NOW()',
                array($request->id,$check->id,$decision['result'],$note ?: NULL,$actor->id));
            $this->db->where('request_id',$request->id)->where('check_id',$check->id)->delete('request_check_units');
            foreach ($items as $item) $this->db->insert('request_check_units',array(
                'request_id'=>$request->id,'check_id'=>$check->id,'item_id'=>$item->id,
                'result'=>in_array((int)$item->id,$decision['items'],TRUE)?'rejected':'ok'));
        }
        $statuses=array();$accepted=0;$rejected=0;
        foreach ($items as $item) {
            $failed=FALSE;
            foreach ($decisions as $decision) if (in_array((int)$item->id,$decision['items'],TRUE)) {$failed=TRUE;break;}
            $to=$failed?'not_suitable':($request->service_mode==='field' && $has_letter?'scheduled':'testing');
            $statuses[]=$to;if ($failed) $rejected++;else $accepted++;
            $this->db->where('id',$item->id)->where('item_status',$item->item_status)->update('request_items',array(
                'item_status'=>$to,'verification_decision'=>$failed?'rejected':'accepted','updated_at'=>date('Y-m-d H:i:s')));
            if ($this->db->affected_rows()!==1) {$this->db->trans_rollback();show_error('Status UTTP berubah.',409);}
            $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>$item->item_status,
                'to_status'=>$to,'actor_user_id'=>$actor->id,'note'=>$note ?: 'Hasil kaji ulang'));
        }
        $overall=$accepted===0?'closed':(count(array_unique($statuses))===1?$statuses[0]:'in_progress');
        $this->db->where('id',$request->id)->update('service_requests',array(
            'verification_finalized_at'=>date('Y-m-d H:i:s'),'completed_at'=>$accepted===0?date('Y-m-d H:i:s'):NULL,
            'request_status'=>$overall,'updated_at'=>date('Y-m-d H:i:s')));
        $this->db->insert('request_status_history',array('service_request_id'=>$request->id,
            'from_status'=>$request->request_status,'to_status'=>$overall,'actor_user_id'=>$actor->id,
            'note'=>'Kaji ulang: '.$accepted.' sesuai, '.$rejected.' tidak sesuai'));
        $this->metro->audit($actor->id,'review_finalized','service_requests',$request->id,
            $accepted.' sesuai, '.$rejected.' tidak sesuai');
        $this->metro->notify($request->user_id,'Hasil kaji ulang permohonan',
            $accepted.' UTTP sesuai dan '.$rejected.' UTTP tidak sesuai.','permohonan/'.$request->id);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Kaji ulang gagal disimpan.',500);}
        $this->db->trans_commit();
        if ($accepted===0) {
            $this->load->model('Wa_model','wa');
            $this->wa->queue_notification($this->metro->user($request->user_id),
                'Setimbang: seluruh UTTP pada order '.$request->request_no.' tidak sesuai hasil kaji ulang.');
            $this->load->library('metro_reports');
            try {$this->metro_reports->sync($request->id);}
            catch (RuntimeException $ex) {log_message('error','Sinkronisasi laporan order '.$request->id.': '.$ex->getMessage());}
        }
        $this->message('Kaji ulang tersimpan. '.$accepted.' UTTP sesuai, '.$rejected.' tidak sesuai.',
            'petugas/permohonan/'.$request->id.'?tab=verification');
    }

    public function reopen_review($id)
    {
        $this->reset_review($id,FALSE);
    }

    public function cancel_review($id)
    {
        $this->reset_review($id,TRUE);
    }

    private function reset_review($id,$clear)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.verify');
        $url='petugas/permohonan/'.$request->id.'?tab=verification';
        $reason=trim((string)$this->input->post('reason',TRUE));
        if (mb_strlen($reason)<5 || mb_strlen($reason)>255) { $this->fail('Isi alasan perubahan kaji ulang (5–255 karakter).',$url);return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $current=$this->metro->request($request->id);
        $items=$this->metro->items($request->id);
        if (!$current->verification_finalized_at) {
            if (!$clear || !$this->db->where('request_id',$request->id)->count_all_results('request_check_results')) {
                $this->db->trans_rollback();$this->fail('Tidak ada hasil kaji ulang untuk diubah atau dibatalkan.',$url);return;
            }
            foreach ($items as $item) if (!in_array($item->item_status,array('submitted','verification'),TRUE)) {
                $this->db->trans_rollback();$this->fail('Status UTTP sudah berlanjut.',$url);return;
            }
        } else {
            $blocker=$this->metro->review_change_blocker($current,$items);
            if ($blocker) { $this->db->trans_rollback();$this->fail($blocker,$url);return; }
            $now=date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $this->db->where('id',$item->id)->where('item_status',$item->item_status)
                    ->update('request_items',array('item_status'=>'verification','verification_decision'=>NULL,
                        'tested_at'=>NULL,'updated_at'=>$now));
                if ($this->db->affected_rows()!==1) { $this->db->trans_rollback();show_error('Status UTTP berubah. Muat ulang halaman.',409); }
                $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>$item->item_status,
                    'to_status'=>'verification','actor_user_id'=>$actor->id,'note'=>($clear?'Pembatalan':'Perubahan').' kaji ulang: '.$reason));
            }
            $this->db->where('id',$request->id)->update('service_requests',array(
                'verification_finalized_at'=>NULL,'completed_at'=>NULL,'request_status'=>'verification','updated_at'=>$now));
            $this->db->insert('request_status_history',array('service_request_id'=>$request->id,
                'from_status'=>$current->request_status,'to_status'=>'verification','actor_user_id'=>$actor->id,
                'note'=>($clear?'Pembatalan':'Perubahan').' kaji ulang: '.$reason));
        }
        if ($clear) {
            $this->db->where('request_id',$request->id)->delete('request_check_units');
            $this->db->where('request_id',$request->id)->delete('request_check_results');
        }
        $this->metro->audit($actor->id,$clear?'review_cancelled':'review_reopened','service_requests',$request->id,$reason);
        $this->metro->notify($request->user_id,'Kaji ulang permohonan',
            $clear?'Kaji ulang dibatalkan untuk diperiksa kembali.':'Kaji ulang sedang diperbaiki oleh petugas.','permohonan/'.$request->id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();show_error('Kaji ulang gagal diperbarui.',500); }
        $this->db->trans_commit();
        $this->message($clear?'Kaji ulang dibatalkan.':'Kaji ulang dibuka untuk diubah. Periksa hasil lalu simpan kembali.',$url);
    }

    public function save_check($id,$check_id)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.verify');
        if ($request->verification_finalized_at) show_error('Hasil verifikasi sudah ditetapkan.',400);
        if (!$request->penera_option_id) show_error('Pilih penera terlebih dahulu.',400);
        $check=$this->db->get_where('verification_checks',array('id'=>(int)$check_id,'is_active'=>1))->row();
        if (!$check) show_404();
        $items=$this->metro->items($request->id);
        if (!$items || count(array_filter($items,function($i){return $i->item_status==='verification';}))!==count($items))
            show_error('Mulai verifikasi seluruh unit terlebih dahulu.',400);
        $result=(string)$this->input->post('result',TRUE);$note=trim((string)$this->input->post('note',TRUE));
        if (!in_array($result,array('ok','rejected','partial'),TRUE) || mb_strlen($note)>1000
            || ($result!=='ok' && mb_strlen($note)<8)) show_error('Isi hasil dan alasan pemeriksaan dengan benar.',400);
        $posted=$this->input->post('unit_decision');$decisions=array();$accepted=0;$rejected=0;
        foreach ($items as $item) {
            $value=$result==='partial' && is_array($posted) ? ($posted[$item->id] ?? '') : ($result==='ok'?'ok':'rejected');
            if (!in_array($value,array('ok','rejected'),TRUE)) show_error('Pilih keputusan untuk setiap UTTP.',400);
            $decisions[$item->id]=$value;if ($value==='ok') $accepted++;else $rejected++;
        }
        if ($result==='partial' && (!$accepted || !$rejected)) show_error('Terima sebagian membutuhkan UTTP yang lolos dan tidak lolos.',400);
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $current=$this->db->get_where('service_requests',array('id'=>$request->id))->row();
        if ($current->verification_finalized_at) {$this->db->trans_rollback();show_error('Verifikasi sudah ditetapkan.',409);}
        $this->db->query('INSERT INTO request_check_results (request_id,check_id,result,note,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE result=VALUES(result),note=VALUES(note),reviewed_by=VALUES(reviewed_by),reviewed_at=NOW()',
            array($request->id,$check->id,$result,$note ?: NULL,$actor->id));
        $this->db->where('request_id',$request->id)->where('check_id',$check->id)->delete('request_check_units');
        foreach ($decisions as $item_id=>$value) $this->db->insert('request_check_units',array(
            'request_id'=>$request->id,'check_id'=>$check->id,'item_id'=>$item_id,'result'=>$value));
        $this->metro->audit($actor->id,'verification_check','request_check_results',$check->id,$check->label.' · '.$result);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Unsur kaji ulang gagal disimpan.',500);}
        $this->db->trans_commit();
        $this->message('Unsur kaji ulang tersimpan.','petugas/permohonan/'.$request->id.'?tab=verification');
    }

    public function finalize($id)
    {
        show_error('Gunakan formulir Kaji ulang pada detail permohonan.',410);
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.verify');
        $note=mb_substr(trim((string)$this->input->post('note',TRUE)),0,1000);
        if (!$request->penera_option_id || $request->verification_finalized_at) show_error('Penera belum dipilih atau hasil sudah ditetapkan.',400);
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $locked=$this->db->get_where('service_requests',array('id'=>$request->id))->row();
        if ($locked->verification_finalized_at) {$this->db->trans_rollback();show_error('Hasil sudah ditetapkan.',409);}
        $checks=$this->db->get_where('verification_checks',array('is_active'=>1))->result();
        $items=$this->metro->items($request->id);
        if (!$checks || !$items) {$this->db->trans_rollback();show_error('Master kaji ulang atau UTTP belum tersedia.',400);}
        foreach ($items as $item) if ($item->item_status!=='verification') {$this->db->trans_rollback();show_error('Semua UTTP harus berada pada tahap verifikasi.',400);}
        $results=$this->db->get_where('request_check_results',array('request_id'=>$request->id))->result();
        $by_check=array();foreach ($results as $r) $by_check[$r->check_id]=$r;
        foreach ($checks as $check) if (!isset($by_check[$check->id])) {$this->db->trans_rollback();show_error('Semua rincian verifikasi aktif harus diisi.',400);}
        $unit_rows=$this->db->get_where('request_check_units',array('request_id'=>$request->id))->result();
        $decisions=array();foreach ($unit_rows as $r) $decisions[$r->check_id][$r->item_id]=$r->result;
        $bad=0;$good=0;
        foreach ($items as $item) {
            $accepted=TRUE;
            foreach ($checks as $check) {
                if (!isset($decisions[$check->id][$item->id])) {$this->db->trans_rollback();show_error('Keputusan UTTP belum lengkap.',400);}
                if ($decisions[$check->id][$item->id]==='rejected') $accepted=FALSE;
            }
            $to=$accepted?($request->service_mode==='office'?'testing':'scheduled'):'rejected';
            if ($accepted) $good++;else $bad++;
            $this->db->where('id',$item->id)->where('item_status','verification')->update('request_items',array(
                'item_status'=>$to,'verification_decision'=>$accepted?'accepted':'rejected','updated_at'=>date('Y-m-d H:i:s')));
            if ($this->db->affected_rows()!==1) {$this->db->trans_rollback();show_error('Status unit berubah. Muat ulang halaman.',409);}
            $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>'verification',
                'to_status'=>$to,'actor_user_id'=>$actor->id,'note'=>$note ?: ($accepted?'Lolos verifikasi':'Tidak lolos verifikasi')));
        }
        $this->db->where('id',$request->id)->update('service_requests',array('verification_finalized_at'=>date('Y-m-d H:i:s')));
        $this->overall($request,$actor,$note ?: 'Hasil verifikasi: '.$good.' lolos, '.$bad.' tidak lolos');
        $this->metro->audit($actor->id,'verification_finalized','service_requests',$request->id,$good.' lolos, '.$bad.' tidak lolos');
        $this->metro->notify($request->user_id,'Hasil verifikasi permohonan',$good.' UTTP lolos, '.$bad.' UTTP tidak lolos verifikasi.','permohonan/'.$request->id);
        if ($good===0) {
            $this->load->model('Wa_model','wa');
            $this->wa->queue_notification($this->metro->user($request->user_id),
                'Setimbang: permohonan '.$request->request_no.' tidak lolos verifikasi dan dibatalkan. Periksa alasan pada akun Anda.');
        }
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Penetapan verifikasi gagal.',500);}
        $this->db->trans_commit();
        $this->message('Kaji ulang tersimpan. Pilih CTT yang akan dipakai pada jadwal tera.','petugas/permohonan/'.$request->id.'?tab=ctt');
    }

    public function criteria()
    {
        $this->require_permission('masters.view');
        $rows=$this->db->order_by('sort_order')->order_by('id')->get('verification_checks')->result();
        $this->page('staff/master/verification',array('title'=>'Master kaji ulang','rows'=>$rows));
    }

    public function save_criteria($id=0)
    {
        $this->post_only();$actor=$this->require_permission($id?'masters.update':'masters.create');
        $row=$id?$this->db->get_where('verification_checks',array('id'=>(int)$id))->row():NULL;
        if ($id && !$row) show_404();
        $label=trim((string)$this->input->post('label',TRUE));
        $description=trim((string)$this->input->post('description',TRUE));
        $sort=(int)$this->input->post('sort_order');
        if (mb_strlen($label)<3 || mb_strlen($label)>190 || mb_strlen($description)>500 || $sort<0 || $sort>9999)
            $this->fail('Periksa uraian, keterangan, dan urutan.','petugas/master/verifikasi');
        $data=array('label'=>$label,'description'=>$description ?: NULL,'sort_order'=>$sort,'updated_at'=>date('Y-m-d H:i:s'));
        if ($row) $this->db->where('id',$id)->update('verification_checks',$data);
        else {$this->db->insert('verification_checks',$data);$id=$this->db->insert_id();}
        $this->metro->audit($actor->id,'verification_master_save','verification_checks',$id);
        $this->message('Master kaji ulang tersimpan.','petugas/master/verifikasi');
    }

    public function toggle_criteria($id)
    {
        $this->post_only();$actor=$this->require_permission('masters.status');
        $row=$this->db->get_where('verification_checks',array('id'=>(int)$id))->row();if (!$row) show_404();
        $this->db->where('id',$row->id)->update('verification_checks',array('is_active'=>(int)!$row->is_active));
        $this->metro->audit($actor->id,'verification_master_toggle','verification_checks',$row->id);
        $this->message('Status rincian verifikasi diperbarui.','petugas/master/verifikasi');
    }

    public function schedule($id)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.order_edit');
        $start=(string)$this->input->post('tera_start_date',TRUE);
        $end=(string)$this->input->post('tera_end_date',TRUE);
        $valid=function($date) { $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $parsed && $parsed->format('Y-m-d')===$date; };
        $url='petugas/permohonan/'.$request->id.'?tab=verification';
        if (!$valid($start) || !$valid($end) || $end<$start) { $this->fail('Periksa tanggal mulai dan selesai jadwal tera.',$url);return; }
        if ((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>365) {
            $this->fail('Rentang jadwal tera maksimal satu tahun.',$url);return;
        }
        if ($request->completed_at) { $this->fail('Jadwal permohonan yang sudah selesai tidak dapat diubah.',$url);return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        if ($this->db->query('SELECT COUNT(*) AS total FROM request_ctt_daily_loans dl '
            .'JOIN request_ctts rc ON rc.id=dl.request_ctt_id WHERE rc.request_id=? AND rc.cancelled_at IS NULL '
            .'AND (dl.loan_date<? OR dl.loan_date>?)',array($request->id,$start,$end))->row()->total>0) {
            $this->db->trans_rollback();$this->fail('Jadwal baru harus mencakup semua tanggal peminjaman yang sudah tercatat.',$url);return;
        }
        $this->db->where('id',$request->id)->update('service_requests',array(
            'tera_start_date'=>$start,'tera_end_date'=>$end,'updated_at'=>date('Y-m-d H:i:s')));
        $this->db->where('request_id',$request->id)
            ->where('cancelled_at IS NULL',NULL,FALSE)
            ->update('request_ctts',array('schedule_start_date'=>$start,'schedule_end_date'=>$end));
        $this->metro->audit($actor->id,'tera_schedule','service_requests',$request->id,$start.' – '.$end);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Jadwal gagal disimpan.',500);}
        $this->db->trans_commit();
        $this->message('Jadwal tera disimpan. Stok CTT diperiksa saat peminjaman.',$url);
    }

    public function ctt_stock($id)
    {
        list($actor,$request)=$this->request_for($id,'requests.order_edit');
        $option=$this->db->get_where('master_options',array('id'=>(int)$this->input->get('ctt_option_id'),
            'group_code'=>'ctt','is_active'=>1))->row();
        if (!$option) show_404();
        $this->load->model('Ctt_inventory_model','ctt_inventory');
        $available=$this->ctt_inventory->availability($option->id,date('Y-m-d'),date('Y-m-d'));
        $this->output->set_content_type('application/json','utf-8')->set_output(json_encode(array(
            'available'=>$available,'start'=>$request->tera_start_date,'end'=>$request->tera_end_date)));
    }

    public function add_ctt($id)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.order_edit');
        if (!$request->tera_start_date || !$request->tera_end_date) {
            $this->fail('Atur jadwal tera pada tab Kaji ulang sebelum memilih CTT.','petugas/permohonan/'.$id.'?tab=verification');return;
        }
        if (!$request->penera_option_id) show_error('Pilih penera sebelum menambah CTT.',400);
        if (!$request->verification_finalized_at && !$this->db->where('service_request_id',$request->id)
            ->where_in('item_status',array('scheduled','testing','review','valid','invalid','returned','not_suitable','completed','rejected','cancelled'))
            ->count_all_results('request_items')) show_error('Simpan kaji ulang sebelum menambahkan CTT.',400);
        $option=$this->db->get_where('master_options',array('id'=>(int)$this->input->post('ctt_option_id'),
            'group_code'=>'ctt','is_active'=>1))->row();if (!$option) show_error('Pilih CTT aktif.',400);
        $units=filter_var($this->input->post('asset_units'),FILTER_VALIDATE_INT);
        if ($units===FALSE || $units<1 || $units>999999) show_error('Jumlah aset CTT harus lebih dari nol.',400);
        $this->db->trans_begin();
        $locked=$this->db->query('SELECT id,tera_start_date,tera_end_date,completed_at FROM service_requests WHERE id=? FOR UPDATE',array($request->id))->row();
        $this->db->query('SELECT id FROM master_options WHERE id=? FOR UPDATE',array($option->id));
        if ($locked->completed_at) {$this->db->trans_rollback();$this->fail('Permohonan yang sudah selesai tidak dapat ditambah CTT.','petugas/permohonan/'.$id.'?tab=ctt');return;}
        if (!$locked->tera_start_date || !$locked->tera_end_date) { $this->db->trans_rollback();$this->fail('Jadwal tera belum tersedia.','petugas/permohonan/'.$id.'?tab=verification');return; }
        $existing=$this->db->query('SELECT * FROM request_ctts WHERE request_id=? AND ctt_option_id=? FOR UPDATE',array($request->id,$option->id))->row();
        if ($existing && !$existing->cancelled_at) { $this->db->trans_rollback();$this->fail('CTT ini sudah tercatat pada permohonan.','petugas/permohonan/'.$id.'?tab=ctt');return; }
        if ($existing && (int)$existing->asset_units!==$units
            && $this->db->where('request_ctt_id',$existing->id)->count_all_results('request_ctt_daily_loans')) {
            $this->db->trans_rollback();$this->fail('Jumlah aset CTT yang pernah dipinjam harus tetap '.(int)$existing->asset_units.' agar riwayatnya tidak berubah.','petugas/permohonan/'.$id.'?tab=ctt');return;
        }
        if ($existing) {
            $this->db->where('id',$existing->id)->update('request_ctts',array('asset_units'=>$units,'cancelled_at'=>NULL,
                'cancelled_by'=>NULL,'cancel_reason'=>NULL,'schedule_start_date'=>$locked->tera_start_date,
                'schedule_end_date'=>$locked->tera_end_date,'added_by'=>$actor->id));
            $entry=$existing->id;
        } else {
            $this->db->insert('request_ctts',array('request_id'=>$request->id,'ctt_option_id'=>$option->id,'asset_units'=>$units,'added_by'=>$actor->id,
                'schedule_start_date'=>$locked->tera_start_date,'schedule_end_date'=>$locked->tera_end_date));
            $entry=$this->db->insert_id();
        }
        $this->metro->audit($actor->id,'ctt_add','request_ctts',$entry,$option->option_code);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('CTT gagal ditambahkan.',500);}
        $this->db->trans_commit();
        $this->message('CTT dicatat sebagai pilihan. Stok aset baru dipakai saat ditandai pinjam.','petugas/permohonan/'.$request->id.'?tab=ctt');
    }

    public function borrow_ctt($id)
    {
        $this->post_only();$entry=$this->db->get_where('request_ctts',array('id'=>(int)$id))->row();if (!$entry) show_404();
        list($actor,$request)=$this->request_for($entry->request_id,'requests.order_edit');
        $date=(string)$this->input->post('loan_date',TRUE);$today=date('Y-m-d');
        $url='petugas/permohonan/'.$request->id.'?tab=ctt';
        if ($request->completed_at) { $this->fail('Permohonan sudah selesai; CTT tidak dapat dipinjam lagi.',$url);return; }
        if ($date!==$today) { $this->fail('Peminjaman CTT hanya dapat dicatat pada hari pelaksanaan.',$url);return; }
        $this->load->model('Ctt_inventory_model','ctt_inventory');
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $this->db->query('SELECT id FROM master_options WHERE id=? FOR UPDATE',array($entry->ctt_option_id));
        $entry=$this->db->query('SELECT * FROM request_ctts WHERE id=? FOR UPDATE',array($entry->id))->row();
        if ($entry->cancelled_at || !$entry->schedule_start_date || $date<$entry->schedule_start_date || $date>$entry->schedule_end_date) {
            $this->db->trans_rollback();$this->fail('CTT tidak aktif pada hari pelaksanaan ini.',$url);return;
        }
        if ($this->db->where('request_ctt_id',$entry->id)->where('loan_date',$date)->count_all_results('request_ctt_daily_loans')) {
            $this->db->trans_rollback();$this->fail('Peminjaman CTT pada tanggal ini sudah tercatat.',$url);return;
        }
        if ($this->db->where('request_ctt_id',$entry->id)->where('returned_at IS NULL',NULL,FALSE)
            ->count_all_results('request_ctt_daily_loans')) {
            $this->db->trans_rollback();$this->fail('Kembalikan CTT dari hari sebelumnya terlebih dahulu.',$url);return;
        }
        $available=$this->ctt_inventory->availability($entry->ctt_option_id,$date,$date);
        if ($available===NULL || $available<(int)$entry->asset_units) {
            $this->db->trans_rollback();$this->fail('Stok CTT tidak tersedia pada hari ini.',$url);return;
        }
        $now=date('Y-m-d H:i:s');
        $this->db->insert('request_ctt_daily_loans',array('request_ctt_id'=>$entry->id,
            'loan_date'=>$date,'borrowed_at'=>$now,'borrowed_by'=>$actor->id));
        $loan_id=$this->db->insert_id();
        if (!$entry->borrowed_at) $this->db->where('id',$entry->id)->update('request_ctts',array('borrowed_at'=>$now,'borrowed_by'=>$actor->id));
        $this->metro->audit($actor->id,'ctt_borrow','request_ctt_daily_loans',$loan_id,$date);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();show_error('Peminjaman CTT gagal.',500); }
        $this->db->trans_commit();
        $this->message('CTT dipinjam untuk tanggal '.tanggal_id($date).'.',$url);
    }

    public function return_ctt($id)
    {
        $this->post_only();$entry=$this->db->get_where('request_ctts',array('id'=>(int)$id))->row();if (!$entry) show_404();
        list($actor,$request)=$this->request_for($entry->request_id,'requests.order_edit');
        $date=(string)$this->input->post('loan_date',TRUE);
        $view_date=(string)$this->input->post('view_date',TRUE);
        $url=$this->input->post('return_to',TRUE)==='ctt' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$view_date)
            ? 'petugas/ctt?date='.$view_date : 'petugas/permohonan/'.$request->id.'?tab=ctt';
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $this->db->query('SELECT id FROM master_options WHERE id=? FOR UPDATE',array($entry->ctt_option_id));
        $entry=$this->db->query('SELECT * FROM request_ctts WHERE id=? FOR UPDATE',array($entry->id))->row();
        $loan=$this->db->query('SELECT * FROM request_ctt_daily_loans WHERE request_ctt_id=? AND loan_date=? FOR UPDATE',
            array($entry->id,$date))->row();
        if ($entry->cancelled_at || !$loan || $loan->returned_at) {
            $this->db->trans_rollback();$this->fail('Peminjaman harian tidak ditemukan atau sudah dikembalikan.',$url);return;
        }
        $now=date('Y-m-d H:i:s');
        $this->db->where('id',$loan->id)->where('returned_at IS NULL',NULL,FALSE)
            ->update('request_ctt_daily_loans',array('returned_at'=>$now,'returned_by'=>$actor->id));
        if ($this->db->affected_rows()!==1) { $this->db->trans_rollback();show_error('Peminjaman berubah. Muat ulang halaman.',409); }
        $this->db->where('id',$entry->id)->update('request_ctts',array('returned_at'=>$now,'returned_by'=>$actor->id));
        $this->metro->audit($actor->id,'ctt_return','request_ctt_daily_loans',$loan->id,$date);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();show_error('Pengembalian CTT gagal.',500); }
        $this->db->trans_commit();
        $option=$this->db->get_where('master_options',array('id'=>$entry->ctt_option_id))->row();
        $this->sync_ctt($loan->id,$request,$option,$url);
    }

    public function delete_ctt($id)
    {
        $this->post_only();
        $row=$this->db->get_where('request_ctts',array('id'=>(int)$id))->row();
        if (!$row) show_404();
        list($actor,$request)=$this->request_for($row->request_id,'requests.order_edit');
        $url='petugas/permohonan/'.$request->id.'?tab=ctt';
        $reason=trim((string)$this->input->post('reason',TRUE));
        if (mb_strlen($reason)<5 || mb_strlen($reason)>255) { $this->fail('Isi alasan penghapusan CTT (5–255 karakter).',$url);return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $this->db->query('SELECT id FROM master_options WHERE id=? FOR UPDATE',array($row->ctt_option_id));
        $row=$this->db->query('SELECT * FROM request_ctts WHERE id=? FOR UPDATE',array($row->id))->row();
        if ($row->cancelled_at) { $this->db->trans_rollback();$this->fail('CTT sudah dihapus.',$url);return; }
        if ($this->db->where('service_request_id',$request->id)
            ->where_in('item_status',array('valid','invalid','returned','completed','rejected','cancelled'))
            ->count_all_results('request_items')
            || $this->db->where('service_request_id',$request->id)
                ->where_in('document_type',array('cerapan','skhp'))->where('is_current',1)
                ->count_all_results('service_documents')) {
            $this->db->trans_rollback();$this->fail('CTT tidak dapat dihapus setelah hasil pengujian atau dokumen dibuat.',$url);return;
        }
        $open_loans=$this->db->query('SELECT id FROM request_ctt_daily_loans WHERE request_ctt_id=? AND returned_at IS NULL FOR UPDATE',array($row->id))->result();
        if ($open_loans && !$this->input->post('asset_confirmed')) {
            $this->db->trans_rollback();$this->fail('Pastikan aset CTT belum digunakan atau sudah kembali sebelum menghapus.',$url);return;
        }
        $now=date('Y-m-d H:i:s');
        $changes=array('cancelled_at'=>$now,'cancelled_by'=>$actor->id,'cancel_reason'=>$reason);
        if ($open_loans) {
            $this->db->where('request_ctt_id',$row->id)->where('returned_at IS NULL',NULL,FALSE)
                ->update('request_ctt_daily_loans',array('returned_at'=>$now,'returned_by'=>$actor->id));
            $changes['returned_at']=$now;$changes['returned_by']=$actor->id;
        }
        $this->db->where('id',$row->id)->where('cancelled_at IS NULL',NULL,FALSE)->update('request_ctts',$changes);
        if ($this->db->affected_rows()!==1) { $this->db->trans_rollback();show_error('CTT berubah. Muat ulang halaman.',409); }
        $this->metro->audit($actor->id,'ctt_cancel','request_ctts',$row->id,$reason);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();show_error('CTT gagal dihapus.',500); }
        $this->db->trans_commit();
        $this->message('CTT dihapus dari permohonan. Riwayat peminjaman tetap tersimpan.',$url);
    }

    private function sync_ctt($entry,$request,$option,$url=NULL)
    {
        if ($url===NULL) $url='petugas/permohonan/'.$request->id.'?tab=ctt';
        $settings=$this->db->get_where('google_settings',array('id'=>1))->row();
        if (!$settings->spreadsheet_id) $this->message('CTT dikembalikan. Spreadsheet belum dikonfigurasi.',$url);
        $this->load->library('google_workspace');
        $ctt=$this->db->get_where('request_ctt_daily_loans',array('id'=>$entry))->row();
        if (!$ctt->returned_at) show_error('CTT belum dikembalikan.',400);
        try {$this->google_workspace->append_ctt($request,$ctt,$option);
            $this->db->where('id',$entry)->update('request_ctt_daily_loans',array('sheet_synced_at'=>date('Y-m-d H:i:s'),'sheet_error'=>NULL));
            $this->message('CTT dikembalikan dan dikirim ke laporan CTT.',$url);
        } catch (RuntimeException $ex) {
            $this->db->where('id',$entry)->update('request_ctt_daily_loans',array('sheet_error'=>mb_substr($ex->getMessage(),0,500)));
            $this->message('CTT dikembalikan. Sinkronisasi laporan tertunda; coba lagi dari tab CTT permohonan.',$url);
        }
    }

    public function retry_ctt($id)
    {
        $this->post_only();$actor=$this->require_permission('requests.order_edit');
        $row=$this->db->select('dl.*,rc.request_id,rc.cancelled_at,o.option_code,o.option_label')
            ->from('request_ctt_daily_loans dl')->join('request_ctts rc','rc.id=dl.request_ctt_id')
            ->join('master_options o','o.id=rc.ctt_option_id')->where('dl.id',(int)$id)->get()->row();
        if (!$row) show_404();list($actor,$request)=$this->request_for($row->request_id,'requests.order_edit');
        if ($row->cancelled_at || $row->sheet_synced_at || !$row->returned_at) show_error('CTT sudah dihapus, tersinkron, atau belum dikembalikan.',400);
        $this->sync_ctt($row->id,$request,$row);
    }

    public function retry_ctt_legacy($id)
    {
        $this->post_only();$entry=$this->db->get_where('request_ctts',array('id'=>(int)$id))->row();
        if (!$entry) show_404();
        list($actor,$request)=$this->request_for($entry->request_id,'requests.order_edit');
        $this->fail('Pilih tanggal peminjaman yang akan dikirim dari tab CTT.',
            'petugas/permohonan/'.$request->id.'?tab=ctt');
    }

    public function photo($id)
    {
        $this->post_only();list($actor,$request)=$this->request_for($id,'requests.verify');
        if (!$request->completed_at) show_error('Dokumentasi foto dapat diunggah setelah seluruh UTTP selesai diproses.',400);
        $item_id=(int)$this->input->post('item_id');$caption=trim((string)$this->input->post('caption',TRUE));
        if (mb_strlen($caption)>255) show_error('Keterangan foto terlalu panjang.',400);
        if ($item_id && !$this->db->get_where('request_items',array('id'=>$item_id,'service_request_id'=>$request->id))->row()) show_404();
        $this->load->library('private_files');
        try {$file=$this->private_files->save_image('photo');}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/permohonan/'.$request->id.'?tab=process#dokumentasi-kegiatan');return;}
        $this->db->insert('request_photos',array('request_id'=>$request->id,'item_id'=>$item_id ?: NULL,
            'storage_key'=>$file['storage_key'],'original_name'=>$file['original_name'],
            'file_size'=>$file['file_size'],'caption'=>$caption ?: NULL,'uploaded_by'=>$actor->id));
        $this->metro->audit($actor->id,'verification_photo','request_photos',$this->db->insert_id());
        $this->message('Foto dokumentasi tersimpan. Anda dapat menambahkan foto berikutnya.','petugas/permohonan/'.$request->id.'?tab=process#dokumentasi-kegiatan');
    }

    public function photo_file($id)
    {
        $actor=$this->require_permission('requests.view');
        $row=$this->db->get_where('request_photos',array('id'=>(int)$id,'deleted_at'=>NULL))->row();if (!$row) show_404();
        $request=$this->metro->request($row->request_id);
        if (!$request || !$this->metro->can_view_request($actor->id,$request->id)) show_error('Akses ditolak.',403);
        if (!$this->metro->can($actor->id,'requests.view_all')
            && $row->item_id && !$this->metro->assigned($row->item_id,$actor->id)) show_error('Akses ditolak.',403);
        $this->load->library('private_files');$path=$this->private_files->image_path($row->storage_key);
        if (!$path) show_404();
        header('Content-Type: '.(substr($row->storage_key,-4)==='.png'?'image/png':'image/jpeg'));
        header('Content-Disposition: inline; filename="dokumentasi-'.$row->id.'"');
        header('X-Content-Type-Options: nosniff');readfile($path);exit;
    }

    public function delete_photo($id)
    {
        $this->post_only();$actor=$this->require_permission('requests.verify');
        $photo=$this->db->get_where('request_photos',array('id'=>(int)$id,'deleted_at'=>NULL))->row();
        if (!$photo) show_404();
        list($actor,$request)=$this->request_for($photo->request_id,'requests.verify');
        if (!$request->completed_at) show_error('Dokumentasi foto dapat diubah setelah seluruh UTTP selesai diproses.',400);
        $this->db->where('id',$photo->id)->where('deleted_at IS NULL',NULL,FALSE)->update('request_photos',array(
            'deleted_at'=>date('Y-m-d H:i:s'),'deleted_by'=>$actor->id));
        $this->metro->audit($actor->id,'verification_photo_delete','request_photos',$photo->id);
        $this->message('Foto dokumentasi dihapus.','petugas/permohonan/'.$request->id.'?tab=process#dokumentasi-kegiatan');
    }

    private function review_pdf_bytes($request)
    {
        require_once FCPATH.'vendor/autoload.php';
        $checks=$this->db->select('vc.id,vc.label,r.result')->from('request_check_results r')
            ->join('verification_checks vc','vc.id=r.check_id')->where('r.request_id',$request->id)
            ->order_by('vc.sort_order')->order_by('vc.id')->get()->result();
        if (!$checks) show_error('Rincian kaji ulang untuk order lama belum tersedia.',400);
        $labels=array();foreach ($checks as $check) $labels[$check->id]=$check->label;
        $failed=$this->db->get_where('request_check_units',array('request_id'=>$request->id,'result'=>'rejected'))->result();
        $reasons=array();foreach ($failed as $row) if (isset($labels[$row->check_id])) $reasons[$row->item_id][]=$labels[$row->check_id];
        $exceptions=array();foreach ($this->metro->items($request->id) as $item)
            if (!empty($reasons[$item->id])) $exceptions[]=array('item'=>$item,'labels'=>$reasons[$item->id]);
        $html=$this->load->view('staff/verification_pdf',array('request'=>$request,'checks'=>$checks,'exceptions'=>$exceptions),TRUE);
        $pdf=new Dompdf\Dompdf();$pdf->loadHtml($html);$pdf->setPaper('A4');$pdf->render();
        return $pdf->output();
    }

    public function pdf($id)
    {
        list($actor,$request)=$this->request_for($id,'requests.view');
        if (!$this->metro->can($actor->id,'requests.view_all') || !$request->verification_finalized_at)
            show_error('Kaji ulang belum tersedia.',403);
        if (!$this->db->where('request_id',$request->id)->count_all_results('request_check_results'))
            show_error('Rincian kaji ulang untuk order lama belum tersedia.',400);
        $this->metro->audit($actor->id,'review_pdf','service_requests',$request->id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: '.($this->input->get('download')==='1'?'attachment':'inline').'; filename="kaji-ulang-'.$request->id.'.pdf"');
        echo $this->review_pdf_bytes($request);
    }

    public function combined_pdf($id)
    {
        list($actor,$request)=$this->request_for($id,'requests.view');
        if (!$this->metro->can($actor->id,'requests.view_all') || !$request->verification_finalized_at)
            show_error('Kaji ulang belum tersedia.',403);
        if (!$this->db->where('request_id',$request->id)->count_all_results('request_check_results'))
            show_error('Rincian kaji ulang untuk order lama belum tersedia.',400);
        require_once FCPATH.'vendor/autoload.php';
        $order_html=$this->load->view('requests/receipt',array('request'=>$request,'items'=>$this->metro->items($request->id)),TRUE);
        $checks=$this->db->select('vc.id,vc.label,r.result')->from('request_check_results r')
            ->join('verification_checks vc','vc.id=r.check_id')->where('r.request_id',$request->id)
            ->order_by('vc.sort_order')->order_by('vc.id')->get()->result();
        $labels=array();foreach ($checks as $check) $labels[$check->id]=$check->label;
        $failed=$this->db->get_where('request_check_units',array('request_id'=>$request->id,'result'=>'rejected'))->result();
        $reasons=array();foreach ($failed as $row) if (isset($labels[$row->check_id])) $reasons[$row->item_id][]=$labels[$row->check_id];
        $exceptions=array();foreach ($this->metro->items($request->id) as $item)
            if (!empty($reasons[$item->id])) $exceptions[]=array('item'=>$item,'labels'=>$reasons[$item->id]);
        $review_html=$this->load->view('staff/verification_pdf',array('request'=>$request,'checks'=>$checks,'exceptions'=>$exceptions),TRUE);
        preg_match('/<body[^>]*>(.*?)<\/body>/is',$order_html,$order_body);
        preg_match('/<body[^>]*>(.*?)<\/body>/is',$review_html,$review_body);
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is',$order_html.' '.$review_html,$styles);
        if (empty($order_body[1]) || empty($review_body[1])) show_error('PDF gabungan gagal dibuat.',500);
        $html='<!doctype html><html lang="id"><head><meta charset="utf-8"><style>'.implode("\n",$styles[1])
            .'</style></head><body><div style="page-break-after:always">'.$order_body[1].'</div><div>'.$review_body[1].'</div></body></html>';
        $pdf=new Dompdf\Dompdf();$pdf->loadHtml($html);$pdf->setPaper('A4');$pdf->render();
        $this->metro->audit($actor->id,'order_review_pdf','service_requests',$request->id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: '.($this->input->get('download')==='1'?'attachment':'inline').'; filename="order-dan-kaji-ulang-'.$request->id.'.pdf"');
        echo $pdf->output();
    }

    public function drive($doc_id)
    {
        $this->post_only();$actor=$this->require_permission('documents.cerapan.upload');
        $doc=$this->db->get_where('service_documents',array('id'=>(int)$doc_id,'document_type'=>'cerapan','is_current'=>1))->row();
        if (!$doc) show_404();list($actor,$request)=$this->request_for($doc->service_request_id,'documents.cerapan.upload');
        if (!$this->metro->can($actor->id,'requests.view_all') && !$this->metro->assigned($doc->request_item_id,$actor->id)) show_error('Akses ditolak.',403);
        if ($doc->drive_file_id) show_error('Cerapan sudah terkirim ke Drive.',400);
        $item=$this->db->get_where('request_items',array('id'=>$doc->request_item_id))->row();
        $this->load->library('google_workspace');
        try {$result=$this->google_workspace->upload_cerapan($doc,$request,$item);}
        catch (RuntimeException $ex) {
            $this->db->where('id',$doc->id)->update('service_documents',array('drive_error'=>mb_substr($ex->getMessage(),0,500)));
            $this->fail('Pengiriman Drive gagal: '.$ex->getMessage(),'petugas/permohonan/'.$request->id.'?tab=ctt');return;
        }
        $this->db->where('id',$doc->id)->update('service_documents',array('drive_file_id'=>$result['id'],
            'drive_synced_at'=>date('Y-m-d H:i:s'),'drive_error'=>NULL));
        $this->metro->audit($actor->id,'cerapan_drive','service_documents',$doc->id,$result['id']);
        $this->message('Cerapan berhasil dikirim ke Google Drive.','petugas/permohonan/'.$request->id.'?tab=ctt');
    }

    public function generate_cerapan($item_id)
    {
        $this->post_only();$actor=$this->require_permission('documents.cerapan.upload');
        $item=$this->db->select('ri.*,t.type_name AS uttp_name')->from('request_items ri')
            ->join('uttp_types t','t.id=ri.uttp_type_id')->where('ri.id',(int)$item_id)->get()->row();
        if (!$item) show_404();
        $request=$this->metro->request($item->service_request_id);
        if (!$request || !$this->metro->can_view_request($actor->id,$request->id)
            || (!$this->metro->can($actor->id,'requests.view_all') && !$this->metro->assigned($item->id,$actor->id)))
            show_error('Akses ditolak.',403);
        if (!in_array($item->item_status,array('scheduled','testing','review','valid','invalid','completed'),TRUE)
            || !$request->penera_option_id)
            show_error('Penera dan verifikasi harus lengkap sebelum cerapan dibuat.',400);
        $observation=trim((string)$this->input->post('observation',TRUE));
        if (mb_strlen($observation)<5 || mb_strlen($observation)>3000)
            $this->fail('Isi catatan pengujian 5–3000 karakter.','petugas/permohonan/'.$request->id.'?tab=process');
        $ctts=$this->db->select('o.option_code,o.option_label')->from('request_ctts rc')
            ->join('master_options o','o.id=rc.ctt_option_id')->where('rc.request_id',$request->id)
            ->where('rc.cancelled_at IS NULL',NULL,FALSE)->get()->result();
        require_once FCPATH.'vendor/autoload.php';
        $html=$this->load->view('staff/cerapan_pdf',array('request'=>$request,'item'=>$item,
            'ctts'=>$ctts,'observation'=>$observation,'actor'=>$actor),TRUE);
        $pdf=new Dompdf\Dompdf();$pdf->loadHtml($html);$pdf->setPaper('A4');$pdf->render();
        $this->load->library('private_files');$this->load->library('google_workspace');
        $settings=$this->db->get_where('google_settings',array('id'=>1))->row();
        $name=$this->google_workspace->cerapan_name($settings->cerapan_filename_pattern,$request,$item);
        $file=$this->private_files->save_generated_pdf($pdf->output(),$name);
        $this->db->trans_begin();
        $this->db->where('request_item_id',$item->id)->where('document_type','cerapan')->update('service_documents',array('is_current'=>0));
        $file['service_request_id']=$request->id;$file['request_item_id']=$item->id;
        $file['document_type']='cerapan';$file['visibility']='internal';$file['uploaded_by']=$actor->id;
        $this->db->insert('service_documents',$file);
        $doc_id=$this->db->insert_id();$this->metro->audit($actor->id,'cerapan_generate','service_documents',$doc_id);
        if ($this->db->trans_status()===FALSE) {
            $this->db->trans_rollback();$this->private_files->delete($file['storage_key']);show_error('Cerapan gagal dibuat.',500);
        }
        $this->db->trans_commit();
        $this->message('Draf cerapan dibuat. Preview PDF lalu kirim ke Drive bila sudah sesuai.',
            'petugas/permohonan/'.$request->id.'?tab=process#unit-'.$item->id);
    }
}
