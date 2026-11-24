<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wa_model extends CI_Model
{
    public function session()
    {
        return $this->db->get_where('wa_session',array('id'=>1))->row();
    }

    public function connected()
    {
        $session=$this->session();
        return $session && (int)$session->is_enabled===1 && $session->status==='CONNECTED'
            && $session->last_ping_at && strtotime($session->last_ping_at)>=time()-90;
    }

    public function queue_code($user)
    {
        if (!$this->connected() || !preg_match('/^08[0-9]{8,12}$/',(string)$user->phone)
            || $user->phone_verified_at) return FALSE;
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM users WHERE id=? FOR UPDATE',array((int)$user->id));
        $since=date('Y-m-d H:i:s',time()-3600);
        if ($this->db->where('user_id',$user->id)->where('created_at >=',$since)
            ->count_all_results('phone_verification_codes')>=5) {
            $this->db->trans_rollback();return FALSE;
        }
        $code=(string)random_int(100000,999999);
        $settings=$this->db->get_where('wa_settings',array('id'=>1))->row();
        $template=$settings ? $settings->otp_template : 'Kode verifikasi Setimbang: {code}. Berlaku 15 menit.';
        $message=str_replace('{code}',$code,$template);
        $this->db->where('user_id',$user->id)->where('verified_at IS NULL',NULL,FALSE)
            ->update('phone_verification_codes',array('verified_at'=>date('Y-m-d H:i:s')));
        $this->db->where('user_id',$user->id)->where('status','PENDING')
            ->update('wa_outbox',array('status'=>'CANCELLED','message'=>''));
        $this->db->insert('phone_verification_codes',array('user_id'=>$user->id,
            'code_hash'=>password_hash($code,PASSWORD_DEFAULT),
            'expires_at'=>date('Y-m-d H:i:s',time()+900),
            'created_by'=>NULL,'delivery_channel'=>'whatsapp'));
        $code_id=(int)$this->db->insert_id();
        $this->db->insert('wa_outbox',array('user_id'=>$user->id,'code_id'=>$code_id,
            'phone_number'=>$user->phone,'message'=>$message));
        if ($this->db->trans_status()===FALSE) {
            $this->db->trans_rollback(); return FALSE;
        }
        $this->db->trans_commit();
        return TRUE;
    }

    public function engine($path,$action=NULL)
    {
        if (!function_exists('curl_init')) return array('ok'=>FALSE,'message'=>'Ekstensi cURL belum tersedia.');
        $config=is_file('/etc/metro/wa.php') ? require '/etc/metro/wa.php' : array();
        if (empty($config['token'])) return array('ok'=>FALSE,'message'=>'Token WA Engine belum dikonfigurasi.');
        $url='http://127.0.0.1:3072/internal/'.$path.'?token='.rawurlencode($config['token']);
        $curl=curl_init($url);
        $options=array(CURLOPT_RETURNTRANSFER=>TRUE,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>8);
        if ($action!==NULL) {
            $options[CURLOPT_POST]=TRUE;
            $options[CURLOPT_POSTFIELDS]=json_encode(array('action'=>$action));
            $options[CURLOPT_HTTPHEADER]=array('Content-Type: application/json');
        }
        curl_setopt_array($curl,$options);
        $raw=curl_exec($curl); $error=curl_error($curl); curl_close($curl);
        $data=json_decode((string)$raw,TRUE);
        return is_array($data) ? $data : array('ok'=>FALSE,'message'=>$error ?: 'WA Engine tidak merespons.');
    }

    public function queue_notification($user,$message)
    {
        if (!$user || !$user->phone_verified_at || !preg_match('/^08[0-9]{8,12}$/',(string)$user->phone)) return FALSE;
        $this->db->insert('wa_notifications',array('user_id'=>$user->id,'phone_number'=>$user->phone,
            'message'=>mb_substr($message,0,600)));
        return $this->db->affected_rows()===1;
    }
}
