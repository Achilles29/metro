<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Technicians extends MY_Controller
{
    private function scope_input()
    {
        $input=$this->input->post('tdur_scope');
        if ($input===NULL) return $this->input->post('scope_present')==='1'?'[]':NULL;
        if (!is_array($input) || count($input)>100) show_error('Pilihan kewenangan tidak valid.',400);
        foreach ($input as $value) if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value<1)
            show_error('Pilihan kewenangan tidak valid.',400);
        $ids=array_values(array_unique(array_map('intval',$input)));
        if ($ids && $this->db->where_in('id',$ids)->count_all_results('uttp_types')!==count($ids))
            show_error('Jenis UTTP kewenangan tidak valid.',400);
        return json_encode($ids);
    }
    private function external()
    {
        $user=$this->require_login();
        if ($user->account_type!=='technician') show_error('Akses hanya untuk pelaku usaha.',403);
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
        if (!$this->metro->can($user->id,'self.technician_profile.view')) show_error('Akses ditolak.',403);
        $profile=$this->db->get_where('technician_profiles',array('user_id'=>$user->id))->row();
        if (!$profile) show_404();
        if ($this->input->method()==='post') {
            if (!$this->metro->can($user->id,'self.technician_profile.update')) show_error('Akses ditolak.',403);
            $number=trim((string)$this->input->post('recommendation_number',TRUE));
            $tdur=trim((string)$this->input->post('tdur_number',TRUE));
            $scope=$this->scope_input();
            $note=trim((string)$this->input->post('recommendation_note',TRUE));
            if (mb_strlen($number)>120 || mb_strlen($tdur)>120 || mb_strlen($note)>2000)
                $this->fail('Nomor rekomendasi atau catatan terlalu panjang.','profil-teknisi');
            $changed=$number!==(string)$profile->recommendation_number || $tdur!==(string)$profile->tdur_number
                || ($scope!==NULL && $scope!==(string)$profile->tdur_scope) || $note!==(string)$profile->recommendation_note;
            $data=array('recommendation_number'=>$number ?: NULL,'recommendation_note'=>$note ?: NULL,
                'tdur_number'=>$tdur ?: NULL,
                'updated_at'=>date('Y-m-d H:i:s'));
            if ($scope!==NULL) $data['tdur_scope']=$scope;
            if ($changed && $profile->verification_status==='approved') {
                $data['verification_status']='pending';$data['reviewed_by']=NULL;$data['reviewed_at']=NULL;
                $data['review_note']='Data rekomendasi berubah; perlu verifikasi ulang.';
            }
            $this->db->where('user_id',$user->id)->update('technician_profiles',$data);
            $this->metro->audit($user->id,'technician_profile_update','technician_profiles',$user->id);
            $this->message('Profil pelaku usaha tersimpan.','profil-teknisi');
        }
        $this->page('technicians/profile',array('title'=>'Profil pelaku usaha','profile'=>$profile,
            'scope_types'=>$this->master->types(FALSE),
            'support_documents'=>$this->db->where('technician_user_id',$user->id)->where('deleted_at IS NULL',NULL,FALSE)
                ->order_by('id','DESC')->get('technician_documents')->result()));
    }
    public function connections()
    {
        $user=$this->require_login();
        if (!$this->metro->can($user->id,'self.delegation.view')) show_error('Akses ditolak.',403);
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
        if (!$this->metro->can($actor->id,'self.delegation.invite')) show_error('Akses ditolak.',403);
        $target_id=(int)$this->input->post('target_user_id');
        $target=$target_id?$this->metro->user($target_id):NULL;
        if (!$target || $target->account_status!=='active') $this->fail('Akun tujuan tidak ditemukan atau nonaktif.','pelimpahan');
        if ($actor->account_type==='applicant') {
            list($actor,$company)=$this->company();
            if ($target->account_type!=='technician') $this->fail('Akun itu bukan pelaku usaha.','pelimpahan');
            $tech=$this->db->get_where('technician_profiles',array('user_id'=>$target->id))->row();
            if (!$tech || $tech->verification_status!=='approved' || ($target->phone_verification_required && !$target->phone_verified_at))
                $this->fail('Pelaku usaha belum diverifikasi oleh dinas.','pelimpahan');
            $tech_id=$target->id;$company_id=$company->id;
        } elseif ($actor->account_type==='technician') {
            $tech=$this->db->get_where('technician_profiles',array('user_id'=>$actor->id))->row();
            if (!$tech || $tech->verification_status!=='approved' || ($actor->phone_verification_required && !$actor->phone_verified_at))
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
    public function search_counterpart()
    {
        $actor=$this->require_login();
        if (!$this->metro->can($actor->id,'self.delegation.invite')) show_error('Akses ditolak.',403);
        $query=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80);
        $rows=array();
        if (mb_strlen($query)>=2) {
            if ($actor->account_type==='applicant') {
                $rows=$this->db->select('u.id,u.full_name,u.username,u.email,u.phone,tp.tdur_number,tp.recommendation_number')
                    ->from('users u')->join('technician_profiles tp','tp.user_id=u.id')
                    ->where('u.account_type','technician')->where('u.account_status','active')
                    ->where('tp.verification_status','approved')
                    ->group_start()->where('u.phone_verified_at IS NOT NULL',NULL,FALSE)
                    ->or_where('u.phone_verification_required',0)->group_end()
                    ->group_start()->like('u.full_name',$query)->or_like('u.username',$query)
                    ->or_like('tp.tdur_number',$query)->group_end()->order_by('u.full_name')
                    ->limit(12)->get()->result();
            } elseif ($actor->account_type==='technician') {
                $rows=$this->db->select('u.id,u.full_name,u.username,u.email,ap.organization_name,ap.address')
                    ->from('users u')->join('applicant_profiles ap','ap.user_id=u.id')
                    ->where('u.account_type','applicant')->where('u.account_status','active')
                    ->where('ap.profile_kind','organization')->where('ap.organization_name IS NOT NULL',NULL,FALSE)
                    ->group_start()->like('ap.organization_name',$query)->or_like('u.username',$query)
                    ->or_like('u.full_name',$query)->group_end()->order_by('ap.organization_name')
                    ->limit(12)->get()->result();
            }
        }
        $results=array();
        foreach ($rows as $row) $results[]=array('id'=>(int)$row->id,
            'label'=>$actor->account_type==='technician'?$row->organization_name:$row->full_name,
            'detail'=>$actor->account_type==='technician'?($row->address?:$row->username):
                (($row->tdur_number?'TDUR '.$row->tdur_number.' · ':'').($row->username?:$row->email)));
        $this->output->set_content_type('application/json')->set_output(json_encode($results,JSON_UNESCAPED_UNICODE));
    }
    private function document_target($id,$write=FALSE)
    {
        $actor=$this->require_login();
        $target=$this->metro->user((int)$id);
        if (!$target || $target->account_type!=='technician') show_404();
        if ((int)$actor->id!==(int)$target->id) {
            $permission=$write?'technicians.update':'technicians.view';
            if (!$this->metro->is_staff($actor->id) || !$this->metro->can($actor->id,$permission)) show_error('Akses ditolak.',403);
        } elseif ($write && !$this->metro->can($actor->id,'self.technician_profile.update')) show_error('Akses ditolak.',403);
        return array($actor,$target);
    }
    public function upload_documents($id)
    {
        $this->post_only();list($actor,$target)=$this->document_target($id,TRUE);
        $back=$this->metro->is_staff($actor->id)?'petugas/teknisi':'profil-teknisi';
        $files=$_FILES['documents']??NULL;
        if (!$files || !is_array($files['name']) || count($files['name'])<1 || count($files['name'])>10)
            { $this->fail('Pilih 1–10 dokumen PDF.',$back);return; }
        $type=(string)$this->input->post('document_type',TRUE);
        if (!in_array($type,array('tdur','recommendation','other'),TRUE)) $this->fail('Jenis dokumen tidak valid.',$back);
        $this->load->library('private_files');$stored=array();$this->db->trans_begin();
        try {
            foreach ($files['name'] as $i=>$name) {
                if ($name==='') continue;
                $_FILES['technician_document']=array('name'=>$name,'type'=>$files['type'][$i],
                    'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]);
                $file=$this->private_files->save('technician_document');$stored[]=$file['storage_key'];
                $this->db->insert('technician_documents',array('technician_user_id'=>$target->id,
                    'document_type'=>$type,'original_name'=>$file['original_name'],
                    'storage_key'=>$file['storage_key'],'uploaded_by'=>$actor->id));
            }
            if (!$stored || $this->db->trans_status()===FALSE) throw new RuntimeException('Dokumen gagal disimpan.');
            $this->db->where('user_id',$target->id)->where('verification_status','approved')
                ->update('technician_profiles',array('verification_status'=>'pending','review_note'=>'Dokumen pendukung berubah; perlu pemeriksaan ulang.','reviewed_by'=>NULL,'reviewed_at'=>NULL));
            $this->metro->audit($actor->id,'technician_documents_upload','technician_profiles',$target->id);
            $this->db->trans_commit();
            $this->message(count($stored).' dokumen tersimpan.',$back);
        } catch (RuntimeException $ex) {
            $this->db->trans_rollback();foreach ($stored as $key) $this->private_files->delete($key);
            $this->fail($ex->getMessage(),$back);
        }
    }
    public function document_file($id)
    {
        $doc=$this->db->get_where('technician_documents',array('id'=>(int)$id,'deleted_at'=>NULL))->row();
        if (!$doc) show_404();
        list($actor)=$this->document_target($doc->technician_user_id,FALSE);
        $this->load->library('private_files');$path=$this->private_files->path($doc->storage_key);
        if (!$path) show_404();
        header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="dokumen-pelaku-usaha-'.$doc->id.'.pdf"');
        header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store, private');
        readfile($path);exit;
    }
    public function delete_document($id)
    {
        $this->post_only();
        $doc=$this->db->get_where('technician_documents',array('id'=>(int)$id,'deleted_at'=>NULL))->row();
        if (!$doc) show_404();
        list($actor)=$this->document_target($doc->technician_user_id,TRUE);
        $this->db->where('id',$doc->id)->update('technician_documents',array('deleted_at'=>date('Y-m-d H:i:s')));
        $this->db->where('user_id',$doc->technician_user_id)->where('verification_status','approved')
            ->update('technician_profiles',array('verification_status'=>'pending','review_note'=>'Dokumen pendukung dihapus; perlu pemeriksaan ulang.','reviewed_by'=>NULL,'reviewed_at'=>NULL));
        $this->metro->audit($actor->id,'technician_document_delete','technician_documents',$doc->id);
        $this->message('Dokumen dihapus.',$this->metro->is_staff($actor->id)?'petugas/teknisi':'profil-teknisi');
    }
    public function respond($id)
    {
        $this->post_only();$actor=$this->require_login();
        if (!$this->metro->can($actor->id,'self.delegation.respond')) show_error('Akses ditolak.',403);
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
                if (!$tech || $tech->verification_status!=='approved' || ($tu->phone_verification_required && !$tu->phone_verified_at))
                    show_error('Pelaku usaha belum terverifikasi.',400);
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
        $technicians=$this->db->select('tp.*,u.full_name,u.username,u.email,u.phone,u.phone_verified_at,u.phone_verification_required,u.account_status')
            ->from('technician_profiles tp')->join('users u','u.id=tp.user_id')
            ->order_by('tp.created_at','DESC')->get()->result();
        $links=$this->db->select('ct.*,ap.organization_name,cu.full_name AS company_name,tu.full_name AS technician_name,tp.verification_status')
            ->from('company_technicians ct')->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->join('users cu','cu.id=ap.user_id')->join('users tu','tu.id=ct.technician_user_id')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->order_by('ct.created_at','DESC')->get()->result();
        $tp=metro_page(count($technicians),$this->input->get('tech_page'),20);
        $lp=metro_page(count($links),$this->input->get('link_page'),20);
        $documents=array();
        foreach ($this->db->where('deleted_at IS NULL',NULL,FALSE)->order_by('id','DESC')->get('technician_documents')->result() as $doc)
            $documents[$doc->technician_user_id][]=$doc;
        $this->page('staff/technicians',array('title'=>'Pelaku usaha dan pelimpahan',
            'technicians'=>array_slice($technicians,$tp['offset'],$tp['per_page']),
            'links'=>array_slice($links,$lp['offset'],$lp['per_page']),
            'tech_pagination'=>$tp,'link_pagination'=>$lp,
            'scope_types'=>$this->master->types(FALSE),'support_documents'=>$documents));
    }

    public function create_admin()
    {
        $this->post_only();$actor=$this->office('technicians.create');
        $name=trim((string)$this->input->post('full_name',TRUE));
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        $phone=trim((string)$this->input->post('phone',TRUE));
        $password=(string)$this->input->post('password');
        $number=trim((string)$this->input->post('recommendation_number',TRUE));
        $tdur=trim((string)$this->input->post('tdur_number',TRUE));
        if (mb_strlen($name)<3 || mb_strlen($name)>160 || mb_strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)
            || !preg_match('/^08[0-9]{8,12}$/',$phone) || strlen($password)<8 || strlen($password)>72
            || mb_strlen($number)>120 || mb_strlen($tdur)>120 || $this->db->where('email',$email)->count_all_results('users')
            || $this->db->where('phone',$phone)->count_all_results('users')) {
            $this->fail('Periksa identitas, nomor telepon, kata sandi, dan pastikan email/telepon belum dipakai.','petugas/teknisi');return;
        }
        $role=$this->db->get_where('roles',array('role_code'=>'external_technician','is_active'=>1))->row();
        if (!$role) show_error('Peran teknisi tidak tersedia.',500);
        $this->db->trans_begin();
        $this->db->insert('users',array('full_name'=>$name,'email'=>$email,'phone'=>$phone,
            'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'account_type'=>'technician',
            'account_status'=>'active','phone_verification_required'=>0));
        $id=$this->db->insert_id();
        $this->db->insert('user_roles',array('user_id'=>$id,'role_id'=>$role->id,'assigned_by'=>$actor->id));
        $this->db->insert('technician_profiles',array('user_id'=>$id,'recommendation_number'=>$number?:NULL,
            'tdur_number'=>$tdur?:NULL,'tdur_scope'=>$this->scope_input()));
        $this->metro->audit($actor->id,'technician_create','users',$id);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();$this->fail('Akun pelaku usaha gagal dibuat.','petugas/teknisi');return;}
        $this->db->trans_commit();
        $this->message('Akun pelaku usaha dibuat. Persetujuan dinas masih diperlukan.','petugas/teknisi');
    }

    public function update_admin($id)
    {
        $this->post_only();$actor=$this->office('technicians.update');
        $target=$this->metro->user((int)$id);
        $profile=$target && $target->account_type==='technician'
            ?$this->db->get_where('technician_profiles',array('user_id'=>$target->id))->row():NULL;
        if (!$profile) show_404();
        $name=trim((string)$this->input->post('full_name',TRUE));
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        $phone=trim((string)$this->input->post('phone',TRUE));
        $password=(string)$this->input->post('password');
        $number=trim((string)$this->input->post('recommendation_number',TRUE));
        $tdur=trim((string)$this->input->post('tdur_number',TRUE));
        $scope=$this->scope_input();
        if (mb_strlen($name)<3 || mb_strlen($name)>160 || mb_strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)
            || !preg_match('/^08[0-9]{8,12}$/',$phone) || ($password!=='' && (strlen($password)<8 || strlen($password)>72))
            || mb_strlen($number)>120 || mb_strlen($tdur)>120 || $this->db->where('email',$email)->where('id !=',$target->id)->count_all_results('users')
            || $this->db->where('phone',$phone)->where('id !=',$target->id)->count_all_results('users')) {
            $this->fail('Periksa identitas, nomor telepon, dan pastikan email/telepon belum dipakai.','petugas/teknisi');return;
        }
        $user_values=array('full_name'=>$name,'email'=>$email,'phone'=>$phone,'updated_at'=>date('Y-m-d H:i:s'));
        if ($password!=='') $user_values['password_hash']=password_hash($password,PASSWORD_DEFAULT);
        if ($phone!==$target->phone) $user_values['phone_verified_at']=NULL;
        $profile_values=array('recommendation_number'=>$number?:NULL,'tdur_number'=>$tdur?:NULL,'updated_at'=>date('Y-m-d H:i:s'));
        if ($scope!==NULL) $profile_values['tdur_scope']=$scope;
        if (($number!==(string)$profile->recommendation_number || $tdur!==(string)$profile->tdur_number
            || ($scope!==NULL && $scope!==(string)$profile->tdur_scope)) && $profile->verification_status==='approved') {
            $profile_values['verification_status']='pending';$profile_values['reviewed_by']=NULL;
            $profile_values['reviewed_at']=NULL;$profile_values['review_note']='Nomor rekomendasi diubah; perlu verifikasi ulang.';
        }
        $this->db->trans_begin();
        $this->db->where('id',$target->id)->update('users',$user_values);
        $this->db->where('user_id',$target->id)->update('technician_profiles',$profile_values);
        if ($phone!==$target->phone) $this->db->where('user_id',$target->id)->where('verified_at IS NULL',NULL,FALSE)
            ->update('phone_verification_codes',array('verified_at'=>date('Y-m-d H:i:s')));
        if ($phone!==$target->phone) $this->db->where('user_id',$target->id)->where('status','PENDING')
            ->update('wa_outbox',array('status'=>'CANCELLED','message'=>''));
        $this->metro->audit($actor->id,'technician_update','users',$target->id);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();$this->fail('Akun pelaku usaha gagal diperbarui.','petugas/teknisi');return;}
        $this->db->trans_commit();
        $this->message('Akun pelaku usaha diperbarui.','petugas/teknisi');
    }

    public function toggle_admin($id)
    {
        $this->post_only();$actor=$this->office('technicians.status');
        $target=$this->metro->user((int)$id);
        if (!$target || $target->account_type!=='technician') show_404();
        if ($target->account_status==='pending') { $this->fail('Gunakan tombol Setujui agar kredensial dapat dikirim.','petugas/teknisi');return; }
        $status=$target->account_status==='active'?'inactive':'active';
        $this->db->where('id',$target->id)->update('users',array('account_status'=>$status,'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,$status==='active'?'technician_activate':'technician_deactivate','users',$target->id);
        $this->message($status==='active'?'Akun pelaku usaha diaktifkan.':'Akun pelaku usaha dinonaktifkan.','petugas/teknisi');
    }

    public function delete_admin($id)
    {
        $this->post_only();$actor=$this->office('technicians.delete');
        $target=$this->metro->user((int)$id);
        if (!$target || $target->account_type!=='technician') show_404();
        $refs=array(array('company_technicians','technician_user_id'),array('company_technicians','initiated_by_user_id'),
            array('technician_documents','technician_user_id'),
            array('service_requests','submitted_by_user_id'),array('audit_logs','actor_user_id'),
            array('service_documents','uploaded_by'),array('user_roles','assigned_by'));
        foreach ($refs as $ref) if ($this->db->where($ref[1],$target->id)->count_all_results($ref[0])) {
            $this->fail('Akun pelaku usaha sudah memiliki riwayat. Nonaktifkan akun sebagai gantinya.','petugas/teknisi');return;
        }
        $this->db->trans_begin();
        $this->db->where('user_id',$target->id)->delete('wa_outbox');
        $this->db->where('user_id',$target->id)->delete('phone_verification_codes');
        $this->db->where('user_id',$target->id)->delete('notifications');
        $this->db->where('user_id',$target->id)->delete('user_roles');
        $this->db->where('user_id',$target->id)->delete('technician_profiles');
        $this->db->where('id',$target->id)->delete('users');
        $this->metro->audit($actor->id,'technician_delete','users',$target->id);
        if ($this->db->trans_status()===FALSE) {$this->db->trans_rollback();$this->fail('Akun pelaku usaha gagal dihapus.','petugas/teknisi');return;}
        $this->db->trans_commit();
        $this->message('Akun pelaku usaha dihapus.','petugas/teknisi');
    }
    public function review_technician($id)
    {
        $this->post_only();$actor=$this->office('technicians.verify');
        $row=$this->db->select('tp.*,u.phone_verified_at,u.phone_verification_required,u.account_status')->from('technician_profiles tp')
            ->join('users u','u.id=tp.user_id')->where('tp.user_id',(int)$id)->get()->row();
        if (!$row) show_404();
        $action=(string)$this->input->post('action',TRUE);
        $number=trim((string)$this->input->post('recommendation_number',TRUE));
        $tdur=trim((string)$this->input->post('tdur_number',TRUE));
        $head=trim((string)$this->input->post('recommendation_head',TRUE));
        $scope=$this->scope_input();
        $note=mb_substr(trim((string)$this->input->post('review_note',TRUE)),0,1000);
        if (!in_array($action,array('approve','reject'),TRUE) || mb_strlen($number)>120 || mb_strlen($tdur)>120 || mb_strlen($head)>160)
            show_error('Data verifikasi tidak valid.',400);
        $has_tdur_document=$this->db->where('technician_user_id',$row->user_id)->where('document_type','tdur')
            ->where('deleted_at IS NULL',NULL,FALSE)->count_all_results('technician_documents')>0;
        if ($action==='approve' && (($row->phone_verification_required && !$row->phone_verified_at) || $row->account_status!=='active' || mb_strlen($number)<3
            || mb_strlen($tdur)<3 || mb_strlen($head)<3 || !($scope===NULL?json_decode((string)$row->tdur_scope,TRUE):json_decode($scope,TRUE))
            || !$has_tdur_document))
            show_error('Persetujuan memerlukan TDUR, kewenangan UTTP, rekomendasi kepala dinas, dan nomor telepon terverifikasi.',400);
        $status=$action==='approve'?'approved':'rejected';
        $update=array(
            'recommendation_number'=>$number ?: NULL,'verification_status'=>$status,
            'tdur_number'=>$tdur ?: NULL,'recommendation_head'=>$head ?: NULL,
            'reviewed_by'=>$actor->id,'reviewed_at'=>date('Y-m-d H:i:s'),'review_note'=>$note ?: NULL,
            'updated_at'=>date('Y-m-d H:i:s'));
        if ($scope!==NULL) $update['tdur_scope']=$scope;
        $this->db->where('user_id',$row->user_id)->update('technician_profiles',$update);
        $this->metro->audit($actor->id,'technician_'.$status,'technician_profiles',$row->user_id);
        $this->metro->notify($row->user_id,'Verifikasi pelaku usaha',$status==='approved'?'Akun pelaku usaha Anda disetujui dinas.':'Verifikasi pelaku usaha belum disetujui. Periksa catatan dinas.','profil-teknisi');
        $this->message('Status pelaku usaha diperbarui.','petugas/teknisi');
    }
    public function review_connection($id)
    {
        $this->post_only();$actor=$this->office('technicians.delegation_review');
        $row=$this->db->select('ct.*,ap.user_id AS company_user_id,ap.profile_kind,tp.verification_status,tu.phone_verified_at,tu.phone_verification_required,tu.account_status AS technician_status,cu.account_status AS company_status')
            ->from('company_technicians ct')->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->join('users tu','tu.id=ct.technician_user_id')->join('users cu','cu.id=ap.user_id')
            ->where('ct.id',(int)$id)->get()->row();
        if (!$row) show_404();
        $action=(string)$this->input->post('action',TRUE);
        $note=mb_substr(trim((string)$this->input->post('review_note',TRUE)),0,1000);
        if ($action==='approve') {
            if ($row->status!=='pending_office' || !$row->counterpart_accepted_at || $row->verification_status!=='approved'
                || ($row->phone_verification_required && !$row->phone_verified_at) || $row->technician_status!=='active' || $row->company_status!=='active'
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
            $this->metro->notify($recipient,'Status pelimpahan','Hubungan pelaku usaha dan perusahaan: '.$status.'.','pelimpahan');
        $this->message('Status pelimpahan diperbarui.','petugas/teknisi');
    }
}
