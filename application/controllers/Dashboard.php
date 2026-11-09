<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
	public function index()
	{
		$this->page('dashboard/index', array('title' => 'Layanan tera dan tera ulang'));
	}
}
