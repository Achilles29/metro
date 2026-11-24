<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Metro_model extends CI_Model
{
    private $role_cache = array();
    private $permission_cache = array();
    public function user($id) { return $this->db->get_where('users', array('id' => $id))->row(); }
    public function user_by_email($email) { return $this->db->get_where('users', array('email' => $email))->row(); }
    public function profile($user_id)
    {
        return $this->db->select('p.*,COALESCE(d.district_name,p.district) AS district_display,COALESCE(v.village_name,p.village) AS village_display', FALSE)
            ->from('applicant_profiles p')->join('master_districts d','d.id=p.district_id','left')
            ->join('master_villages v','v.id=p.village_id','left')->where('p.user_id',$user_id)->get()->row();
    }
    public function types() { return $this->master->types(); }
    public function is_staff($user_id)
    {
        return $this->db->where('id',(int)$user_id)->where('account_type','staff')
            ->count_all_results('users') > 0;
    }
    public function roles($user_id)
    {
        if (isset($this->role_cache[$user_id])) return $this->role_cache[$user_id];
        $rows = $this->db->select('r.role_code')->from('user_roles ur')->join('roles r', 'r.id=ur.role_id')
            ->where('ur.user_id', $user_id)->get()->result();
        return $this->role_cache[$user_id] = array_map(function ($r) { return $r->role_code; }, $rows);
    }
    public function has_role($user_id, $codes) { return count(array_intersect($this->roles($user_id), $codes)) > 0; }
    public function active_company_link($technician_id,$profile_id)
    {
        return $this->db->from('company_technicians ct')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->join('users tu','tu.id=ct.technician_user_id')
            ->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->join('users cu','cu.id=ap.user_id')
            ->where('ct.technician_user_id',(int)$technician_id)
            ->where('ct.company_profile_id',(int)$profile_id)
            ->where('ct.status','active')->where('tp.verification_status','approved')
            ->where('tu.account_status','active')->where('tu.phone_verified_at IS NOT NULL',NULL,FALSE)
            ->where('cu.account_status','active')->where('ap.profile_kind','organization')
            ->count_all_results()>0;
    }
    public function can_access_request($user,$request)
    {
        if ((int)$request->user_id===(int)$user->id) return TRUE;
        return $user->account_type==='technician'
            && (int)$request->submitted_by_user_id===(int)$user->id
            && $this->active_company_link($user->id,$request->applicant_profile_id);
    }
    public function technician_companies($user_id)
    {
        return $this->db->select('ap.id,ap.organization_name,ap.address,ap.latitude,ap.longitude,ap.location_source,ap.contact_person,ap.district_id,ap.village_id,u.full_name,u.phone')
            ->from('company_technicians ct')->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->join('users u','u.id=ap.user_id')->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->where('ct.technician_user_id',(int)$user_id)->where('ct.status','active')
            ->where('tp.verification_status','approved')->where('u.account_status','active')
            ->where('ap.profile_kind','organization')->order_by('ap.organization_name')->get()->result();
    }
    public function submitted_requests_for($user_id,$limit=NULL,$offset=0,$bucket=NULL)
    {
        $this->db->select('sr.*,COUNT(ri.id) AS unit_count')->from('service_requests sr')
            ->join('request_items ri','ri.service_request_id=sr.id')
            ->join('company_technicians ct','ct.company_profile_id=sr.applicant_profile_id AND ct.technician_user_id=sr.submitted_by_user_id')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->where('sr.submitted_by_user_id',(int)$user_id)->where('ct.status','active')
            ->where('tp.verification_status','approved')->where('ap.profile_kind','organization')
            ->group_by('sr.id')->order_by('sr.created_at','DESC')->order_by('sr.id','DESC');
        if ($bucket!==NULL) $this->request_bucket($bucket);
        if ($limit!==NULL) $this->db->limit($limit,$offset);
        return $this->db->get()->result();
    }
    public function submitted_requests_count($user_id,$bucket=NULL)
    {
        $this->db->from('service_requests sr')
            ->join('company_technicians ct','ct.company_profile_id=sr.applicant_profile_id AND ct.technician_user_id=sr.submitted_by_user_id')
            ->join('technician_profiles tp','tp.user_id=ct.technician_user_id')
            ->join('applicant_profiles ap','ap.id=ct.company_profile_id')
            ->where('sr.submitted_by_user_id',(int)$user_id)->where('ct.status','active')
            ->where('tp.verification_status','approved')->where('ap.profile_kind','organization');
        if ($bucket!==NULL) $this->request_bucket($bucket);
        return $this->db->count_all_results();
    }
    public function permissions_for($user_id)
    {
        if (isset($this->permission_cache[$user_id])) return $this->permission_cache[$user_id];
        if ($this->has_role($user_id,array('administrator'))) return $this->permission_cache[$user_id] = array('*');
        $rows=$this->db->distinct()->select('p.permission_code')->from('user_roles ur')
            ->join('role_permissions rp','rp.role_id=ur.role_id')
            ->join('permissions p','p.id=rp.permission_id')
            ->where('ur.user_id',$user_id)->get()->result();
        return $this->permission_cache[$user_id] = array_map(function($r){return $r->permission_code;},$rows);
    }
    public function can($user_id, $permission)
    {
        if ($permission==='staff.manage') return $this->has_role($user_id,array('administrator'));
        $permissions=$this->permissions_for($user_id);
        return in_array('*',$permissions,TRUE) || in_array($permission,$permissions,TRUE);
    }
    public function users_with_permission($permission)
    {
        return $this->db->distinct()->select('u.id,u.full_name')->from('users u')
            ->join('user_roles ur','ur.user_id=u.id')->join('roles r','r.id=ur.role_id')
            ->join('role_permissions rp','rp.role_id=r.id')
            ->join('permissions p','p.id=rp.permission_id')
            ->where('u.account_type','staff')->where('u.account_status','active')
            ->where('r.role_code !=','administrator')->where('p.permission_code',$permission)
            ->order_by('u.full_name')->get()->result();
    }
    public function assigned($item_id, $user_id)
    {
        return $this->db->where('request_item_id', $item_id)->where('assigned_to', $user_id)
            ->where('assignment_status', 'assigned')->count_all_results('staff_assignments') > 0;
    }
    public function can_view_request($user_id, $request_id)
    {
        if (!$this->can($user_id,'requests.view')) return FALSE;
        if ($this->can($user_id,'requests.view_all')) return TRUE;
        return $this->db->from('staff_assignments a')->join('request_items i','i.id=a.request_item_id')
            ->where('i.service_request_id',(int)$request_id)->where('a.assigned_to',(int)$user_id)
            ->where('a.assignment_status','assigned')->count_all_results()>0;
    }
    public function request($id)
    {
        return $this->db->select('sr.*, p.user_id, u.full_name, u.email, u.phone, p.organization_name, p.address AS owner_address,
            COALESCE(po.option_label,sr.penera_name) AS penera_display,
            COALESCE(co.option_label,sr.ctt_code) AS ctt_display,
            COALESCE(eo.option_label,sr.standard_equipment) AS standard_equipment_display', FALSE)
            ->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('users u', 'u.id=p.user_id')
            ->join('master_options po','po.id=sr.penera_option_id','left')
            ->join('master_options co','co.id=sr.ctt_option_id','left')
            ->join('master_options eo','eo.id=sr.standard_equipment_option_id','left')
            ->where('sr.id', $id)->get()->row();
    }

    public function review_change_blocker($request, $items)
    {
        if (!$request->verification_finalized_at) return 'Kaji ulang belum ditetapkan.';
        if ($this->db->where('request_id',$request->id)->where('cancelled_at IS NULL',NULL,FALSE)
            ->count_all_results('request_ctts')) return 'Hapus CTT yang masih terpilih sebelum mengubah kaji ulang.';
        if ($this->db->where('service_request_id',$request->id)->where_in('document_type',array('cerapan','skhp'))
            ->where('is_current',1)->count_all_results('service_documents'))
            return 'Dokumen cerapan atau SKHP sudah dibuat.';
        if ($this->db->where('request_id',$request->id)->where('synced_at IS NOT NULL',NULL,FALSE)
            ->count_all_results('request_report_syncs')) return 'Laporan hasil sudah tersinkron.';
        if ($this->db->from('staff_assignments a')->join('request_items i','i.id=a.request_item_id')
            ->where('i.service_request_id',$request->id)->where('a.assignment_status','assigned')
            ->count_all_results()) return 'Lepas penugasan petugas teknis terlebih dahulu.';
        $has_letter=$this->db->where('service_request_id',$request->id)->where('document_type','application_letter')
            ->where('is_current',1)->count_all_results('service_documents')>0;
        foreach ($items as $item) {
            $expected=$item->verification_decision==='rejected'?'not_suitable':
                ($request->service_mode==='field' && $has_letter?'scheduled':'testing');
            if ($item->item_status!==$expected || $item->tested_at)
                return 'Proses pengujian atau status UTTP sudah berlanjut.';
        }
        return NULL;
    }
    public function items($request_id)
    {
        return $this->db->select('ri.*, t.type_name AS uttp_name,t.certificate_scope')->from('request_items ri')
            ->join('uttp_types t', 't.id=ri.uttp_type_id')->where('ri.service_request_id', $request_id)
            ->order_by('ri.unit_sequence')->get()->result();
    }
    private function request_bucket($bucket)
    {
        $open="EXISTS (SELECT 1 FROM request_items bucket_item WHERE bucket_item.service_request_id=sr.id AND bucket_item.item_status NOT IN ('valid','invalid','returned','not_suitable','completed','rejected','cancelled'))";
        $passed="EXISTS (SELECT 1 FROM request_items bucket_item WHERE bucket_item.service_request_id=sr.id AND bucket_item.item_status IN ('valid','completed'))";
        if ($bucket==='valid') $this->db->where('NOT '.$open,NULL,FALSE)->where($passed,NULL,FALSE);
        elseif ($bucket==='cancelled') $this->db->where('NOT '.$open,NULL,FALSE)->where('NOT '.$passed,NULL,FALSE);
        elseif ($bucket==='terminal') $this->db->where('NOT '.$open,NULL,FALSE);
        else $this->db->where($open,NULL,FALSE);
    }
    public function requests_for($user_id, $limit = NULL, $offset = 0, $bucket=NULL)
    {
        $this->db->select('sr.*, COUNT(ri.id) AS unit_count')
            ->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('request_items ri', 'ri.service_request_id=sr.id')
            ->where('p.user_id', $user_id)->group_by('sr.id')->order_by('sr.created_at', 'DESC')->order_by('sr.id','DESC');
        if ($bucket!==NULL) $this->request_bucket($bucket);
        if ($limit !== NULL) $this->db->limit($limit, $offset);
        return $this->db->get()->result();
    }
    public function requests_for_count($user_id,$bucket=NULL)
    {
        $this->db->from('service_requests sr')->join('applicant_profiles p','p.id=sr.applicant_profile_id')
            ->where('p.user_id',$user_id);
        if ($bucket!==NULL) $this->request_bucket($bucket);
        return $this->db->count_all_results();
    }
    private function queue_base($query, $technician_id, $filters)
    {
        $this->db->from('service_requests sr')->join('applicant_profiles p', 'p.id=sr.applicant_profile_id')
            ->join('users u', 'u.id=p.user_id')->join('request_items ri', 'ri.service_request_id=sr.id')
            ->join('master_options po','po.id=sr.penera_option_id','left');
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
            elseif ($filters['status'] === 'valid') $this->db->where_in('ri.item_status',array('valid','completed'));
            elseif ($filters['status'] === 'invalid') $this->db->where_in('ri.item_status',array('invalid','cancelled'));
            elseif ($filters['status'] === 'not_suitable') $this->db->where_in('ri.item_status',array('not_suitable','rejected'));
            elseif ($filters['status'] === 'in_progress') $this->db->where('sr.request_status', 'in_progress');
            else $this->db->where('ri.item_status', $filters['status']);
        }
        if (!empty($filters['service_type'])) $this->db->where('sr.service_type', $filters['service_type']);
        if (!empty($filters['date_from'])) $this->db->where('sr.submitted_at >=', $filters['date_from'] . ' 00:00:00');
        if (!empty($filters['date_to'])) $this->db->where('sr.submitted_at <=', $filters['date_to'] . ' 23:59:59');
        if (!empty($filters['applicant_id'])) $this->db->where('p.user_id', (int) $filters['applicant_id']);
        if (!empty($filters['bucket'])) $this->request_bucket($filters['bucket']);
    }
    public function queue_count($query = '', $technician_id = NULL, $filters = array())
    {
        $this->queue_base($query, $technician_id, $filters);
        return (int) $this->db->select('COUNT(DISTINCT sr.id) AS total', FALSE)->get()->row()->total;
    }
    public function queue($query = '', $technician_id = NULL, $filters = array(), $limit = 100, $offset = 0)
    {
        $this->queue_base($query, $technician_id, $filters);
        return $this->db->select('sr.*, u.full_name, p.organization_name, COALESCE(po.option_label,sr.penera_name) AS penera_display, COUNT(DISTINCT ri.id) AS unit_count',FALSE)
            ->group_by('sr.id')->order_by('sr.submitted_at','DESC')->order_by('sr.id','DESC')
            ->limit($limit,$offset)->get()->result();
    }

    private function applicants_base($query)
    {
        $this->db->from('users u')->join('applicant_profiles p', 'p.user_id=u.id')
            ->where('u.account_type', 'applicant');
        if ($query !== '') $this->db->group_start()->like('u.full_name', $query)
            ->or_like('u.email', $query)->or_like('p.organization_name', $query)->group_end();
    }
    public function applicants_count($query = '')
    {
        $this->applicants_base($query);
        return $this->db->count_all_results();
    }
    public function applicants($query = '', $limit = 20, $offset = 0)
    {
        $this->applicants_base($query);
        $this->db->select('u.id, u.full_name, u.email, u.phone, u.account_status, u.phone_verified_at, u.phone_verification_required, p.id AS profile_id, p.organization_name, p.profile_kind, p.contact_person, p.address, p.district_id, p.village_id, v.village_name,
            COUNT(DISTINCT sr.id) AS request_count, MAX(sr.submitted_at) AS last_submission', FALSE)
            ->join('master_villages v','v.id=p.village_id','left')
            ->join('service_requests sr', 'sr.applicant_profile_id=p.id', 'left')
            ->group_by('u.id')->order_by('u.created_at', 'DESC')->order_by('u.id','DESC')->limit($limit,$offset);
        return $this->db->get()->result();
    }

    public function dashboard_summary($technician_id = NULL)
    {
        $month_start = date('Y-m-01 00:00:00');
        $statuses = array('submitted'=>0, 'verification'=>0, 'revision'=>0, 'scheduled'=>0,
            'testing'=>0, 'review'=>0, 'valid'=>0, 'invalid'=>0, 'returned'=>0,
            'not_suitable'=>0, 'completed'=>0, 'rejected'=>0, 'cancelled'=>0);
        $this->db->select('ri.item_status, COUNT(*) AS total')->from('request_items ri');
        if ($technician_id) $this->db->where('EXISTS (SELECT 1 FROM staff_assignments sa WHERE sa.request_item_id=ri.id AND sa.assigned_to=' . (int) $technician_id . " AND sa.assignment_status='assigned')", NULL, FALSE);
        foreach ($this->db->group_by('ri.item_status')->get()->result() as $row) $statuses[$row->item_status] = (int) $row->total;

        $this->db->select('COUNT(DISTINCT sr.id) AS total')->from('service_requests sr')->where('sr.submitted_at >=', $month_start);
        if ($technician_id) $this->db->where('EXISTS (SELECT 1 FROM request_items ri JOIN staff_assignments sa ON sa.request_item_id=ri.id WHERE ri.service_request_id=sr.id AND sa.assigned_to=' . (int) $technician_id . " AND sa.assignment_status='assigned')", NULL, FALSE);
        $requests_month = (int) $this->db->get()->row()->total;

        $this->db->select('COUNT(DISTINCT h.request_item_id) AS total',FALSE)->from('item_status_history h')
            ->where_in('h.to_status',metro_terminal_statuses())->where('h.created_at >=', $month_start);
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
