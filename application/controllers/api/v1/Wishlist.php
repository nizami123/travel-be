<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wishlist extends API_Controller
{
    public function __construct() { parent::__construct(); $this->require_auth(); $this->load->model('catalog_model'); }

    public function index()
    {
        $type = $this->input->get('type') === 'destination' ? 'destination' : 'package';
        if ($type === 'package') {
            $ids = array_map(function ($r) { return (int)$r->package_id; }, $this->db->query("SELECT package_id FROM wishlists WHERE user_id = ? AND item_type = 'package' ORDER BY id DESC LIMIT 100", [$this->user->id])->result());
            $out = [];
            foreach ($ids as $pid) {
                $d = $this->catalog_model->package_detail($pid, $this->user->id);
                if ($d) { unset($d['description'], $d['itinerary'], $d['schedules'], $d['media'], $d['terms_conditions']); $out[] = $d; }
            }
            api_ok($out);
        }
        $rows = $this->db->query(
            "SELECT d.id, d.name, d.slug, d.scope, d.country, d.province, d.city, d.cover_path, d.rating_avg, d.rating_count, NULL AS category_name, NULL AS min_price
               FROM wishlists w JOIN destinations d ON d.id = w.destination_id WHERE w.user_id = ? AND w.item_type = 'destination' AND d.status = 'published' ORDER BY w.id DESC", [$this->user->id])->result();
        api_ok(array_map([$this->catalog_model, 'format_destination'], $rows));
    }

    public function toggle()
    {
        $this->validate(['type' => 'required|in_list[package,destination]', 'id' => 'required|integer']);
        $type = $this->in('type'); $id = (int)$this->in('id');
        $col = $type === 'package' ? 'package_id' : 'destination_id';
        $exists = $this->db->get_where('wishlists', ['user_id' => $this->user->id, $col => $id])->row();
        if ($exists) {
            $this->db->delete('wishlists', ['id' => $exists->id]);
            api_ok(['wishlisted' => false], 'Dihapus dari favorit.');
        }
        $tbl = $type === 'package' ? 'travel_packages' : 'destinations';
        if (!$this->db->get_where($tbl, ['id' => $id])->row()) { api_error('Item tidak ditemukan.', 404); }
        $this->db->insert('wishlists', ['user_id' => $this->user->id, 'item_type' => $type, $col => $id]);
        api_ok(['wishlisted' => true], 'Ditambahkan ke favorit.', null, 201);
    }
}
