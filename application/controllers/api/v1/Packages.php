<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Packages extends API_Controller
{
    public function __construct() { parent::__construct(); $this->load->model('catalog_model'); }

    public function index()
    {
        list($page, $per, $off) = $this->paging(20);
        list($rows, $total) = $this->catalog_model->search_packages($this->input->get(), $per, $off);
        api_ok(array_map([$this->catalog_model, 'format_package'], $rows), 'OK', api_meta($page, $per, $total));
    }

    public function show($id)
    {
        $u = $this->optional_auth();
        $d = $this->catalog_model->package_detail($id, $u ? $u->id : null);
        if (!$d) { api_error('Paket tidak ditemukan.', 404); }
        api_ok($d);
    }

    public function reviews($id)
    {
        list($page, $per, $off) = $this->paging(10);
        $total = (int)$this->db->query("SELECT COUNT(*) c FROM reviews WHERE package_id = ? AND status = 'published'", [(int)$id])->row()->c;
        $rows = $this->db->query(
            "SELECT r.id, r.rating, r.title, r.comment, r.partner_reply, r.created_at, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id
              WHERE r.package_id = ? AND r.status = 'published' ORDER BY r.id DESC LIMIT $per OFFSET $off", [(int)$id])->result();
        foreach ($rows as $r) {   // samarkan nama
            $parts = explode(' ', trim($r->full_name));
            $r->full_name = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '');
        }
        api_ok($rows, 'OK', api_meta($page, $per, $total));
    }

    public function partner($id)
    {
        $p = $this->db->query(
            "SELECT id, name, logo_path, cover_path, description, city, province, website, whatsapp, rating_avg, rating_count, ppiu_number, pihk_number
               FROM travel_partners WHERE id = ? AND status = 'active' AND verification_status = 'verified' AND deleted_at IS NULL", [(int)$id])->row();
        if (!$p) { api_error('Travel tidak ditemukan.', 404); }
        $p->logo_url = media_url($p->logo_path); $p->cover_url = media_url($p->cover_path);
        unset($p->logo_path, $p->cover_path);
        list($rows) = $this->catalog_model->search_packages(['partner_id' => $id], 20, 0);
        $p->packages = array_map([$this->catalog_model, 'format_package'], $rows);
        api_ok($p);
    }
}
