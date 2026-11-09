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
    public function queue($query = '', $technician_id = NULL, $filters = array(), $limit = 100)
    {
        $this->db->select('sr.*, u.full_name, p.organization_name, COUNT(DISTINCT ri.id) AS unit_count')
            ->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('users u', 'u.id=p.user_id')->join('request_items ri', 'ri.service_request_id=sr.id')
            ->group_by('sr.id')->order_by('sr.submitted_at', 'DESC')->limit($limit);
        if ($technician_id) {
            $this->db->join('staff_assignments a', 'a.request_item_id=ri.id')
                ->where('a.assigned_to', $technician_id)->where('a.assignment_status', 'assigned');
        }
        if ($query !== '') {
            $this->db->group_start()->like('sr.request_no', $query)->or_like('u.full_name', $query)
                ->or_like('p.organization_name', $query)->group_end();
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'verification') $this->db->where_in('ri.item_status', array('submitted','verification'));
            elseif ($filters['status'] === 'in_progress') $this->db->where('sr.request_status', 'in_progress');
            else $this->db->where('ri.item_status', $filters['status']);
        }
        if (!empty($filters['service_type'])) $this->db->where('sr.service_type', $filters['service_type']);
        if (!empty($filters['date_from'])) $this->db->where('sr.submitted_at >=', $filters['date_from'] . ' 00:00:00');
        if (!empty($filters['date_to'])) $this->db->where('sr.submitted_at <=', $filters['date_to'] . ' 23:59:59');
        if (!empty($filters['applicant_id'])) $this->db->where('p.user_id', (int) $filters['applicant_id']);
        return $this->db->get()->result();
    }

    public function applicants($query = '')
    {
        $this->db->select('u.id, u.full_name, u.email, u.phone, u.account_status, p.organization_name,
            COUNT(DISTINCT sr.id) AS request_count, MAX(sr.submitted_at) AS last_submission', FALSE)
            ->from('users u')->join('applicant_profiles p', 'p.user_id=u.id')
            ->join('service_requests sr', 'sr.applicant_profile_id=p.id', 'left')
            ->where('u.account_type', 'applicant')->group_by('u.id')
            ->order_by('u.created_at', 'DESC')->limit(100);
        if ($query !== '') $this->db->group_start()->like('u.full_name', $query)
            ->or_like('u.email', $query)->or_like('p.organization_name', $query)->group_end();
        return $this->db->get()->result();
    }

    public function dashboard_summary($technician_id = NULL)
    {
        $month_start = date('Y-m-01 00:00:00');
        $statuses = array('submitted'=>0, 'verification'=>0, 'revision'=>0, 'scheduled'=>0,
            'testing'=>0, 'review'=>0, 'completed'=>0, 'rejected'=>0, 'cancelled'=>0);
        $this->db->select('ri.item_status, COUNT(*) AS total')->from('request_items ri');
        if ($technician_id) $this->db->where('EXISTS (SELECT 1 FROM staff_assignments sa WHERE sa.request_item_id=ri.id AND sa.assigned_to=' . (int) $technician_id . " AND sa.assignment_status='assigned')", NULL, FALSE);
        foreach ($this->db->group_by('ri.item_status')->get()->result() as $row) $statuses[$row->item_status] = (int) $row->total;

        $this->db->select('COUNT(DISTINCT sr.id) AS total')->from('service_requests sr')->where('sr.submitted_at >=', $month_start);
        if ($technician_id) $this->db->where('EXISTS (SELECT 1 FROM request_items ri JOIN staff_assignments sa ON sa.request_item_id=ri.id WHERE ri.service_request_id=sr.id AND sa.assigned_to=' . (int) $technician_id . " AND sa.assignment_status='assigned')", NULL, FALSE);
        $requests_month = (int) $this->db->get()->row()->total;

        $this->db->select('COUNT(*) AS total')->from('item_status_history h')->where('h.to_status', 'completed')->where('h.created_at >=', $month_start);
        if ($technician_id) $this->db->where('EXISTS (SELECT 1 FROM staff_assignments sa WHERE sa.request_item_id=h.request_item_id AND sa.assigned_to=' . (int) $technician_id . " AND sa.assignment_status='assigned')", NULL, FALSE);
        $completed_month = (int) $this->db->get()->row()->total;

        return array('statuses' => $statuses, 'requests_month' => $requests_month,
            'completed_month' => $completed_month, 'month_start' => $month_start,
            'as_of' => date('Y-m-d H:i:s'));
    }

    public function report_summary($start, $end)
    {
        $requests = $this->db->select('service_type, COUNT(*) AS total')->from('service_requests')
            ->where('submitted_at >=', $start)->where('submitted_at <', $end)
            ->group_by('service_type')->get()->result();
        $items = $this->db->select('ri.item_status, COUNT(*) AS total')->from('request_items ri')
            ->join('service_requests sr', 'sr.id=ri.service_request_id')
            ->where('sr.submitted_at >=', $start)->where('sr.submitted_at <', $end)
            ->group_by('ri.item_status')->get()->result();
        $types = $this->db->select('t.type_name, COUNT(*) AS total')->from('request_items ri')
            ->join('service_requests sr', 'sr.id=ri.service_request_id')
            ->join('uttp_types t', 't.id=ri.uttp_type_id')
            ->where('sr.submitted_at >=', $start)->where('sr.submitted_at <', $end)
            ->group_by('t.id')->order_by('total', 'DESC')->limit(10)->get()->result();
        return array('requests' => $requests, 'items' => $items, 'types' => $types);
    }

    public function report_rows($start, $end)
    {
        return $this->db->select('sr.id, sr.request_no, sr.submitted_at, sr.service_type, sr.request_status, COUNT(ri.id) AS unit_count')
            ->from('service_requests sr')->join('request_items ri', 'ri.service_request_id=sr.id')
            ->where('sr.submitted_at >=', $start)->where('sr.submitted_at <', $end)
            ->group_by('sr.id')->order_by('sr.submitted_at', 'ASC')->get()->result();
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
