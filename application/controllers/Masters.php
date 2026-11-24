<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Masters extends MY_Controller
{
    private function admin()
    {
        $actor = $this->require_staff();
        if (!$this->metro->can($actor->id,'masters.manage')) show_error('Akses ditolak.',403);
        return $actor;
    }

    private function integer($name, $default = 0)
    {
        $value = filter_var($this->input->post($name), FILTER_VALIDATE_INT);
        return $value === FALSE || $value === NULL ? $default : max(0, min(999999, $value));
    }

    private function code($value, $max = 48)
    {
        return preg_match('/^[A-Za-z0-9_.-]{1,'.$max.'}$/', $value) === 1;
    }

    private function saved($actor, $entity, $id, $url)
    {
        $this->metro->audit($actor->id, 'master_save', $entity, $id);
        $this->message('Data master tersimpan.', $url);
    }

    private function paged($rows, $size = 20)
    {
        $pagination = metro_page(count($rows), $this->input->get('page'), $size);
        return array(array_slice($rows, $pagination['offset'], $pagination['per_page']), $pagination);
    }

    public function toggle($kind, $id)
    {
        $this->post_only(); $actor=$this->admin(); $id=(int)$id;
        $map=array('uttp'=>array('uttp_types','petugas/master/uttp'),
            'kecamatan'=>array('master_districts','petugas/master/kecamatan'),
            'desa'=>array('master_villages','petugas/master/desa'),
            'lokasi'=>array('master_locations','petugas/master/lokasi'));
        if (!isset($map[$kind]) || $id<1) show_404();
        list($table,$url)=$map[$kind];
        $row=$this->db->get_where($table,array('id'=>$id))->row();
        if (!$row) show_404();
        $active=(int)!$row->is_active;
        if (!$active) {
            if ($kind==='lokasi' && $this->db->where('service_location_id',$id)->where('is_active',1)->count_all_results('master_options')) {
                $this->fail('Lokasi masih dipakai tempat layanan aktif.',$url); return;
            }
            if ($kind==='desa') {
                $district=$this->master->district($row->district_id,FALSE);
                if ($district && $district->is_active && $this->db->where('district_id',$row->district_id)->where('is_active',1)->count_all_results('master_villages')<=1) {
                    $this->fail('Minimal satu desa dalam kecamatan aktif harus tersedia.',$url); return;
                }
            } elseif ($kind!=='lokasi' && $this->db->where('is_active',1)->count_all_results($table)<=1) {
                $this->fail('Minimal satu data harus tetap aktif.',$url); return;
            }
        }
        $this->db->where('id',$id)->update($table,array('is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,$active?'master_activate':'master_deactivate',$table,$id);
        $this->message($active?'Data diaktifkan.':'Data dinonaktifkan.',$url);
    }

    public function toggle_option($group_code, $id)
    {
        $this->post_only(); $actor=$this->admin(); $id=(int)$id;
        $group=$this->master->group($group_code);
        $row=$group ? $this->master->option($group_code,$id,FALSE) : NULL;
        if (!$row) show_404();
        $url='petugas/master/pilihan/'.$group_code;
        if (!$group->allow_create) { $this->fail('Pilihan sistem ini tidak dapat dinonaktifkan.',$url); return; }
        $active=(int)!$row->is_active;
        if (!$active && in_array($group_code,array('service_type','service_mode'),TRUE)
            && $this->db->where('group_code',$group_code)->where('is_active',1)->count_all_results('master_options')<=1) {
            $this->fail('Minimal satu pilihan harus tetap aktif.',$url); return;
        }
        $this->db->where('id',$id)->where('group_code',$group_code)->update('master_options',array('is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s')));
        $this->metro->audit($actor->id,$active?'master_activate':'master_deactivate','master_options',$id,$group_code);
        $this->message($active?'Pilihan diaktifkan.':'Pilihan dinonaktifkan.',$url);
    }

    public function toggle_role($id)
    {
        $this->post_only(); $actor=$this->admin(); $id=(int)$id;
        $url='petugas/master/peran';
        $row=$this->db->get_where('roles',array('id'=>$id))->row();
        if (!$row) show_404();
        if (in_array($row->role_code,array('administrator','applicant'),TRUE)) {
            $this->fail('Peran inti harus tetap aktif.',$url); return;
        }
        $active=(int)!$row->is_active;
        $this->db->where('id',$row->id)->update('roles',array('is_active'=>$active));
        $this->metro->audit($actor->id,$active?'master_activate':'master_deactivate','roles',$row->id);
        $this->message($active?'Peran diaktifkan.':'Peran dinonaktifkan.',$url);
    }

    public function delete($kind, $id)
    {
        $this->post_only(); $actor=$this->admin(); $id=(int)$id;
        $map=array(
            'uttp'=>array('uttp_types','petugas/master/uttp',array(array('request_items','uttp_type_id'),array('applicant_uttp','uttp_type_id'))),
            'kecamatan'=>array('master_districts','petugas/master/kecamatan',array(array('master_villages','district_id'),array('master_locations','district_id'),array('applicant_profiles','district_id'),array('service_requests','district_id'))),
            'desa'=>array('master_villages','petugas/master/desa',array(array('master_locations','village_id'),array('applicant_profiles','village_id'),array('service_requests','village_id'))),
            'lokasi'=>array('master_locations','petugas/master/lokasi',array(array('master_options','service_location_id'),array('service_requests','service_location_id')))
        );
        if (!isset($map[$kind]) || $id<1) show_404();
        list($table,$url,$refs)=$map[$kind];
        $row=$this->db->get_where($table,array('id'=>$id))->row();
        if (!$row) show_404();
        foreach ($refs as $ref) if ($this->db->where($ref[1],$id)->count_all_results($ref[0])) {
            $this->fail('Data sudah digunakan. Nonaktifkan jika tidak lagi tersedia.',$url); return;
        }
        if (!empty($row->is_active) && $this->db->where('is_active',1)->count_all_results($table)<=1) {
            $this->fail('Minimal satu data harus tetap aktif.',$url); return;
        }
        $this->db->trans_begin();
        $this->db->where('id',$id)->delete($table);
        $this->metro->audit($actor->id,'master_delete',$table,$id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Data gagal dihapus karena sedang digunakan.',$url); return; }
        $this->db->trans_commit();
        $this->message('Data master dihapus.',$url);
    }

    public function delete_option($group_code, $id)
    {
        $this->post_only(); $actor=$this->admin(); $id=(int)$id;
        $group=$this->master->group($group_code);
        $row=$group ? $this->master->option($group_code,$id,FALSE) : NULL;
        if (!$row) show_404();
        $url='petugas/master/pilihan/'.$group_code;
        if (!$group->allow_create || !in_array($group_code,array('penera','ctt','standard_equipment','service_type','service_mode'),TRUE)) {
            $this->fail('Pilihan sistem tidak dapat dihapus. Gunakan status nonaktif atau ubah label.',$url); return;
        }
        if (in_array($group_code,array('service_type','service_mode'),TRUE)) {
            if ($this->db->where($group_code,$row->option_code)->count_all_results('service_requests')) {
                $this->fail('Pilihan sudah digunakan. Nonaktifkan jika tidak lagi tersedia.',$url); return;
            }
            if ($row->is_active && $this->db->where('group_code',$group_code)->where('is_active',1)->count_all_results('master_options')<=1) {
                $this->fail('Minimal satu pilihan harus tetap aktif.',$url); return;
            }
        }
        foreach (array('penera_option_id','ctt_option_id','standard_equipment_option_id') as $column)
            if ($this->db->where($column,$id)->count_all_results('service_requests')) {
                $this->fail('Pilihan sudah digunakan. Nonaktifkan jika tidak lagi tersedia.',$url); return;
            }
        if ($group_code==='ctt' && $this->db->where('ctt_option_id',$id)->count_all_results('request_ctts')) {
            $this->fail('CTT memiliki riwayat peminjaman. Nonaktifkan jika tidak lagi tersedia.',$url); return;
        }
        $this->db->trans_begin();
        $this->db->where('id',$id)->where('group_code',$group_code)->delete('master_options');
        $this->metro->audit($actor->id,'master_delete','master_options',$id,$group_code);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $this->fail('Pilihan gagal dihapus.',$url); return; }
        $this->db->trans_commit();
        $this->message('Pilihan master dihapus.',$url);
    }

    public function index()
    {
        $this->admin();
        $counts = array(
            'uttp' => $this->db->count_all('uttp_types'),
            'uttp_active' => $this->db->where('is_active',1)->count_all_results('uttp_types'),
            'districts' => $this->db->count_all('master_districts'),
            'villages' => $this->db->count_all('master_villages'),
            'locations' => $this->db->count_all('master_locations'),
            'roles' => $this->db->count_all('roles')
        );
        foreach ($this->master->groups() as $group) $counts[$group->group_code] = $this->db->where('group_code',$group->group_code)->count_all_results('master_options');
        $this->page('staff/master/index', array('title'=>'Master data','groups'=>$this->master->groups(),'counts'=>$counts));
    }

    public function options($group_code = '')
    {
        $actor = $this->admin();
        $group = $this->master->group($group_code);
        if (!$group) show_404();
        $url = 'petugas/master/pilihan/'.$group_code;
        if ($this->input->method() === 'post') {
            $id = (int) $this->input->post('id');
            $existing = $id ? $this->master->option($group_code, $id, FALSE) : NULL;
            if ($id && !$existing) show_404();
            if (!$id && !$group->allow_create) { $this->fail('Kelompok ini hanya mengizinkan perubahan label data yang sudah ada.', $url); return; }
            $code = $existing ? $existing->option_code : trim((string) $this->input->post('option_code', TRUE));
            $label = trim((string) $this->input->post('option_label', TRUE));
            $description = trim((string) $this->input->post('description', TRUE));
            $sort = $this->integer('sort_order');
            $active = $group->allow_create ? ($this->input->post('is_active') ? 1 : 0) : (int) ($existing ? $existing->is_active : 1);
            $tone = $group->has_tone ? (string) $this->input->post('tone', TRUE) : NULL;
            $location_id = $group->has_location ? (int) $this->input->post('service_location_id') : 0;
            $sheet_url = $group_code==='ctt' ? trim((string)$this->input->post('spreadsheet_url',TRUE)) : NULL;
            $asset_quantity = $group_code==='ctt' ? trim((string)$this->input->post('asset_quantity',TRUE)) : NULL;
            $max_code = in_array($group_code, array('service_type','service_mode'), TRUE) ? 24 : 48;
            if (!$this->code($code, $max_code) || mb_strlen($label) < 2 || mb_strlen($label) > 190 || mb_strlen($description) > 255
                || ($group->has_tone && !in_array($tone, array('info','warning','success','danger'), TRUE))
                || ($location_id && !$this->master->location($location_id))
                || ($sheet_url && (mb_strlen($sheet_url)>500 || !preg_match('#^https://docs\.google\.com/spreadsheets/d/[A-Za-z0-9_-]+(?:/[^\s]*)?$#',$sheet_url)))
                || ($group_code==='ctt' && !preg_match('/^(0|[1-9][0-9]{0,5})$/',$asset_quantity))) {
                $this->fail('Periksa kode, nama, warna status, dan lokasi master.', $url); return;
            }
            if (!$existing && $this->master->option_code($group_code, $code, FALSE)) { $this->fail('Kode master sudah digunakan.', $url); return; }
            if (!$active && in_array($group_code, array('service_type','service_mode'), TRUE)
                && $this->db->where('group_code',$group_code)->where('is_active',1)->count_all_results('master_options') <= 1
                && (!$existing || (int) $existing->is_active === 1)) { $this->fail('Minimal satu pilihan harus tetap aktif.', $url); return; }
            $values = array('group_code'=>$group_code,'option_code'=>$code,'option_label'=>$label,
                'description'=>$description ?: NULL,'sort_order'=>$sort,'is_active'=>$active,
                'tone'=>$tone,'service_location_id'=>$location_id ?: NULL,
                'spreadsheet_url'=>$sheet_url ?: NULL,'updated_at'=>date('Y-m-d H:i:s'));
            if ($group_code==='ctt') {
                $this->load->model('Ctt_inventory_model','ctt_inventory');
                $this->db->trans_begin();
                if ($existing) {
                    $this->db->query('SELECT id FROM master_options WHERE id=? FOR UPDATE',array($existing->id));
                    $minimum=$this->ctt_inventory->minimum_stock($existing->id);
                    if ((int)$asset_quantity < $minimum) {
                        $this->db->trans_rollback();
                        $this->fail('Jumlah aset minimal '.$minimum.' karena masih dipinjam.',$url); return;
                    }
                }
                $values['asset_quantity']=(int)$asset_quantity;
            }
            if ($existing) $this->db->where('id',$existing->id)->update('master_options',$values);
            else $this->db->insert('master_options',$values);
            if ($this->db->error()['code']) {
                if ($group_code==='ctt') $this->db->trans_rollback();
                $this->fail('Gagal menyimpan master. Periksa kode unik.', $url); return;
            }
            if ($group_code==='ctt') $this->db->trans_commit();
            $this->saved($actor,'master_options',$existing ? $existing->id : $this->db->insert_id(),$url);
            return;
        }
        list($options,$pagination)=$this->paged($this->master->options($group_code,FALSE));
        $ctt_stock=array();$ctt_history_counts=array();
        $stock_date=(string)$this->input->get('date',TRUE);
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$stock_date);
        if (!$parsed || $parsed->format('Y-m-d')!==$stock_date) $stock_date=date('Y-m-d');
        if ($group_code==='ctt') {
            $this->load->model('Ctt_inventory_model','ctt_inventory');
            foreach ($options as $option) {
                $ctt_stock[$option->id]=$this->ctt_inventory->availability($option->id,$stock_date,$stock_date);
                $ctt_history_counts[$option->id]=$this->db->from('request_ctt_daily_loans dl')
                    ->join('request_ctts rc','rc.id=dl.request_ctt_id')
                    ->where('rc.ctt_option_id',$option->id)->count_all_results();
            }
        }
        $this->page('staff/master/options',array('title'=>'Master '.$group->group_name,'group'=>$group,
            'options'=>$options,'pagination'=>$pagination,'locations'=>$this->master->locations(),
            'ctt_stock'=>$ctt_stock,'ctt_history_counts'=>$ctt_history_counts,'stock_date'=>$stock_date));
    }

    public function ctt_history($id)
    {
        $this->require_permission('masters.manage');
        $option=$this->master->option('ctt',(int)$id,FALSE);
        if (!$option) show_404();
        $base=function() use ($option) { return $this->db->from('request_ctt_daily_loans dl')
            ->join('request_ctts rc','rc.id=dl.request_ctt_id')->where('rc.ctt_option_id',$option->id); };
        $pagination=metro_page($base()->count_all_results(),$this->input->get('page'),20);
        $rows=$base()->select('dl.*,rc.request_id,rc.asset_units,rc.cancelled_at,rc.cancel_reason,sr.request_no,u.full_name AS borrower_name')
            ->join('service_requests sr','sr.id=rc.request_id')
            ->join('users u','u.id=dl.borrowed_by','left')
            ->order_by('dl.loan_date','DESC')->order_by('dl.id','DESC')
            ->limit($pagination['per_page'],$pagination['offset'])->get()->result();
        $this->page('staff/master/ctt_history',array('title'=>'Riwayat CTT '.$option->option_label,
            'option'=>$option,'rows'=>$rows,'pagination'=>$pagination));
    }

    public function types()
    {
        $actor = $this->admin(); $url = 'petugas/master/uttp';
        if ($this->input->method() === 'post') {
            $id = (int) $this->input->post('id');
            $existing = $id ? $this->db->get_where('uttp_types',array('id'=>$id))->row() : NULL;
            if ($id && !$existing) show_404();
            $code = $existing ? $existing->type_code : strtoupper(trim((string) $this->input->post('type_code',TRUE)));
            $name = trim((string) $this->input->post('type_name',TRUE));
            $desc = trim((string) $this->input->post('description',TRUE));
            $active = $this->input->post('is_active') ? 1 : 0;
            if (!$this->code($code) || mb_strlen($name) < 2 || mb_strlen($name) > 160 || mb_strlen($desc) > 1000) {
                $this->fail('Periksa kode, nama, dan deskripsi UTTP.',$url); return;
            }
            if (!$existing && $this->db->get_where('uttp_types',array('type_code'=>$code))->row()) { $this->fail('Kode UTTP sudah digunakan.',$url); return; }
            if (!$active && $existing && $existing->is_active && $this->db->where('is_active',1)->count_all_results('uttp_types') <= 1) {
                $this->fail('Minimal satu jenis UTTP harus tetap aktif.',$url); return;
            }
            $values = array('type_code'=>$code,'type_name'=>$name,'description'=>$desc ?: NULL,
                'requires_capacity'=>$this->input->post('requires_capacity') ? 1 : 0,
                'sort_order'=>$this->integer('sort_order'),'is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s'));
            if ($existing) $this->db->where('id',$existing->id)->update('uttp_types',$values); else $this->db->insert('uttp_types',$values);
            if ($this->db->error()['code']) { $this->fail('Gagal menyimpan jenis UTTP.',$url); return; }
            $this->saved($actor,'uttp_types',$existing ? $existing->id : $this->db->insert_id(),$url);
            return;
        }
        list($rows,$pagination)=$this->paged($this->master->types(FALSE));
        $this->page('staff/master/types',array('title'=>'Master jenis UTTP','rows'=>$rows,'pagination'=>$pagination,
            'active_count'=>$this->db->where('is_active',1)->count_all_results('uttp_types')));
    }

    public function districts()
    {
        $actor = $this->admin(); $url = 'petugas/master/kecamatan';
        if ($this->input->method() === 'post') {
            $id=(int)$this->input->post('id'); $existing=$id?$this->master->district($id,FALSE):NULL;
            if ($id && !$existing) show_404();
            $code=$existing?$existing->district_code:trim((string)$this->input->post('district_code',TRUE));
            $name=trim((string)$this->input->post('district_name',TRUE));
            if (!$this->code($code,24) || mb_strlen($name)<2 || mb_strlen($name)>120) { $this->fail('Periksa kode dan nama kecamatan.',$url); return; }
            if (!$existing && $this->db->get_where('master_districts',array('district_code'=>$code))->row()) { $this->fail('Kode kecamatan sudah digunakan.',$url); return; }
            $active=$this->input->post('is_active')?1:0;
            if (!$active && $existing && $existing->is_active && $this->db->where('is_active',1)->count_all_results('master_districts')<=1) {
                $this->fail('Minimal satu kecamatan harus tetap aktif.',$url); return;
            }
            $values=array('district_code'=>$code,'district_name'=>$name,'sort_order'=>$this->integer('sort_order'),
                'is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s'));
            if ($existing) $this->db->where('id',$existing->id)->update('master_districts',$values); else $this->db->insert('master_districts',$values);
            if ($this->db->error()['code']) { $this->fail('Gagal menyimpan kecamatan.',$url); return; }
            $this->saved($actor,'master_districts',$existing?$existing->id:$this->db->insert_id(),$url);
            return;
        }
        list($rows,$pagination)=$this->paged($this->master->districts(FALSE));
        $this->page('staff/master/districts',array('title'=>'Master kecamatan','rows'=>$rows,'pagination'=>$pagination));
    }

    public function villages()
    {
        $actor = $this->admin(); $url = 'petugas/master/desa';
        if ($this->input->method() === 'post') {
            $id=(int)$this->input->post('id'); $existing=$id?$this->master->village($id,FALSE):NULL;
            if ($id && !$existing) show_404();
            $district_id=(int)$this->input->post('district_id');
            $district=$this->master->district($district_id,FALSE);
            $code=$existing?$existing->village_code:trim((string)$this->input->post('village_code',TRUE));
            $name=trim((string)$this->input->post('village_name',TRUE));
            if (!$district || !$this->code($code,32) || mb_strlen($name)<2 || mb_strlen($name)>120) { $this->fail('Periksa kecamatan, kode, dan nama desa.',$url); return; }
            if (!$existing && $this->db->get_where('master_villages',array('village_code'=>$code))->row()) { $this->fail('Kode desa sudah digunakan.',$url); return; }
            if ($existing && (int)$existing->district_id!==$district_id &&
                ($this->db->where('village_id',$id)->count_all_results('applicant_profiles') || $this->db->where('village_id',$id)->count_all_results('service_requests') || $this->db->where('village_id',$id)->count_all_results('master_locations'))) {
                $this->fail('Desa yang sudah digunakan tidak dapat dipindah kecamatan.',$url); return;
            }
            $active=$this->input->post('is_active')?1:0;
            if (!$active && $existing && $existing->is_active && $district->is_active
                && $this->db->where('district_id',$existing->district_id)->where('is_active',1)->count_all_results('master_villages')<=1) {
                $this->fail('Minimal satu desa dalam kecamatan aktif harus tetap tersedia.',$url); return;
            }
            $values=array('district_id'=>$district_id,'village_code'=>$code,'village_name'=>$name,'sort_order'=>$this->integer('sort_order'),
                'is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s'));
            if ($existing) $this->db->where('id',$existing->id)->update('master_villages',$values); else $this->db->insert('master_villages',$values);
            if ($this->db->error()['code']) { $this->fail('Gagal menyimpan desa.',$url); return; }
            $this->saved($actor,'master_villages',$existing?$existing->id:$this->db->insert_id(),$url.'?district_id='.$district_id);
            return;
        }
        $district_id=(int)$this->input->get('district_id');
        $districts=$this->master->districts(FALSE);
        if (!$district_id && $districts) $district_id=(int)$districts[0]->id;
        $query=mb_substr(trim((string)$this->input->get('q',TRUE)),0,100);
        list($rows,$pagination)=$this->paged($district_id?$this->master->villages($district_id,$query,FALSE,NULL):array());
        $this->page('staff/master/villages',array('title'=>'Master desa / kelurahan','rows'=>$rows,'pagination'=>$pagination,'districts'=>$districts,'district_id'=>$district_id,'query'=>$query));
    }

    public function locations()
    {
        $actor = $this->admin(); $url = 'petugas/master/lokasi';
        if ($this->input->method() === 'post') {
            $id=(int)$this->input->post('id');$existing=$id?$this->master->location($id,FALSE):NULL;
            if ($id && !$existing) show_404();
            $code=$existing?$existing->location_code:strtoupper(trim((string)$this->input->post('location_code',TRUE)));
            $name=trim((string)$this->input->post('location_name',TRUE));
            $address=trim((string)$this->input->post('address',TRUE));
            $district_id=(int)$this->input->post('district_id');$village_id=(int)$this->input->post('village_id');
            $region_ok = (!$district_id && !$village_id) || $this->master->profile_region($district_id,$village_id)
                || ($existing && $district_id===(int)$existing->district_id && $village_id===(int)$existing->village_id);
            if (!$this->code($code) || mb_strlen($name)<3 || mb_strlen($name)>160 || mb_strlen($address)<10 || mb_strlen($address)>2000
                || !$region_ok) {
                $this->fail('Periksa nama, alamat, kecamatan, dan desa lokasi.',$url); return;
            }
            if (!$existing && $this->db->get_where('master_locations',array('location_code'=>$code))->row()) { $this->fail('Kode lokasi sudah digunakan.',$url); return; }
            $active=$this->input->post('is_active')?1:0;
            if (!$active && $existing && $this->db->where('service_location_id',$existing->id)->where('is_active',1)->count_all_results('master_options')) {
                $this->fail('Lokasi masih dipakai oleh tempat pelayanan aktif. Nonaktifkan pilihan tempat terlebih dahulu.',$url); return;
            }
            $values=array('location_code'=>$code,'location_name'=>$name,'address'=>$address,'district_id'=>$district_id?:NULL,
                'village_id'=>$village_id?:NULL,'sort_order'=>$this->integer('sort_order'),'is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s'));
            if ($existing) $this->db->where('id',$existing->id)->update('master_locations',$values); else $this->db->insert('master_locations',$values);
            if ($this->db->error()['code']) { $this->fail('Gagal menyimpan lokasi.',$url); return; }
            $this->saved($actor,'master_locations',$existing?$existing->id:$this->db->insert_id(),$url);
            return;
        }
        list($rows,$pagination)=$this->paged($this->master->locations(FALSE));
        $village_names=array();
        foreach ($rows as $row) if ($row->village_id) { $v=$this->master->village($row->village_id,FALSE);if($v)$village_names[$row->id]=$v->village_name; }
        $this->page('staff/master/locations',array('title'=>'Master lokasi dan alamat','rows'=>$rows,'pagination'=>$pagination,
            'districts'=>$this->master->districts(FALSE),'village_names'=>$village_names));
    }

    public function roles()
    {
        $actor = $this->admin(); $url='petugas/master/peran';
        if ($this->input->method() === 'post') {
            $id=(int)$this->input->post('id');$row=$this->db->get_where('roles',array('id'=>$id))->row();
            if (!$row) show_404();
            $name=trim((string)$this->input->post('role_name',TRUE));
            $description=trim((string)$this->input->post('description',TRUE));
            $active=$this->input->post('is_active')?1:0;
            if (mb_strlen($name)<3 || mb_strlen($name)>100 || mb_strlen($description)>255) { $this->fail('Periksa nama dan deskripsi peran.',$url); return; }
            if (in_array($row->role_code,array('administrator','applicant'),TRUE)) $active=1;
            $this->db->where('id',$id)->update('roles',array('role_name'=>$name,'description'=>$description?:NULL,
                'sort_order'=>$this->integer('sort_order'),'is_active'=>$active));
            $this->saved($actor,'roles',$id,$url);
            return;
        }
        list($rows,$pagination)=$this->paged($this->db->order_by('sort_order')->get('roles')->result());
        $this->page('staff/master/roles',array('title'=>'Master peran','rows'=>$rows,'pagination'=>$pagination));
    }
}
