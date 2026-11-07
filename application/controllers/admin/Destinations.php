<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Destinations extends Admin_Controller
{
    public function index()
    {
        $this->need('destination.view');
        $rows = $this->db->query('SELECT d.*, dc.name AS category_name FROM destinations d LEFT JOIN destination_categories dc ON dc.id = d.category_id WHERE d.deleted_at IS NULL ORDER BY d.id DESC')->result();
        $this->render('admin/destinations', ['rows' => $rows], 'Destinasi');
    }

    public function form($id = null)
    {
        $this->need($id ? 'destination.update' : 'destination.create');
        $row = $id ? $this->db->get_where('destinations', ['id' => (int)$id, 'deleted_at' => null])->row() : null;
        if ($id && !$row) { show_404(); }
        $media = $id ? $this->db->query('SELECT * FROM destination_media WHERE destination_id = ? ORDER BY sort_order, id', [(int)$id])->result() : [];
        $cats = $this->db->query("SELECT id, name FROM destination_categories WHERE status = 'active' ORDER BY sort_order")->result();
        $this->render('admin/destination_form', ['row' => $row, 'cats' => $cats, 'media' => $media], $row ? 'Ubah Destinasi' : 'Tambah Destinasi');
    }

    public function save($id = null)
    {
        $this->need($id ? 'destination.update' : 'destination.create'); $this->post_only();
        $this->form_validation->set_rules('name', 'Nama', 'required|max_length[150]');
        $this->form_validation->set_rules('scope', 'Cakupan', 'required|in_list[local,international,hajj_umrah]');
        if (!$this->form_validation->run()) { $this->go($id ? "admin/destinations/form/$id" : 'admin/destinations/form', 'error', strip_tags(validation_errors(' ', ' '))); }
        $i = $this->input;
        $fac = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$i->post('facilities')))));
        $lat = $i->post('latitude') !== '' && is_numeric($i->post('latitude')) ? (float)$i->post('latitude') : null;
        $lng = $i->post('longitude') !== '' && is_numeric($i->post('longitude')) ? (float)$i->post('longitude') : null;
        if (($lat !== null && abs($lat) > 90) || ($lng !== null && abs($lng) > 180)) { $this->go('admin/destinations', 'error', 'Koordinat tidak valid.'); }
        $status = in_array($i->post('status'), ['draft', 'pending', 'published', 'archived'], true) ? $i->post('status') : 'draft';
        $row = [
            'category_id' => (int)$i->post('category_id') ?: null, 'scope' => $i->post('scope'), 'name' => trim($i->post('name')),
            'description' => $i->post('description') ?: null, 'country' => trim($i->post('country')) ?: 'Indonesia', 'province' => trim($i->post('province')) ?: null,
            'city' => trim($i->post('city')) ?: null, 'address' => $i->post('address') ?: null, 'latitude' => $lat, 'longitude' => $lng,
            'google_maps_url' => trim($i->post('google_maps_url')) ?: null, 'facilities' => $fac ? json_encode($fac, JSON_UNESCAPED_UNICODE) : null,
            'additional_info' => $i->post('additional_info') ?: null, 'is_popular' => $i->post('is_popular') ? 1 : 0, 'status' => $status,
        ];
        $this->load->library('uploader');
        if (!empty($_FILES['cover']['name'])) {
            $f = $this->uploader->save('cover', 'destinations', false, 'jpg|jpeg|png|webp', 5120);
            if (!$f) { $this->go('admin/destinations', 'error', $this->uploader->error); }
            $row['cover_path'] = $f['path'];
        }
        if ($id) {
            $this->db->where('id', (int)$id)->update('destinations', $row);
        } else {
            $slug = slugify($row['name']);
            if ($this->db->get_where('destinations', ['slug' => $slug])->row()) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }
            $row['slug'] = $slug;
            $this->db->insert('destinations', $row);
            $id = $this->db->insert_id();
        }
        // galeri (multi upload)
        if (!empty($_FILES['gallery']['name'][0])) {
            $n = count($_FILES['gallery']['name']); $orig = $_FILES['gallery'];
            for ($k = 0; $k < min($n, 10); $k++) {
                if ($orig['error'][$k] !== UPLOAD_ERR_OK) { continue; }
                $_FILES['g_one'] = ['name' => $orig['name'][$k], 'type' => $orig['type'][$k], 'tmp_name' => $orig['tmp_name'][$k], 'error' => $orig['error'][$k], 'size' => $orig['size'][$k]];
                $f = $this->uploader->save('g_one', 'destinations', false, 'jpg|jpeg|png|webp', 5120);
                if ($f) { $this->db->insert('destination_media', ['destination_id' => (int)$id, 'file_path' => $f['path'], 'sort_order' => $k]); }
            }
        }
        audit_log('destination.save', 'destinations', (int)$id, null, ['name' => $row['name'], 'status' => $status], $this->me->id);
        $this->go('admin/destinations', 'success', 'Destinasi disimpan.');
    }

    public function media_delete($mid)
    {
        $this->need('destination.update'); $this->post_only();
        $m = $this->db->get_where('destination_media', ['id' => (int)$mid])->row(); if (!$m) { show_404(); }
        $this->db->delete('destination_media', ['id' => $m->id]);
        $f = realpath(FCPATH . 'uploads/public/' . $m->file_path);
        if ($f && strpos($f, realpath(FCPATH . 'uploads/public')) === 0) { @unlink($f); }
        $this->go('admin/destinations/form/' . $m->destination_id, 'success', 'Foto dihapus.');
    }

    public function remove($id)
    {
        $this->need('destination.delete'); $this->post_only();
        $used = $this->db->query('SELECT COUNT(*) c FROM package_destinations WHERE destination_id = ?', [(int)$id])->row()->c;
        if ($used) { $this->go('admin/destinations', 'error', 'Destinasi dipakai oleh paket. Arsipkan saja (ubah status).'); }
        $this->db->where('id', (int)$id)->update('destinations', ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'archived']);
        $this->go('admin/destinations', 'success', 'Destinasi dihapus.');
    }
}
