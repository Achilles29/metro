<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Permissions extends MY_Controller
{
    private function superadmin()
    {
        $user=$this->require_staff();
        if (!$this->metro->has_role($user->id,array('administrator'))) show_error('Akses ditolak.',403);
        return $user;
    }

    private function editable_role($id)
    {
        $role=$this->db->get_where('roles',array('id'=>(int)$id))->row();
        if (!$role || in_array($role->role_code,array('administrator','applicant','external_technician'),TRUE)) show_404();
        return $role;
    }

    public function index()
    {
        $this->superadmin();
        $roles=$this->db->where_not_in('role_code',array('administrator','applicant','external_technician'))
            ->order_by('sort_order')->order_by('role_name')->get('roles')->result();
        $requested=(int)$this->input->get('role');
        $selected=NULL;
        foreach ($roles as $role) if ($requested && (int)$role->id===$requested) $selected=$role;
        if (!$selected && $roles) $selected=$roles[0];
        $permissions=$this->db->order_by('sort_order')->get('permissions')->result();
        $granted=array();
        if ($selected) {
            $rows=$this->db->select('permission_id')->where('role_id',$selected->id)->get('role_permissions')->result();
            foreach ($rows as $row) $granted[(int)$row->permission_id]=TRUE;
        }
        $this->page('staff/permissions',array('title'=>'Pengaturan hak akses',
            'roles'=>$roles,'selected_role'=>$selected,'permissions'=>$permissions,'granted'=>$granted));
    }

    public function save($id)
    {
        $this->post_only(); $actor=$this->superadmin();
        $role=$this->editable_role($id);
        $catalog=$this->db->select('id,permission_code')->get('permissions')->result();
        $valid=array(); $required=NULL;
        foreach ($catalog as $permission) {
            if ($permission->permission_code!=='staff.manage') $valid[(int)$permission->id]=TRUE;
            if ($permission->permission_code==='requests.view') $required=(int)$permission->id;
        }
        $posted=$this->input->post('permissions');
        if ($posted!==NULL && !is_array($posted)) show_error('Daftar izin tidak valid.',400);
        $selected=array_values(array_unique(array_map('intval',$posted ?: array())));
        foreach ($selected as $permission_id) if (!isset($valid[$permission_id])) show_error('Izin tidak valid.',400);
        if ($required) $selected[]=$required;
        $selected=array_values(array_unique($selected));
        $this->db->trans_begin();
        $this->db->where('role_id',$role->id)->delete('role_permissions');
        foreach ($selected as $permission_id) $this->db->insert('role_permissions',array(
            'role_id'=>$role->id,'permission_id'=>$permission_id,'granted_by'=>$actor->id));
        $this->metro->audit($actor->id,'role_permissions_update','roles',$role->id,
            $role->role_code.' · '.count($selected).' izin');
        if ($this->db->trans_status()===FALSE) {
            $this->db->trans_rollback();$this->fail('Hak akses gagal disimpan.','petugas/hak-akses?role='.$role->id);return;
        }
        $this->db->trans_commit();
        $this->message('Hak akses peran '.$role->role_name.' tersimpan.','petugas/hak-akses?role='.$role->id);
    }
}
