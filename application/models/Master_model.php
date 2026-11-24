<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_model extends CI_Model
{
    public function groups()
    {
        return $this->db->order_by('sort_order')->order_by('group_name')->get('master_groups')->result();
    }

    public function group($code)
    {
        return $this->db->get_where('master_groups', array('group_code' => $code))->row();
    }

    public function options($group, $active_only = TRUE)
    {
        $this->db->where('group_code', $group);
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->order_by('is_active','DESC')->order_by('sort_order')->order_by('option_label')->get('master_options')->result();
    }

    public function option($group, $id, $active_only = TRUE)
    {
        $this->db->where('id', (int) $id)->where('group_code', $group);
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->get('master_options')->row();
    }

    public function option_code($group, $code, $active_only = TRUE)
    {
        $this->db->where('group_code', $group)->where('option_code', $code);
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->get('master_options')->row();
    }

    public function districts($active_only = TRUE)
    {
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->order_by('is_active','DESC')->order_by('sort_order')->order_by('district_name')->get('master_districts')->result();
    }

    public function district($id, $active_only = TRUE)
    {
        $this->db->where('id', (int) $id);
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->get('master_districts')->row();
    }

    public function village($id, $active_only = TRUE)
    {
        $this->db->select('v.*,d.is_active AS district_active')->from('master_villages v')
            ->join('master_districts d','d.id=v.district_id')->where('v.id', (int) $id);
        if ($active_only) $this->db->where('v.is_active', 1)->where('d.is_active', 1);
        return $this->db->get()->row();
    }

    public function villages($district_id, $query = '', $active_only = TRUE, $limit = 30)
    {
        $this->db->select('v.*,d.district_name')->from('master_villages v')
            ->join('master_districts d','d.id=v.district_id');
        if ($district_id) $this->db->where('v.district_id', (int) $district_id);
        if ($active_only) $this->db->where('v.is_active', 1)->where('d.is_active', 1);
        if ($query !== '') $this->db->like('v.village_name', $query);
        $this->db->order_by('v.is_active','DESC')->order_by('v.sort_order')->order_by('v.village_name');
        if ($limit !== NULL) $this->db->limit($limit);
        return $this->db->get()->result();
    }

    public function locations($active_only = TRUE)
    {
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->order_by('is_active','DESC')->order_by('sort_order')->order_by('location_name')->get('master_locations')->result();
    }

    public function location($id, $active_only = TRUE)
    {
        $this->db->where('id', (int) $id);
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->get('master_locations')->row();
    }

    public function types($active_only = TRUE)
    {
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->order_by('is_active','DESC')->order_by('sort_order')->order_by('type_name')->get('uttp_types')->result();
    }

    public function type($id,$active_only=TRUE)
    {
        $this->db->where('id',(int)$id);
        if ($active_only) $this->db->where('is_active',1);
        return $this->db->get('uttp_types')->row();
    }

    public function staff_roles($active_only = TRUE)
    {
        $this->db->where_not_in('role_code',array('applicant','business_actor','administrator','external_technician'));
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->order_by('sort_order')->order_by('role_name')->get('roles')->result();
    }

    public function profile_region($district_id, $village_id)
    {
        $district = $this->district($district_id);
        $village = $this->village($village_id);
        return $district && $village && (int) $village->district_id === (int) $district->id
            ? array($district, $village) : FALSE;
    }
}
