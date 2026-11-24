<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Regions extends MY_Controller
{
    public function villages()
    {
        if ($this->input->method() !== 'get') show_error('Metode tidak diizinkan.',405);
        $district_id = (int) $this->input->get('district_id');
        $query = mb_substr(trim((string) $this->input->get('q', TRUE)), 0, 100);
        $district = $this->master->district($district_id);
        $rows = $district ? $this->master->villages($district->id, $query, TRUE, 30) : array();
        $results = array_map(function ($row) { return array('id'=>(int) $row->id, 'text'=>$row->village_name); }, $rows);
        $this->output->set_content_type('application/json','utf-8')
            ->set_header('Cache-Control: no-store, private')
            ->set_output(json_encode(array('results'=>$results), JSON_UNESCAPED_UNICODE));
    }
}
