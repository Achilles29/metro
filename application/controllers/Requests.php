<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Requests extends MY_Controller
{
    public function index()
    {
        $user = $this->require_login();
        if ($this->metro->is_staff($user->id)) redirect('petugas');
        $this->page('requests/index', array('title' => 'Permohonan saya',
            'requests' => $this->metro->requests_for($user->id),
            'notifications' => $this->db->where('user_id', $user->id)->order_by('sent_at', 'DESC')->limit(5)->get('notifications')->result()));
    }

    public function create()
    {
        $user = $this->require_login();
        if ($this->metro->is_staff($user->id)) show_error('Akses ditolak.', 403);
        $profile = $this->metro->profile($user->id);
        if (!$profile || !$profile->address) { $this->fail('Lengkapi profil sebelum mengajukan.', 'profil'); return; }
        $error = NULL;
        if ($this->input->method() === 'post') {
            try { $id = $this->save_request($user, $profile); redirect('permohonan/' . $id); return; }
            catch (RuntimeException $ex) { $error = $ex->getMessage(); }
        }
        $this->page('requests/create', array('title' => 'Ajukan layanan', 'types' => $this->metro->types(),
            'profile' => $profile, 'error' => $error));
    }

    private function save_request($user, $profile)
    {
        $type_ids = $this->input->post('uttp_type_id');
        $owners = $this->input->post('owner_name');
        $brands = $this->input->post('brand');
        $types = $this->input->post('type_name');
        $models = $this->input->post('model_name');
        $capacities = $this->input->post('capacity');
        $quantities = $this->input->post('quantity');
        $valid_types = array_map('intval', array_column($this->metro->types(), 'id'));
        if (!is_array($type_ids) || count($type_ids) < 1 || count($type_ids) > 20 || !is_array($quantities) || count($type_ids) !== count($quantities)) {
            throw new RuntimeException('Tambahkan 1–20 jenis UTTP.');
        }
        $rows = array(); $unit_count = 0;
        foreach ($type_ids as $i => $type_id) {
            $quantity = filter_var($quantities[$i], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100)));
            $owner = trim((string) ($owners[$i] ?? ''));
            if (!in_array((int) $type_id, $valid_types, TRUE) || $quantity === FALSE || mb_strlen($owner) < 3 || mb_strlen($owner) > 190) {
                throw new RuntimeException('Periksa jenis, nama pemilik, dan jumlah UTTP (1–100).');
            }
            $unit_count += $quantity;
            if ($unit_count > 100) throw new RuntimeException('Maksimal 100 unit dalam satu permohonan.');
            $rows[] = array('uttp_type_id' => (int) $type_id, 'owner_name' => $owner,
                'brand' => mb_substr(trim((string) ($brands[$i] ?? '')), 0, 120),
                'type_name' => mb_substr(trim((string) ($types[$i] ?? '')), 0, 120),
                'model_name' => mb_substr(trim((string) ($models[$i] ?? '')), 0, 120),
                'capacity' => mb_substr(trim((string) ($capacities[$i] ?? '')), 0, 120),
                'quantity' => $quantity);
        }
        $service_type = $this->input->post('service_type');
        if (!in_array($service_type, array('tera', 'tera_ulang'), TRUE)) throw new RuntimeException('Pilih jenis layanan.');
        $this->load->library('private_files');
        $file = $this->private_files->save('application_letter');
        $this->db->trans_begin();
        try {
            $year = (int) date('Y');
            $this->db->query('INSERT INTO number_sequences (sequence_code, sequence_year, last_value) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE last_value=last_value', array('REQUEST', $year));
            $sequence = $this->db->query('SELECT last_value FROM number_sequences WHERE sequence_code=? AND sequence_year=? FOR UPDATE', array('REQUEST', $year))->row();
            $next = (int) $sequence->last_value + 1;
            $this->db->where('sequence_code', 'REQUEST')->where('sequence_year', $year)->update('number_sequences', array('last_value' => $next));
            $number = sprintf('%04d-%02d/%s', $next, $year % 100, date('d-m-Y'));
            $this->db->insert('service_requests', array('request_no' => $number, 'applicant_profile_id' => $profile->id,
                'service_type' => $service_type, 'request_status' => 'submitted',
                'service_mode' => $this->input->post('service_mode') === 'field' ? 'field' : 'office',
                'submitted_at' => date('Y-m-d H:i:s'), 'contact_name' => $profile->contact_person ?: $user->full_name,
                'contact_phone' => $user->phone, 'service_address' => trim((string) $this->input->post('service_address', TRUE)) ?: $profile->address,
                'district' => $profile->district, 'village' => $profile->village,
                'applicant_note' => mb_substr(trim((string) $this->input->post('note', TRUE)), 0, 1000)));
            $request_id = $this->db->insert_id();
            $unit = 0;
            foreach ($rows as $row) for ($j = 0; $j < $row['quantity']; $j++) {
                $unit++;
                $item = $row; unset($item['quantity']);
                $item['service_request_id'] = $request_id; $item['unit_sequence'] = $unit; $item['item_status'] = 'submitted';
                $this->db->insert('request_items', $item);
                $this->db->insert('item_status_history', array('request_item_id' => $this->db->insert_id(), 'to_status' => 'submitted',
                    'actor_user_id' => $user->id, 'note' => 'Permohonan dikirim'));
            }
            $this->db->insert('request_status_history', array('service_request_id' => $request_id,
                'to_status' => 'submitted', 'actor_user_id' => $user->id, 'note' => 'Permohonan dikirim'));
            $file['service_request_id'] = $request_id; $file['document_type'] = 'application_letter';
            $file['visibility'] = 'applicant'; $file['uploaded_by'] = $user->id;
            $this->db->insert('service_documents', $file);
            $this->metro->audit($user->id, 'request_submit', 'service_requests', $request_id, $number);
            if ($this->db->trans_status() === FALSE) throw new RuntimeException('Penyimpanan permohonan gagal.');
            $this->db->trans_commit();
            return $request_id;
        } catch (Exception $ex) {
            $this->db->trans_rollback(); $this->private_files->delete($file['storage_key']);
            throw new RuntimeException('Penyimpanan permohonan gagal. Coba lagi.');
        }
    }

    public function show($id)
    {
        $user = $this->require_login();
        $request = $this->metro->request((int) $id);
        if (!$request || ((int) $request->user_id !== (int) $user->id && !$this->metro->is_staff($user->id))) show_404();
        $docs = array_filter($this->metro->documents($request->id), function ($d) use ($user) {
            return $d->visibility === 'applicant' || $this->metro->is_staff($user->id);
        });
        $items = $this->metro->items($request->id);
        $needs_revision = FALSE;
        foreach ($items as $item) if ($item->item_status === 'revision') $needs_revision = TRUE;
        $this->page('requests/show', array('title' => 'Permohonan ' . $request->request_no, 'request' => $request,
            'items' => $items, 'needs_revision' => $needs_revision, 'documents' => $docs,
            'history' => $this->metro->history($request->id),
            'feedback' => $this->db->get_where('service_feedback', array('service_request_id' => $request->id))->row()));
    }

    public function receipt($id)
    {
        $user = $this->require_login();
        $request = $this->metro->request((int) $id);
        if (!$request || ((int) $request->user_id !== (int) $user->id && !$this->metro->is_staff($user->id))) show_404();
        require_once FCPATH . 'vendor/autoload.php';
        $html = $this->load->view('requests/receipt', array('request' => $request, 'items' => $this->metro->items($request->id)), TRUE);
        $pdf = new Dompdf\Dompdf();
        $pdf->loadHtml($html); $pdf->setPaper('A4'); $pdf->render();
        $this->output->set_content_type('application/pdf')->set_header('Content-Disposition: attachment; filename="bukti-' . $request->id . '.pdf"')
            ->set_output($pdf->output());
    }

    public function feedback($id)
    {
        $this->post_only();
        $user = $this->require_login();
        $request = $this->metro->request((int) $id);
        if (!$request || (int) $request->user_id !== (int) $user->id) show_404();
        $done = $this->db->where('service_request_id', $request->id)->where('item_status !=', 'completed')->count_all_results('request_items') === 0;
        if (!$done) show_error('Umpan balik tersedia setelah semua unit selesai.', 400);
        $rating = filter_var($this->input->post('rating'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 5)));
        if (!$rating) { $this->fail('Pilih nilai 1–5.', 'permohonan/' . $id); return; }
        $this->db->replace('service_feedback', array('service_request_id' => $request->id,
            'applicant_profile_id' => $request->applicant_profile_id, 'rating' => $rating,
            'comment' => mb_substr(trim((string) $this->input->post('comment', TRUE)), 0, 2000)));
        $this->message('Terima kasih atas masukan Anda.', 'permohonan/' . $id);
    }

    public function revise($id)
    {
        $this->post_only();
        $user = $this->require_login();
        $request = $this->metro->request((int) $id);
        if (!$request || (int) $request->user_id !== (int) $user->id ||
            $this->db->where('service_request_id', $request->id)->where('item_status', 'revision')->count_all_results('request_items') === 0) show_error('Perbaikan tidak tersedia.', 403);
        $this->load->library('private_files');
        try { $file = $this->private_files->save('application_letter'); }
        catch (RuntimeException $ex) { $this->fail($ex->getMessage(), 'permohonan/' . $id); return; }
        $this->db->trans_begin();
        $this->db->where('service_request_id', $request->id)->where('document_type', 'application_letter')->update('service_documents', array('is_current' => 0));
        $file['service_request_id'] = $request->id; $file['document_type'] = 'application_letter';
        $file['visibility'] = 'applicant'; $file['uploaded_by'] = $user->id;
        $this->db->insert('service_documents', $file);
        $items = $this->metro->items($request->id);
        foreach ($items as $item) if ($item->item_status === 'revision') {
            $this->db->where('id', $item->id)->update('request_items', array('item_status' => 'verification'));
            $this->db->insert('item_status_history', array('request_item_id' => $item->id,
                'from_status' => 'revision', 'to_status' => 'verification', 'actor_user_id' => $user->id,
                'note' => 'Berkas perbaikan dikirim'));
        }
        $statuses = array_column($this->db->select('item_status')->get_where('request_items', array('service_request_id' => $request->id))->result_array(), 'item_status');
        $overall = count(array_unique($statuses)) === 1 ? $statuses[0] : 'in_progress';
        $this->db->where('id', $request->id)->update('service_requests', array('request_status' => $overall));
        $this->db->insert('request_status_history', array('service_request_id' => $request->id,
            'from_status' => $request->request_status, 'to_status' => $overall, 'actor_user_id' => $user->id));
        $this->metro->audit($user->id, 'request_revision', 'service_requests', $request->id);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback(); $this->private_files->delete($file['storage_key']);
            $this->fail('Perbaikan gagal dikirim.', 'permohonan/' . $id); return;
        }
        $this->db->trans_commit();
        $this->message('Berkas perbaikan terkirim.', 'permohonan/' . $id);
    }
}
