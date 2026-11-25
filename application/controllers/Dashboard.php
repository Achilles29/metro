<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
	public function index()
	{
		$this->page('dashboard/index', array('title' => 'Layanan tera dan tera ulang'));
	}

	public function track()
	{
		$this->output->set_header('Cache-Control: no-store, private');
		$data=array('title'=>'Lacak status permohonan');
		if ($this->input->method()!=='post') { $this->page('dashboard/track',$data); return; }
		$ip=(string)($_SERVER['REMOTE_ADDR'] ?? $this->input->ip_address());
		$this->db->insert('booking_lookup_attempts',array('ip_address'=>$ip));
		$recent=(int)$this->db->where('ip_address',$ip)->where('attempted_at >=',date('Y-m-d H:i:s',time()-60))
			->count_all_results('booking_lookup_attempts');
		if (random_int(1,100)===1) $this->db->where('attempted_at <',date('Y-m-d H:i:s',time()-86400))->delete('booking_lookup_attempts');
		$submitted_code=strtoupper(trim((string)$this->input->post('booking_code',TRUE)));
		$data['tracking_code']=mb_substr($submitted_code,0,24);
		if ($recent>15) {
			$this->output->set_status_header(429);
			$data['tracking_error']='Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.';
		} else {
			$this->load->library('booking_codes');
			$code=$this->booking_codes->normalize($submitted_code);
			$request=$code?$this->db->select('id,request_no,booking_code,submitted_at,service_type,request_status')
				->get_where('service_requests',array('booking_code'=>$code))->row():NULL;
			if (!$request) $data['tracking_error']='Kode booking tidak ditemukan. Periksa kembali enam karakter pada bukti order.';
			else {
				$data['tracking']=$request;
				$data['tracking_items']=$this->db->select('ri.id,ri.unit_sequence,ri.item_status,ri.service_type,ri.brand,ri.type_name AS model_name,ri.media,ri.nozzle_no,ri.dispenser_no,ri.manufacture_year,ri.flow_rate,ri.readability,ut.type_name,ut.certificate_scope')
					->from('request_items ri')->join('uttp_types ut','ut.id=ri.uttp_type_id')
					->where('ri.service_request_id',$request->id)->order_by('ri.unit_sequence')->get()->result();
				$data['tracking_documents']=$this->db->select('sd.id,sd.request_item_id,sd.original_name')
					->from('service_documents sd')->where('sd.service_request_id',$request->id)
					->where('sd.document_type','skhp')->where('sd.is_current',1)->get()->result();
				$data['tracking_completed']=$this->db->where('id',$request->id)
					->where('completed_at IS NOT NULL',NULL,FALSE)->count_all_results('service_requests')>0;
			}
		}
		$this->page('dashboard/track',$data);
	}

	public function download_skhp($id)
	{
		$this->post_only();
		$ip=(string)($_SERVER['REMOTE_ADDR'] ?? $this->input->ip_address());
		$this->db->insert('booking_lookup_attempts',array('ip_address'=>$ip));
		if ($this->db->where('ip_address',$ip)->where('attempted_at >=',date('Y-m-d H:i:s',time()-60))
			->count_all_results('booking_lookup_attempts')>15) show_error('Terlalu banyak percobaan. Coba lagi sebentar.',429);
		$this->load->library('booking_codes');
		$code=$this->booking_codes->normalize((string)$this->input->post('booking_code',TRUE));
		if (!$code) show_404();
		$request=$this->db->select('id')->where('booking_code',$code)
			->where('completed_at IS NOT NULL',NULL,FALSE)->get('service_requests')->row();
		if (!$request) show_404();
		if ($this->db->where('service_request_id',$request->id)
			->where_not_in('item_status',metro_terminal_statuses())->count_all_results('request_items')) show_404();
		$doc=$this->db->select('sd.*,ri.item_status')->from('service_documents sd')
			->join('request_items ri','ri.id=sd.request_item_id')
			->where('sd.id',(int)$id)->where('sd.service_request_id',$request->id)
			->where('sd.document_type','skhp')->where('sd.is_current',1)
			->where('sd.visibility','applicant')->get()->row();
		if (!$doc || !metro_is_valid($doc->item_status)) show_404();
		$this->load->library('private_files');
		$path=$this->private_files->path($doc->storage_key);
		if (!$path) show_404();
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="SKHP-'.$doc->id.'.pdf"');
		header('Content-Length: '.filesize($path));
		header('Cache-Control: no-store, private');
		header('X-Content-Type-Options: nosniff');
		readfile($path);exit;
	}
}
