<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Remember_me
{
    private const COOKIE = 'setimbang_remember';
    private const DAYS = 30;

    private function cookie($value,$expires)
    {
        setcookie(self::COOKIE,$value,array('expires'=>$expires,'path'=>'/',
            'secure'=>is_https(),'httponly'=>TRUE,'samesite'=>'Lax'));
        if ($value==='') unset($_COOKIE[self::COOKIE]);
        else $_COOKIE[self::COOKIE]=$value;
    }

    public function forget()
    {
        $ci=&get_instance();
        $parts=explode(':',(string)($ci->input->cookie(self::COOKIE,TRUE) ?: ''),2);
        if (count($parts)===2 && preg_match('/^[a-f0-9]{24}$/',$parts[0]))
            $ci->db->where('selector',$parts[0])->delete('remember_tokens');
        $this->cookie('',time()-3600);
    }

    public function forget_user($user_id)
    {
        $ci=&get_instance();
        $ci->db->where('user_id',(int)$user_id)->delete('remember_tokens');
        $this->cookie('',time()-3600);
    }

    public function issue($user_id)
    {
        $ci=&get_instance();
        $selector=bin2hex(random_bytes(12));
        $verifier=bin2hex(random_bytes(32));
        $expiry=time()+self::DAYS*86400;
        $ci->db->insert('remember_tokens',array('user_id'=>(int)$user_id,'selector'=>$selector,
            'verifier_hash'=>hash('sha256',$verifier),'expires_at'=>date('Y-m-d H:i:s',$expiry)));
        $this->cookie($selector.':'.$verifier,$expiry);
    }

    public function restore()
    {
        $ci=&get_instance();
        $parts=explode(':',(string)($ci->input->cookie(self::COOKIE,TRUE) ?: ''),2);
        if (count($parts)!==2 || !preg_match('/^[a-f0-9]{24}$/',$parts[0])
            || !preg_match('/^[a-f0-9]{64}$/',$parts[1])) return FALSE;
        $token=$ci->db->get_where('remember_tokens',array('selector'=>$parts[0]))->row();
        if (!$token || strtotime($token->expires_at)<time()
            || !hash_equals($token->verifier_hash,hash('sha256',$parts[1]))) {
            $this->cookie('',time()-3600);return FALSE;
        }
        $user=$ci->db->get_where('users',array('id'=>$token->user_id,'account_status'=>'active'))->row();
        if (!$user) {$this->forget();return FALSE;}
        $verifier=bin2hex(random_bytes(32));
        $expiry=time()+self::DAYS*86400;
        $ci->db->where('id',$token->id)->where('verifier_hash',$token->verifier_hash)
            ->update('remember_tokens',array(
            'verifier_hash'=>hash('sha256',$verifier),'expires_at'=>date('Y-m-d H:i:s',$expiry),
            'last_used_at'=>date('Y-m-d H:i:s')));
        if ($ci->db->affected_rows()!==1) {$this->cookie('',time()-3600);return FALSE;}
        $ci->session->sess_regenerate(TRUE);
        $ci->session->set_userdata('user_id',(int)$user->id);
        $ci->session->set_userdata('auth_marker',hash('sha256',$user->password_hash));
        $this->cookie($parts[0].':'.$verifier,$expiry);
        return TRUE;
    }
}
