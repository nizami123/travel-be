<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Packages extends Partner_Controller
{
    public function index()
    {
        $this->need('package.view');
        $rows = $this->db->query(
            "SELECT p.*, pc.name AS category_name, (SELECT COUNT(*) FROM package_schedules s WHERE s.package_id = p.id AND s.status = 'open') AS open_sched
               FROM travel_packages p JOIN package_categories pc ON pc.id = p.category_id
              WHERE p.travel_partner_id = ? AND p.deleted_at IS NULL ORDER BY p.id DESC", [$this->partner_id])->result();
        $this->render('partner/packages', ['rows' => $rows], 'Paket Perjalanan');
    }

    public function form($id = null)
    {
        $this->need($id ? 'package.update' : 'package.create');
        $row = null; $d = ['itin' => [], 'inc' => [], 'exc' => [], 'dest' => [], 'hu' => null, 'media' => []];
        if ($id) {
            $row = $this->own_package($id);
            $d['itin'] = $this->db->query('SELECT * FROM package_itineraries WHERE package_id = ? ORDER BY day_number', [$row->id])->result();
            $fac = $this->db->query('SELECT * FROM package_facilities WHERE package_id = ? ORDER BY sort_order, id', [$row->id])->result();
            foreach ($fac as $f) { if ($f->facility_type === 'included') { $d['inc'][] = $f->name; } else { $d['exc'][] = $f->name; } }
            $d['dest'] = array_map(function ($r) { return (int)$r->destination_id; }, $this->db->get_where('package_destinations', ['package_id' => $row->id])->result());
            $d['hu'] = $this->db->get_where('package_hajj_umrah_details', ['package_id' => $row->id])->row();
            $d['media'] = $this->db->query('SELECT * FROM package_media WHERE package_id = ? ORDER BY sort_order, id', [$row->id])->result();
        }
        $d['row'] = $row;
        $d['cats'] = $this->db->query("SELECT id, type, name FROM package_categories WHERE status = 'active' ORDER BY sort_order")->result();
        $d['dests'] = $this->db->query("SELECT id, CONCAT(name, ' (', COALESCE(city,country), ')') AS name FROM destinations WHERE status = 'published' AND deleted_at IS NULL ORDER BY name")->result();
        $this->render('partner/package_form', $d, $row ? 'Ubah Paket' : 'Tambah Paket');
    }

    public function save($id = null)
    {
        $this->need($id ? 'package.update' : 'package.create'); $this->post_only();
        $existing = $id ? $this->own_package($id) : null;
        $i = $this->input;
        $this->form_validation->set_rules('name', 'Nama paket', 'required|max_length[190]');
        $this->form_validation->set_rules('category_id', 'Kategori', 'required|integer');
        $this->form_validation->set_rules('duration_days', 'Durasi (hari)', 'required|integer|greater_than[0]|less_than[100]');
        $this->form_validation->set_rules('dp_percent', 'DP %', 'required|numeric|greater_than[0]|less_than_equal_to[100]');
        $this->form_validation->set_rules('full_payment_days_before', 'Pelunasan H-', 'required|integer|greater_than_equal_to[0]');
        if (!$this->form_validation->run()) { $this->go($id ? "partner/packages/form/$id" : 'partner/packages/form', 'error', strip_tags(validation_errors(' ', ' '))); }
        $cat = $this->db->get_where('package_categories', ['id' => (int)$i->post('category_id'), 'status' => 'active'])->row();
        if (!$cat) { $this->go('partner/packages', 'error', 'Kategori tidak valid.'); }

        $docs = array_values(array_intersect((array)$i->post('required_documents'), ['ktp', 'passport', 'kk', 'photo', 'vaccine', 'mahram_letter', 'visa']));
        $days = (int)$i->post('duration_days');
        $row = [
            'category_id' => $cat->id, 'name' => trim($i->post('name')), 'summary' => mb_substr(trim((string)$i->post('summary')), 0, 300) ?: null,
            'description' => $i->post('description') ?: null, 'duration_days' => $days, 'duration_nights' => max(0, (int)$i->post('duration_nights')),
            'start_city' => trim($i->post('start_city')) ?: null, 'min_participants' => max(1, (int)$i->post('min_participants')),
            'max_participants_per_booking' => max(1, min(50, (int)$i->post('max_participants_per_booking') ?: 10)),
            'transportation' => $i->post('transportation') ?: null, 'accommodation' => $i->post('accommodation') ?: null,
            'terms_conditions' => $i->post('terms_conditions') ?: null, 'cancellation_policy' => $i->post('cancellation_policy') ?: null,
            'required_documents' => json_encode($docs), 'dp_percent' => (float)$i->post('dp_percent'),
            'full_payment_days_before' => (int)$i->post('full_payment_days_before'),
            'whatsapp_number' => preg_replace('/\D+/', '', (string)$i->post('whatsapp_number')) ?: null,
        ];
        $this->load->library('uploader');
        if (!empty($_FILES['cover']['name'])) {
            $f = $this->uploader->save('cover', 'packages', false, 'jpg|jpeg|png|webp', 5120);
            if (!$f) { $this->go('partner/packages', 'error', $this->uploader->error); }
            $row['cover_path'] = $f['path'];
        }

        $this->db->trans_begin();
        if ($existing) {
            $this->db->where(['id' => $existing->id, 'travel_partner_id' => $this->partner_id])->update('travel_packages', $row);
            $pid = (int)$existing->id;
        } else {
            $row += ['travel_partner_id' => $this->partner_id, 'code' => 'PKG' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
                     'slug' => 'tmp-' . bin2hex(random_bytes(6)), 'approval_status' => 'draft', 'status' => 'inactive', 'created_by' => $this->me->id];
            $this->db->insert('travel_packages', $row);
            $pid = (int)$this->db->insert_id();
            $this->db->where('id', $pid)->update('travel_packages', ['slug' => slugify($row['name']) . '-' . $pid]);
        }
        // destinasi
        $this->db->delete('package_destinations', ['package_id' => $pid]);
        foreach (array_unique(array_map('intval', (array)$i->post('destinations'))) as $k => $did) {
            if ($this->db->get_where('destinations', ['id' => $did, 'status' => 'published'])->row()) { $this->db->insert('package_destinations', ['package_id' => $pid, 'destination_id' => $did, 'sort_order' => $k]); }
        }
        // itinerary: satu baris per hari "Judul | deskripsi"
        $this->db->delete('package_itineraries', ['package_id' => $pid]);
        $n = 0;
        foreach (preg_split('/\R/', (string)$i->post('itinerary')) as $line) {
            $line = trim($line); if ($line === '') { continue; }
            $parts = array_map('trim', explode('|', $line, 3)); $n++;
            $this->db->insert('package_itineraries', ['package_id' => $pid, 'day_number' => $n, 'title' => mb_substr($parts[0], 0, 190), 'description' => $parts[1] ?? null, 'meals' => isset($parts[2]) ? mb_substr($parts[2], 0, 60) : null]);
        }
        // fasilitas
        $this->db->delete('package_facilities', ['package_id' => $pid]);
        foreach (['included' => 'included', 'excluded' => 'excluded'] as $field => $type) {
            foreach (preg_split('/\R/', (string)$i->post($field)) as $k => $name) {
                $name = trim($name); if ($name === '') { continue; }
                $this->db->insert('package_facilities', ['package_id' => $pid, 'facility_type' => $type, 'name' => mb_substr($name, 0, 150), 'sort_order' => $k]);
            }
        }
        // haji/umroh
        if (in_array($cat->type, ['hajj', 'umrah'], true)) {
            $rt = array_values(array_filter(array_map('trim', explode(',', (string)$i->post('room_types')))));
            $hu = [
                'package_id' => $pid, 'program_type' => in_array($i->post('program_type'), ['umrah_reguler', 'umrah_plus', 'umrah_vip', 'haji_khusus', 'haji_furoda', 'lainnya'], true) ? $i->post('program_type') : 'lainnya',
                'ppiu_pihk_number' => trim($i->post('ppiu_pihk_number')) ?: null, 'ppiu_pihk_name' => trim($i->post('ppiu_pihk_name')) ?: null,
                'airline' => trim($i->post('airline')) ?: null, 'departure_airport' => trim($i->post('departure_airport')) ?: null,
                'hotel_makkah' => trim($i->post('hotel_makkah')) ?: null, 'hotel_makkah_star' => (int)$i->post('hotel_makkah_star') ?: null,
                'hotel_madinah' => trim($i->post('hotel_madinah')) ?: null, 'hotel_madinah_star' => (int)$i->post('hotel_madinah_star') ?: null,
                'mutawwif_name' => trim($i->post('mutawwif_name')) ?: null, 'manasik_info' => $i->post('manasik_info') ?: null,
                'visa_info' => $i->post('visa_info') ?: null, 'room_types' => json_encode($rt), 'extra_facilities' => $i->post('extra_facilities') ?: null,
            ];
            $this->db->query('REPLACE INTO package_hajj_umrah_details SET ' . implode(', ', array_map(function ($k) { return "`$k` = ?"; }, array_keys($hu))), array_values($hu));
        }
        // galeri
        if (!empty($_FILES['gallery']['name'][0])) {
            $orig = $_FILES['gallery'];
            for ($k = 0; $k < min(count($orig['name']), 10); $k++) {
                if ($orig['error'][$k] !== UPLOAD_ERR_OK) { continue; }
                $_FILES['g_one'] = ['name' => $orig['name'][$k], 'type' => $orig['type'][$k], 'tmp_name' => $orig['tmp_name'][$k], 'error' => $orig['error'][$k], 'size' => $orig['size'][$k]];
                $f = $this->uploader->save('g_one', 'packages', false, 'jpg|jpeg|png|webp', 5120);
                if ($f) { $this->db->insert('package_media', ['package_id' => $pid, 'file_path' => $f['path'], 'sort_order' => $k]); }
            }
        }
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); $this->go('partner/packages', 'error', 'Gagal menyimpan paket.'); }
        $this->db->trans_commit();
        audit_log('package.save', 'travel_packages', $pid, null, ['name' => $row['name']], $this->me->id, $this->partner_id);
        $this->go("partner/packages/form/$pid", 'success', 'Paket disimpan. Tambahkan jadwal, harga & kuota, lalu ajukan persetujuan.');
    }

    public function submit($id)
    {
        $this->need('package.update'); $this->post_only(); $this->need_verified();
        $p = $this->own_package($id);
        if (!in_array($p->approval_status, ['draft', 'rejected'], true)) { $this->go('partner/packages', 'error', 'Paket sudah diajukan/disetujui.'); }
        $has = $this->db->query("SELECT COUNT(*) c FROM package_schedules s JOIN schedule_prices sp ON sp.schedule_id = s.id WHERE s.package_id = ? AND s.status IN ('draft','open')", [$p->id])->row()->c;
        if (!$has) { $this->go('partner/packages', 'error', 'Tambahkan minimal satu jadwal dengan harga sebelum mengajukan.'); }
        if (!$p->cover_path) { $this->go('partner/packages', 'error', 'Unggah foto utama paket terlebih dahulu.'); }
        $this->db->where('id', $p->id)->update('travel_packages', ['approval_status' => 'pending', 'approval_note' => null]);
        $this->go('partner/packages', 'success', 'Paket diajukan ke platform untuk disetujui.');
    }

    public function toggle($id)
    {
        $this->need('package.update'); $this->post_only();
        $p = $this->own_package($id);
        if ($p->approval_status !== 'approved') { $this->go('partner/packages', 'error', 'Hanya paket yang disetujui yang dapat diaktif/nonaktifkan.'); }
        $this->db->where(['id' => $p->id, 'travel_partner_id' => $this->partner_id])->update('travel_packages', ['status' => $p->status === 'active' ? 'inactive' : 'active']);
        $this->go('partner/packages', 'success', 'Status paket diperbarui.');
    }

    public function media_delete($mid)
    {
        $this->need('package.update'); $this->post_only();
        $m = $this->db->query('SELECT m.* FROM package_media m JOIN travel_packages p ON p.id = m.package_id WHERE m.id = ? AND p.travel_partner_id = ?', [(int)$mid, $this->partner_id])->row();
        if (!$m) { show_404(); }
        $this->db->delete('package_media', ['id' => $m->id]);
        $f = realpath(FCPATH . 'uploads/public/' . $m->file_path);
        if ($f && strpos($f, realpath(FCPATH . 'uploads/public')) === 0) { @unlink($f); }
        $this->go('partner/packages/form/' . $m->package_id, 'success', 'Foto dihapus.');
    }

    public function remove($id)
    {
        $this->need('package.delete'); $this->post_only();
        $p = $this->own_package($id);
        $active = $this->db->query("SELECT COUNT(*) c FROM bookings WHERE package_id = ? AND status IN ('pending','partially_paid','paid')", [$p->id])->row()->c;
        if ($active) { $this->go('partner/packages', 'error', 'Masih ada booking aktif pada paket ini.'); }
        $this->db->where(['id' => $p->id, 'travel_partner_id' => $this->partner_id])->update('travel_packages', ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'inactive']);
        $this->go('partner/packages', 'success', 'Paket dihapus.');
    }
}
