<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Staff extends MY_Controller
{
    public function index()
    {
        $user = $this->require_staff();
        $query = mb_substr(trim((string) $this->input->get('q', TRUE)), 0, 100);
        $technical_only = $this->metro->has_role($user->id, array('technician'))
            && !$this->metro->has_role($user->id, array('administrator','verifier','coordinator','issuer'));
        $this->page('staff/index', array('title' => 'Antrean layanan', 'requests' => $this->metro->queue($query, $technical_only ? $user->id : NULL), 'query' => $query,
            'notifications' => $this->db->where('user_id', $user->id)->order_by('sent_at', 'DESC')->limit(5)->get('notifications')->result()));
    }

    public function show($id)
    {
        $user = $this->require_staff();
        $request = $this->metro->request((int) $id);
        if (!$request) show_404();
        $technical_only = $this->metro->has_role($user->id, array('technician'))
            && !$this->metro->has_role($user->id, array('administrator','verifier','coordinator','issuer'));
        if ($technical_only
            && !$this->db->from('staff_assignments a')->join('request_items i','i.id=a.request_item_id')
                ->where('i.service_request_id',$request->id)->where('a.assigned_to',$user->id)
                ->where('a.assignment_status','assigned')->count_all_results()) show_error('Akses ditolak.', 403);
        $items = $this->metro->items($request->id);
        $documents = $this->metro->documents($request->id);
        $history = $this->metro->history($request->id);
        if ($technical_only) {
            $items = array_values(array_filter($items, function ($i) use ($user) { return $this->metro->assigned($i->id, $user->id); }));
            $item_ids = array_map(function ($i) { return (int) $i->id; }, $items);
            $documents = array_values(array_filter($documents, function ($d) use ($item_ids) {
                return $d->document_type === 'application_letter' || ($d->document_type === 'cerapan' && in_array((int) $d->request_item_id, $item_ids, TRUE));
            }));
            $history = array_values(array_filter($history, function ($h) use ($item_ids) { return in_array((int) $h->request_item_id, $item_ids, TRUE); }));
        }
        $this->page('staff/show', array('title' => 'Kelola ' . $request->request_no,
            'request' => $request, 'items' => $items,
            'documents' => $documents, 'history' => $history,
            'roles' => $this->metro->roles($user->id),
            'technicians' => $this->db->select('u.id,u.full_name')->from('users u')->join('user_roles ur','ur.user_id=u.id')->join('roles r','r.id=ur.role_id')
                ->where('r.role_code','technician')->where('u.account_status','active')->order_by('u.full_name')->get()->result(),
            'assignments' => $this->db->select('a.request_item_id,u.full_name')->from('staff_assignments a')->join('users u','u.id=a.assigned_to')
                ->join('request_items i','i.id=a.request_item_id')->where('i.service_request_id',$request->id)
                ->where('a.assignment_status','assigned')->get()->result()));
    }

    public function status($id)
    {
        $this->post_only();
        $user = $this->require_staff();
        $item = $this->db->get_where('request_items', array('id' => (int) $id))->row();
        if (!$item) show_404();
        $to = (string) $this->input->post('status', TRUE);
        $note = mb_substr(trim((string) $this->input->post('note', TRUE)), 0, 1000);
        $map = array(
            'submitted' => array('verification', 'cancelled'),
            'verification' => array('revision', 'scheduled', 'rejected'),
            'revision' => array('verification', 'rejected'),
            'scheduled' => array('testing', 'cancelled'),
            'testing' => array('review', 'cancelled'),
            'review' => array('testing', 'completed', 'rejected')
        );
        $allowed_roles = array(
            'verification' => array('verifier', 'administrator'),
            'revision' => array('verifier', 'administrator'),
            'scheduled' => array('coordinator', 'administrator'),
            'testing' => array('technician', 'administrator'),
            'review' => array('technician', 'issuer', 'administrator'),
            'completed' => array('issuer', 'administrator'),
            'cancelled' => array('coordinator', 'administrator'),
            'rejected' => array('verifier', 'issuer', 'administrator')
        );
        if (!in_array($to, isset($map[$item->item_status]) ? $map[$item->item_status] : array(), TRUE)
            || !isset($allowed_roles[$to]) || !$this->metro->has_role($user->id, $allowed_roles[$to])) show_error('Perubahan status tidak diizinkan.', 403);
        if ($this->metro->has_role($user->id, array('technician')) && !$this->metro->has_role($user->id, array('administrator','issuer'))
            && in_array($to, array('testing','review'), TRUE) && !$this->metro->assigned($item->id, $user->id)) show_error('Unit belum ditugaskan kepada Anda.', 403);
        if (in_array($to, array('revision', 'rejected', 'cancelled'), TRUE) && mb_strlen($note) < 8) {
            $this->fail('Jelaskan alasan minimal 8 karakter.', 'petugas/permohonan/' . $item->service_request_id); return;
        }
        if ($to === 'completed' && !$this->db->get_where('certificates', array('request_item_id' => $item->id, 'certificate_status' => 'valid'))->row()) {
            $this->fail('Unggah SKHP sebelum menyelesaikan unit.', 'petugas/permohonan/' . $item->service_request_id); return;
        }
        $this->db->trans_begin();
        $this->db->where('id', $item->id)->where('item_status', $item->item_status)->update('request_items', array('item_status' => $to, 'updated_at' => date('Y-m-d H:i:s')));
        if ($this->db->affected_rows() !== 1) { $this->db->trans_rollback(); $this->fail('Status berubah. Muat ulang halaman.', 'petugas/permohonan/' . $item->service_request_id); return; }
        $this->db->insert('item_status_history', array('request_item_id' => $item->id, 'from_status' => $item->item_status,
            'to_status' => $to, 'actor_user_id' => $user->id, 'note' => $note ?: NULL));
        $request = $this->metro->request($item->service_request_id);
        $statuses = array_column($this->db->select('item_status')->get_where('request_items', array('service_request_id' => $request->id))->result_array(), 'item_status');
        $overall = count(array_unique($statuses)) === 1 ? $statuses[0] : 'in_progress';
        if ($overall !== $request->request_status) {
            $this->db->where('id', $request->id)->update('service_requests', array('request_status' => $overall, 'updated_at' => date('Y-m-d H:i:s')));
            $this->db->insert('request_status_history', array('service_request_id' => $request->id,
                'from_status' => $request->request_status, 'to_status' => $overall, 'actor_user_id' => $user->id));
        }
        $this->metro->audit($user->id, 'item_status', 'request_items', $item->id, $item->item_status . ' > ' . $to);
        $this->metro->notify($request->user_id, 'Status UTTP diperbarui', 'Unit #' . $item->unit_sequence . ': ' . status_label($to) . ($note ? '. ' . $note : ''), 'permohonan/' . $request->id);
        if ($overall === 'completed' && $request->request_status !== 'completed')
            $this->metro->notify($request->user_id, 'Seluruh UTTP selesai', 'Seluruh unit selesai. Silakan hubungi UPT untuk pengambilan sesuai arahan petugas.', 'permohonan/' . $request->id);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); $this->fail('Pembaruan gagal.', 'petugas/permohonan/' . $request->id); return; }
        $this->db->trans_commit();
        $this->message('Status unit diperbarui.', 'petugas/permohonan/' . $request->id);
    }

    public function upload($id)
    {
        $this->post_only();
        $user = $this->require_staff();
        $item = $this->db->get_where('request_items', array('id' => (int) $id))->row();
        if (!$item) show_404();
        $type = $this->input->post('document_type');
        $roles = $type === 'cerapan' ? array('technician', 'administrator') : array('issuer', 'administrator');
        if (!in_array($type, array('cerapan', 'skhp'), TRUE) || !$this->metro->has_role($user->id, $roles)) show_error('Akses ditolak.', 403);
        if ($type === 'cerapan' && $this->metro->has_role($user->id, array('technician')) && !$this->metro->has_role($user->id, array('administrator'))
            && !$this->metro->assigned($item->id, $user->id)) show_error('Unit belum ditugaskan kepada Anda.', 403);
        $number = trim((string) $this->input->post('certificate_no', TRUE));
        if ($type === 'skhp' && (mb_strlen($number) < 3 || mb_strlen($number) > 100)) {
            $this->fail('Isi nomor SKHP.', 'petugas/permohonan/' . $item->service_request_id); return;
        }
        $this->load->library('private_files');
        try { $file = $this->private_files->save('document'); }
        catch (RuntimeException $ex) { $this->fail($ex->getMessage(), 'petugas/permohonan/' . $item->service_request_id); return; }
        $this->db->trans_begin();
        $this->db->where('request_item_id', $item->id)->where('document_type', $type)->update('service_documents', array('is_current' => 0));
        $file['service_request_id'] = $item->service_request_id; $file['request_item_id'] = $item->id;
        $file['document_type'] = $type; $file['visibility'] = $type === 'cerapan' ? 'internal' : 'applicant';
        $file['uploaded_by'] = $user->id;
        $this->db->insert('service_documents', $file);
        $doc_id = $this->db->insert_id();
        if ($type === 'skhp') {
            $this->db->where('request_item_id', $item->id)->update('certificates', array('certificate_status' => 'superseded'));
            $version = $this->db->where('request_item_id', $item->id)->count_all_results('certificates') + 1;
            $this->db->insert('certificates', array('request_item_id' => $item->id, 'service_document_id' => $doc_id,
                'certificate_no' => $number, 'certificate_version' => $version, 'issued_at' => date('Y-m-d H:i:s'),
                'issued_by' => $user->id, 'verification_token' => bin2hex(random_bytes(32))));
        }
        $this->metro->audit($user->id, 'document_upload', 'service_documents', $doc_id, $type);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback(); $this->private_files->delete($file['storage_key']);
            $this->fail('Unggah gagal. Periksa nomor SKHP.', 'petugas/permohonan/' . $item->service_request_id); return;
        }
        $this->db->trans_commit();
        $this->message('Dokumen tersimpan.', 'petugas/permohonan/' . $item->service_request_id);
    }

    public function assign($id)
    {
        $this->post_only();
        $actor = $this->require_staff();
        if (!$this->metro->has_role($actor->id, array('coordinator','administrator'))) show_error('Akses ditolak.', 403);
        $item = $this->db->get_where('request_items', array('id' => (int) $id))->row();
        if (!$item) show_404();
        $assignee = (int) $this->input->post('assigned_to');
        $person = $this->metro->user($assignee);
        if (!$person || $person->account_status !== 'active' || !$this->metro->has_role($assignee, array('technician'))) show_error('Pilih petugas teknis yang aktif.', 400);
        if (!in_array($item->item_status, array('scheduled','testing','review'), TRUE)) show_error('Unit belum siap ditugaskan.', 400);
        $this->db->trans_begin();
        $this->db->where('request_item_id', $item->id)->where('assignment_status', 'assigned')->update('staff_assignments', array('assignment_status' => 'replaced','completed_at' => date('Y-m-d H:i:s')));
        $this->db->insert('staff_assignments', array('request_item_id' => $item->id, 'assigned_to' => $assignee,
            'assigned_by' => $actor->id, 'assignment_type' => 'testing', 'note' => mb_substr(trim((string) $this->input->post('note', TRUE)), 0, 1000)));
        $this->metro->audit($actor->id, 'assign_item', 'request_items', $item->id, 'Petugas #' . $assignee);
        $this->metro->notify($assignee, 'Penugasan UTTP', 'Unit #' . $item->unit_sequence . ' ditugaskan kepada Anda.', 'petugas/permohonan/' . $item->service_request_id);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); $this->fail('Penugasan gagal.', 'petugas/permohonan/' . $item->service_request_id); return; }
        $this->db->trans_commit();
        $this->message('Petugas teknis ditugaskan.', 'petugas/permohonan/' . $item->service_request_id);
    }

    public function users()
    {
        $actor = $this->require_staff();
        if (!$this->metro->has_role($actor->id, array('administrator'))) show_error('Akses ditolak.', 403);
        $error = NULL;
        if ($this->input->method() === 'post') {
            $name = trim((string) $this->input->post('full_name', TRUE));
            $email = strtolower(trim((string) $this->input->post('email', TRUE)));
            $password = (string) $this->input->post('password');
            $role_code = (string) $this->input->post('role_code', TRUE);
            $role = $this->db->get_where('roles', array('role_code' => $role_code))->row();
            if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72
                || !in_array($role_code, array('verifier','technician','coordinator','issuer'), TRUE)) $error = 'Periksa nama, email, peran, dan kata sandi 12–72 karakter.';
            elseif ($this->metro->user_by_email($email)) $error = 'Email sudah digunakan.';
            else {
                $this->db->trans_begin();
                $this->db->insert('users', array('full_name' => $name, 'email' => $email, 'password_hash' => password_hash($password,PASSWORD_DEFAULT),
                    'account_type' => 'staff', 'account_status' => 'active'));
                $id = $this->db->insert_id();
                $this->db->insert('user_roles', array('user_id' => $id, 'role_id' => $role->id, 'assigned_by' => $actor->id));
                $this->metro->audit($actor->id, 'staff_create', 'users', $id, $role_code);
                if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); $error = 'Akun gagal dibuat.'; }
                else { $this->db->trans_commit(); $this->message('Akun petugas dibuat.', 'petugas/akun'); return; }
            }
        }
        $this->page('staff/users', array('title' => 'Akun petugas', 'error' => $error,
            'staff' => $this->db->select('u.id,u.full_name,u.email,u.account_status,r.role_name')->from('users u')
                ->join('user_roles ur','ur.user_id=u.id')->join('roles r','r.id=ur.role_id')
                ->where('u.account_type','staff')->order_by('u.full_name')->get()->result()));
    }

    public function deactivate($id)
    {
        $this->post_only();
        $actor = $this->require_staff();
        if (!$this->metro->has_role($actor->id, array('administrator')) || (int) $id === (int) $actor->id) show_error('Akses ditolak.', 403);
        $target = $this->metro->user((int) $id);
        if (!$target || $target->account_type !== 'staff') show_404();
        $this->db->where('id', $target->id)->update('users', array('account_status' => 'inactive','updated_at' => date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id, 'staff_deactivate', 'users', $target->id);
        $this->message('Akun dinonaktifkan.', 'petugas/akun');
    }
}
