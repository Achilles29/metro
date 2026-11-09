<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Metro_model', 'metro');
        $this->load->helper('metro');
    }

    protected function user()
    {
        $id = (int) $this->session->userdata('user_id');
        if (!$id) return NULL;
        $user = $this->metro->user($id);
        $marker = (string) $this->session->userdata('auth_marker');
        if (!$user || !hash_equals(hash('sha256', $user->password_hash), $marker)) {
            $this->session->sess_destroy();
            return NULL;
        }
        return $user;
    }

    protected function require_login()
    {
        $user = $this->user();
        if (!$user || $user->account_status !== 'active') {
            redirect('login');
            exit;
        }
        return $user;
    }

    protected function require_staff()
    {
        $user = $this->require_login();
        if (!$this->metro->is_staff($user->id)) show_error('Akses ditolak.', 403);
        return $user;
    }

    protected function post_only()
    {
        if (strtoupper($this->input->method()) !== 'POST') show_error('Metode tidak diizinkan.', 405);
    }

    protected function page($view, $data = array())
    {
        $data['current_user'] = $this->user();
        $data['body_view'] = $view;
        $data['flash'] = $this->session->flashdata('message');
        $data['error'] = isset($data['error']) ? $data['error'] : $this->session->flashdata('error');
        $this->load->view('layout', $data);
    }

    protected function message($text, $url)
    {
        $this->session->set_flashdata('message', $text);
        redirect($url);
    }

    protected function fail($text, $url)
    {
        $this->session->set_flashdata('error', $text);
        redirect($url);
    }
}
