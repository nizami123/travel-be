<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Trait CRUD promo, dipakai Admin (promo platform) dan Partner (promo milik travel).
 * Host wajib punya: $this->promo_scope_partner_id (null = platform) & view 'shared/promotions'.
 */
trait Promo_manager
{
    protected function promo_where()
    {
        return $this->promo_scope_partner_id === null ? ['sql' => 'pr.created_by_partner_id IS NULL', 'p' => []] : ['sql' => 'pr.created_by_partner_id = ?', 'p' => [$this->promo_scope_partner_id]];
    }

    protected function promo_packages_options()
    {
        if ($this->promo_scope_partner_id === null) {
            return $this->db->query("SELECT p.id, CONCAT(p.name,' - ',tp.name) AS name FROM travel_packages p JOIN travel_partners tp ON tp.id = p.travel_partner_id WHERE p.deleted_at IS NULL AND p.approval_status = 'approved' ORDER BY tp.name, p.name")->result();
        }
        return $this->db->query("SELECT id, name FROM travel_packages WHERE travel_partner_id = ? AND deleted_at IS NULL ORDER BY name", [$this->promo_scope_partner_id])->result();
    }

    public function index()
    {
        $this->need('promotion.manage');
        $w = $this->promo_where();
        $rows = $this->db->query('SELECT pr.* FROM promotions pr WHERE ' . $w['sql'] . ' ORDER BY pr.id DESC', $w['p'])->result();
        $this->render('shared/promotions', ['rows' => $rows, 'base' => $this->area . '/promotions'], 'Promo');
    }

    public function form($id = null)
    {
        $this->need('promotion.manage');
        $row = null; $sel = [];
        if ($id) {
            $w = $this->promo_where();
            $row = $this->db->query('SELECT pr.* FROM promotions pr WHERE pr.id = ? AND ' . $w['sql'], array_merge([(int)$id], $w['p']))->row();
            if (!$row) { show_404(); }
            $sel = array_map(function ($r) { return (int)$r->package_id; }, $this->db->get_where('promotion_packages', ['promotion_id' => $row->id])->result());
        }
        $this->render('shared/promotion_form', ['row' => $row, 'sel' => $sel, 'packages' => $this->promo_packages_options(), 'base' => $this->area . '/promotions'], $row ? 'Ubah Promo' : 'Tambah Promo');
    }

    public function save($id = null)
    {
        $this->need('promotion.manage'); $this->post_only();
        $i = $this->input;
        $type = $i->post('discount_type');
        $val = (float)$i->post('discount_value');
        $code = strtoupper(trim((string)$i->post('code')));
        $starts = strtotime((string)$i->post('starts_at')); $ends = strtotime((string)$i->post('ends_at'));
        $err = null;
        if (trim((string)$i->post('title')) === '') { $err = 'Judul wajib diisi.'; }
        elseif (!in_array($type, ['percent', 'fixed'], true) || $val <= 0 || ($type === 'percent' && $val > 100)) { $err = 'Nilai diskon tidak valid.'; }
        elseif (!$starts || !$ends || $ends <= $starts) { $err = 'Periode promo tidak valid.'; }
        elseif ($code !== '' && !preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) { $err = 'Kode promo hanya huruf/angka/-/_ (3-40).'; }
        if ($err) { $this->go($this->area . '/promotions', 'error', $err); }
        if ($code !== '') {
            $dup = $this->db->query('SELECT id FROM promotions WHERE code = ? AND id <> ?', [$code, (int)$id])->row();
            if ($dup) { $this->go($this->area . '/promotions', 'error', 'Kode promo sudah dipakai.'); }
        }
        $pkgs = array_map('intval', (array)$i->post('packages'));
        $allowed = array_map(function ($r) { return (int)$r->id; }, $this->promo_packages_options());
        $pkgs = array_values(array_intersect($pkgs, $allowed));       // hanya paket yang boleh dipromokan
        $applies_all = $i->post('applies_to_all') ? 1 : 0;
        if (!$applies_all && !$pkgs) { $this->go($this->area . '/promotions', 'error', 'Pilih paket atau centang "semua paket".'); }
        $status = in_array($i->post('status'), ['draft', 'active', 'inactive'], true) ? $i->post('status') : 'draft';
        $row = [
            'created_by_partner_id' => $this->promo_scope_partner_id, 'code' => $code !== '' ? $code : null,
            'title' => trim($i->post('title')), 'description' => $i->post('description') ?: null,
            'discount_type' => $type, 'discount_value' => $val, 'max_discount' => $i->post('max_discount') !== '' ? max(0, (float)$i->post('max_discount')) : null,
            'min_purchase' => max(0, (float)$i->post('min_purchase')), 'quota' => $i->post('quota') !== '' ? max(0, (int)$i->post('quota')) : null,
            'per_user_limit' => max(1, (int)$i->post('per_user_limit') ?: 1),
            'funded_by' => $this->promo_scope_partner_id === null ? (in_array($i->post('funded_by'), ['platform', 'partner'], true) ? $i->post('funded_by') : 'platform') : 'partner',
            'applies_to_all' => $applies_all, 'starts_at' => date('Y-m-d H:i:s', $starts), 'ends_at' => date('Y-m-d H:i:s', $ends), 'status' => $status,
        ];
        $this->db->trans_begin();
        if ($id) {
            $w = $this->promo_where();
            $own = $this->db->query('SELECT id FROM promotions pr WHERE pr.id = ? AND ' . $w['sql'], array_merge([(int)$id], $w['p']))->row();
            if (!$own) { $this->db->trans_rollback(); show_404(); }
            unset($row['created_by_partner_id']);
            $this->db->where('id', (int)$id)->update('promotions', $row);
        } else {
            $this->db->insert('promotions', $row); $id = $this->db->insert_id();
        }
        $this->db->delete('promotion_packages', ['promotion_id' => (int)$id]);
        foreach ($pkgs as $pk) { $this->db->insert('promotion_packages', ['promotion_id' => (int)$id, 'package_id' => $pk]); }
        $this->db->trans_commit();
        audit_log('promotion.save', 'promotions', (int)$id, null, ['title' => $row['title'], 'status' => $status], $this->me->id, $this->promo_scope_partner_id);
        $this->go($this->area . '/promotions', 'success', 'Promo disimpan.');
    }

    public function toggle($id)
    {
        $this->need('promotion.manage'); $this->post_only();
        $w = $this->promo_where();
        $r = $this->db->query('SELECT pr.* FROM promotions pr WHERE pr.id = ? AND ' . $w['sql'], array_merge([(int)$id], $w['p']))->row();
        if (!$r) { show_404(); }
        $this->db->where('id', $r->id)->update('promotions', ['status' => $r->status === 'active' ? 'inactive' : 'active']);
        $this->go($this->area . '/promotions', 'success', 'Status promo diperbarui.');
    }
}
