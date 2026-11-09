<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
    public function login()
    {
        if ($this->user()) redirect($this->metro->is_staff($this->user()->id) ? 'petugas' : 'permohonan');
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
                $user = $this->metro->user_by_email($email);
                if ($user && $user->account_status === 'active' && password_verify($password, $user->password_hash)) {
                    $this->session->sess_regenerate(TRUE);
                    $this->session->set_userdata('user_id', (int) $user->id);
                    $this->session->set_userdata('auth_marker', hash('sha256', $user->password_hash));
                    $this->db->where('id', $user->id)->update('users', array('last_login_at' => date('Y-m-d H:i:s')));
                    if ($this->metro->is_staff($user->id)) $this->metro->audit($user->id, 'staff_login', 'users', $user->id);
                    redirect($this->metro->is_staff($user->id) ? 'petugas' : 'permohonan');
                    return;
                }
                $this->db->insert('login_attempts', array('email' => $email, 'ip_address' => $this->input->ip_address()));
                $error = 'Email atau kata sandi tidak cocok.';
            }
        }
        $this->page('auth/login', array('title' => 'Masuk', 'error' => $error));
    }

    public function register()
    {
        if ($this->user()) redirect('permohonan');
        $error = NULL;
        if ($this->input->method() === 'post') {
            $name = trim((string) $this->input->post('full_name', TRUE));
            $email = strtolower(trim((string) $this->input->post('email', TRUE)));
            $phone = trim((string) $this->input->post('phone', TRUE));
            $password = (string) $this->input->post('password');
            if (mb_strlen($name) < 3 || mb_strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72) {
                $error = 'Isi nama, email yang valid, dan kata sandi 12–72 karakter.';
            } elseif ($this->metro->user_by_email($email)) {
                $error = 'Email sudah terdaftar.';
            } else {
                $this->db->trans_begin();
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $this->db->insert('users', array('full_name' => $name, 'email' => $email,
                    'phone' => $phone ?: NULL, 'password_hash' => $password_hash,
                    'account_type' => 'applicant', 'account_status' => 'active'));
                $id = $this->db->insert_id();
                $applicant_role = $this->db->get_where('roles', array('role_code' => 'applicant'))->row();
                $this->db->insert('user_roles', array('user_id' => $id, 'role_id' => $applicant_role->id));
                $this->db->insert('applicant_profiles', array('user_id' => $id, 'contact_person' => $name));
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    $error = 'Pendaftaran gagal. Periksa data dan coba lagi.';
                } else {
                    $this->db->trans_commit();
                    $this->session->sess_regenerate(TRUE);
                    $this->session->set_userdata('user_id', $id);
                    $this->session->set_userdata('auth_marker', hash('sha256', $password_hash));
                    redirect('profil');
                    return;
                }
            }
        }
        $this->page('auth/register', array('title' => 'Daftar pemohon', 'error' => $error));
    }

    public function logout()
    {
        $this->post_only();
        $this->session->sess_destroy();
        redirect('login');
    }

    public function profile()
    {
        $user = $this->require_login();
        if ($this->metro->is_staff($user->id)) redirect('petugas');
        $profile = $this->metro->profile($user->id);
        $error = NULL;
        if ($this->input->method() === 'post') {
            $address = trim((string) $this->input->post('address', TRUE));
            $contact = trim((string) $this->input->post('contact_person', TRUE));
            if (mb_strlen($address) < 10 || mb_strlen($contact) < 3) {
                $error = 'Isi alamat dan nama penanggung jawab dengan lengkap.';
            } else {
                $this->db->where('id', $profile->id)->update('applicant_profiles', array(
                    'profile_kind' => $this->input->post('profile_kind') === 'organization' ? 'organization' : 'individual',
                    'organization_name' => trim((string) $this->input->post('organization_name', TRUE)) ?: NULL,
                    'contact_person' => $contact, 'address' => $address,
                    'district' => trim((string) $this->input->post('district', TRUE)),
                    'village' => trim((string) $this->input->post('village', TRUE)),
                    'updated_at' => date('Y-m-d H:i:s')));
                $this->message('Profil tersimpan.', 'permohonan');
                return;
            }
        }
        $this->page('auth/profile', array('title' => 'Profil pemohon', 'profile' => $profile, 'error' => $error));
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
            elseif (strlen($new) < 12 || strlen($new) > 72) $error = 'Kata sandi baru harus 12–72 karakter.';
            elseif ($new !== $confirm) $error = 'Konfirmasi kata sandi tidak cocok.';
            else {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $this->db->where('id', $user->id)->update('users', array('password_hash' => $new_hash, 'updated_at' => date('Y-m-d H:i:s')));
                $this->session->set_userdata('auth_marker', hash('sha256', $new_hash));
                $this->metro->audit($user->id, 'password_change', 'users', $user->id);
                $this->message('Kata sandi berhasil diganti.', $this->metro->is_staff($user->id) ? 'petugas' : 'permohonan');
                return;
            }
        }
        $this->page('auth/change_password', array('title' => 'Ganti kata sandi', 'error' => $error));
    }
}
