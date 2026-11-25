<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
    private function home_for($user)
    {
        if ($this->metro->is_staff($user->id)) return 'petugas';
        $routes=$user->account_type==='technician'
            ?array('self.technician_profile.view'=>'profil-teknisi','self.requests.view'=>'permohonan','self.delegation.view'=>'pelimpahan')
            :array('self.requests.view'=>'permohonan','self.profile.view'=>'profil','self.uttp.view'=>'uttp','self.delegation.view'=>'pelimpahan');
        foreach ($routes as $permission=>$route) if ($this->metro->can($user->id,$permission)) return $route;
        return 'ganti-kata-sandi';
    }

    public function login()
    {
        if ($this->user()) redirect($this->home_for($this->user()));
        $error = NULL;
        if ($this->input->method() === 'post') {
            $email = strtolower(trim((string) $this->input->post('email', TRUE)));
            $password = (string) $this->input->post('password');
            $since = date('Y-m-d H:i:s', time() - 900);
            $attempts = $this->db->where('attempted_at >=', $since)->group_start()
                ->where('email', $email)->or_where('ip_address', $this->input->ip_address())->group_end()
                ->count_all_results('login_attempts');
            if ($attempts >= 8) {
                $error = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
            } else {
                $user = $this->db->group_start()->where('email',$email)->or_where('username',$email)->group_end()->get('users')->row();
                if ($user && $user->account_status === 'active' && password_verify($password, $user->password_hash)) {
                    $this->load->library('remember_me');
                    $this->remember_me->forget();
                    $this->session->sess_regenerate(TRUE);
                    $this->session->set_userdata('user_id', (int) $user->id);
                    $this->session->set_userdata('auth_marker', hash('sha256', $user->password_hash));
                    if ($this->input->post('remember_me')==='1') $this->remember_me->issue($user->id);
                    $this->db->where('id', $user->id)->update('users', array('last_login_at' => date('Y-m-d H:i:s')));
                    if ($this->metro->is_staff($user->id)) $this->metro->audit($user->id, 'staff_login', 'users', $user->id);
                    redirect($this->home_for($user));
                    return;
                }
                $this->db->insert('login_attempts', array('email' => $email, 'ip_address' => $this->input->ip_address()));
                $error = 'Username/email atau kata sandi tidak cocok. Jika baru mendaftar, tunggu persetujuan petugas.';
            }
        }
        $this->page('auth/login', array('title' => 'Masuk', 'error' => $error));
    }

    public function register()
    {
        if ($this->user()) redirect($this->home_for($this->user()));
        $error = NULL;
        if ($this->input->method() === 'post') {
            $name = trim((string) $this->input->post('full_name', TRUE));
            $email = strtolower(trim((string) $this->input->post('email', TRUE)));
            $phone = trim((string) $this->input->post('phone', TRUE));
            $district_id = (int) $this->input->post('district_id');
            $village_id = (int) $this->input->post('village_id');
            $kind=(string)$this->input->post('account_kind',TRUE);
            $technician=$kind==='technician';
            $region=$technician ? NULL : $this->master->profile_region($district_id,$village_id);
            $recommendation=trim((string)$this->input->post('recommendation_number',TRUE));
            if (mb_strlen($name) < 3 || mb_strlen($name) > 160 || mb_strlen($email)>190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Isi nama dan email yang valid.';
            } elseif (!in_array($kind,array('applicant','technician'),TRUE)
                || !preg_match('/^08[0-9]{8,12}$/', $phone) || (!$technician && !$region)
                || mb_strlen($recommendation)>120) {
                $error = 'Periksa jenis akun, nomor telepon, kecamatan, desa, dan nomor rekomendasi.';
            } elseif ($this->db->get_where('users', array('phone' => $phone))->row()) {
                $error = 'Nomor telepon sudah terdaftar.';
            } elseif ($this->metro->user_by_email($email)) {
                $error = 'Email sudah terdaftar.';
            } else {
                $role=$this->db->get_where('roles',array('role_code'=>$technician?'external_technician':'applicant','is_active'=>1))->row();
                if (!$role) { $error='Pendaftaran jenis akun ini sedang tidak tersedia.'; }
                else {
                $this->db->trans_begin();
                $password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $this->db->insert('users', array('full_name' => $name, 'email' => $email,
                    'phone' => $phone, 'password_hash' => $password_hash,
                    'account_type' => $technician?'technician':'applicant', 'account_status' => 'pending', 'phone_verification_required' => 0));
                $id = $this->db->insert_id();
                $this->db->insert('user_roles', array('user_id' => $id, 'role_id' => $role->id));
                if ($technician) $this->db->insert('technician_profiles',array('user_id'=>$id,
                    'recommendation_number'=>$recommendation ?: NULL));
                else $this->db->insert('applicant_profiles', array('user_id' => $id, 'contact_person' => $name, 'district' => $region[0]->district_name, 'district_id' => $district_id,
                    'village' => $region[1]->village_name, 'village_id' => $village_id));
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    $error = 'Pendaftaran gagal. Periksa data dan coba lagi.';
                } else {
                    $this->db->trans_commit();
                    $this->session->set_flashdata('message','Pendaftaran diterima. Petugas akan memeriksa data dan mengirim username serta kata sandi melalui WhatsApp secara manual.');
                    redirect('login');
                    return;
                }
                }
            }
        }
        $selected_village = $this->master->village((int) $this->input->post('village_id'), FALSE);
        $this->page('auth/register', array('title' => 'Daftar akun', 'error' => $error,
            'districts' => $this->master->districts(), 'selected_village' => $selected_village));
    }

    public function logout()
    {
        $this->post_only();
        $this->load->library('remember_me');
        $this->remember_me->forget();
        $this->session->sess_destroy();
        redirect('login');
    }

    public function profile()
    {
        $user = $this->require_login();
        if ($this->metro->is_staff($user->id)) redirect('petugas');
        if ($user->account_type==='technician') redirect('profil-teknisi');
        if (!$this->metro->can($user->id,'self.profile.view')) show_error('Akses ditolak.',403);
        $profile = $this->metro->profile($user->id);
        $error = NULL;
        if ($this->input->method() === 'post') {
            if (!$this->metro->can($user->id,'self.profile.update')) show_error('Akses ditolak.',403);
            $address = trim((string) $this->input->post('address', TRUE));
            $contact = trim((string) $this->input->post('contact_person', TRUE));
            $district_id = (int) $this->input->post('district_id');
            $village_id = (int) $this->input->post('village_id');
            $region = $this->master->profile_region($district_id, $village_id);
            $kind = (string) $this->input->post('profile_kind', TRUE);
            $coordinates=metro_coordinates($this->input->post('latitude'),$this->input->post('longitude'));
            $source=(string)$this->input->post('location_source',TRUE);
            if (mb_strlen($address) < 10 || mb_strlen($contact) < 3 || !$region || !$this->master->option_code('profile_kind', $kind)
                || $coordinates===FALSE || ($coordinates && !in_array($source,array('','manual','map','google','google_review'),TRUE))) {
                $error = 'Isi alamat, wilayah, dan koordinat dengan benar. Lintang serta bujur harus diisi berpasangan.';
            } else {
                $this->db->where('id', $profile->id)->update('applicant_profiles', array(
                    'profile_kind' => $kind,
                    'organization_name' => trim((string) $this->input->post('organization_name', TRUE)) ?: NULL,
                    'contact_person' => $contact, 'address' => $address,
                    'latitude'=>$coordinates?$coordinates[0]:NULL,
                    'longitude'=>$coordinates?$coordinates[1]:NULL,
                    'location_source'=>$coordinates?($source ?: 'manual'):NULL,
                    'district' => $region[0]->district_name, 'district_id' => $district_id,
                    'village' => $region[1]->village_name, 'village_id' => $village_id,
                    'updated_at' => date('Y-m-d H:i:s')));
                $this->metro->forget_permissions($user->id);
                $this->message('Profil tersimpan.', $user->phone_verification_required && !$user->phone_verified_at ? 'profil' : $this->home_for($user));
                return;
            }
        }
        $selected_village = $this->master->village($profile->village_id, FALSE);
        $this->page('auth/profile', array('title' => 'Profil pemohon', 'profile' => $profile, 'error' => $error,
            'districts' => $this->master->districts(), 'profile_kinds' => $this->master->options('profile_kind'),
            'selected_village' => $selected_village));
    }

    public function verify_phone()
    {
        $this->post_only();
        $user = $this->require_login();
        if ($this->metro->is_staff($user->id)) show_error('Akses ditolak.',403);
        if ($user->phone_verified_at) { $this->message('Nomor telepon sudah terverifikasi.',$user->account_type==='technician'?'profil-teknisi':'profil'); return; }
        $code = trim((string) $this->input->post('code',TRUE));
        $row = $this->db->where('user_id',$user->id)->where('verified_at IS NULL',NULL,FALSE)->order_by('id','DESC')->get('phone_verification_codes')->row();
        $target=$user->account_type==='technician'?'profil-teknisi':'profil';
        if (!$row || strtotime($row->expires_at) < time() || $row->attempts >= 5 || !preg_match('/^[0-9]{6}$/',$code)) {
            $this->fail('Kode tidak tersedia atau kedaluwarsa. Minta kode baru.',$target); return;
        }
        $this->db->where('id',$row->id)->set('attempts','attempts+1',FALSE)->update('phone_verification_codes');
        if (!password_verify($code,$row->code_hash)) { $this->fail('Kode tidak cocok. Periksa kembali enam digit kode.',$target); return; }
        $this->db->trans_begin();
        $this->db->where('id',$user->id)->update('users',array('phone_verified_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')));
        $this->db->where('id',$row->id)->update('phone_verification_codes',array('verified_at'=>date('Y-m-d H:i:s')));
        $this->db->where('user_id',$user->id)->where('status','PENDING')->update('wa_outbox',array('status'=>'CANCELLED','message'=>''));
        $this->metro->audit($user->id,'phone_verified','users',$user->id);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); show_error('Verifikasi gagal.',500); }
        $this->db->trans_commit();
        $this->message('Nomor telepon berhasil diverifikasi.',$target);
    }

    public function resend_phone_code()
    {
        $this->post_only();
        $user=$this->require_login();
        if ($this->metro->is_staff($user->id) || !in_array($user->account_type,array('applicant','technician'),TRUE))
            show_error('Akses ditolak.',403);
        $target=$user->account_type==='technician'?'profil-teknisi':'profil';
        if ($user->phone_verified_at) $this->message('Nomor sudah terverifikasi.',$target);
        $this->load->model('Wa_model','wa');
        if (!$this->wa->connected()) $this->fail('Bot WhatsApp belum terhubung. Minta kode manual kepada petugas.',$target);
        if (!$this->wa->queue_code($user)) $this->fail('Kode belum dapat dikirim. Batas pengiriman lima kode per jam atau nomor tidak valid.',$target);
        $this->metro->audit($user->id,'phone_code_wa','users',$user->id);
        $this->message('Kode baru dimasukkan ke antrean WhatsApp.',$target);
    }

    public function change_password()
    {
        $user = $this->require_login();
        $error = NULL;
        if ($this->input->method() === 'post') {
            $old = (string) $this->input->post('old_password');
            $new = (string) $this->input->post('new_password');
            $confirm = (string) $this->input->post('confirm_password');
            if (!password_verify($old, $user->password_hash)) $error = 'Kata sandi lama tidak cocok.';
            elseif (strlen($new) < 8 || strlen($new) > 72) $error = 'Kata sandi baru harus 8–72 karakter.';
            elseif ($new !== $confirm) $error = 'Konfirmasi kata sandi tidak cocok.';
            else {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $this->db->where('id', $user->id)->update('users', array('password_hash' => $new_hash, 'updated_at' => date('Y-m-d H:i:s')));
                $this->load->library('remember_me');
                $this->remember_me->forget_user($user->id);
                $this->session->set_userdata('auth_marker', hash('sha256', $new_hash));
                $this->metro->audit($user->id, 'password_change', 'users', $user->id);
                $this->message('Kata sandi berhasil diganti.', $this->metro->is_staff($user->id) ? 'petugas' : ($user->account_type==='technician'?'profil-teknisi':'permohonan'));
                return;
            }
        }
        $this->page('auth/change_password', array('title' => 'Ganti kata sandi', 'error' => $error));
    }

    public function account()
    {
        $this->post_only();
        $user=$this->require_login();
        if ($this->metro->is_staff($user->id)) show_error('Akses ditolak.',403);
        $target=$user->account_type==='technician'?'profil-teknisi':'profil';
        $username=strtolower(trim((string)$this->input->post('username',TRUE)));
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        $phone=trim((string)$this->input->post('phone',TRUE));
        $password=(string)$this->input->post('current_password');
        if (!password_verify($password,$user->password_hash)) { $this->fail('Kata sandi saat ini tidak cocok.',$target); return; }
        if (!preg_match('/^[a-z0-9._-]{4,90}$/',$username) || mb_strlen($email)>190 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))
            || ($phone!=='' && !preg_match('/^08[0-9]{8,12}$/',$phone))) {
            $this->fail('Periksa username, email, dan nomor telepon.',$target); return;
        }
        foreach (array('username'=>$username,'email'=>$email?:NULL,'phone'=>$phone?:NULL) as $field=>$value)
            if ($value!==NULL && $this->db->where($field,$value)->where('id !=',$user->id)->count_all_results('users')) {
                $this->fail(ucfirst($field).' sudah digunakan akun lain.',$target); return;
            }
        $changes=array('username'=>$username,'email'=>$email?:NULL,'phone'=>$phone?:NULL,'updated_at'=>date('Y-m-d H:i:s'));
        if ($phone!==(string)$user->phone) $changes['phone_verified_at']=NULL;
        $this->db->where('id',$user->id)->update('users',$changes);
        $this->metro->audit($user->id,'account_contact_update','users',$user->id);
        $this->message('Kontak dan username tersimpan.',$target);
    }
}
