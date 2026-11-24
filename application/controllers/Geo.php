<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Geo extends MY_Controller
{
    public function index()
    {
        $user=$this->require_staff();
        if (!$this->metro->can($user->id,'applicants.manage') && !$this->metro->can($user->id,'requests.view_all'))
            show_error('Akses ditolak.',403);
        $district=(int)$this->input->get('district_id');
        $profiles=$this->db->select('p.id,p.user_id,p.organization_name,p.latitude,p.longitude,p.location_source,p.address,u.full_name,d.district_name')
            ->from('applicant_profiles p')->join('users u','u.id=p.user_id')
            ->join('master_districts d','d.id=p.district_id','left')
            ->where('p.latitude IS NOT NULL',NULL,FALSE)->where('p.longitude IS NOT NULL',NULL,FALSE);
        if ($district) $profiles->where('p.district_id',$district);
        $profiles=$profiles->get()->result();
        $tools=$this->db->select('a.id,a.applicant_profile_id,a.brand,a.serial_number,a.latitude,a.longitude,a.service_address,p.user_id,p.organization_name,p.district_id,u.full_name,t.type_name')
            ->from('applicant_uttp a')->join('applicant_profiles p','p.id=a.applicant_profile_id')
            ->join('users u','u.id=p.user_id')->join('uttp_types t','t.id=a.uttp_type_id')
            ->where('a.latitude IS NOT NULL',NULL,FALSE)->where('a.longitude IS NOT NULL',NULL,FALSE);
        if ($district) $tools->where('p.district_id',$district);
        $tools=$tools->get()->result();
        $points=array();
        foreach ($profiles as $p) $points[]=array('lat'=>(float)$p->latitude,'lng'=>(float)$p->longitude,
            'title'=>$p->organization_name ?: $p->full_name,
            'detail'=>$p->address.($p->location_source==='google_review'?' · Titik peta daring perlu konfirmasi':''),
            'kind'=>'Profil pemohon',
            'url'=>site_url('petugas/pemohon/'.$p->user_id));
        foreach ($tools as $t) $points[]=array('lat'=>(float)$t->latitude,'lng'=>(float)$t->longitude,
            'title'=>$t->type_name.' · '.$t->brand,'detail'=>$t->organization_name ?: $t->full_name,
            'kind'=>'UTTP','url'=>site_url('petugas/pemohon/'.$t->user_id.'?tab=uttp'));
        $this->page('staff/map',array('title'=>'Peta GIS','points'=>$points,'districts'=>$this->master->districts(),
            'district_id'=>$district,'profile_count'=>count($profiles),'uttp_count'=>count($tools)));
    }
}
