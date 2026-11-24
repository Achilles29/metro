<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gallery extends MY_Controller
{
    private function viewer()
    {
        $user=$this->require_permission('reports.view');
        if (!$this->metro->can($user->id,'requests.view') || !$this->metro->can($user->id,'requests.view_all'))
            show_error('Akses ditolak.',403);
        return $user;
    }

    private function filtered_photos($query,$date_from,$date_to)
    {
        $this->db->from('request_photos rp')
            ->join('service_requests sr','sr.id=rp.request_id')
            ->join('applicant_profiles ap','ap.id=sr.applicant_profile_id')
            ->join('users u','u.id=ap.user_id')
            ->where('rp.deleted_at IS NULL',NULL,FALSE);
        if ($query!=='') $this->db->group_start()->like('sr.request_no',$query)
            ->or_like('ap.organization_name',$query)->or_like('u.full_name',$query)->group_end();
        if ($date_from) $this->db->where('rp.created_at >=',$date_from.' 00:00:00');
        if ($date_to) $this->db->where('rp.created_at <=',$date_to.' 23:59:59');
    }

    public function index()
    {
        $viewer=$this->viewer();
        $query=mb_substr(trim((string)$this->input->get('q',TRUE)),0,100);
        $date_from=(string)$this->input->get('date_from',TRUE);
        $date_to=(string)$this->input->get('date_to',TRUE);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from) || !strtotime($date_from)) $date_from='';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to) || !strtotime($date_to)) $date_to='';
        $this->filtered_photos($query,$date_from,$date_to);
        $total=(int)$this->db->select('COUNT(DISTINCT sr.id) AS total',FALSE)->get()->row()->total;
        $pagination=metro_page($total,$this->input->get('page'),10);
        $this->filtered_photos($query,$date_from,$date_to);
        $orders=$this->db->select('sr.id,sr.request_no,ap.organization_name,u.full_name,COUNT(rp.id) AS photo_count,MAX(rp.created_at) AS last_photo',FALSE)
            ->group_by('sr.id,sr.request_no,ap.organization_name,u.full_name')
            ->order_by('last_photo','DESC')->order_by('sr.id','DESC')
            ->limit($pagination['per_page'],$pagination['offset'])->get()->result();
        $photos=array();
        if ($orders) {
            $ids=array_map(function($order){return (int)$order->id;},$orders);
            $this->db->from('request_photos rp')->where_in('rp.request_id',$ids)
                ->where('rp.deleted_at IS NULL',NULL,FALSE);
            if ($date_from) $this->db->where('rp.created_at >=',$date_from.' 00:00:00');
            if ($date_to) $this->db->where('rp.created_at <=',$date_to.' 23:59:59');
            foreach ($this->db->select('rp.id,rp.request_id,rp.caption,rp.original_name,rp.file_size,rp.created_at')
                ->order_by('rp.created_at','DESC')->order_by('rp.id','DESC')->get()->result() as $photo)
                $photos[(int)$photo->request_id][]=$photo;
        }
        $this->page('staff/gallery',array('title'=>'Galeri laporan','orders'=>$orders,'photos'=>$photos,
            'pagination'=>$pagination,'query'=>$query,'date_from'=>$date_from,'date_to'=>$date_to,
            'can_download'=>$this->metro->can($viewer->id,'reports.export')));
    }

    public function download()
    {
        $this->post_only();
        $viewer=$this->viewer();
        if (!$this->metro->can($viewer->id,'reports.export')) show_error('Akses ditolak.',403);
        $input=$this->input->post('photo_ids');
        if (!is_array($input) || count($input)<1 || count($input)>150)
            { $this->fail('Pilih 1 sampai 150 foto untuk diunduh.','petugas/galeri-laporan'); return; }
        $ids=array();
        foreach ($input as $value) {
            if (!ctype_digit((string)$value) || (int)$value<1) show_error('Pilihan foto tidak valid.',400);
            $ids[(int)$value]=(int)$value;
        }
        $rows=$this->db->select('rp.id,rp.storage_key,rp.original_name,sr.id AS request_id,sr.request_no')
            ->from('request_photos rp')->join('service_requests sr','sr.id=rp.request_id')
            ->where_in('rp.id',array_values($ids))->where('rp.deleted_at IS NULL',NULL,FALSE)
            ->order_by('sr.id')->order_by('rp.id')->get()->result();
        if (count($rows)!==count($ids)) show_error('Sebagian foto sudah tidak tersedia. Muat ulang galeri.',400);
        $this->load->library('private_files');
        $zip_path=tempnam(sys_get_temp_dir(),'metro-gallery-');
        if (!$zip_path) show_error('Arsip foto gagal dibuat.',500);
        $zip=new ZipArchive();
        if ($zip->open($zip_path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==TRUE) {
            unlink($zip_path); show_error('Arsip foto gagal dibuat.',500);
        }
        $added=0;
        foreach ($rows as $row) {
            $path=$this->private_files->image_path($row->storage_key);
            if (!$path) continue;
            $folder='order-'.$row->request_id.'-'.trim(preg_replace('/[^A-Za-z0-9._-]+/','-',(string)$row->request_no),'-');
            $name=trim(preg_replace('/[^A-Za-z0-9._-]+/','-',pathinfo($row->original_name,PATHINFO_FILENAME)),'-');
            $extension=strtolower(pathinfo($row->storage_key,PATHINFO_EXTENSION));
            if ($zip->addFile($path,$folder.'/foto-'.$row->id.'-'.substr($name?:'dokumentasi',0,80).'.'.$extension)) $added++;
        }
        $closed=$zip->close();
        if (!$closed || !$added) { unlink($zip_path); show_error('Foto yang dipilih tidak tersedia di penyimpanan.',404); }
        $this->metro->audit($viewer->id,'gallery_download','request_photos',0,'Unduh ZIP berisi '.$added.' foto.');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="galeri-laporan-'.date('Ymd-His').'.zip"');
        header('Content-Length: '.filesize($zip_path));
        header('X-Content-Type-Options: nosniff');
        readfile($zip_path);
        unlink($zip_path);
        exit;
    }
}
