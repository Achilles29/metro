<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Integrations extends MY_Controller
{
    private function admin()
    {
        $user=$this->require_staff();
        if (!$this->metro->has_role($user->id,array('administrator'))) show_error('Akses ditolak.',403);
        return $user;
    }

    public function index()
    {
        $this->admin();$this->load->library('google_workspace');
        $this->page('staff/integrations',array('title'=>'Integrasi Google',
            'settings'=>$this->db->get_where('google_settings',array('id'=>1))->row(),
            'account_email'=>$this->google_workspace->account_email(),
            'oauth_client'=>$this->google_workspace->oauth_client_configured(),
            'oauth_connected'=>$this->google_workspace->oauth_connected()));
    }

    public function save()
    {
        $this->post_only();$actor=$this->admin();
        $sheet=trim((string)$this->input->post('spreadsheet_id',TRUE));
        $range=trim((string)$this->input->post('sheet_range',TRUE));
        $order_sheet=trim((string)$this->input->post('order_spreadsheet_id',TRUE));
        $order_range=trim((string)$this->input->post('order_sheet_range',TRUE));
        $ttu_sheet=trim((string)$this->input->post('ttu_spreadsheet_id',TRUE));
        $sah_range=trim((string)$this->input->post('ttu_sah_range',TRUE));
        $batal_range=trim((string)$this->input->post('ttu_batal_range',TRUE));
        $other_range=trim((string)$this->input->post('ttu_other_range',TRUE));
        $folder=trim((string)$this->input->post('drive_folder_id',TRUE));
        $pattern=trim((string)$this->input->post('cerapan_filename_pattern',TRUE));
        $auth_mode=(string)$this->input->post('drive_auth_mode',TRUE);
        if (($sheet!=='' && !preg_match('/^[A-Za-z0-9_-]{15,190}$/',$sheet))
            || ($order_sheet!=='' && !preg_match('/^[A-Za-z0-9_-]{15,190}$/',$order_sheet))
            || ($ttu_sheet!=='' && !preg_match('/^[A-Za-z0-9_-]{15,190}$/',$ttu_sheet))
            || ($folder!=='' && !preg_match('/^[A-Za-z0-9_-]{10,190}$/',$folder))
            || !preg_match('~^[A-Za-z0-9_ !:\-\x27/]{2,190}$~',$range)
            || !preg_match('~^[A-Za-z0-9_ !:\-\x27/]{2,190}$~',$order_range)
            || !preg_match('~^[A-Za-z0-9_ !:\-\x27/]{2,190}$~',$sah_range)
            || !preg_match('~^[A-Za-z0-9_ !:\-\x27/]{2,190}$~',$batal_range)
            || !preg_match('~^[A-Za-z0-9_ !:\-\x27/]{2,190}$~',$other_range)
            || mb_strlen($pattern)<8 || mb_strlen($pattern)>190 || strpos($pattern,'{request_no}')===FALSE
            || !in_array($auth_mode,array('service_account','oauth'),TRUE))
            $this->fail('Periksa ID, rentang sheet, dan pola nama berkas. Pola wajib memuat {request_no}.','petugas/integrasi');
        $this->db->where('id',1)->update('google_settings',array('spreadsheet_id'=>$sheet ?: NULL,
            'sheet_range'=>$range,'order_spreadsheet_id'=>$order_sheet ?: NULL,'order_sheet_range'=>$order_range,
            'ttu_spreadsheet_id'=>$ttu_sheet ?: NULL,'ttu_sah_range'=>$sah_range,'ttu_batal_range'=>$batal_range,
            'ttu_other_range'=>$other_range,'drive_folder_id'=>$folder ?: NULL,'drive_auth_mode'=>$auth_mode,
            'cerapan_filename_pattern'=>$pattern,'updated_by'=>$actor->id));
        $this->metro->audit($actor->id,'google_settings_update','google_settings',1);
        $this->message('Pengaturan Google tersimpan.','petugas/integrasi');
    }

    public function credentials()
    {
        $this->post_only();$actor=$this->admin();$this->load->library('google_workspace');
        try {$email=$this->google_workspace->install_credentials('credentials');}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/integrasi');return;}
        $this->metro->audit($actor->id,'google_credentials_update','google_settings',1,$email);
        $this->message('Akun layanan Google tersimpan. Bagikan sheet dan folder kepada '.$email.'.','petugas/integrasi');
    }

    public function test()
    {
        $this->post_only();$this->admin();$this->load->library('google_workspace');
        try {$result=$this->google_workspace->test_connection();}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/integrasi');return;}
        $parts=array();foreach (array('ctt'=>'CTT','order'=>'Order','ttu'=>'TTU') as $key=>$label) if (isset($result[$key])) $parts[]=$label.': '.$result[$key]['properties']['title'];
        if (isset($result['folder'])) $parts[]='Folder: '.$result['folder']['name'];
        $this->message($parts?'Koneksi berhasil. '.implode('; ',$parts):'Kredensial valid. Isi ID spreadsheet dan folder untuk menguji akses.','petugas/integrasi');
    }

    public function oauth_credentials()
    {
        $this->post_only();$actor=$this->admin();$this->load->library('google_workspace');
        try {$this->google_workspace->install_oauth_client('oauth_credentials');}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/integrasi');return;}
        $this->metro->audit($actor->id,'google_oauth_client_update','google_settings',1);
        $this->message('OAuth client tersimpan. Klik Hubungkan akun Google.','petugas/integrasi');
    }

    public function oauth_start()
    {
        $this->admin();$this->load->library('google_workspace');
        $state=bin2hex(random_bytes(24));$this->session->set_userdata('google_oauth_state',$state);
        try {$url=$this->google_workspace->authorization_url($state);}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/integrasi');return;}
        redirect($url,'location',302);
    }

    public function oauth_callback()
    {
        $actor=$this->admin();$state=(string)$this->input->get('state',TRUE);
        $expected=(string)$this->session->userdata('google_oauth_state');
        $this->session->unset_userdata('google_oauth_state');
        if (!$expected || !hash_equals($expected,$state)) show_error('Status OAuth tidak cocok. Mulai ulang sambungan.',403);
        if ($this->input->get('error',TRUE)) {$this->fail('Google menolak atau membatalkan sambungan.','petugas/integrasi');return;}
        $code=(string)$this->input->get('code',FALSE);if (!$code) show_error('Kode OAuth tidak diterima.',400);
        $this->load->library('google_workspace');
        try {$this->google_workspace->finish_oauth($code);}
        catch (RuntimeException $ex) {$this->fail($ex->getMessage(),'petugas/integrasi');return;}
        $this->db->where('id',1)->update('google_settings',array('drive_auth_mode'=>'oauth','updated_by'=>$actor->id));
        $this->metro->audit($actor->id,'google_oauth_connected','google_settings',1);
        $this->message('Akun Google tersambung. Jalankan Uji koneksi untuk memeriksa ketiga sheet dan folder.','petugas/integrasi');
    }
}
