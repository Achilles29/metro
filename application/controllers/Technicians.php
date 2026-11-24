<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Technicians extends MY_Controller
{
    private function external()
    {
        $user=$this->require_login();
        if ($user->account_type!=='technician') show_error('Akses hanya untuk teknisi eksternal.',403);
        return $user;
    }
    private function company()
    {
        $user=$this->require_login();
        if ($user->account_type!=='applicant') show_error('Akses hanya untuk perusahaan.',403);
        $profile=$this->metro->profile($user->id);
        if (!$profile || $profile->profile_kind!=='organization' || !$profile->organization_name)
            show_error('Atur profil sebagai instansi/perusahaan terlebih dahulu.',400);
        return array($user,$profile);
    }
    private function office($permission='technicians.view')
    {
        $user=$this->require_staff();
        if (!$this->metro->can($user->id,$permission)) show_error('Akses ditolak.',403);
        return $user;
    }
    public function profile()
    {
        $user=$this->external();
        $profile=$this->db->get_where('technician_profiles',array('user_id'=>$user->id))->row();
        if (!$profile) show_404();
        if ($this->input->method()==='post') {
            $number=trim((string)$this->input->post('recommendation_number',TRUE));
            $note=trim((string)$this->input->post('recommendation_note',TRUE));
            if (mb_strlen($number)>120 || mb_strlen($note)>2000)
                $this->fail('Nomor rekomendasi atau catatan terlalu panjang.','profil-teknisi');
            $changed=$number!==(string)$profile->recommendation_number || $note!==(string)$profile->recommendation_note;
            $data=array('recommendation_number'=>$number ?: NULL,'recommendation_note'=>$note ?: NULL,
                'updated_at'=>date('Y-m-d H:i:s'));
            if ($changed && $profile->verification_status==='approved') {
                $data['verification_status']='pending';$data['reviewed_by']=NULL;$data['reviewed_at']=NULL;
                $data['review_note']='Data rekomendasi berubah; perlu verifikasi ulang.';
            }
            $this->db->where('user_id',$user->id)->update('technician_profiles',$data);
            $this->metro->audit($user->id,'technician_profile_update','technician_profiles',$user->id);
            $this->message('Profil teknisi tersimpan.','profil-teknisi');
        }
        $this->page('technicians/profile',array('title'=>'Profil teknisi','profile'=>$profile));
    }
    public function connections()
    {
        $user=$this->require_login();
        $eligible_company=FALSE;
        if ($user->account_type==='technician') {
            $profile=$this->db->get_where('technician_profiles',array('user_id'=>$user->id))->row();
            $rows=$this->db->select('ct.*,ap.organization_name,u.full_name AS counterpart_name,u.email AS counterpart_email')
                ->from('company_technicians ct')->join('applicant_profiles ap','ap.id=ct.company_profile_id')
                ->join('users u','u.id=ap.user_id')->where('ct.technician_user_id',$user->id)
                ->order_by('ct.created_at','DESC')->get()->result();
        } elseif ($user->account_type==='applicant') {
            $profile=$this->metro->profile($user->id);
            $eligible_company=$profile && $profile->profile_kind==='organization' && !!$profile->organization_name;
            $rows=$profile ? $this->db->select('ct.*,u.full_name AS counterpart_name,u.email AS counterpart_email')
                ->from('company_technicians ct')->join('users u','u.id=ct.technician_user_id')
                ->where('ct.company_profile_id',$profile->id)->order_by('ct.created_at','DESC')->get()->result() : array();
        } else show_error('Akses ditolak.',403);
        $this->page('technicians/connections',array('title'=>'Pelimpahan perusahaan','rows'=>$rows,
            'technician'=>$user->account_type==='technician','technician_profile'=>$user->account_type==='technician'?$profile:NULL,
            'eligible_company'=>$eligible_company));
    }
    public function invite()
    {
        $this->post_only();$actor=$this->require_login();
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $this->fail('Isi email akun tujuan yang valid.','pelimpahan');
        $target=$this->metro->user_by_email($email);
        if (!$target || $target->account_status!=='active') $this->fail('Akun tujuan tidak ditemukan atau nonaktif.','pelimpahan');
        if ($actor->account_type==='applicant') {
            list($actor,$company)=$this->company();
            if ($target->account_type!=='technician') $this->fail('Email itu bukan akun teknisi.','pelimpahan');
            $tech=$this->db->get_where('technician_profiles',array('user_id'=>$target->id))->row();
            if (!$tech || $tech->verification_status!=='approved' || !$target->phone_verified_at)
                $this->fail('Teknisi belum diverifikasi oleh dinas.','pelimpahan');
            $tech_id=$target->id;$company_id=$company->id;
        } elseif ($actor->account_type==='technician') {
            $tech=$this->db->get_where('technician_profiles',array('user_id'=>$actor->id))->row();
            if (!$tech || $tech->verification_status!=='approved' || !$actor->phone_verified_at)
                $this->fail('Verifikasi nomor dan persetujuan dinas diperlukan.','pelimpahan');
            if ($target->account_type!=='applicant') $this->fail('Email itu bukan akun perusahaan.','pelimpahan');
            $company=$this->metro->profile($target->id);
            if (!$company || $company->profile_kind!=='organization' || !$company->organization_name)
                $this->fail('Akun tujuan belum berprofil perusahaan.','pelimpahan');
            $tech_id=$actor->id;$company_id=$company->id;
        } else show_error('Akses ditolak.',403);
        $existing=$this->db->get_where('company_technicians',array('company_profile_id'=>$company_id,'technician_user_id'=>$tech_id))->row();
        if ($existing && !in_array($existing->status,array('rejected','revoked'),TRUE))
            $this->fail('Permintaan pelimpahan untuk pasangan akun ini sudah ada.','pelimpahan');
        $data=array('company_profile_id'=>$company_id,'technician_user_id'=>$tech_id,
            'initiated_by_user_id'=>$actor->id,'status'=>'awaiting_counterpart',
            'counterpart_accepted_at'=>NULL,'reviewed_by'=>NULL,'reviewed_at'=>NULL,'review_note'=>NULL,
            'updated_at'=>date('Y-m-d H:i:s'));
        if ($existing) {$this->db->where('id',$existing->id)->update('company_technicians',$data);$id=$existing->id;}
        else {$this->db->insert('company_technicians',$data);$id=$this->db->insert_id();}
        $this->metro->audit($actor->id,'delegation_invite','company_technicians',$id);
        $this->metro->notify($target->id,'Permintaan pelimpahan','Ada permintaan hubungan teknisi dan perusahaan. Buka halaman Pelimpahan untuk menanggapi.','pelimpahan');
        $this->message('Undangan dikirim. Akun tujuan perlu menyetujui sebelum dinas memeriksa.','pelimpahan');
    }
    public function respond($id)
    {
        $this->post_only();$actor=$this->require_login();
        $row=$this->db->select('ct.*,ap.user_id AS company_user_id')->from('company_technicians ct')
            ->join('applicant_profiles ap','ap.id=ct.company_profile_id')->where('ct.id',(int)$id)->get()->row();
        if (!$row || !in_array((int)$actor->id,array((int)$row->company_user_id,(int)$row->technician_user_id),TRUE)) show_404();
        $action=(string)$this->input->post('action',TRUE);
        if ($action==='accept') {
            if ($row->status!=='awaiting_counterpart' || (int)$row->initiated_by_user_id===(int)$actor->id)
                show_error('Undangan tidak dapat disetujui oleh pengirimnya.',400);
            if (!$this->metro->active_company_link($row->technician_user_id,$row->company_profile_id)) {
                $tech=$this->db->get_where('technician_profiles',array('user_id'=>$row->technician_user_id))->row();
                $tu=$this->metro->user($row->technician_user_id);
                if (!$tech || $tech->verification_status!=='approved' || !$tu->phone_verified_at)
                    show_error('Teknisi belum terverifikasi.',400);
            }
            $this->db->where('id',$row->id)->where('status','awaiting_counterpart')
                ->update('company_technicians',array('status'=>'pending_office','counterpart_accepted_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')));
        } elseif ($action==='reject' && in_array($row->status,array('awaiting_counterpart','pending_office'),TRUE)) {
            $this->db->where('id',$row->id)->update('company_technicians',array('status'=>'rejected','updated_at'=>date('Y-m-d H:i:s')));
        } elseif ($action==='revoke' && $row->status==='active') {
            $this->db->where('id',$row->id)->update('company_technicians',array('status'=>'revoked','updated_at'=>date('Y-m-d H:i:s')));
        } else show_error('Aksi tidak berlaku untuk status saat ini.',400);
        $this->metro->audit($actor->id,'delegation_'.$action,'company_technicians',$row->id);
        $this->message($action==='accept'?'Persetujuan dikirim. Menunggu verifikasi dinas.':'Hubungan diperbarui.','pelimpahan');
    }
    public function admin()
    {
        $this->office();
        $technicians=$this->db->select('tp.*,u.full_name,u.email,u.phone,u.phone_verified_at,u.account_status')
            ->from('technician_profiles tp')->join('users u','u.id=tp.user_id')
            ->order_by('tp.created_at','DESC')->get()->result();
        $links=$this->db->select('ct.*,ap.organization_name,cu.full_name AS company_name,tu.full_name AS technician_name,tp.verification_status')
            ->from('company_technicians ct')->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->join('users cu','cu.id=ap.user_id')->join('users tu','tu.id=ct.technician_user_id')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->order_by('ct.created_at','DESC')->get()->result();
        $tp=metro_page(count($technicians),$this->input->get('tech_page'),20);
        $lp=metro_page(count($links),$this->input->get('link_page'),20);
        $this->page('staff/technicians',array('title'=>'Teknisi dan pelimpahan',
            'technicians'=>array_slice($technicians,$tp['offset'],$tp['per_page']),
            'links'=>array_slice($links,$lp['offset'],$lp['per_page']),
            'tech_pagination'=>$tp,'link_pagination'=>$lp));
    }
    public function review_technician($id)
    {
        $this->post_only();$actor=$this->office('technicians.verify');
        $row=$this->db->select('tp.*,u.phone_verified_at,u.account_status')->from('technician_profiles tp')
            ->join('users u','u.id=tp.user_id')->where('tp.user_id',(int)$id)->get()->row();
        if (!$row) show_404();
        $action=(string)$this->input->post('action',TRUE);
        $number=trim((string)$this->input->post('recommendation_number',TRUE));
        $note=mb_substr(trim((string)$this->input->post('review_note',TRUE)),0,1000);
        if (!in_array($action,array('approve','reject'),TRUE) || mb_strlen($number)>120)
            show_error('Data verifikasi tidak valid.',400);
        if ($action==='approve' && (!$row->phone_verified_at || $row->account_status!=='active' || mb_strlen($number)<3))
            show_error('Nomor telepon harus terverifikasi dan nomor rekomendasi dinas harus diisi.',400);
        $status=$action==='approve'?'approved':'rejected';
        $this->db->where('user_id',$row->user_id)->update('technician_profiles',array(
            'recommendation_number'=>$number ?: NULL,'verification_status'=>$status,
            'reviewed_by'=>$actor->id,'reviewed_at'=>date('Y-m-d H:i:s'),'review_note'=>$note ?: NULL,
            'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,'technician_'.$status,'technician_profiles',$row->user_id);
        $this->metro->notify($row->user_id,'Verifikasi teknisi',$status==='approved'?'Akun teknisi Anda disetujui dinas.':'Verifikasi teknisi belum disetujui. Periksa catatan dinas.','profil-teknisi');
        $this->message('Status teknisi diperbarui.','petugas/teknisi');
    }
    public function review_connection($id)
    {
        $this->post_only();$actor=$this->office('technicians.delegation_review');
        $row=$this->db->select('ct.*,ap.user_id AS company_user_id,ap.profile_kind,tp.verification_status,tu.phone_verified_at,tu.account_status AS technician_status,cu.account_status AS company_status')
            ->from('company_technicians ct')->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->join('users tu','tu.id=ct.technician_user_id')->join('users cu','cu.id=ap.user_id')
            ->where('ct.id',(int)$id)->get()->row();
        if (!$row) show_404();
        $action=(string)$this->input->post('action',TRUE);
        $note=mb_substr(trim((string)$this->input->post('review_note',TRUE)),0,1000);
        if ($action==='approve') {
            if ($row->status!=='pending_office' || !$row->counterpart_accepted_at || $row->verification_status!=='approved'
                || !$row->phone_verified_at || $row->technician_status!=='active' || $row->company_status!=='active'
                || $row->profile_kind!=='organization') show_error('Persetujuan kedua pihak dan verifikasi teknisi diperlukan.',400);
            $status='active';
        } elseif ($action==='reject' && $row->status==='pending_office') $status='rejected';
        elseif ($action==='revoke' && $row->status==='active') $status='revoked';
        else show_error('Aksi tidak berlaku untuk status saat ini.',400);
        $this->db->where('id',$row->id)->update('company_technicians',array('status'=>$status,
            'reviewed_by'=>$actor->id,'reviewed_at'=>date('Y-m-d H:i:s'),'review_note'=>$note ?: NULL,
            'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,'delegation_'.$status,'company_technicians',$row->id);
        foreach (array($row->technician_user_id,$row->company_user_id) as $recipient)
            $this->metro->notify($recipient,'Status pelimpahan','Hubungan teknisi dan perusahaan: '.$status.'.','pelimpahan');
        $this->message('Status pelimpahan diperbarui.','petugas/teknisi');
    }
}
