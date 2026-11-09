<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Documents extends MY_Controller
{
    public function download($id)
    {
        $user = $this->require_login();
        $doc = $this->metro->document((int) $id);
        if (!$doc || !$doc->is_current) show_404();
        $request = $this->metro->request($doc->service_request_id);
        if (!$request) show_404();
        $owner = (int) $request->user_id === (int) $user->id;
        $roles = $this->metro->roles($user->id);
        $staff = count(array_intersect($roles, array('administrator', 'verifier', 'technician', 'coordinator', 'issuer'))) > 0;
        $allowed = $owner && $doc->visibility === 'applicant';
        if ($doc->document_type === 'skhp' && $owner) {
            $item = $this->db->get_where('request_items', array('id' => $doc->request_item_id))->row();
            $allowed = $item && $item->item_status === 'completed';
        }
        if ($staff) {
            if ($doc->document_type === 'application_letter') {
                $allowed = count(array_intersect($roles, array('administrator','verifier','coordinator','issuer'))) > 0
                    || (in_array('technician', $roles, TRUE) && $this->db->from('staff_assignments a')
                        ->join('request_items i','i.id=a.request_item_id')->where('i.service_request_id',$request->id)
                        ->where('a.assigned_to',$user->id)->where('a.assignment_status','assigned')->count_all_results() > 0);
            }
            elseif ($doc->document_type === 'cerapan') $allowed = count(array_intersect($roles, array('administrator', 'issuer'))) > 0
                || (in_array('technician', $roles, TRUE) && $this->metro->assigned($doc->request_item_id, $user->id));
            elseif ($doc->document_type === 'skhp') $allowed = count(array_intersect($roles, array('administrator', 'issuer', 'verifier', 'coordinator'))) > 0;
        }
        if (!$allowed) show_error('Akses ditolak.', 403);
        $this->load->library('private_files');
        $path = $this->private_files->path($doc->storage_key);
        if (!$path) show_404();
        $this->metro->audit($user->id, 'document_download', 'service_documents', $doc->id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="dokumen-' . $doc->id . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
