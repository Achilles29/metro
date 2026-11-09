<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Metro_model extends CI_Model
{
    public function user($id) { return $this->db->get_where('users', array('id' => $id))->row(); }
    public function user_by_email($email) { return $this->db->get_where('users', array('email' => $email))->row(); }
    public function profile($user_id) { return $this->db->get_where('applicant_profiles', array('user_id' => $user_id))->row(); }
    public function types() { return $this->db->order_by('type_name')->get_where('uttp_types', array('is_active' => 1))->result(); }
    public function is_staff($user_id)
    {
        return $this->db->from('user_roles ur')->join('roles r', 'r.id=ur.role_id')
            ->where('ur.user_id', $user_id)->where('r.role_code !=', 'applicant')->count_all_results() > 0;
    }
    public function roles($user_id)
    {
        $rows = $this->db->select('r.role_code')->from('user_roles ur')->join('roles r', 'r.id=ur.role_id')
            ->where('ur.user_id', $user_id)->get()->result();
        return array_map(function ($r) { return $r->role_code; }, $rows);
    }
    public function has_role($user_id, $codes) { return count(array_intersect($this->roles($user_id), $codes)) > 0; }
    public function assigned($item_id, $user_id)
    {
        return $this->db->where('request_item_id', $item_id)->where('assigned_to', $user_id)
            ->where('assignment_status', 'assigned')->count_all_results('staff_assignments') > 0;
    }
    public function request($id)
    {
        return $this->db->select('sr.*, p.user_id, u.full_name, u.email, u.phone, p.organization_name')
            ->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('users u', 'u.id=p.user_id')->where('sr.id', $id)->get()->row();
    }
    public function items($request_id)
    {
        return $this->db->select('ri.*, t.type_name AS uttp_name')->from('request_items ri')
            ->join('uttp_types t', 't.id=ri.uttp_type_id')->where('ri.service_request_id', $request_id)
            ->order_by('ri.unit_sequence')->get()->result();
    }
    public function requests_for($user_id)
    {
        return $this->db->select('sr.*, COUNT(ri.id) AS unit_count')
            ->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('request_items ri', 'ri.service_request_id=sr.id')
            ->where('p.user_id', $user_id)->group_by('sr.id')->order_by('sr.created_at', 'DESC')->get()->result();
    }
    public function queue($query = '', $technician_id = NULL)
    {
        $this->db->select('sr.*, u.full_name, p.organization_name, COUNT(ri.id) AS unit_count')
            ->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('users u', 'u.id=p.user_id')->join('request_items ri', 'ri.service_request_id=sr.id')
            ->group_by('sr.id')->order_by('sr.submitted_at', 'DESC')->limit(100);
        if ($technician_id) {
            $this->db->join('staff_assignments a', 'a.request_item_id=ri.id')
                ->where('a.assigned_to', $technician_id)->where('a.assignment_status', 'assigned');
        }
        if ($query !== '') {
            $this->db->group_start()->like('sr.request_no', $query)->or_like('u.full_name', $query)
                ->or_like('p.organization_name', $query)->group_end();
        }
        return $this->db->get()->result();
    }
    public function document($id) { return $this->db->get_where('service_documents', array('id' => $id))->row(); }
    public function documents($request_id)
    {
        return $this->db->where('service_request_id', $request_id)->where('is_current', 1)
            ->order_by('uploaded_at', 'DESC')->get('service_documents')->result();
    }
    public function history($request_id)
    {
        return $this->db->select('h.*, u.full_name')->from('item_status_history h')
            ->join('request_items i', 'i.id=h.request_item_id')->join('users u', 'u.id=h.actor_user_id', 'left')
            ->where('i.service_request_id', $request_id)->order_by('h.created_at', 'DESC')->get()->result();
    }
    public function audit($actor, $action, $entity, $id, $summary = '')
    {
        $ip = $this->input->ip_address();
        $this->db->insert('audit_logs', array('actor_user_id' => $actor, 'action_code' => $action,
            'entity_type' => $entity, 'entity_id' => $id, 'summary' => $summary,
            'ip_address' => filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : NULL,
            'user_agent' => substr((string) $this->input->user_agent(), 0, 255)));
    }
    public function notify($user_id, $title, $message, $url)
    {
        $this->db->insert('notifications', array('user_id' => $user_id, 'notification_type' => 'service',
            'title' => $title, 'message' => $message, 'target_url' => $url));
    }
}
