<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Documents extends MY_Controller
{
    public function download($id)
    {
        $user = $this->require_login();
        if (!$this->metro->is_staff($user->id) && !$this->metro->can($user->id,'self.requests.view')) show_error('Akses ditolak.',403);
        $doc = $this->metro->document((int) $id);
        if (!$doc || !$doc->is_current) show_404();
        $request = $this->metro->request($doc->service_request_id);
        if (!$request) show_404();
        $owner = $this->metro->can_access_request($user,$request);
        $allowed = $owner && $doc->visibility === 'applicant';
        if ($doc->document_type === 'skhp' && $owner) {
            $item = $this->db->get_where('request_items', array('id' => $doc->request_item_id))->row();
            $allowed = $item && metro_is_valid($item->item_status);
        }
        if ($this->metro->is_staff($user->id)) {
            if ($doc->document_type === 'application_letter') {
                $allowed = $this->metro->can($user->id,'documents.application.read')
                    && $this->metro->can_view_request($user->id,$request->id);
            }
            elseif ($doc->document_type === 'cerapan') $allowed = $this->metro->can($user->id,'documents.cerapan.read')
                && $this->metro->can_view_request($user->id,$request->id)
                && ($this->metro->can($user->id,'requests.view_all') || $this->metro->assigned($doc->request_item_id,$user->id));
            elseif ($doc->document_type === 'skhp') $allowed = $this->metro->can($user->id,'documents.skhp.read')
                && $this->metro->can_view_request($user->id,$request->id)
                && ($this->metro->can($user->id,'requests.view_all') || $this->metro->assigned($doc->request_item_id,$user->id));
        }
        if (!$allowed) show_error('Akses ditolak.', 403);
        $this->load->library('private_files');
        $path = $this->private_files->path($doc->storage_key);
        if (!$path) show_404();
        $this->metro->audit($user->id, 'document_download', 'service_documents', $doc->id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($this->input->get('download') === '1' ? 'attachment' : 'inline') . '; filename="dokumen-' . $doc->id . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
