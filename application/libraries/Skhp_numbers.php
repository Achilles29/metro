<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Skhp_numbers
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); }
    private function maximum($year)
    {
        $pattern='^510[.]9/[0-9]+/SKHP/'.$year.'$';
        $row=$this->ci->db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(certificate_no,'/',2),'/',-1) AS UNSIGNED)),0) AS n FROM certificates WHERE certificate_no REGEXP ?",array($pattern))->row();
        return (int)$row->n;
    }
    private function format($value,$year) { return '510.9/'.sprintf('%03d',$value).'/SKHP/'.$year; }
    public function preview()
    {
        $year=(int)date('Y');
        $row=$this->ci->db->get_where('number_sequences',array('sequence_code'=>'SKHP','sequence_year'=>$year))->row();
        return $this->format(max($row?(int)$row->last_value:0,$this->maximum($year))+1,$year);
    }
    public function allocate()
    {
        $year=(int)date('Y');
        $this->ci->db->query('INSERT INTO number_sequences(sequence_code,sequence_year,last_value) VALUES(?,?,0) ON DUPLICATE KEY UPDATE last_value=last_value',array('SKHP',$year));
        $row=$this->ci->db->query('SELECT last_value FROM number_sequences WHERE sequence_code=? AND sequence_year=? FOR UPDATE',array('SKHP',$year))->row();
        $next=max((int)$row->last_value,$this->maximum($year))+1;
        $this->ci->db->where('sequence_code','SKHP')->where('sequence_year',$year)->update('number_sequences',array('last_value'=>$next));
        return $this->format($next,$year);
    }
}
