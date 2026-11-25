<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registry extends MY_Controller
{
    private function owner()
    {
        $user=$this->require_login();
        if ($user->account_type!=='applicant') show_error('Akses ditolak.',403);
        $profile=$this->metro->profile($user->id);
        if (!$profile) show_error('Lengkapi profil terlebih dahulu.',400);
        return array($user,$profile);
    }

    public function index()
    {
        list($user,$profile)=$this->owner();
        if (!$this->metro->can($user->id,'self.uttp.view')) show_error('Akses ditolak.',403);
        $total=$this->db->where('applicant_profile_id',$profile->id)->count_all_results('applicant_uttp');
        $pagination=metro_page($total,$this->input->get('page'),12);
        $items=$this->db->select('a.*,t.type_name AS uttp_name,t.requires_capacity')->from('applicant_uttp a')
            ->join('uttp_types t','t.id=a.uttp_type_id')->where('a.applicant_profile_id',$profile->id)
            ->order_by('a.is_active','DESC')->order_by('a.id','DESC')
            ->limit($pagination['per_page'],$pagination['offset'])->get()->result();
        $points=array();
        foreach ($items as $tool) if ($tool->latitude!==NULL && $tool->longitude!==NULL)
            $points[]=array('lat'=>(float)$tool->latitude,'lng'=>(float)$tool->longitude,
                'title'=>$tool->uttp_name.' · '.$tool->brand,'detail'=>$tool->service_address,'kind'=>'UTTP');
        $this->page('requests/registry',array('title'=>'Daftar UTTP','items'=>$items,'types'=>$this->master->types(FALSE),
            'pagination'=>$pagination,'profile'=>$profile,'map_points'=>$points));
    }

    public function admin_save($profile_id,$id=0)
    {
        $this->save($id,(int)$profile_id);
    }

    public function save($id=0,$admin_profile_id=0)
    {
        $this->post_only();
        if ($admin_profile_id) {
            $user=$this->require_staff();
            if (!$this->metro->can($user->id,'applicants.update')) show_error('Akses ditolak.',403);
            $profile=$this->db->get_where('applicant_profiles',array('id'=>$admin_profile_id))->row();
            if (!$profile) show_404();
            $target='petugas/pemohon/'.$profile->user_id.'?tab=uttp';
        } else {
            list($user,$profile)=$this->owner();
            if (!$this->metro->can($user->id,$id?'self.uttp.update':'self.uttp.create')) show_error('Akses ditolak.',403);
            $target='uttp';
        }
        $existing=$id?$this->db->get_where('applicant_uttp',array('id'=>(int)$id,'applicant_profile_id'=>$profile->id))->row():NULL;
        if ($id && !$existing) show_404();
        $type_id=(int)$this->input->post('uttp_type_id');
        $type=$this->master->type($type_id,FALSE);
        $owner=trim((string)$this->input->post('owner_name',TRUE));
        $brand=trim((string)$this->input->post('brand',TRUE));
        $type_name=trim((string)$this->input->post('type_name',TRUE));
        $capacity=trim((string)$this->input->post('capacity',TRUE));
        $serial=trim((string)$this->input->post('serial_number',TRUE));
        $pubbm=$type && in_array($type->type_code,array('PUBBM','POMPA_UKUR'),TRUE);
        $nozzle=trim((string)$this->input->post('nozzle_no',TRUE));
        $dispenser=trim((string)$this->input->post('dispenser_no',TRUE));
        $media=trim((string)$this->input->post('media',TRUE));
        $year=trim((string)$this->input->post('manufacture_year',TRUE));
        $flow=trim((string)$this->input->post('flow_rate',TRUE));
        $readability=trim((string)$this->input->post('readability',TRUE));
        $address=trim((string)$this->input->post('service_address',TRUE));
        $coordinates=metro_coordinates($this->input->post('latitude'),$this->input->post('longitude'));
        $source=(string)$this->input->post('location_source',TRUE);
        if (!$type || (!$type->is_active && (!$existing || (int)$existing->uttp_type_id!==$type_id))
            || mb_strlen($owner)<3 || mb_strlen($owner)>190 || $brand==='' || mb_strlen($brand)>120
            || mb_strlen($type_name)>120 || mb_strlen($capacity)>120 || mb_strlen($serial)>120
            || mb_strlen($nozzle)>40 || mb_strlen($dispenser)>40 || mb_strlen($media)>80
            || mb_strlen($year)>40 || mb_strlen($flow)>80 || mb_strlen($readability)>80
            || mb_strlen($address)<10 || mb_strlen($address)>2000 || $coordinates===FALSE
            || ($coordinates && !in_array($source,array('','manual','map','google','google_review'),TRUE)))
            { $this->fail('Periksa jenis, pemilik, merek, alamat, dan pasangan koordinat UTTP.',$target); return; }
        $data=array('applicant_profile_id'=>$profile->id,'uttp_type_id'=>$type_id,'owner_name'=>$owner,
            'brand'=>$brand,'type_name'=>$type_name ?: NULL,
            'capacity'=>$type->requires_capacity?$capacity ?: NULL:NULL,
            'serial_number'=>$serial ?: NULL,'nozzle_no'=>$pubbm?($nozzle?:NULL):NULL,
            'dispenser_no'=>$pubbm?($dispenser?:NULL):NULL,'media'=>$pubbm?($media?:NULL):NULL,
            'manufacture_year'=>$pubbm?($year?:NULL):NULL,'flow_rate'=>$pubbm?($flow?:NULL):NULL,
            'readability'=>$pubbm?NULL:($readability?:NULL),'service_address'=>$address,
            'latitude'=>$coordinates?$coordinates[0]:NULL,'longitude'=>$coordinates?$coordinates[1]:NULL,
            'location_source'=>$coordinates?($source ?: 'manual'):NULL,'updated_at'=>date('Y-m-d H:i:s'));
        if ($existing) $this->db->where('id',$existing->id)->update('applicant_uttp',$data);
        else { $this->db->insert('applicant_uttp',$data);$id=$this->db->insert_id(); }
        $this->metro->audit($user->id,$existing?'uttp_update':'uttp_create','applicant_uttp',$id);
        $this->message('Data UTTP tersimpan.',$target);
    }

    public function history($id)
    {
        list($user,$profile)=$this->owner();
        if (!$this->metro->can($user->id,'self.uttp.view')) show_error('Akses ditolak.',403);
        $item=$this->db->select('a.*,t.type_name AS uttp_name')->from('applicant_uttp a')
            ->join('uttp_types t','t.id=a.uttp_type_id')->where('a.id',(int)$id)
            ->where('a.applicant_profile_id',$profile->id)->get()->row();
        if (!$item) show_404();
        $total=$this->db->where('applicant_uttp_id',$item->id)->count_all_results('request_items');
        $pagination=metro_page($total,$this->input->get('page'),15);
        $rows=$this->db->select('ri.id,ri.unit_sequence,ri.item_status,sr.id AS request_id,sr.request_no,sr.submitted_at,COALESCE(ri.service_type,sr.service_type) AS service_type',FALSE)
            ->from('request_items ri')->join('service_requests sr','sr.id=ri.service_request_id')
            ->where('ri.applicant_uttp_id',$item->id)->order_by('sr.submitted_at','DESC')
            ->limit($pagination['per_page'],$pagination['offset'])->get()->result();
        $this->page('requests/registry_history',array('title'=>'Riwayat UTTP','item'=>$item,
            'rows'=>$rows,'pagination'=>$pagination));
    }

    public function status($id)
    {
        $this->post_only();list($user,$profile)=$this->owner();
        if (!$this->metro->can($user->id,'self.uttp.status')) show_error('Akses ditolak.',403);
        $item=$this->db->get_where('applicant_uttp',array('id'=>(int)$id,'applicant_profile_id'=>$profile->id))->row();
        if (!$item) show_404();
        $active=(int)!$item->is_active;
        $this->db->where('id',$item->id)->update('applicant_uttp',array('is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($user->id,$active?'uttp_activate':'uttp_deactivate','applicant_uttp',$item->id);
        $this->message($active?'UTTP diaktifkan.':'UTTP dinonaktifkan.','uttp');
    }

    public function delete($id)
    {
        $this->post_only();list($user,$profile)=$this->owner();
        if (!$this->metro->can($user->id,'self.uttp.delete')) show_error('Akses ditolak.',403);
        $item=$this->db->get_where('applicant_uttp',array('id'=>(int)$id,'applicant_profile_id'=>$profile->id))->row();
        if (!$item) show_404();
        if ($this->db->where('applicant_uttp_id',$item->id)->count_all_results('request_items'))
            { $this->fail('UTTP sudah dipakai pada permohonan. Nonaktifkan saja agar riwayat tetap utuh.','uttp'); return; }
        $this->db->where('id',$item->id)->delete('applicant_uttp');
        $this->metro->audit($user->id,'uttp_delete','applicant_uttp',$item->id);
        $this->message('UTTP dihapus.','uttp');
    }
}
