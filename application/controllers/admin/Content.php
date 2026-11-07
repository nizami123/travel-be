<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** CRUD generik: banner, artikel, FAQ. */
class Content extends Admin_Controller
{
    protected function defs()
    {
        return [
            'banners' => ['title' => 'Banner', 'table' => 'banners', 'list' => ['title' => 'Judul', 'action_type' => 'Aksi', 'sort_order' => 'Urut', 'status' => 'Status'],
                'fields' => [
                    ['title', 'Judul', 'text', true], ['subtitle', 'Subjudul', 'text'], ['image_path', 'Gambar', 'image', false, 'banners'],
                    ['action_type', 'Jenis aksi', 'select', true, ['none', 'package', 'destination', 'promotion', 'article', 'url']], ['action_value', 'Nilai aksi (ID / URL)', 'text'],
                    ['cta_label', 'Label tombol', 'text'], ['sort_order', 'Urutan', 'number'], ['starts_at', 'Mulai', 'datetime'], ['ends_at', 'Selesai', 'datetime'],
                    ['status', 'Status', 'select', true, ['active', 'inactive']]]],
            'articles' => ['title' => 'Artikel Wisata', 'table' => 'articles', 'soft' => true, 'list' => ['title' => 'Judul', 'status' => 'Status', 'published_at' => 'Terbit'],
                'fields' => [
                    ['title', 'Judul', 'text', true], ['excerpt', 'Ringkasan', 'text'], ['content', 'Isi', 'textarea', true], ['cover_path', 'Cover', 'image', false, 'banners'],
                    ['tags', 'Tag', 'text'], ['status', 'Status', 'select', true, ['draft', 'published', 'archived']]]],
            'faqs' => ['title' => 'FAQ', 'table' => 'faqs', 'list' => ['category' => 'Kategori', 'question' => 'Pertanyaan', 'status' => 'Status'],
                'fields' => [
                    ['category', 'Kategori', 'text', true], ['question', 'Pertanyaan', 'text', true], ['answer', 'Jawaban', 'textarea', true],
                    ['sort_order', 'Urutan', 'number'], ['status', 'Status', 'select', true, ['active', 'inactive']]]],
        ];
    }

    protected function def($e)
    {
        $d = $this->defs();
        if (!isset($d[$e])) { show_404(); }
        return $d[$e];
    }

    public function index($e = 'banners')
    {
        $this->need('content.manage');
        $d = $this->def($e);
        $where = !empty($d['soft']) ? ' WHERE deleted_at IS NULL' : '';
        $rows = $this->db->query('SELECT * FROM ' . $d['table'] . $where . ' ORDER BY id DESC LIMIT 1000')->result();
        $this->render('admin/content_list', ['e' => $e, 'd' => $d, 'rows' => $rows], $d['title']);
    }

    public function form($e, $id = null)
    {
        $this->need('content.manage');
        $d = $this->def($e);
        $row = $id ? $this->db->get_where($d['table'], ['id' => (int)$id])->row() : null;
        if ($id && !$row) { show_404(); }
        $this->render('admin/content_form', ['e' => $e, 'd' => $d, 'row' => $row], ($row ? 'Ubah ' : 'Tambah ') . $d['title']);
    }

    public function save($e, $id = null)
    {
        $this->need('content.manage'); $this->post_only();
        $d = $this->def($e);
        $data = [];
        $this->load->library('uploader');
        foreach ($d['fields'] as $f) {
            list($name, $label, $type) = $f; $required = !empty($f[3]);
            if ($type === 'image') {
                if (!empty($_FILES[$name]['name'])) {
                    $u = $this->uploader->save($name, $f[4], false, 'jpg|jpeg|png|webp', 5120);
                    if (!$u) { $this->go("admin/content/index/$e", 'error', $this->uploader->error); }
                    $data[$name] = $u['path'];
                }
                continue;
            }
            $v = $this->input->post($name);
            if ($required && trim((string)$v) === '') { $this->go("admin/content/index/$e", 'error', "$label wajib diisi."); }
            if ($type === 'select' && !in_array($v, $f[4], true)) { $this->go("admin/content/index/$e", 'error', "$label tidak valid."); }
            if ($type === 'number') { $v = (int)$v; }
            if ($type === 'datetime') { $v = $v ? date('Y-m-d H:i:s', strtotime($v)) : null; }
            $data[$name] = ($v === '' ? null : $v);
        }
        if ($e === 'banners' && !$id && empty($data['image_path'])) { $this->go("admin/content/index/$e", 'error', 'Gambar banner wajib.'); }
        if ($e === 'articles') {
            $data['author_id'] = $this->me->id;
            if (!$id) {
                $slug = slugify($data['title']);
                if ($this->db->get_where('articles', ['slug' => $slug])->row()) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }
                $data['slug'] = $slug;
            }
            if (($data['status'] ?? '') === 'published') { $data['published_at'] = date('Y-m-d H:i:s'); }
        }
        if ($id) { $this->db->where('id', (int)$id)->update($d['table'], $data); }
        else { $this->db->insert($d['table'], $data); }
        $this->go("admin/content/index/$e", 'success', $d['title'] . ' disimpan.');
    }

    public function remove($e, $id)
    {
        $this->need('content.manage'); $this->post_only();
        $d = $this->def($e);
        if (!empty($d['soft'])) { $this->db->where('id', (int)$id)->update($d['table'], ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'archived']); }
        else { $this->db->delete($d['table'], ['id' => (int)$id]); }
        $this->go("admin/content/index/$e", 'success', 'Dihapus.');
    }
}
