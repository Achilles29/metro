<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Metro_reports
{
    public function sync($request_id)
    {
        $ci=&get_instance();$lock='metro-report-'.(int)$request_id;
        $acquired=$ci->db->query('SELECT GET_LOCK(?,5) AS acquired',array($lock))->row();
        if (!$acquired || (int)$acquired->acquired!==1) throw new RuntimeException('Sinkronisasi laporan sedang berjalan. Coba lagi sebentar.');
        try {return $this->sync_unlocked($request_id);}
        finally {$ci->db->query('SELECT RELEASE_LOCK(?)',array($lock));}
    }

    private function sync_unlocked($request_id)
    {
        $ci=&get_instance();$ci->load->library('google_workspace');
        $request=$ci->metro->request((int)$request_id);if (!$request) throw new RuntimeException('Order tidak ditemukan.');
        if ($request->submission_source==='historical')
            throw new RuntimeException('Arsip SKHP lama tidak dikirim ulang ke spreadsheet laporan aktif.');
        $items=$ci->metro->items($request->id);if (!$items) throw new RuntimeException('UTTP tidak ditemukan.');
        foreach ($items as $item) if (!metro_is_terminal($item->item_status))
            throw new RuntimeException('Laporan dikirim setelah seluruh UTTP selesai.');
        $certificates=array();
        $rows=$ci->db->select('c.*,d.request_item_id')->from('certificates c')->join('service_documents d','d.id=c.service_document_id')
            ->join('request_items i','i.id=d.request_item_id')->where('i.service_request_id',$request->id)
            ->where('c.certificate_status !=','superseded')->get()->result();
        foreach ($rows as $row) $certificates[(int)$row->request_item_id]=$row;
        foreach ($items as $item) if ($item->certificate_scope==='order' && empty($certificates[(int)$item->id]))
            foreach ($items as $source) if ($source->certificate_scope==='order'
                && isset($certificates[(int)$source->id])) {$certificates[(int)$item->id]=$certificates[(int)$source->id];break;}
        $groups=array('valid'=>array(),'invalid'=>array(),'other'=>array());
        foreach ($items as $item) {
            if (metro_is_valid($item->item_status)) $groups['valid'][]=$item;
            elseif (in_array($item->item_status,array('invalid','cancelled'),TRUE)) $groups['invalid'][]=$item;
            else $groups['other'][]=$item;
        }
        $notes=array();$history=$ci->db->select('h.request_item_id,h.note')->from('item_status_history h')
            ->join('request_items i','i.id=h.request_item_id')->where('i.service_request_id',$request->id)
            ->where('h.note IS NOT NULL',NULL,FALSE)->order_by('h.id','DESC')->get()->result();
        foreach ($history as $row) if (!isset($notes[(int)$row->request_item_id])) $notes[(int)$row->request_item_id]=$row->note;
        $jobs=array('order'=>array($items,'order'));
        foreach ($groups as $key=>$group) if ($group) $jobs['ttu_'.$key]=array($group,$key);
        $errors=array();foreach ($jobs as $name=>$job) {
            $sync=$ci->db->get_where('request_report_syncs',array('request_id'=>$request->id,'report_type'=>$name))->row();
            if ($sync && $sync->synced_at) continue;
            $payload=array('request_id'=>$request->id,'report_type'=>$name,'row_count'=>count($job[0]),'attempted_at'=>date('Y-m-d H:i:s'));
            if (!$sync) {$ci->db->insert('request_report_syncs',$payload);$sync_id=$ci->db->insert_id();}
            else {$sync_id=$sync->id;$ci->db->where('id',$sync_id)->update('request_report_syncs',$payload);}
            try {
                if ($name==='order') $result=$ci->google_workspace->append_order($request,$items,$certificates,$sync?$sync->remote_range:NULL);
                else $result=$ci->google_workspace->append_ttu($request,$job[0],$job[1],$notes);
                $remote_range=$result['updates']['updatedRange'] ?? $result['updatedRange'] ?? NULL;
                if (!$remote_range) throw new RuntimeException('Google tidak mengembalikan rentang baris laporan.');
                $ci->db->where('id',$sync_id)->update('request_report_syncs',array('synced_at'=>date('Y-m-d H:i:s'),
                    'remote_range'=>$remote_range,'last_error'=>NULL));
            } catch (Exception $ex) {
                $message=mb_substr($ex->getMessage(),0,500);$errors[$name]=$message;
                $ci->db->where('id',$sync_id)->update('request_report_syncs',array('last_error'=>$message));
            }
        }
        return $errors;
    }
}
