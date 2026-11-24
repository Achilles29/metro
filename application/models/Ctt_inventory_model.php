<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ctt_inventory_model extends CI_Model
{
    public function minimum_stock($option_id)
    {
        $row=$this->db->query(
            'SELECT COALESCE(SUM(rc.asset_units),0) AS used_units '
            .'FROM request_ctt_daily_loans dl JOIN request_ctts rc ON rc.id=dl.request_ctt_id '
            .'WHERE rc.ctt_option_id=? AND rc.cancelled_at IS NULL AND dl.returned_at IS NULL',
            array((int)$option_id)
        )->row();
        return (int)$row->used_units;
    }

    public function availability($option_id,$start,$end)
    {
        $option=$this->db->select('asset_quantity')->get_where('master_options',array('id'=>(int)$option_id,'group_code'=>'ctt'))->row();
        if (!$option || $option->asset_quantity===NULL) return NULL;
        $events=array();
        $loans=$this->db->query(
            'SELECT rc.asset_units,dl.loan_date,dl.returned_at '
            .'FROM request_ctt_daily_loans dl JOIN request_ctts rc ON rc.id=dl.request_ctt_id '
            .'WHERE rc.ctt_option_id=? AND rc.cancelled_at IS NULL AND dl.loan_date<=? '
            .'AND (dl.returned_at IS NULL OR DATE(dl.returned_at)>?)',
            array((int)$option_id,$end,$start)
        )->result();
        foreach ($loans as $loan) {
            $from=max($start,$loan->loan_date);
            $until=$loan->returned_at
                ? (new DateTimeImmutable(substr($loan->returned_at,0,10)))->modify('-1 day')->format('Y-m-d')
                : $end;
            $until=min($until,$end);
            if ($from>$until) continue;
            $units=(int)$loan->asset_units;
            $events[$from]=($events[$from]??0)+$units;
            $after=(new DateTimeImmutable($until))->modify('+1 day')->format('Y-m-d');
            $events[$after]=($events[$after]??0)-$units;
        }
        ksort($events);$occupied=0;$peak=0;
        foreach ($events as $change) {$occupied+=$change;$peak=max($peak,$occupied);}
        return max(0,(int)$option->asset_quantity-$peak);
    }
}
