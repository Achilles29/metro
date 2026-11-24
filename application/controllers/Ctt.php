<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ctt extends MY_Controller
{
    private function valid_date($value)
    {
        $value=(string)$value;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date && $date->format('Y-m-d')===$value;
    }

    public function index()
    {
        $actor=$this->require_permission('requests.view_all');
        $date=(string)$this->input->get('date',TRUE);
        if (!$this->valid_date($date)) $date=date('Y-m-d');
        $this->load->model('Ctt_inventory_model','ctt_inventory');
        $rows=$this->db->query(
            'SELECT rc.id AS ctt_entry_id,rc.request_id,rc.ctt_option_id,rc.asset_units, '
            .'rc.schedule_start_date,rc.schedule_end_date, '
            .'o.option_code,o.option_label,o.asset_quantity,o.is_active,o.sort_order, '
            .'sr.request_no,sr.completed_at,sr.tera_start_date,sr.tera_end_date, '
            .'COALESCE(NULLIF(ap.organization_name,\'\'),u.full_name) AS applicant_name, '
            .'COALESCE(po.option_label,sr.penera_name) AS penera_name, '
            .'dl.id AS loan_id,dl.loan_date,dl.borrowed_at,dl.returned_at, '
            .'overdue.id AS overdue_loan_id,overdue.loan_date AS overdue_date '
            .'FROM request_ctts rc '
            .'JOIN master_options o ON o.id=rc.ctt_option_id AND o.group_code=\'ctt\' '
            .'JOIN service_requests sr ON sr.id=rc.request_id '
            .'JOIN applicant_profiles ap ON ap.id=sr.applicant_profile_id '
            .'JOIN users u ON u.id=ap.user_id '
            .'LEFT JOIN master_options po ON po.id=sr.penera_option_id '
            .'LEFT JOIN request_ctt_daily_loans dl ON dl.request_ctt_id=rc.id AND dl.loan_date=? '
            .'LEFT JOIN request_ctt_daily_loans overdue ON overdue.request_ctt_id=rc.id '
            .'AND overdue.returned_at IS NULL AND overdue.loan_date<? '
            .'WHERE rc.cancelled_at IS NULL AND rc.schedule_start_date<=? AND rc.schedule_end_date>=? '
            .'AND sr.tera_start_date<=? AND sr.tera_end_date>=? '
            .'ORDER BY o.sort_order,o.option_label,sr.request_no,rc.id',
            array($date,$date,$date,$date,$date,$date)
        )->result();
        $groups=array();$open_count=0;
        foreach ($rows as $row) {
            $id=(int)$row->ctt_option_id;
            if (!isset($groups[$id])) $groups[$id]=array(
                'option'=>$row,'orders'=>array(),'planned_units'=>0,
                'borrowed_units'=>0,'returned_units'=>0,
                'available'=>$this->ctt_inventory->availability($id,$date,$date)
            );
            $units=(int)$row->asset_units;
            if ($row->schedule_start_date<=$date && $row->schedule_end_date>=$date)
                $groups[$id]['planned_units']+=$units;
            if (($row->loan_id && !$row->returned_at) || $row->overdue_loan_id) {
                $groups[$id]['borrowed_units']+=$units;$open_count++;
            } elseif ($row->loan_id) $groups[$id]['returned_units']+=$units;
            $groups[$id]['orders'][]=$row;
        }
        $this->page('staff/ctt',array('title'=>'Operasional CTT','date'=>$date,'today'=>date('Y-m-d'),
            'groups'=>$groups,'order_count'=>count($rows),'open_count'=>$open_count,
            'can_borrow'=>$this->metro->can($actor->id,'requests.order_edit')));
    }

    public function borrow_bulk()
    {
        $this->post_only();
        $actor=$this->require_permission('requests.order_edit');
        if (!$this->metro->can($actor->id,'requests.view_all')) show_error('Akses ditolak.',403);
        $date=(string)$this->input->post('date',TRUE);
        $url='petugas/ctt?date='.rawurlencode($this->valid_date($date)?$date:date('Y-m-d'));
        if ($date!==date('Y-m-d')) {$this->fail('Peminjaman hanya dapat dicatat untuk hari ini.',$url);return;}
        $option_id=filter_var($this->input->post('ctt_option_id'),FILTER_VALIDATE_INT);
        $raw=$this->input->post('request_ctt_ids');
        if (!$option_id || $option_id<1 || !is_array($raw) || !$raw || count($raw)>100) {
            $this->fail('Centang order yang sudah memilih CTT ini.',$url);return;
        }
        $ids=array();
        foreach ($raw as $value) {
            if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value<1) {
                $this->fail('Pilihan order tidak valid.',$url);return;
            }
            $ids[]=(int)$value;
        }
        $ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $selected=$this->db->query('SELECT id,request_id,ctt_option_id FROM request_ctts '
            .'WHERE id IN ('.$marks.') ORDER BY request_id,id',$ids)->result();
        if (count($selected)!==count($ids)) {
            $this->fail('Ada pilihan CTT yang berubah. Muat ulang halaman.',$url);return;
        }
        $request_ids=array();
        foreach ($selected as $row) {
            if ((int)$row->ctt_option_id!==(int)$option_id) {
                $this->fail('Pilihan CTT tidak sesuai. Muat ulang halaman.',$url);return;
            }
            $request_ids[]=(int)$row->request_id;
        }
        $request_ids=array_values(array_unique($request_ids));sort($request_ids,SORT_NUMERIC);
        $this->load->model('Ctt_inventory_model','ctt_inventory');
        $this->db->trans_begin();
        $requests=array();
        foreach ($request_ids as $request_id) $requests[$request_id]=$this->db->query(
            'SELECT id,completed_at,submission_source,tera_start_date,tera_end_date '
            .'FROM service_requests WHERE id=? FOR UPDATE',array($request_id)
        )->row();
        $option=$this->db->query('SELECT id,asset_quantity,is_active FROM master_options '
            .'WHERE id=? AND group_code=\'ctt\' FOR UPDATE',array($option_id))->row();
        if (!$option || !$option->is_active || $option->asset_quantity===NULL) {
            $this->db->trans_rollback();$this->fail('CTT tidak aktif atau jumlah aset belum diisi di master.',$url);return;
        }
        $now=date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $entry=$this->db->query('SELECT * FROM request_ctts WHERE id=? FOR UPDATE',array($id))->row();
            $request=$entry ? ($requests[(int)$entry->request_id]??NULL) : NULL;
            if (!$entry || !$request || (int)$entry->ctt_option_id!==(int)$option_id || $entry->cancelled_at
                || $request->completed_at || $request->submission_source==='historical'
                || !$entry->schedule_start_date || $date<$entry->schedule_start_date || $date>$entry->schedule_end_date
                || !$request->tera_start_date || $date<$request->tera_start_date || $date>$request->tera_end_date) {
                $this->db->trans_rollback();$this->fail('Ada order yang tidak memakai CTT ini pada hari pelaksanaan.',$url);return;
            }
            if ($this->db->where('request_ctt_id',$id)->where('loan_date',$date)
                    ->count_all_results('request_ctt_daily_loans')
                || $this->db->where('request_ctt_id',$id)->where('returned_at IS NULL',NULL,FALSE)
                    ->count_all_results('request_ctt_daily_loans')) {
                $this->db->trans_rollback();$this->fail('Ada CTT yang sudah dipinjam atau belum dikembalikan.',$url);return;
            }
            $available=$this->ctt_inventory->availability($option_id,$date,$date);
            if ($available===NULL || $available<(int)$entry->asset_units) {
                $this->db->trans_rollback();$this->fail('Stok CTT tidak cukup. Tersedia '.(int)$available.' aset.',$url);return;
            }
            $this->db->insert('request_ctt_daily_loans',array('request_ctt_id'=>$id,
                'loan_date'=>$date,'borrowed_at'=>$now,'borrowed_by'=>$actor->id));
            $loan_id=$this->db->insert_id();
            if (!$entry->borrowed_at) $this->db->where('id',$id)->update('request_ctts',
                array('borrowed_at'=>$now,'borrowed_by'=>$actor->id));
            $this->metro->audit($actor->id,'ctt_borrow','request_ctt_daily_loans',$loan_id,$date);
        }
        if ($this->db->trans_status()===FALSE) {
            $this->db->trans_rollback();show_error('Peminjaman CTT gagal dicatat.',500);
        }
        $this->db->trans_commit();
        $this->message(count($ids).' order ditandai pinjam untuk '.tanggal_id($date).'.',$url);
    }
}
