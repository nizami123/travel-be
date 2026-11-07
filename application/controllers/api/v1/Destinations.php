<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Destinations extends API_Controller
{
    public function __construct() { parent::__construct(); $this->load->model('catalog_model'); }

    public function index()
    {
        list($page, $per, $off) = $this->paging(20);
        list($rows, $total) = $this->catalog_model->search_destinations($this->input->get(), $per, $off);
        api_ok(array_map([$this->catalog_model, 'format_destination'], $rows), 'OK', api_meta($page, $per, $total));
    }

    public function show($id)
    {
        $d = $this->catalog_model->destination_detail($id);
        if (!$d) { api_error('Destinasi tidak ditemukan.', 404); }
        list($pk) = $this->catalog_model->search_packages(['destination_id' => $id, 'sort' => 'popular'], 10, 0);
        $d['packages'] = array_map([$this->catalog_model, 'format_package'], $pk);
        api_ok($d);
    }
}
