<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Wa_model','wa');
    }

    private function admin()
    {
        $user=$this->require_staff();
        if (!$this->metro->has_role($user->id,array('administrator'))) show_error('Akses ditolak.',403);
        return $user;
    }

    public function index()
    {
        $this->admin();
        $session=$this->wa->session();
        $settings=$this->db->get_where('wa_settings',array('id'=>1))->row();
        $rows=$this->db->select('o.id,o.phone_number,o.status,o.attempts,o.error_message,o.created_at,o.sent_at,u.full_name')
            ->from('wa_outbox o')->join('users u','u.id=o.user_id')->order_by('o.id','DESC')->limit(30)->get()->result();
        $notifications=$this->db->select('n.id,n.phone_number,n.status,n.attempts,n.error_message,n.created_at,n.sent_at,u.full_name')
            ->from('wa_notifications n')->join('users u','u.id=n.user_id')->order_by('n.id','DESC')->limit(30)->get()->result();
        $this->page('staff/whatsapp',array('title'=>'Pengaturan WhatsApp','wa_session'=>$session,
            'wa_settings'=>$settings,'outbox'=>$rows,'notifications'=>$notifications,'engine_status'=>$this->wa->engine('status')));
    }

    public function qr()
    {
        $this->admin();
        $this->output->set_header('Cache-Control: no-store, private')->set_content_type('application/json')
            ->set_output(json_encode($this->wa->engine('qr')));
    }

    public function control()
    {
        $this->post_only(); $actor=$this->admin();
        $action=(string)$this->input->post('action',TRUE);
        if (!in_array($action,array('enable','disable','unlink'),TRUE)) show_error('Aksi tidak valid.',400);
        $result=$this->wa->engine('control',$action);
        if (empty($result['ok'])) $this->fail($result['message'] ?? 'WA Engine tidak merespons.','petugas/whatsapp');
        $this->metro->audit($actor->id,'wa_control','wa_session',1,$action);
        $this->message($result['message'] ?? 'Pengaturan WhatsApp diperbarui.','petugas/whatsapp');
    }

    public function template()
    {
        $this->post_only(); $actor=$this->admin();
        $template=trim((string)$this->input->post('otp_template',TRUE));
        if (mb_strlen($template)<20 || mb_strlen($template)>500 || substr_count($template,'{code}')!==1)
            $this->fail('Pesan harus 20–500 karakter dan memuat tepat satu {code}.','petugas/whatsapp');
        $this->db->where('id',1)->update('wa_settings',array('otp_template'=>$template,'updated_by'=>$actor->id));
        $this->metro->audit($actor->id,'wa_template_update','wa_settings',1);
        $this->message('Template kode WhatsApp tersimpan.','petugas/whatsapp');
    }

    public function retry($id)
    {
        $this->post_only(); $actor=$this->admin();
        $row=$this->db->select('o.*,c.expires_at,c.verified_at,u.phone AS current_phone,u.phone_verified_at')
            ->from('wa_outbox o')->join('phone_verification_codes c','c.id=o.code_id')
            ->join('users u','u.id=o.user_id')->where('o.id',(int)$id)->get()->row();
        if (!$row || $row->status!=='FAILED' || strtotime($row->expires_at)<=time()
            || $row->verified_at || $row->phone_verified_at || $row->current_phone!==$row->phone_number)
            show_error('Kode tidak dapat dikirim ulang. Buat kode baru jika masih diperlukan.',400);
        $this->db->where('id',$row->id)->where('status','FAILED')->update('wa_outbox',array('status'=>'PENDING','error_message'=>NULL));
        $this->metro->audit($actor->id,'wa_retry','wa_outbox',$row->id);
        $this->message('Kode dimasukkan kembali ke antrean WhatsApp.','petugas/whatsapp');
    }

    public function retry_notification($id)
    {
        $this->post_only();$actor=$this->admin();
        $row=$this->db->select('n.*,u.phone AS current_phone')->from('wa_notifications n')
            ->join('users u','u.id=n.user_id')->where('n.id',(int)$id)->get()->row();
        if (!$row || $row->status!=='FAILED' || $row->current_phone!==$row->phone_number
            || strtotime($row->created_at)<time()-86400) show_error('Pesan tidak dapat dikirim ulang.',400);
        $this->db->where('id',$row->id)->update('wa_notifications',array('status'=>'PENDING','error_message'=>NULL));
        $this->metro->audit($actor->id,'wa_notification_retry','wa_notifications',$row->id);
        $this->message('Notifikasi dimasukkan kembali ke antrean.','petugas/whatsapp');
    }
}
