<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Staff extends MY_Controller
{
    public function index()
    {
        $user = $this->require_permission('requests.view');
        $technical_only = $this->technical_only($user->id);
        $this->page('staff/dashboard', array('title' => 'Dashboard operasional',
            'summary' => $this->metro->dashboard_summary($technical_only ? $user->id : NULL),
            'recent' => $this->metro->queue('', $technical_only ? $user->id : NULL, array(), 6),
            'notifications' => $this->db->where('user_id', $user->id)->order_by('sent_at', 'DESC')->limit(4)->get('notifications')->result()));
    }

    private function technical_only($user_id)
    {
        return !$this->metro->can($user_id,'requests.view_all');
    }

    public function queue()
    {
        $user = $this->require_permission('requests.view');
        $query = mb_substr(trim((string) $this->input->get('q', TRUE)), 0, 100);
        $status = (string) $this->input->get('status', TRUE);
        $service_type = (string) $this->input->get('service_type', TRUE);
        $date_from = (string) $this->input->get('date_from', TRUE);
        $date_to = (string) $this->input->get('date_to', TRUE);
        $valid_status = array_map(function ($o) { return $o->option_code; }, $this->master->options('status'));
        $active_services = array_map(function ($o) { return $o->option_code; }, $this->master->options('service_type'));
        $filters = array(
            'bucket'=>'',
            'status' => in_array($status, $valid_status, TRUE) ? $status : '',
            'service_type' => in_array($service_type, $active_services, TRUE) ? $service_type : '',
            'date_from' => $this->valid_date($date_from) ? $date_from : '',
            'date_to' => $this->valid_date($date_to) ? $date_to : '',
            'applicant_id' => max(0, (int) $this->input->get('pemohon'))
        );
        $selected_applicant = $filters['applicant_id'] ? $this->metro->user($filters['applicant_id']) : NULL;
        if (!$selected_applicant || $selected_applicant->account_type !== 'applicant') $filters['applicant_id'] = 0;
        $technical_only = $this->technical_only($user->id);
        $total = $this->metro->queue_count($query, $technical_only ? $user->id : NULL, $filters);
        $pagination = metro_page($total, $this->input->get('page'), 20);
        $this->page('staff/index', array('title' => 'Antrean permohonan',
            'requests' => $this->metro->queue($query, $technical_only ? $user->id : NULL, $filters, $pagination['per_page'], $pagination['offset']),
            'pagination'=>$pagination,
            'query' => $query, 'filters' => $filters, 'valid_status' => $valid_status,
            'selected_applicant' => $filters['applicant_id'] ? $selected_applicant : NULL,
            'service_types' => $this->master->options('service_type'),
            'notifications' => $this->db->where('user_id', $user->id)->order_by('sent_at', 'DESC')->limit(5)->get('notifications')->result()));
    }

    public function applicants()
    {
        $user = $this->require_permission('applicants.view');
        $query = mb_substr(trim((string) $this->input->get('q', TRUE)), 0, 100);
        $pagination = metro_page($this->metro->applicants_count($query), $this->input->get('page'), 20);
        $this->page('staff/applicants', array('title' => 'Data pemohon',
            'applicants' => $this->metro->applicants($query, $pagination['per_page'], $pagination['offset']),
            'query' => $query, 'pagination'=>$pagination,'districts'=>$this->master->districts(FALSE),
            'profile_kinds'=>$this->master->options('profile_kind',FALSE)));
    }

    private function applicant_input($existing = NULL)
    {
        $name=trim((string)$this->input->post('full_name',TRUE));
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        $phone=trim((string)$this->input->post('phone',TRUE));
        $password=(string)$this->input->post('password');
        $district_id=(int)$this->input->post('district_id');
        $village_id=(int)$this->input->post('village_id');
        $region=$this->master->profile_region($district_id,$village_id);
        $kind=(string)$this->input->post('profile_kind',TRUE);
        $contact=trim((string)$this->input->post('contact_person',TRUE));
        $address=trim((string)$this->input->post('address',TRUE));
        $organization=trim((string)$this->input->post('organization_name',TRUE));
        $kind_row=$this->master->option_code('profile_kind',$kind,FALSE);
        $unchanged_archive_id=$existing && strtolower((string)$existing->email)===$email
            && preg_match('/^skhp[0-9]{3}@metrologi$/',$email);
        if (mb_strlen($name)<3 || mb_strlen($name)>160 || (!filter_var($email,FILTER_VALIDATE_EMAIL) && !$unchanged_archive_id)
            || !preg_match('/^08[0-9]{8,12}$/',$phone) || (!$existing && (strlen($password)<8 || strlen($password)>72))
            || ($existing && $password!=='' && (strlen($password)<8 || strlen($password)>72))
            || !$region || !$kind_row || (!$kind_row->is_active && (!$existing || $existing->profile_kind!==$kind))
            || mb_strlen($contact)<3 || mb_strlen($contact)>160 || mb_strlen($address)<10 || mb_strlen($address)>2000
            || mb_strlen($organization)>190) return NULL;
        $id=$existing?(int)$existing->id:0;
        if ($this->db->where('email',$email)->where('id !=',$id)->count_all_results('users')
            || $this->db->where('phone',$phone)->where('id !=',$id)->count_all_results('users')) return NULL;
        return array('user'=>array('full_name'=>$name,'email'=>$email,'phone'=>$phone),
            'password'=>$password,'profile'=>array('profile_kind'=>$kind,'organization_name'=>$organization?:NULL,
                'contact_person'=>$contact,'address'=>$address,'district_id'=>$district_id,'district'=>$region[0]->district_name,
                'village_id'=>$village_id,'village'=>$region[1]->village_name));
    }

    public function create_applicant()
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'applicants.create')) show_error('Akses ditolak.',403);
        $values=$this->applicant_input();
        if (!$values) { $this->fail('Periksa identitas, wilayah, alamat, dan pastikan email/telepon belum digunakan.','petugas/pemohon'); return; }
        $role=$this->db->get_where('roles',array('role_code'=>'applicant'))->row();
        if (!$role) show_error('Peran pemohon tidak tersedia.',500);
        $this->db->trans_begin();
        $user=$values['user'];
        $user['password_hash']=password_hash($values['password'],PASSWORD_DEFAULT);
        $user['account_type']='applicant'; $user['account_status']='active'; $user['phone_verification_required']=1;
        $this->db->insert('users',$user); $id=$this->db->insert_id();
        $this->db->insert('user_roles',array('user_id'=>$id,'role_id'=>$role->id,'assigned_by'=>$actor->id));
        $profile=$values['profile']; $profile['user_id']=$id;
        $this->db->insert('applicant_profiles',$profile);
        $this->metro->audit($actor->id,'applicant_create','users',$id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Pemohon gagal dibuat.','petugas/pemohon'); return; }
        $this->db->trans_commit();
        $this->load->model('Wa_model','wa');
        $queued=$this->wa->queue_code($this->metro->user($id));
        $this->message($queued?'Akun dibuat. Kode verifikasi sedang dikirim ke WhatsApp.':'Akun dibuat. Nomor telepon perlu diverifikasi; kode manual tetap tersedia.','petugas/pemohon/'.$id);
    }

    public function update_applicant($id)
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'applicants.update')) show_error('Akses ditolak.',403);
        $person=$this->metro->user((int)$id);
        if (!$person || $person->account_type!=='applicant') show_404();
        $profile=$this->metro->profile($person->id);
        if (!$profile) show_404();
        $person->profile_kind=$profile->profile_kind;
        $values=$this->applicant_input($person);
        if (!$values) { $this->fail('Periksa identitas, wilayah, alamat, dan pastikan email/telepon belum digunakan.','petugas/pemohon'); return; }
        $user=$values['user']; $user['updated_at']=date('Y-m-d H:i:s');
        if ($values['password']!=='') $user['password_hash']=password_hash($values['password'],PASSWORD_DEFAULT);
        if ($person->phone!==$user['phone']) { $user['phone_verified_at']=NULL; $user['phone_verification_required']=1; }
        $this->db->trans_begin();
        $this->db->where('id',$person->id)->update('users',$user);
        $values['profile']['updated_at']=date('Y-m-d H:i:s');
        $this->db->where('id',$profile->id)->update('applicant_profiles',$values['profile']);
        if ($person->phone!==$user['phone']) $this->db->where('user_id',$person->id)->where('verified_at IS NULL',NULL,FALSE)
            ->update('phone_verification_codes',array('verified_at'=>date('Y-m-d H:i:s')));
        if ($person->phone!==$user['phone']) $this->db->where('user_id',$person->id)->where('status','PENDING')
            ->update('wa_outbox',array('status'=>'CANCELLED','message'=>''));
        $this->metro->audit($actor->id,'applicant_update','users',$person->id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Pemohon gagal diperbarui.','petugas/pemohon'); return; }
        $this->db->trans_commit();
        $this->message('Data pemohon diperbarui.','petugas/pemohon/'.$person->id);
    }

    public function toggle_applicant($id)
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'applicants.status')) show_error('Akses ditolak.',403);
        $person=$this->metro->user((int)$id);
        if (!$person || $person->account_type!=='applicant') show_404();
        $status=$person->account_status==='active'?'inactive':'active';
        $this->db->where('id',$person->id)->update('users',array('account_status'=>$status,'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,$status==='active'?'applicant_activate':'applicant_deactivate','users',$person->id);
        $this->message($status==='active'?'Akun pemohon diaktifkan.':'Akun pemohon dinonaktifkan.','petugas/pemohon');
    }

    public function delete_applicant($id)
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'applicants.delete')) show_error('Akses ditolak.',403);
        $person=$this->metro->user((int)$id);
        if (!$person || $person->account_type!=='applicant') show_404();
        $profile=$this->metro->profile($person->id); $url='petugas/pemohon';
        if (!$profile) show_404();
        foreach (array(array('service_requests','applicant_profile_id'),array('applicant_uttp','applicant_profile_id'),
            array('service_feedback','applicant_profile_id')) as $ref)
            if ($this->db->where($ref[1],$profile->id)->count_all_results($ref[0])) {
                $this->fail('Pemohon sudah memiliki riwayat layanan. Nonaktifkan akun sebagai gantinya.',$url); return;
            }
        foreach (array(array('audit_logs','actor_user_id'),array('phone_verification_codes','user_id'),
            array('notifications','user_id'),array('service_documents','uploaded_by')) as $ref)
            if ($this->db->where($ref[1],$person->id)->count_all_results($ref[0])) {
                $this->fail('Pemohon sudah memiliki riwayat. Nonaktifkan akun sebagai gantinya.',$url); return;
            }
        $this->db->trans_begin();
        $this->db->where('user_id',$person->id)->delete('user_roles');
        $this->db->where('id',$profile->id)->delete('applicant_profiles');
        $this->db->where('id',$person->id)->delete('users');
        $this->metro->audit($actor->id,'applicant_delete','users',$person->id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Akun pemohon gagal dihapus.',$url); return; }
        $this->db->trans_commit();
        $this->message('Akun pemohon dihapus.',$url);
    }

    private function valid_date($value)
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    public function report()
    {
        $user = $this->require_permission('reports.view');
        $period = (string) $this->input->get('period', TRUE);
        if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period) || (int) substr($period, 0, 4) > (int) date('Y') + 1) $period = date('Y-m');
        $start = $period . '-01 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
        if ($this->input->get('download') === 'csv') {
            if (!$this->metro->can($user->id,'reports.export')) show_error('Akses ditolak.',403);
            $this->metro->audit($user->id, 'report_export', 'service_requests', NULL, 'Periode ' . $period);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="laporan-setimbang-' . $period . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, array('Nomor permohonan', 'Tanggal masuk', 'Layanan', 'Status saat ini', 'Jumlah unit'));
            foreach ($this->metro->report_rows($start, $end) as $row) {
                fputcsv($output, array($row->request_no, $row->submitted_at,
                    master_label('service_type',$row->service_type), status_label($row->request_status), (int) $row->unit_count));
            }
            fclose($output);
            exit;
        }
        $this->page('staff/report', array('title' => 'Laporan layanan', 'period' => $period,
            'as_of' => date('Y-m-d H:i:s'), 'report' => $this->metro->report_summary($start, $end),
            'service_types' => $this->master->options('service_type')));
    }

    public function show($id)
    {
        $user = $this->require_permission('requests.view');
        $request = $this->metro->request((int) $id);
        if (!$request) show_404();
        if (!$this->metro->can_view_request($user->id,$request->id)) show_error('Akses ditolak.',403);
        $technical_only = $this->technical_only($user->id);
        $items = $this->metro->items($request->id);
        $documents = $this->metro->documents($request->id);
        $history = $this->metro->history($request->id);
        if ($technical_only) {
            $items = array_values(array_filter($items, function ($i) use ($user) { return $this->metro->assigned($i->id, $user->id); }));
            $item_ids = array_map(function ($i) { return (int) $i->id; }, $items);
            $documents = array_values(array_filter($documents, function ($d) use ($item_ids) {
                return $d->document_type === 'application_letter' || in_array((int) $d->request_item_id, $item_ids, TRUE);
            }));
            $history = array_values(array_filter($history, function ($h) use ($item_ids) { return in_array((int) $h->request_item_id, $item_ids, TRUE); }));
        }
        $documents=array_values(array_filter($documents,function($d) use ($user) {
            $permission=array('application_letter'=>'documents.application.read','cerapan'=>'documents.cerapan.read','skhp'=>'documents.skhp.read');
            return isset($permission[$d->document_type]) && $this->metro->can($user->id,$permission[$d->document_type]);
        }));
        $checks=$this->db->where('is_active',1)->order_by('sort_order')->order_by('id')->get('verification_checks')->result();
        $check_results=$this->db->get_where('request_check_results',array('request_id'=>$request->id))->result();
        $check_units=$this->db->get_where('request_check_units',array('request_id'=>$request->id))->result();
        $ctts=$this->db->select('rc.*,o.option_code,o.option_label,o.spreadsheet_url')->from('request_ctts rc')
            ->join('master_options o','o.id=rc.ctt_option_id')->where('rc.request_id',$request->id)
            ->where('rc.cancelled_at IS NULL',NULL,FALSE)
            ->order_by('rc.created_at')->get()->result();
        $cancelled_ctts=$this->db->select('rc.*,o.option_code,o.option_label')->from('request_ctts rc')
            ->join('master_options o','o.id=rc.ctt_option_id')->where('rc.request_id',$request->id)
            ->where('rc.cancelled_at IS NOT NULL',NULL,FALSE)->order_by('rc.cancelled_at','DESC')->get()->result();
        $daily_loans=array();
        $daily_rows=$this->db->select('dl.*')->from('request_ctt_daily_loans dl')
            ->join('request_ctts rc','rc.id=dl.request_ctt_id')->where('rc.request_id',$request->id)
            ->order_by('dl.loan_date')->get()->result();
        foreach ($daily_rows as $daily) $daily_loans[$daily->request_ctt_id][$daily->loan_date]=$daily;
        $ctt_delete_allowed=!$request->completed_at
            && !$this->db->where('service_request_id',$request->id)
                ->where_in('item_status',array('valid','invalid','returned','completed','rejected','cancelled'))
                ->count_all_results('request_items')
            && !$this->db->where('service_request_id',$request->id)
                ->where_in('document_type',array('cerapan','skhp'))->where('is_current',1)
                ->count_all_results('service_documents');
        $photos=$this->db->get_where('request_photos',array('request_id'=>$request->id,'deleted_at'=>NULL))->result();
        if ($technical_only) $photos=array_values(array_filter($photos,function($photo) use ($item_ids) {
            return !$photo->item_id || in_array((int)$photo->item_id,$item_ids,TRUE);
        }));
        $this->page('staff/workflow', array('title' => 'Kelola ' . $request->request_no,
            'request' => $request, 'items' => $items,
            'documents' => $documents, 'history' => $history,
            'roles' => $this->metro->roles($user->id),
            'penera_options' => $this->master->options('penera', FALSE), 'ctt_options' => $this->master->options('ctt', FALSE),
            'checks'=>$checks,'check_results'=>$check_results,'check_units'=>$check_units,
            'ctts'=>$ctts,'cancelled_ctts'=>$cancelled_ctts,'daily_loans'=>$daily_loans,
            'ctt_delete_allowed'=>$ctt_delete_allowed,
            'photos'=>$photos,
            'review_blocker'=>$request->verification_finalized_at && !$technical_only
                ?$this->metro->review_change_blocker($request,$items):NULL,
            'report_syncs'=>$this->db->get_where('request_report_syncs',array('request_id'=>$request->id))->result(),
            'technicians' => $this->metro->users_with_permission('units.testing'),
            'assignments' => $this->db->select('a.request_item_id,u.full_name')->from('staff_assignments a')->join('users u','u.id=a.assigned_to')
                ->join('request_items i','i.id=a.request_item_id')->where('i.service_request_id',$request->id)
                ->where('a.assignment_status','assigned')->get()->result()));
    }

    public function order_details($id)
    {
        $this->post_only();
        $user = $this->require_permission('requests.order_edit');
        if (!$this->metro->can($user->id,'requests.view_all')) show_error('Akses ditolak.',403);
        $request = $this->metro->request((int) $id);
        if (!$request) show_404();
        if ($request->submission_source === 'historical') show_error('Arsip SKHP 2025 hanya dapat dibaca.',403);
        if (!$this->metro->can_view_request($user->id,$request->id)) show_error('Akses ditolak.',403);
        if ($request->verification_finalized_at && $request->penera_option_id)
            show_error('Penera tidak dapat diubah setelah hasil verifikasi ditetapkan.',400);
        $penera_id = (int) $this->input->post('penera_option_id');
        $penera = $penera_id ? $this->master->option('penera',$penera_id,FALSE) : NULL;
        if ($penera_id && (!$penera || (!$penera->is_active && $penera_id !== (int)$request->penera_option_id)))
            show_error('Periksa penera.', 400);
        $this->db->where('id', $request->id)->update('service_requests', array(
            'penera_option_id'=>$penera_id ?: NULL,'penera_name'=>$penera ? $penera->option_label : NULL,
            'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($user->id, 'order_details', 'service_requests', $request->id);
        $this->message('Data penera tersimpan.', 'petugas/permohonan/' . $request->id . '?tab=penera');
    }

    public function verify_order($id)
    {
        show_error('Gunakan formulir Kaji ulang pada detail permohonan.',410);
        $this->post_only();
        $user = $this->require_permission('requests.verify');
        if (!$this->metro->can($user->id,'requests.view_all')) show_error('Akses ditolak.',403);
        $request = $this->metro->request((int) $id);
        if (!$request) show_404();
        if (!$this->metro->can_view_request($user->id,$request->id)) show_error('Akses ditolak.',403);
        $to = (string) $this->input->post('status', TRUE);
        $note = mb_substr(trim((string) $this->input->post('note', TRUE)), 0, 1000);
        if ($to!=='verification' || !$request->penera_option_id) show_error('Pilih penera, lalu mulai verifikasi dari tab Verifikasi.', 400);
        if (in_array($to, array('revision','rejected'), TRUE) && mb_strlen($note) < 8) show_error('Jelaskan alasan minimal 8 karakter.', 400);
        $allowed = array('submitted'=>array('verification'), 'verification'=>array('revision','scheduled','rejected'));
        $items = $this->metro->items($request->id);
        $eligible = array();
        foreach ($items as $item) if (in_array($to, $allowed[$item->item_status] ?? array(), TRUE)) $eligible[] = $item;
        if (!$eligible) show_error('Tidak ada unit yang siap untuk tindakan ini.', 400);
        $this->db->trans_begin();
        foreach ($eligible as $item) {
            $this->db->where('id', $item->id)->where('item_status', $item->item_status)->update('request_items', array('item_status'=>$to,'updated_at'=>date('Y-m-d H:i:s')));
            if ($this->db->affected_rows() !== 1) { $this->db->trans_rollback(); show_error('Status unit berubah. Muat ulang halaman.', 409); }
            $this->db->insert('item_status_history', array('request_item_id'=>$item->id,'from_status'=>$item->item_status,'to_status'=>$to,'actor_user_id'=>$user->id,'note'=>$note ?: NULL));
        }
        $statuses = array_column($this->db->select('item_status')->get_where('request_items', array('service_request_id'=>$request->id))->result_array(),'item_status');
        $overall = count(array_unique($statuses)) === 1 ? $statuses[0] : 'in_progress';
        if ($overall !== $request->request_status) {
            $this->db->where('id',$request->id)->update('service_requests',array('request_status'=>$overall,'updated_at'=>date('Y-m-d H:i:s')));
            $this->db->insert('request_status_history',array('service_request_id'=>$request->id,'from_status'=>$request->request_status,'to_status'=>$overall,'actor_user_id'=>$user->id,'note'=>$note ?: NULL));
        }
        $this->metro->audit($user->id,'order_verification','service_requests',$request->id,$to);
        $this->metro->notify($request->user_id,'Verifikasi permohonan',status_label($to).($note ? ': '.$note : ''),'permohonan/'.$request->id);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); show_error('Verifikasi gagal.',500); }
        $this->db->trans_commit();
        $this->message('Verifikasi dimulai.', 'petugas/permohonan/' . $request->id . '?tab=verification');
    }

    public function legacy_continue($id)
    {
        $this->post_only();$actor=$this->require_permission('requests.verify');
        if (!$this->metro->can($actor->id,'requests.view_all')) show_error('Akses ditolak.',403);
        $request=$this->metro->request((int)$id);if (!$request) show_404();
        if (!$this->metro->can_view_request($actor->id,$request->id) || !$request->penera_option_id || $request->verification_finalized_at)
            show_error('Order tidak memenuhi syarat penyesuaian alur lama.',400);
        $items=$this->metro->items($request->id);$pending=array();$progressed=FALSE;
        foreach ($items as $item) {
            if (in_array($item->item_status,array('submitted','verification'),TRUE)) $pending[]=$item;
            elseif (in_array($item->item_status,array('scheduled','testing','review','valid','invalid','returned','not_suitable','completed','rejected','cancelled'),TRUE)) $progressed=TRUE;
        }
        if (!$progressed || !$pending) show_error('Tidak ada UTTP lama yang perlu dilanjutkan.',400);
        $has_letter=$this->db->where('service_request_id',$request->id)->where('document_type','application_letter')
            ->where('is_current',1)->count_all_results('service_documents')>0;
        $to=$request->service_mode==='field' && $has_letter?'scheduled':'testing';
        $now=date('Y-m-d H:i:s');$this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        foreach ($pending as $item) {
            $this->db->where('id',$item->id)->where('item_status',$item->item_status)
                ->update('request_items',array('item_status'=>$to,'updated_at'=>$now));
            if ($this->db->affected_rows()!==1) {$this->db->trans_rollback();show_error('Status UTTP berubah. Muat ulang halaman.',409);}
            $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>$item->item_status,
                'to_status'=>$to,'actor_user_id'=>$actor->id,'note'=>'Penyesuaian order lama: UTTP tertunda dilanjutkan.'));
        }
        $this->db->where('id',$request->id)->update('service_requests',array('request_status'=>'in_progress','updated_at'=>$now));
        $this->metro->audit($actor->id,'legacy_request_continue','service_requests',$request->id,count($pending).' UTTP');
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Penyesuaian order lama gagal.',500);}
        $this->db->trans_commit();
        $this->message(count($pending).' UTTP tertunda dilanjutkan tanpa mengubah UTTP yang sudah diproses.',
            'petugas/permohonan/'.$request->id.'?tab=process');
    }

    public function applicant($id)
    {
        $user = $this->require_permission('applicants.view');
        $person = $this->metro->user((int) $id);
        if (!$person || $person->account_type !== 'applicant') show_404();
        $profile = $this->metro->profile($person->id);
        $error = NULL;
        if ($this->input->method() === 'post') {
            $phone = trim((string) $this->input->post('phone', TRUE));
            $duplicate = $this->db->where('phone',$phone)->where('id !=',$person->id)->count_all_results('users');
            if (!preg_match('/^08[0-9]{8,12}$/',$phone) || $duplicate) $error = 'Nomor telepon tidak valid atau sudah terdaftar.';
            elseif ($phone === $person->phone) $this->message('Nomor telepon tidak berubah.', 'petugas/pemohon/'.$person->id);
            else {
                $this->db->where('id',$person->id)->update('users',array('phone'=>$phone,'phone_verified_at'=>NULL,'phone_verification_required'=>1,'updated_at'=>date('Y-m-d H:i:s')));
                $this->db->where('user_id',$person->id)->where('verified_at IS NULL',NULL,FALSE)->update('phone_verification_codes',array('verified_at'=>date('Y-m-d H:i:s')));
                $this->metro->audit($user->id,'applicant_phone_edit','users',$person->id);
                $this->message('Nomor telepon pemohon diperbarui.', 'petugas/pemohon/'.$person->id);
            }
        }
        $active_pagination=metro_page($this->metro->requests_for_count($person->id,'active'),$this->input->get('active_page'),10);
        $done_pagination=metro_page($this->metro->requests_for_count($person->id,'terminal'),$this->input->get('done_page'),10);
        $active=$this->metro->requests_for($person->id,$active_pagination['per_page'],$active_pagination['offset'],'active');
        $done=$this->metro->requests_for($person->id,$done_pagination['per_page'],$done_pagination['offset'],'terminal');
        $uttp_total=$this->db->where('applicant_profile_id',$profile->id)->count_all_results('applicant_uttp');
        $uttp_pagination=metro_page($uttp_total,$this->input->get('uttp_page'),10);
        $uttp=$this->db->select('a.*,t.type_name AS uttp_name')->from('applicant_uttp a')
            ->join('uttp_types t','t.id=a.uttp_type_id')->where('a.applicant_profile_id',$profile->id)
            ->order_by('a.id','DESC')->limit($uttp_pagination['per_page'],$uttp_pagination['offset'])->get()->result();
        $history_total=$this->db->from('request_items ri')->join('service_requests sr','sr.id=ri.service_request_id')
            ->where('sr.applicant_profile_id',$profile->id)->count_all_results();
        $history_pagination=metro_page($history_total,$this->input->get('history_page'),15);
        $uttp_history=$this->db->select('ri.id,ri.applicant_uttp_id,ri.unit_sequence,ri.brand,ri.serial_number,ri.item_status,t.type_name,sr.id AS request_id,sr.request_no,sr.submitted_at')
            ->from('request_items ri')->join('service_requests sr','sr.id=ri.service_request_id')
            ->join('uttp_types t','t.id=ri.uttp_type_id')->where('sr.applicant_profile_id',$profile->id)
            ->order_by('sr.submitted_at','DESC')->order_by('ri.id','DESC')
            ->limit($history_pagination['per_page'],$history_pagination['offset'])->get()->result();
        $map_points=array();
        if ($profile->latitude!==NULL && $profile->longitude!==NULL) $map_points[]=array('lat'=>(float)$profile->latitude,'lng'=>(float)$profile->longitude,'kind'=>'Profil','title'=>$profile->organization_name ?: $person->full_name,
            'detail'=>$profile->address.($profile->location_source==='google_review'?' · Titik peta daring perlu konfirmasi':''));
        $all_tools=$this->db->select('a.latitude,a.longitude,a.brand,a.service_address,t.type_name')->from('applicant_uttp a')
            ->join('uttp_types t','t.id=a.uttp_type_id')->where('a.applicant_profile_id',$profile->id)
            ->where('a.latitude IS NOT NULL',NULL,FALSE)->get()->result();
        foreach ($all_tools as $tool) $map_points[]=array('lat'=>(float)$tool->latitude,'lng'=>(float)$tool->longitude,'kind'=>'UTTP','title'=>$tool->type_name.' · '.$tool->brand,'detail'=>$tool->service_address);
        $this->page('staff/applicant',array('title'=>'Profil pemohon','person'=>$person,'profile'=>$profile,
            'active'=>$active,'done'=>$done,
            'active_pagination'=>$active_pagination,'done_pagination'=>$done_pagination,
            'uttp'=>$uttp,'uttp_pagination'=>$uttp_pagination,'uttp_history'=>$uttp_history,
            'history_pagination'=>$history_pagination,'map_points'=>$map_points,'error'=>$error));
    }

    public function phone_code($id)
    {
        $this->post_only();
        $actor = $this->require_permission('applicants.phone_code');
        $person = $this->metro->user((int) $id);
        if (!$person || !in_array($person->account_type,array('applicant','technician'),TRUE)
            || !preg_match('/^08[0-9]{8,12}$/',(string) $person->phone)) show_404();
        if ($person->phone_verified_at) show_error('Nomor telepon sudah terverifikasi.',400);
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM users WHERE id=? FOR UPDATE',array($person->id));
        $since = date('Y-m-d H:i:s',time()-3600);
        if ($this->db->where('user_id',$person->id)->where('created_at >=',$since)->count_all_results('phone_verification_codes') >= 5) {
            $this->db->trans_rollback();
            show_error('Batas lima kode per jam tercapai.',429);
        }
        $code = (string) random_int(100000,999999);
        $this->db->where('user_id',$person->id)->where('status','PENDING')->update('wa_outbox',array('status'=>'CANCELLED','message'=>''));
        $this->db->where('user_id',$person->id)->where('verified_at IS NULL',NULL,FALSE)
            ->update('phone_verification_codes',array('verified_at'=>date('Y-m-d H:i:s')));
        $this->db->insert('phone_verification_codes',array('user_id'=>$person->id,'code_hash'=>password_hash($code,PASSWORD_DEFAULT),
            'expires_at'=>date('Y-m-d H:i:s',time()+900),'created_by'=>$actor->id,'delivery_channel'=>'manual'));
        $this->metro->audit($actor->id,'phone_code_created','users',$person->id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); show_error('Kode gagal dibuat.',500); }
        $this->db->trans_commit();
        $this->output->set_header('Cache-Control: no-store, private');
        $this->page('staff/phone_code',array('title'=>'Kode verifikasi telepon','person'=>$person,'code'=>$code));
    }

    public function activity()
    {
        $this->require_permission('activity.view');
        $from=(string)$this->input->get('date_from',TRUE);$to=(string)$this->input->get('date_to',TRUE);
        if (!$this->valid_date($from)) $from=date('Y-m-01');
        if (!$this->valid_date($to)) $to=date('Y-m-d');
        $scope=$this->input->get('scope',TRUE)==='system'?'system':'completed';
        $type=(int)$this->input->get('uttp_type_id');
        if ($scope==='system') {
            $base=function() use ($from,$to) {return $this->db->from('audit_logs a')->join('users u','u.id=a.actor_user_id','left')
                ->where('a.created_at >=',$from.' 00:00:00')->where('a.created_at <=',$to.' 23:59:59');};
            $pagination=metro_page($base()->count_all_results(),$this->input->get('page'),20);
            $rows=$base()->select('a.*,u.full_name AS actor_name')->order_by('a.created_at','DESC')->order_by('a.id','DESC')
                ->limit($pagination['per_page'],$pagination['offset'])->get()->result();
        } else {
            $base=function() use ($from,$to,$type) {
                $query=$this->db->from('service_requests sr')->join('applicant_profiles p','p.id=sr.applicant_profile_id')
                    ->join('users u','u.id=p.user_id')->join('master_options po','po.id=sr.penera_option_id','left')
                    ->where('sr.completed_at >=',$from.' 00:00:00')->where('sr.completed_at <=',$to.' 23:59:59')
                    ->where("NOT EXISTS (SELECT 1 FROM request_items unfinished WHERE unfinished.service_request_id=sr.id AND unfinished.item_status NOT IN ('valid','invalid','returned','not_suitable','completed','rejected','cancelled'))",NULL,FALSE);
                if ($type>0) $query->where('EXISTS (SELECT 1 FROM request_items typed WHERE typed.service_request_id=sr.id AND typed.uttp_type_id='.(int)$type.')',NULL,FALSE);
                return $query;
            };
            $pagination=metro_page($base()->count_all_results(),$this->input->get('page'),20);
            $rows=$base()->select('sr.id AS request_id,sr.request_no,sr.service_type,sr.submitted_at,sr.completed_at,sr.request_status,u.full_name AS applicant_name,p.organization_name,COALESCE(po.option_label,sr.penera_name) AS penera_name,(SELECT COUNT(*) FROM request_items c WHERE c.service_request_id=sr.id) AS unit_count',FALSE)
                ->order_by('sr.completed_at','DESC')->order_by('sr.id','DESC')->limit($pagination['per_page'],$pagination['offset'])->get()->result();
        }
        $this->page('staff/activity',array('title'=>'Log aktivitas','rows'=>$rows,'types'=>$this->master->types(FALSE),
            'pagination'=>$pagination,'filters'=>array('date_from'=>$from,'date_to'=>$to,'uttp_type_id'=>$scope==='completed'?$type:0,'scope'=>$scope)));
    }

    public function status($id)
    {
        $this->post_only();$user=$this->require_permission('requests.view');
        $item=$this->db->get_where('request_items',array('id'=>(int)$id))->row();if (!$item) show_404();
        $request=$this->metro->request($item->service_request_id);if (!$request) show_404();
        if (!$request->verification_finalized_at
            && in_array($item->item_status,array('submitted','verification','revision'),TRUE))
            show_error('Kaji ulang belum selesai.',400);
        if (!$this->metro->can($user->id,'requests.view_all') && !$this->metro->assigned($item->id,$user->id)) show_error('Unit belum ditugaskan.',403);
        $to=(string)$this->input->post('status',TRUE);$note=mb_substr(trim((string)$this->input->post('note',TRUE)),0,1000);
        $map=array('scheduled'=>array('testing','valid','invalid','returned'),
            'testing'=>array('valid','invalid','returned'), 'review'=>array('testing','valid','invalid','returned'));
        $permissions=array('testing'=>'units.testing','valid'=>'units.complete','invalid'=>'units.cancel','returned'=>'units.reject');
        if (!in_array($to,$map[$item->item_status]??array(),TRUE) || !$this->metro->can($user->id,$permissions[$to]))
            show_error('Perubahan status tidak diizinkan.',403);
        if (in_array($to,array('invalid','returned'),TRUE) && mb_strlen($note)<8)
            show_error('Isi alasan sekurangnya 8 karakter.',400);
        $now=date('Y-m-d H:i:s');$this->db->trans_begin();
        $changes=array('item_status'=>$to,'updated_at'=>$now);
        if (in_array($to,array('valid','invalid'),TRUE)) $changes['tested_at']=date('Y-m-d');
        $this->db->where('id',$item->id)->where('item_status',$item->item_status)->update('request_items',$changes);
        if ($this->db->affected_rows()!==1) {$this->db->trans_rollback();show_error('Status berubah. Muat ulang halaman.',409);}
        $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>$item->item_status,
            'to_status'=>$to,'actor_user_id'=>$user->id,'note'=>$note ?: NULL));
        $statuses=array_column($this->db->select('item_status')->get_where('request_items',array('service_request_id'=>$request->id))->result_array(),'item_status');
        $all_terminal=TRUE;foreach ($statuses as $status) if (!metro_is_terminal($status)) {$all_terminal=FALSE;break;}
        $overall=$all_terminal?'closed':(count(array_unique($statuses))===1?$statuses[0]:'in_progress');
        $this->db->where('id',$request->id)->update('service_requests',array('request_status'=>$overall,
            'completed_at'=>$all_terminal?($request->completed_at ?: $now):NULL,'updated_at'=>$now));
        if ($overall!==$request->request_status) $this->db->insert('request_status_history',array('service_request_id'=>$request->id,
            'from_status'=>$request->request_status,'to_status'=>$overall,'actor_user_id'=>$user->id,'note'=>$note ?: NULL));
        $this->metro->audit($user->id,'item_status','request_items',$item->id,$item->item_status.' > '.$to);
        $this->metro->notify($request->user_id,'Status UTTP diperbarui',
            'UTTP '.$item->unit_sequence.': '.status_label($to).($note?'. '.$note:''),'permohonan/'.$request->id);
        if ($all_terminal && !$request->completed_at) {
            $this->metro->notify($request->user_id,'Permohonan selesai','Seluruh UTTP pada order '.$request->request_no.' telah diproses.','permohonan/'.$request->id);
            $this->load->model('Wa_model','wa');$this->wa->queue_notification($this->metro->user($request->user_id),
                'Setimbang: seluruh UTTP pada order '.$request->request_no.' selesai diproses. Lihat hasil di akun Anda.');
        }
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('Pembaruan status gagal.',500);}
        $this->db->trans_commit();
        if ($all_terminal) {
            $this->load->library('metro_reports');
            try {$this->metro_reports->sync($request->id);}
            catch (RuntimeException $ex) {log_message('error','Sinkronisasi laporan order '.$request->id.': '.$ex->getMessage());}
        }
        $this->message('Status UTTP diperbarui.','petugas/permohonan/'.$request->id.'?tab=process&saved='.$item->id.'#unit-'.$item->id);
    }

    public function sync_reports($id)
    {
        $this->post_only();$user=$this->require_permission('requests.view_all');
        $request=$this->metro->request((int)$id);if (!$request) show_404();
        $this->load->library('metro_reports');
        try {$errors=$this->metro_reports->sync($request->id);}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/permohonan/'.$request->id.'?tab=process');return;}
        $this->message($errors?'Sebagian laporan tertunda. Periksa integrasi Google.':'Laporan order dan TTU tersinkron.',
            'petugas/permohonan/'.$request->id.'?tab=process');
    }

    private function upload_fail($message, $request_id)
    {
        if ($this->input->is_ajax_request()) {
            $this->output->set_status_header(400)->set_content_type('application/json')->set_output(json_encode(array(
                'ok'=>FALSE,'message'=>$message,'csrf_name'=>$this->security->get_csrf_token_name(),
                'csrf_hash'=>$this->security->get_csrf_hash())));
            return;
        }
        $this->fail($message, 'petugas/permohonan/' . $request_id);
    }

    public function upload($id)
    {
        $this->post_only();
        $user = $this->require_permission('requests.view');
        $item = $this->db->get_where('request_items', array('id' => (int) $id))->row();
        if (!$item) show_404();
        $type = $this->input->post('document_type');
        $request = $this->metro->request($item->service_request_id);
        if ($request && $request->submission_source === 'historical') show_error('Arsip SKHP 2025 hanya dapat dibaca.',403);
        if ($type !== 'skhp'
            || !$this->metro->can($user->id,'documents.'.$type.'.upload')) show_error('Akses ditolak.', 403);
        if (!metro_is_valid($item->item_status))
            show_error('Dokumen belum dapat diunggah pada tahap ini.',400);
        $uttp_type=$this->db->get_where('uttp_types',array('id'=>$item->uttp_type_id))->row();
        if ($uttp_type && $uttp_type->certificate_scope==='order') {
            $first=$this->db->select('ri.id')->from('request_items ri')->join('uttp_types t','t.id=ri.uttp_type_id')
                ->where('ri.service_request_id',$item->service_request_id)->where('t.certificate_scope','order')
                ->where_in('ri.item_status',array('valid','completed'))->order_by('ri.id')->limit(1)->get()->row();
            if (!$first || (int)$first->id!==(int)$item->id) show_error('SKHP PUBBM diunggah satu kali melalui UTTP PUBBM pertama yang Sah.',400);
        }
        if (!$this->metro->can($user->id,'requests.view_all') && !$this->metro->assigned($item->id,$user->id))
            show_error('Unit belum ditugaskan kepada Anda.',403);
        $number = trim((string) $this->input->post('certificate_no', TRUE));
        if ($type === 'skhp' && (mb_strlen($number) < 3 || mb_strlen($number) > 100)) {
            $this->upload_fail('Isi nomor SKHP.', $item->service_request_id); return;
        }
        $this->load->library('private_files');
        try { $file = $this->private_files->save('document'); }
        catch (RuntimeException $ex) { $this->upload_fail($ex->getMessage(), $item->service_request_id); return; }
        $this->db->trans_begin();
        $this->db->where('request_item_id', $item->id)->where('document_type', $type)->update('service_documents', array('is_current' => 0));
        $file['service_request_id'] = $item->service_request_id; $file['request_item_id'] = $item->id;
        $file['document_type'] = $type; $file['visibility'] = $type === 'cerapan' ? 'internal' : 'applicant';
        $file['uploaded_by'] = $user->id;
        $this->db->insert('service_documents', $file);
        $doc_id = $this->db->insert_id();
        if ($type === 'skhp') {
            $this->db->where('request_item_id', $item->id)->update('certificates', array('certificate_status' => 'superseded'));
            $version = $this->db->where('request_item_id', $item->id)->count_all_results('certificates') + 1;
            $this->db->insert('certificates', array('request_item_id' => $item->id, 'service_document_id' => $doc_id,
                'certificate_no' => $number, 'certificate_version' => $version, 'issued_at' => date('Y-m-d H:i:s'),
                'issued_by' => $user->id, 'verification_token' => bin2hex(random_bytes(32))));
            $this->db->where('request_id',$item->service_request_id)->where('report_type','order')
                ->update('request_report_syncs',array('synced_at'=>NULL,'last_error'=>NULL));
        }
        $this->metro->audit($user->id, 'document_upload', 'service_documents', $doc_id, $type);
        if ($type === 'skhp') {
            $request = $this->metro->request($item->service_request_id);
            $this->metro->notify($request->user_id, 'SKHP tersedia', 'SKHP untuk UTTP ' . $item->unit_sequence . ' sudah tersedia di detail permohonan.', 'permohonan/' . $request->id);
            $this->load->model('Wa_model','wa');
            $this->wa->queue_notification($this->metro->user($request->user_id),
                'Setimbang: SKHP untuk permohonan '.$request->request_no.' UTTP '.$item->unit_sequence.' sudah tersedia. Buka akun Anda untuk melihat hasil.');
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback(); $this->private_files->delete($file['storage_key']);
            $this->upload_fail('Unggah gagal. Periksa nomor SKHP.', $item->service_request_id); return;
        }
        $this->db->trans_commit();
        if ($type==='skhp' && $request->completed_at) {
            $this->load->library('metro_reports');
            try {$this->metro_reports->sync($item->service_request_id);}
            catch (RuntimeException $ex) {log_message('error','Sinkronisasi laporan order '.$item->service_request_id.': '.$ex->getMessage());}
        }
        if ($this->input->is_ajax_request()) {
            $readable=$this->metro->can($user->id,'documents.'.$type.'.read');
            $this->output->set_content_type('application/json')->set_output(json_encode(array('ok'=>TRUE,'message'=>'Dokumen tersimpan.',
                'url'=>$readable?site_url('dokumen/'.$doc_id.'?inline=1'):NULL,
                'label'=>$readable?'Preview '.$file['original_name']:'Dokumen tersimpan',
                'csrf_name'=>$this->security->get_csrf_token_name(),'csrf_hash'=>$this->security->get_csrf_hash())));
            return;
        }
        $this->message('Dokumen tersimpan.', 'petugas/permohonan/' . $item->service_request_id . '?tab=process#unit-' . $item->id);
    }

    public function delete_skhp($id)
    {
        $this->post_only();$actor=$this->require_permission('documents.skhp.upload');
        $doc=$this->metro->document((int)$id);if (!$doc || !$doc->is_current || $doc->document_type!=='skhp') show_404();
        $request=$this->metro->request($doc->service_request_id);
        if ($request && $request->submission_source === 'historical') show_error('Arsip SKHP 2025 hanya dapat dibaca.',403);
        if (!$this->metro->can_view_request($actor->id,$doc->service_request_id)) show_error('Akses ditolak.',403);
        if (!$this->metro->can($actor->id,'requests.view_all') && !$this->metro->assigned($doc->request_item_id,$actor->id))
            show_error('Akses ditolak.',403);
        $this->db->trans_begin();
        $this->db->where('id',$doc->id)->where('is_current',1)->update('service_documents',array('is_current'=>0));
        $this->db->where('service_document_id',$doc->id)->update('certificates',array('certificate_status'=>'superseded'));
        $this->db->where('request_id',$doc->service_request_id)->where('report_type','order')
            ->update('request_report_syncs',array('synced_at'=>NULL,'last_error'=>'SKHP dihapus; menunggu dokumen pengganti.'));
        $this->metro->audit($actor->id,'skhp_delete','service_documents',$doc->id);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();show_error('SKHP gagal dihapus.',500);}
        $this->db->trans_commit();
        $request=$this->metro->request($doc->service_request_id);
        if ($request && $request->completed_at) {
            $this->load->library('metro_reports');
            try {$this->metro_reports->sync($request->id);}
            catch (RuntimeException $ex) {log_message('error','Sinkronisasi laporan order '.$request->id.': '.$ex->getMessage());}
        }
        $this->message('SKHP dihapus. Unggah dokumen pengganti bila diperlukan.',
            'petugas/permohonan/'.$doc->service_request_id.'?tab=process#unit-'.$doc->request_item_id);
    }

    public function assign($id)
    {
        $this->post_only();
        $actor = $this->require_permission('requests.assign');
        $item = $this->db->get_where('request_items', array('id' => (int) $id))->row();
        if (!$item) show_404();
        if (!$this->metro->can_view_request($actor->id,$item->service_request_id)) show_error('Akses ditolak.',403);
        $assignee = (int) $this->input->post('assigned_to');
        $person = $this->metro->user($assignee);
        if (!$person || $person->account_type!=='staff' || $person->account_status !== 'active'
            || $this->metro->has_role($assignee,array('administrator')) || !$this->metro->can($assignee,'units.testing'))
            show_error('Pilih petugas teknis yang aktif.', 400);
        if (!in_array($item->item_status, array('scheduled','testing','review'), TRUE)) show_error('Unit belum siap ditugaskan.', 400);
        $this->db->trans_begin();
        $this->db->where('request_item_id', $item->id)->where('assignment_status', 'assigned')->update('staff_assignments', array('assignment_status' => 'replaced','completed_at' => date('Y-m-d H:i:s')));
        $this->db->insert('staff_assignments', array('request_item_id' => $item->id, 'assigned_to' => $assignee,
            'assigned_by' => $actor->id, 'assignment_type' => 'testing', 'note' => mb_substr(trim((string) $this->input->post('note', TRUE)), 0, 1000)));
        $this->metro->audit($actor->id, 'assign_item', 'request_items', $item->id, 'Petugas #' . $assignee);
        $this->metro->notify($assignee, 'Penugasan UTTP', 'UTTP ' . $item->unit_sequence . ' ditugaskan kepada Anda.', 'petugas/permohonan/' . $item->service_request_id);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); $this->fail('Penugasan gagal.', 'petugas/permohonan/' . $item->service_request_id); return; }
        $this->db->trans_commit();
        $this->message('Petugas teknis ditugaskan.', 'petugas/permohonan/' . $item->service_request_id . '?tab=process#unit-' . $item->id);
    }

    public function users()
    {
        $actor = $this->require_staff();
        if (!$this->metro->can($actor->id,'staff.view')) show_error('Akses ditolak.',403);
        $error = NULL;
        if ($this->input->method() === 'post') {
            if (!$this->metro->can($actor->id,'staff.create')) show_error('Akses ditolak.',403);
            $name = trim((string) $this->input->post('full_name', TRUE));
            $email = strtolower(trim((string) $this->input->post('email', TRUE)));
            $password = (string) $this->input->post('password');
            $role_code = (string) $this->input->post('role_code', TRUE);
            $role = $this->db->get_where('roles', array('role_code' => $role_code, 'is_active' => 1))->row();
            if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || strlen($password) > 72
                || !$role || in_array($role_code, array('administrator','applicant','business_actor','external_technician'), TRUE)) $error = 'Periksa nama, email, peran, dan kata sandi 8–72 karakter.';
            elseif ($this->metro->user_by_email($email)) $error = 'Email sudah digunakan.';
            else {
                $this->db->trans_begin();
                $this->db->insert('users', array('full_name' => $name, 'email' => $email, 'password_hash' => password_hash($password,PASSWORD_DEFAULT),
                    'account_type' => 'staff', 'account_status' => 'active'));
                $id = $this->db->insert_id();
                $this->db->insert('user_roles', array('user_id' => $id, 'role_id' => $role->id, 'assigned_by' => $actor->id));
                $this->metro->audit($actor->id, 'staff_create', 'users', $id, $role_code);
                if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); $error = 'Akun gagal dibuat.'; }
                else { $this->db->trans_commit(); $this->message('Akun petugas dibuat.', 'petugas/akun'); return; }
            }
        }
        $total=$this->db->where('account_type','staff')->count_all_results('users');
        $pagination=metro_page($total,$this->input->get('page'),20);
        $this->page('staff/users', array('title' => 'Akun petugas', 'error' => $error,
            'staff_roles' => $this->master->staff_roles(),'pagination'=>$pagination,
            'staff' => $this->db->select("u.id,u.full_name,u.email,u.account_status,GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.sort_order SEPARATOR ', ') AS role_name,GROUP_CONCAT(DISTINCT r.role_code ORDER BY r.sort_order SEPARATOR ',') AS role_codes",FALSE)->from('users u')
                ->join('user_roles ur','ur.user_id=u.id')->join('roles r','r.id=ur.role_id')
                ->where('u.account_type','staff')->group_by('u.id')->order_by('u.full_name')->order_by('u.id','ASC')
                ->limit($pagination['per_page'],$pagination['offset'])->get()->result()));
    }

    public function update_user($id)
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'staff.update')) show_error('Akses ditolak.',403);
        $target=$this->metro->user((int)$id);
        if (!$target || $target->account_type!=='staff') show_404();
        $name=trim((string)$this->input->post('full_name',TRUE));
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        $password=(string)$this->input->post('password');
        $role_code=(string)$this->input->post('role_code',TRUE);
        $is_admin=$this->metro->has_role($target->id,array('administrator'));
        if ($is_admin && !$this->metro->has_role($actor->id,array('administrator'))) show_error('Akses ditolak.',403);
        $roles=$this->metro->roles($target->id);
        $role=$role_code ? $this->db->get_where('roles',array('role_code'=>$role_code,'is_active'=>1))->row() : NULL;
        if (mb_strlen($name)<3 || mb_strlen($name)>160 || !filter_var($email,FILTER_VALIDATE_EMAIL)
            || ($password!=='' && (strlen($password)<8 || strlen($password)>72))
            || (!$is_admin && (!$role || in_array($role_code,array('administrator','applicant','business_actor','external_technician'),TRUE) || count($roles)!==1))) {
            $this->fail('Periksa nama, email, peran, dan kata sandi (jika diubah).','petugas/akun'); return;
        }
        if ($this->db->where('email',$email)->where('id !=',$target->id)->count_all_results('users')) {
            $this->fail('Email sudah digunakan.','petugas/akun'); return;
        }
        $values=array('full_name'=>$name,'email'=>$email,'updated_at'=>date('Y-m-d H:i:s'));
        if ($password!=='') $values['password_hash']=password_hash($password,PASSWORD_DEFAULT);
        $this->db->trans_begin();
        $this->db->where('id',$target->id)->update('users',$values);
        if (!$is_admin && $roles[0]!==$role_code) {
            $this->db->where('user_id',$target->id)->delete('user_roles');
            $this->db->insert('user_roles',array('user_id'=>$target->id,'role_id'=>$role->id,'assigned_by'=>$actor->id));
        }
        $this->metro->audit($actor->id,'staff_update','users',$target->id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Akun gagal diperbarui.','petugas/akun'); return; }
        $this->db->trans_commit();
        $this->message('Akun petugas diperbarui.','petugas/akun');
    }

    public function toggle_user($id)
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'staff.status')) show_error('Akses ditolak.',403);
        $target=$this->metro->user((int)$id);
        if (!$target || $target->account_type!=='staff') show_404();
        if ($target->id==$actor->id || $this->metro->has_role($target->id,array('administrator'))) {
            $this->fail('Akun superadmin tidak dapat dinonaktifkan dari daftar.','petugas/akun'); return;
        }
        $status=$target->account_status==='active'?'inactive':'active';
        $this->db->where('id',$target->id)->update('users',array('account_status'=>$status,'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,$status==='active'?'staff_activate':'staff_deactivate','users',$target->id);
        $this->message($status==='active'?'Akun diaktifkan.':'Akun dinonaktifkan.','petugas/akun');
    }

    public function delete_user($id)
    {
        $this->post_only(); $actor=$this->require_staff();
        if (!$this->metro->can($actor->id,'staff.delete')) show_error('Akses ditolak.',403);
        $target=$this->metro->user((int)$id);
        if (!$target || $target->account_type!=='staff') show_404();
        $url='petugas/akun';
        if ($target->id==$actor->id || $this->metro->has_role($target->id,array('administrator'))) {
            $this->fail('Akun superadmin tidak dapat dihapus.',$url); return;
        }
        $refs=array(array('audit_logs','actor_user_id'),array('item_status_history','actor_user_id'),
            array('request_status_history','actor_user_id'),array('staff_assignments','assigned_to'),
            array('staff_assignments','assigned_by'),array('service_documents','uploaded_by'),
            array('certificates','issued_by'),array('field_visits','scheduled_by'),
            array('notifications','user_id'),array('phone_verification_codes','created_by'),
            array('user_roles','assigned_by'));
        foreach ($refs as $ref) if ($this->db->where($ref[1],$target->id)->count_all_results($ref[0])) {
            $this->fail('Akun sudah memiliki riwayat. Nonaktifkan akun sebagai gantinya.',$url); return;
        }
        $this->db->trans_begin();
        $this->db->where('user_id',$target->id)->delete('user_roles');
        $this->db->where('id',$target->id)->delete('users');
        $this->metro->audit($actor->id,'staff_delete','users',$target->id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Akun gagal dihapus.',$url); return; }
        $this->db->trans_commit();
        $this->message('Akun petugas dihapus.',$url);
    }

    public function deactivate($id)
    {
        $this->post_only();
        $actor = $this->require_staff();
        if (!$this->metro->can($actor->id,'staff.status') || (int) $id === (int) $actor->id) show_error('Akses ditolak.', 403);
        $target = $this->metro->user((int) $id);
        if (!$target || $target->account_type !== 'staff') show_404();
        if ($this->metro->has_role($target->id,array('administrator'))) show_error('Akses ditolak.',403);
        $this->db->where('id', $target->id)->update('users', array('account_status' => 'inactive','updated_at' => date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id, 'staff_deactivate', 'users', $target->id);
        $this->message('Akun dinonaktifkan.', 'petugas/akun');
    }
}
