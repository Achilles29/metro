<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Requests extends MY_Controller
{
    private function can_create_uttp($user,$profile)
    {
        return $this->metro->is_staff($user->id)
            || $this->metro->can((int)$profile->user_id,'self.uttp.create');
    }

    public function index()
    {
        $user = $this->require_login();
        if ($this->metro->is_staff($user->id)) redirect('petugas');
        if (!$this->metro->can($user->id,'self.requests.view')) show_error('Akses ditolak.',403);
        $technician=$user->account_type==='technician';
        $q=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80);
        $status=(string)$this->input->get('status',TRUE);
        if (!preg_match('/^[a-z_]{2,24}$/',$status)) $status='';
        $rows=(int)$this->input->get('rows');
        if (!in_array($rows,array(5,10,25),TRUE)) $rows=10;
        $filters=array('q'=>$q,'status'=>$status);
        $total=$technician?$this->metro->submitted_requests_count($user->id,NULL,$filters):$this->metro->requests_for_count($user->id,NULL,$filters);
        $pagination = metro_page($total,$this->input->get('page'),$rows);
        $this->news_base($user,$q,$status);
        $news_total=$this->db->count_all_results();
        $news_pagination=metro_page($news_total,$this->input->get('news_page'),$rows);
        $this->news_base($user,$q,$status);
        $news=$this->db->select('h.id,h.to_status,h.note,h.created_at,ri.unit_sequence,ri.brand,ri.type_name,ri.capacity,ri.media,ri.nozzle_no,ri.dispenser_no,ri.manufacture_year,ri.flow_rate,ri.readability,ut.type_name AS uttp_name,sr.id AS request_id,sr.request_no')
            ->order_by('h.created_at','DESC')->order_by('h.id','DESC')
            ->limit($news_pagination['per_page'],$news_pagination['offset'])->get()->result();
        $this->page('requests/index', array('title' => 'Permohonan saya',
            'requests' => $technician
                ?$this->metro->submitted_requests_for($user->id,$pagination['per_page'],$pagination['offset'],NULL,$filters)
                :$this->metro->requests_for($user->id,$pagination['per_page'],$pagination['offset'],NULL,$filters),
            'pagination'=>$pagination,'news'=>$news,'news_pagination'=>$news_pagination,
            'query'=>$q,'selected_status'=>$status,'rows'=>$rows));
    }

    private function news_base($user,$q,$status)
    {
        $this->db->from('item_status_history h')->join('request_items ri','ri.id=h.request_item_id')
            ->join('service_requests sr','sr.id=ri.service_request_id')
            ->join('uttp_types ut','ut.id=ri.uttp_type_id')->where('h.visibility','applicant');
        if ($user->account_type==='technician') {
            $this->db->join('company_technicians ct','ct.company_profile_id=sr.applicant_profile_id AND ct.technician_user_id=sr.submitted_by_user_id')
                ->where('sr.submitted_by_user_id',$user->id)->where('ct.status','active');
        } else {
            $this->db->join('applicant_profiles ap','ap.id=sr.applicant_profile_id')->where('ap.user_id',$user->id);
        }
        if ($q!=='') $this->db->group_start()->like('sr.request_no',$q)->or_like('ut.type_name',$q)
            ->or_like('ri.brand',$q)->or_like('ri.media',$q)->group_end();
        if ($status!=='') $this->db->where('h.to_status',$status);
    }

    public function create()
    {
        $user = $this->require_login();
        $staff=$this->metro->is_staff($user->id);
        if ($staff && !$this->metro->can($user->id,'requests.create_offline')) show_error('Akses ditolak.',403);
        if (!$staff && !$this->metro->can($user->id,'self.requests.create')) show_error('Akses ditolak.',403);
        $technician=$user->account_type==='technician';
        if (!$staff && $user->phone_verification_required && !$user->phone_verified_at) {
            $this->fail('Verifikasi nomor telepon sebelum mengajukan permohonan.',$technician?'profil-teknisi':'profil'); return;
        }
        $companies=array();
        if ($technician) {
            $tech=$this->db->get_where('technician_profiles',array('user_id'=>$user->id))->row();
            if (!$tech || $tech->verification_status!=='approved') {
                $this->fail('Akun pelaku usaha perlu persetujuan dinas.','profil-teknisi'); return;
            }
            $companies=$this->metro->technician_companies($user->id);
            if (!$companies) { $this->fail('Belum ada perusahaan yang terhubung dan disetujui.','pelimpahan'); return; }
            $company_id=(int)($this->input->method()==='post'?$this->input->post('company_profile_id'):$this->input->get('company_id'));
            if (!$company_id) $company_id=(int)$companies[0]->id;
            if (!$this->metro->active_company_link($user->id,$company_id)) show_error('Hubungan perusahaan tidak aktif.',403);
            $company=$this->db->get_where('applicant_profiles',array('id'=>$company_id))->row();
            $profile=$company?$this->metro->profile($company->user_id):NULL;
        } elseif ($staff) {
            $applicant_id=(int)($this->input->method()==='post'?$this->input->post('applicant_profile_id'):$this->input->get('applicant_id'));
            if (!$applicant_id) {
                $query=trim((string)$this->input->get('q',TRUE));
                $base=function() use ($query) {
                    $this->db->from('applicant_profiles ap')->join('users u','u.id=ap.user_id')
                        ->where('u.account_type','applicant')->where('u.account_status','active');
                    if ($query!=='') $this->db->group_start()->like('u.full_name',$query)->or_like('u.email',$query)
                        ->or_like('ap.organization_name',$query)->group_end();
                    return $this->db;
                };
                $pagination=metro_page($base()->count_all_results(),$this->input->get('page'),20);
                $profiles=$base()->select('ap.id,ap.organization_name,ap.address,u.full_name,u.phone,u.email')
                    ->order_by('u.full_name')->order_by('ap.id')
                    ->limit($pagination['per_page'],$pagination['offset'])->get()->result();
                $this->page('staff/choose_applicant',array('title'=>'Input permohonan loket','profiles'=>$profiles,
                    'query'=>$query,'pagination'=>$pagination));return;
            }
            $company=$this->db->select('ap.*,u.account_status')->from('applicant_profiles ap')
                ->join('users u','u.id=ap.user_id')->where('ap.id',$applicant_id)
                ->where('u.account_type','applicant')->get()->row();
            if (!$company || $company->account_status!=='active') show_404();
            $profile=$this->metro->profile($company->user_id);
        } else $profile = $this->metro->profile($user->id);
        if (!$profile || !$profile->address || !$this->master->profile_region($profile->district_id, $profile->village_id)) {
            $this->fail('Lengkapi profil dan wilayah sebelum mengajukan.',$staff?'petugas/pemohon/'.$company->user_id:($technician?'pelimpahan':'profil')); return;
        }
        $error = NULL;
        if ($this->input->method() === 'post') {
            try { $id = $this->save_request($user, $profile); redirect('permohonan/' . $id); return; }
            catch (RuntimeException $ex) { $error = $ex->getMessage(); }
        }
        $locations = array();
        foreach ($this->master->locations() as $location) $locations[$location->id] = $location;
        $registered=$this->db->select('a.*,t.type_name AS uttp_name')->from('applicant_uttp a')
            ->join('uttp_types t','t.id=a.uttp_type_id')->where('a.applicant_profile_id',$profile->id)
            ->where('a.is_active',1)->order_by('a.id','DESC')->get()->result();
        $available_types=$this->metro->types();
        if ($technician) {
            $scope=array_map('intval',(array)json_decode((string)$tech->tdur_scope,TRUE));
            $available_types=array_values(array_filter($available_types,function($type) use ($scope) {
                return in_array((int)$type->id,$scope,TRUE);
            }));
            $registered=array_values(array_filter($registered,function($tool) use ($scope) {
                return in_array((int)$tool->uttp_type_id,$scope,TRUE);
            }));
            if (!$available_types) { $this->fail('Kewenangan jenis UTTP belum dicatat pada TDUR. Hubungi dinas.','profil-teknisi');return; }
        }
        $can_new_uttp=$this->can_create_uttp($user,$profile);
        if (!$can_new_uttp && !$registered) {
            $this->fail('Belum ada UTTP tersimpan yang dapat dipilih. Hubungi petugas untuk menambahkan UTTP.','permohonan');return;
        }
        $this->page('requests/create', array('title' => 'Ajukan layanan', 'types' => $available_types,
            'service_types' => $this->master->options('service_type'), 'service_modes' => $this->master->options('service_mode'),
            'locations' => $locations, 'profile' => $profile, 'registered'=>$registered,
            'companies'=>$companies,'technician'=>$technician,'staff'=>$staff,'can_new_uttp'=>$can_new_uttp,
            'profile_owner'=>$this->metro->user($profile->user_id),'error' => $error));
    }

    private function save_request($user, $profile)
    {
        $type_ids = $this->input->post('uttp_type_id');
        $item_services = $this->input->post('item_service_type');
        $owner = trim((string) $this->input->post('owner_name', TRUE));
        $brands = $this->input->post('brand');
        $types = $this->input->post('type_name');
        $capacities = $this->input->post('capacity');
        $quantities = $this->input->post('quantity');
        $registry_ids=$this->input->post('applicant_uttp_id');
        $can_new_uttp=$this->can_create_uttp($user,$profile);
        $serials=$this->input->post('serial_number');
        $nozzles=$this->input->post('nozzle_no');
        $dispensers=$this->input->post('dispenser_no');
        $media_rows=$this->input->post('media');
        $years=$this->input->post('manufacture_year');
        $flows=$this->input->post('flow_rate');
        $readabilities=$this->input->post('readability');
        $item_addresses=$this->input->post('item_address');
        $latitudes=$this->input->post('item_latitude');
        $longitudes=$this->input->post('item_longitude');
        $sources=$this->input->post('item_location_source');
        $active_types = $this->metro->types();
        $valid_types = array_map('intval', array_column($active_types, 'id'));
        $mass_ids = array();
        foreach ($active_types as $type) if ((int) $type->requires_capacity === 1) $mass_ids[] = (int) $type->id;
        if (mb_strlen($owner) < 3 || mb_strlen($owner) > 190) throw new RuntimeException('Isi nama pemilik UTTP.');
        if (!is_array($type_ids) || count($type_ids) < 1 || count($type_ids) > 100
            || !is_array($item_services) || !is_array($quantities) || !is_array($brands) || !is_array($types)
            || !is_array($capacities) || !is_array($registry_ids) || !is_array($serials)
            || !is_array($item_addresses) || !is_array($latitudes)
            || !is_array($longitudes) || !is_array($sources)
            || count($type_ids) !== count($item_services) || count($type_ids) !== count($quantities) || count($type_ids) !== count($brands)
            || count($type_ids) !== count($item_addresses)) {
            throw new RuntimeException('Tambahkan 1–100 jenis UTTP.');
        }
        $rows = array(); $unit_count = 0;
        $valid_services=array_map(function($option){return $option->option_code;},$this->master->options('service_type'));
        foreach ($type_ids as $i => $type_id) {
            $quantity = filter_var($quantities[$i], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100)));
            if (!in_array((int) $type_id, $valid_types, TRUE) || !in_array($item_services[$i] ?? '',$valid_services,TRUE)
                || $quantity === FALSE || mb_strlen($owner) < 3 || mb_strlen($owner) > 190) {
                throw new RuntimeException('Periksa jenis, nama pemilik, dan jumlah UTTP (1–100).');
            }
            $unit_count += $quantity;
            if ($unit_count > 100) throw new RuntimeException('Maksimal 100 unit dalam satu permohonan.');
            $brand = trim((string) ($brands[$i] ?? ''));
            if ($brand === '' || mb_strlen($brand) > 120) throw new RuntimeException('Merek UTTP wajib diisi (maksimal 120 karakter).');
            $registry_id=(int)($registry_ids[$i] ?? 0);
            if (!$registry_id && !$can_new_uttp)
                throw new RuntimeException('Izin tambah UTTP tidak aktif. Pilih UTTP yang sudah tersimpan.');
            if ($registry_id) {
                $saved=$this->db->get_where('applicant_uttp',array('id'=>$registry_id,
                    'applicant_profile_id'=>$profile->id,'is_active'=>1))->row();
                if (!$saved || (int)$saved->uttp_type_id!==(int)$type_id)
                    throw new RuntimeException('UTTP tersimpan tidak sesuai dengan perusahaan yang dipilih.');
                if ($quantity!==1) throw new RuntimeException('UTTP tersimpan mewakili satu alat. Pilih satu unit atau tambahkan UTTP baru.');
            }
            $item_address=trim((string)($item_addresses[$i] ?? ''));
            $coordinates=metro_coordinates($latitudes[$i] ?? '',$longitudes[$i] ?? '');
            $source=(string)($sources[$i] ?? '');
            $serial=trim((string)($serials[$i] ?? ''));
            $type_row=$this->master->type((int)$type_id,FALSE);
            $pubbm=$type_row && in_array($type_row->type_code,array('PUBBM','POMPA_UKUR'),TRUE);
            $spec=array('nozzle_no'=>trim((string)($nozzles[$i]??'')),
                'dispenser_no'=>trim((string)($dispensers[$i]??'')),
                'media'=>trim((string)($media_rows[$i]??'')),
                'manufacture_year'=>trim((string)($years[$i]??'')),
                'flow_rate'=>trim((string)($flows[$i]??'')),
                'readability'=>trim((string)($readabilities[$i]??'')));
            if ($registry_id) foreach ($spec as $field=>$_) $spec[$field]=(string)$saved->$field;
            foreach (array('nozzle_no'=>40,'dispenser_no'=>40,'media'=>80,'manufacture_year'=>40,'flow_rate'=>80,'readability'=>80) as $field=>$length)
                if (mb_strlen($spec[$field])>$length) throw new RuntimeException('Rincian UTTP terlalu panjang.');
            if (mb_strlen($item_address)<10 || mb_strlen($item_address)>2000 || $coordinates===FALSE
                || ($coordinates && !in_array($source,array('','manual','map','google','google_review'),TRUE)) || mb_strlen($serial)>120)
                throw new RuntimeException('Periksa alamat dan pasangan koordinat setiap UTTP.');
            if ($quantity>1 && $serial!=='') throw new RuntimeException('Jumlah lebih dari satu harus memakai UTTP baru tanpa nomor seri bersama.');
            $rows[] = array('uttp_type_id' => (int) $type_id, 'service_type'=>$item_services[$i], 'owner_name' => $owner,
                'brand' => $brand,
                'type_name' => mb_substr(trim((string) ($types[$i] ?? '')), 0, 120),
                'capacity' => in_array((int) $type_id, $mass_ids, TRUE) ? mb_substr(trim((string) ($capacities[$i] ?? '')), 0, 120) : NULL,
                'serial_number'=>$serial ?: NULL,'applicant_uttp_id'=>$registry_id ?: NULL,
                'nozzle_no'=>$pubbm?($spec['nozzle_no']?:NULL):NULL,
                'dispenser_no'=>$pubbm?($spec['dispenser_no']?:NULL):NULL,
                'media'=>$pubbm?($spec['media']?:NULL):NULL,
                'manufacture_year'=>$pubbm?($spec['manufacture_year']?:NULL):NULL,
                'flow_rate'=>$pubbm?($spec['flow_rate']?:NULL):NULL,
                'readability'=>$pubbm?NULL:($spec['readability']?:NULL),
                'service_address'=>$item_address,
                'latitude'=>$coordinates?$coordinates[0]:NULL,'longitude'=>$coordinates?$coordinates[1]:NULL,
                'location_source'=>$coordinates?($source ?: 'manual'):NULL,
                'quantity' => $quantity);
        }
        $service_type = $rows[0]['service_type'];
        if ($user->account_type==='technician') {
            $scope_row=$this->db->select('tdur_scope,verification_status')->get_where('technician_profiles',array('user_id'=>$user->id))->row();
            $scope=$scope_row?array_map('intval',(array)json_decode((string)$scope_row->tdur_scope,TRUE)):array();
            if (!$scope_row || $scope_row->verification_status!=='approved') throw new RuntimeException('Pelaku usaha belum disetujui dinas.');
            foreach ($rows as $row) if (!in_array((int)$row['uttp_type_id'],$scope,TRUE))
                throw new RuntimeException('Jenis UTTP di luar kewenangan TDUR pelaku usaha.');
        }
        $service_mode = (string) $this->input->post('service_mode', TRUE);
        $mode = $this->master->option_code('service_mode', $service_mode);
        if (!$mode) throw new RuntimeException('Pilih tempat pelayanan.');
        $location = $mode->service_location_id ? $this->master->location($mode->service_location_id) : NULL;
        if ($mode->service_location_id && !$location) throw new RuntimeException('Lokasi pelayanan belum aktif. Hubungi petugas.');
        $address = $location ? $location->address : trim((string) $this->input->post('service_address', TRUE));
        if (mb_strlen($address) < 10) throw new RuntimeException('Isi alamat pelayanan dengan lengkap.');
        $region = $this->master->profile_region($profile->district_id, $profile->village_id);
        if (!$region) throw new RuntimeException('Wilayah profil sudah berubah. Perbarui profil terlebih dahulu.');
        $company_user=$this->metro->user($profile->user_id);
        $this->load->library('private_files');
        $file = !empty($_FILES['application_letter']['name']) ? $this->private_files->save('application_letter') : NULL;
        $this->db->trans_begin();
        try {
            if ($user->account_type==='technician') {
                $link=$this->db->query('SELECT id FROM company_technicians WHERE technician_user_id=? AND company_profile_id=? AND status=? FOR UPDATE',
                    array((int)$user->id,(int)$profile->id,'active'))->row();
                if (!$link || !$this->metro->active_company_link($user->id,$profile->id))
                    throw new RuntimeException('Pelimpahan tidak lagi aktif.');
            }
            $year = (int) date('Y');
            $this->db->query('INSERT INTO number_sequences (sequence_code, sequence_year, last_value) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE last_value=last_value', array('REQUEST', $year));
            $sequence = $this->db->query('SELECT last_value FROM number_sequences WHERE sequence_code=? AND sequence_year=? FOR UPDATE', array('REQUEST', $year))->row();
            $next = (int) $sequence->last_value + 1;
            $this->db->where('sequence_code', 'REQUEST')->where('sequence_year', $year)->update('number_sequences', array('last_value' => $next));
            $number = sprintf('%04d-%02d/%s', $next, $year % 100, date('d-m-Y'));
            $this->load->library('booking_codes');
            $booking_code=NULL;
            for ($attempt=0;$attempt<20;$attempt++) {
                $candidate=$this->booking_codes->generate();
                if (!$this->db->where('booking_code',$candidate)->count_all_results('service_requests')) {
                    $booking_code=$candidate;break;
                }
            }
            if ($booking_code===NULL) throw new RuntimeException('Kode booking belum tersedia. Coba lagi.');
            $this->db->insert('service_requests', array('request_no' => $number, 'booking_code'=>$booking_code, 'applicant_profile_id' => $profile->id,
                'submitted_by_user_id'=>$user->id,
                'submission_source'=>$this->metro->is_staff($user->id)?'counter':'online',
                'service_type' => $service_type, 'request_status' => 'submitted',
                'service_mode' => $service_mode, 'service_location_id' => $location ? $location->id : NULL,
                'submitted_at' => date('Y-m-d H:i:s'), 'contact_name' => $profile->contact_person ?: $company_user->full_name,
                'contact_phone' => $company_user->phone, 'service_address' => $address,
                'latitude'=>$profile->latitude,'longitude'=>$profile->longitude,
                'district' => $region[0]->district_name, 'district_id' => $region[0]->id,
                'village' => $region[1]->village_name, 'village_id' => $region[1]->id,
                'applicant_note' => mb_substr(trim((string) $this->input->post('note', TRUE)), 0, 1000)));
            $request_id = $this->db->insert_id();
            $unit = 0;
            foreach ($rows as $row) {
                for ($j = 0; $j < $row['quantity']; $j++) {
                $unit++;
                $item = $row; unset($item['quantity']);
                if (!$item['applicant_uttp_id']) {
                    $this->db->insert('applicant_uttp',array('applicant_profile_id'=>$profile->id,
                        'uttp_type_id'=>$row['uttp_type_id'],'owner_name'=>$row['owner_name'],
                        'brand'=>$row['brand'],'type_name'=>$row['type_name'],'capacity'=>$row['capacity'],
                        'serial_number'=>$row['serial_number'],'nozzle_no'=>$row['nozzle_no'],
                        'dispenser_no'=>$row['dispenser_no'],'media'=>$row['media'],
                        'manufacture_year'=>$row['manufacture_year'],'flow_rate'=>$row['flow_rate'],
                        'readability'=>$row['readability'],'service_address'=>$row['service_address'],
                        'latitude'=>$row['latitude'],'longitude'=>$row['longitude'],
                        'location_source'=>$row['location_source']));
                    $item['applicant_uttp_id']=$this->db->insert_id();
                }
                $item['service_request_id'] = $request_id; $item['unit_sequence'] = $unit; $item['item_status'] = 'submitted';
                $this->db->insert('request_items', $item);
                $this->db->insert('item_status_history', array('request_item_id' => $this->db->insert_id(), 'to_status' => 'submitted',
                    'actor_user_id' => $user->id, 'note' => 'Permohonan dikirim'));
                }
            }
            $this->db->insert('request_status_history', array('service_request_id' => $request_id,
                'to_status' => 'submitted', 'actor_user_id' => $user->id, 'note' => 'Permohonan dikirim'));
            if ($file) {
                $file['original_name']='permohonan_'.str_replace('/','-',$number).'.pdf';
                $file['service_request_id'] = $request_id; $file['document_type'] = 'application_letter';
                $file['visibility'] = 'applicant'; $file['uploaded_by'] = $user->id;
                $this->db->insert('service_documents', $file);
            }
            $this->metro->audit($user->id, 'request_submit', 'service_requests', $request_id, $number);
            if ((int)$profile->user_id!==(int)$user->id)
                $this->metro->notify($profile->user_id,$user->account_type==='staff'?'Permohonan loket':'Permohonan oleh teknisi',
                    ($user->account_type==='staff'?'Petugas loket':'Pelaku usaha').' mengirim permohonan '.$number.' atas nama Anda.','permohonan/'.$request_id);
            if ($this->db->trans_status() === FALSE) throw new RuntimeException('Penyimpanan permohonan gagal.');
            $this->db->trans_commit();
            return $request_id;
        } catch (Exception $ex) {
            $this->db->trans_rollback(); if ($file) $this->private_files->delete($file['storage_key']);
            throw new RuntimeException('Penyimpanan permohonan gagal. Coba lagi.');
        }
    }

    public function show($id)
    {
        $user = $this->require_login();
        if (!$this->metro->is_staff($user->id) && !$this->metro->can($user->id,'self.requests.view')) show_error('Akses ditolak.',403);
        $request = $this->metro->request((int) $id);
        if (!$request || (!$this->metro->can_access_request($user,$request)
            && !$this->metro->can_view_request($user->id,$request->id))) show_404();
        if ($this->metro->is_staff($user->id)) {
            redirect('petugas/permohonan/'.$request->id);
            return;
        }
        $docs = array_filter($this->metro->documents($request->id), function ($d) use ($user) {
            return $d->visibility === 'applicant' || $this->metro->is_staff($user->id);
        });
        $items = $this->metro->items($request->id);
        $needs_revision = FALSE;
        foreach ($items as $item) if ($item->item_status === 'revision') $needs_revision = TRUE;
        $this->page('requests/show', array('title' => 'Permohonan ' . $request->request_no, 'request' => $request,
            'items' => $items, 'needs_revision' => $needs_revision, 'documents' => $docs,
            'can_revise'=>$this->metro->can_access_request($user,$request),
            'history' => $this->metro->history($request->id),
            'feedback' => $this->db->get_where('service_feedback', array('service_request_id' => $request->id))->row()));
    }

    public function receipt($id)
    {
        $user = $this->require_login();
        if (!$this->metro->is_staff($user->id) && !$this->metro->can($user->id,'self.requests.view')) show_error('Akses ditolak.',403);
        $request = $this->metro->request((int) $id);
        if (!$request || (!$this->metro->can_access_request($user,$request)
            && !$this->metro->can_view_request($user->id,$request->id))) show_404();
        if (!$this->metro->can_access_request($user,$request) && !$this->metro->can($user->id,'requests.view_all'))
            show_error('Akses ditolak.',403);
        require_once FCPATH . 'vendor/autoload.php';
        $html = $this->load->view('requests/receipt', array('request' => $request, 'items' => $this->metro->items($request->id)), TRUE);
        $pdf = new Dompdf\Dompdf();
        $pdf->loadHtml($html); $pdf->setPaper('A4'); $pdf->render();
        $this->output->set_content_type('application/pdf')->set_header('Content-Disposition: '.($this->input->get('download')==='1'?'attachment':'inline').'; filename="bukti-' . $request->id . '.pdf"')
            ->set_output($pdf->output());
    }

    public function cancel($id)
    {
        $this->post_only();
        $user=$this->require_login();
        if (!$this->metro->can($user->id,'self.requests.view')) show_error('Akses ditolak.',403);
        $request=$this->metro->request((int)$id);
        if (!$request || !$this->metro->can_access_request($user,$request)) show_404();
        $reason=mb_substr(trim((string)$this->input->post('reason',TRUE)),0,500);
        if (mb_strlen($reason)<5) { $this->fail('Tuliskan alasan pembatalan minimal lima karakter.','permohonan/'.$id);return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM service_requests WHERE id=? FOR UPDATE',array($request->id));
        $items=$this->db->get_where('request_items',array('service_request_id'=>$request->id))->result();
        foreach ($items as $item) if (!in_array($item->item_status,array('submitted','revision'),TRUE)) {
            $this->db->trans_rollback();$this->fail('Permohonan sudah diproses. Hubungi petugas untuk pembatalan.','permohonan/'.$id);return;
        }
        if (!$items || $request->cancelled_at || $request->completed_at) {
            $this->db->trans_rollback();$this->fail('Permohonan tidak dapat dibatalkan.','permohonan/'.$id);return;
        }
        $now=date('Y-m-d H:i:s');
        foreach ($items as $item) {
            $this->db->where('id',$item->id)->update('request_items',array('item_status'=>'cancelled','updated_at'=>$now));
            $this->db->insert('item_status_history',array('request_item_id'=>$item->id,'from_status'=>$item->item_status,
                'to_status'=>'cancelled','actor_user_id'=>$user->id,'note'=>$reason,'visibility'=>'applicant'));
        }
        $this->db->where('id',$request->id)->update('service_requests',array('request_status'=>'cancelled',
            'cancelled_at'=>$now,'cancelled_by_user_id'=>$user->id,'cancellation_reason'=>$reason,'completed_at'=>$now));
        $this->db->insert('request_status_history',array('service_request_id'=>$request->id,
            'from_status'=>$request->request_status,'to_status'=>'cancelled','actor_user_id'=>$user->id,'note'=>$reason));
        $this->metro->audit($user->id,'request_cancel','service_requests',$request->id,$reason);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();show_error('Pembatalan gagal.',500); }
        $this->db->trans_commit();
        $this->message('Permohonan dibatalkan.','permohonan/'.$id);
    }

    public function feedback($id)
    {
        $this->post_only();
        $user = $this->require_login();
        if (!$this->metro->can($user->id,'self.requests.feedback')) show_error('Akses ditolak.',403);
        $request = $this->metro->request((int) $id);
        if (!$request || (int) $request->user_id !== (int) $user->id) show_404();
        $done = $this->db->where('service_request_id', $request->id)->where_not_in('item_status',metro_terminal_statuses())->count_all_results('request_items') === 0;
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
        if (!$this->metro->can($user->id,'self.requests.revise')) show_error('Akses ditolak.',403);
        $request = $this->metro->request((int) $id);
        if (!$request || !$this->metro->can_access_request($user,$request) ||
            $this->db->where('service_request_id', $request->id)->where('item_status', 'revision')->count_all_results('request_items') === 0) show_error('Perbaikan tidak tersedia.', 403);
        $this->load->library('private_files');
        try { $file = $this->private_files->save('application_letter'); }
        catch (RuntimeException $ex) { $this->fail($ex->getMessage(), 'permohonan/' . $id); return; }
        $this->db->trans_begin();
        $this->db->where('service_request_id', $request->id)->where('document_type', 'application_letter')->update('service_documents', array('is_current' => 0));
        $file['original_name']='permohonan_'.str_replace('/','-',$request->request_no).'.pdf';
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
