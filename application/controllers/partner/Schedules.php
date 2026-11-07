<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Jadwal + harga + kuota (satu halaman per paket). */
class Schedules extends Partner_Controller
{
    public function index($package_id)
    {
        $this->need('schedule.manage');
        $p = $this->own_package($package_id);
        $rows = $this->db->query('SELECT s.*, q.quota_total, q.quota_held, q.quota_booked FROM package_schedules s JOIN package_quotas q ON q.schedule_id = s.id WHERE s.package_id = ? ORDER BY s.departure_date DESC', [$p->id])->result();
        foreach ($rows as $r) { $r->prices = $this->db->query('SELECT * FROM schedule_prices WHERE schedule_id = ? ORDER BY is_default DESC, id', [$r->id])->result(); }
        $this->render('partner/schedules', ['p' => $p, 'rows' => $rows], 'Jadwal, Harga & Kuota');
    }

    public function save($package_id)
    {
        $this->need('schedule.manage'); $this->post_only();
        $p = $this->own_package($package_id);
        $i = $this->input;
        $dep = (string)$i->post('departure_date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dep) || $dep <= date('Y-m-d')) { $this->go("partner/schedules/index/{$p->id}", 'error', 'Tanggal berangkat harus di masa depan.'); }
        $ret = (string)$i->post('return_date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ret)) { $ret = date('Y-m-d', strtotime($dep . ' +' . max(0, $p->duration_days - 1) . ' days')); }
        if ($ret < $dep) { $this->go("partner/schedules/index/{$p->id}", 'error', 'Tanggal pulang tidak valid.'); }
        $quota = (int)$i->post('quota_total');
        if ($quota < 1) { $this->go("partner/schedules/index/{$p->id}", 'error', 'Kuota minimal 1.'); }
        // harga: baris "kode | label | harga"  (kode: adult, child, infant, quad, triple, double, single ...)
        $prices = [];
        foreach (preg_split('/\R/', (string)$i->post('prices')) as $line) {
            $line = trim($line); if ($line === '') { continue; }
            $x = array_map('trim', explode('|', $line));
            if (count($x) !== 3 || !preg_match('/^[a-z0-9_]{2,30}$/', $x[0]) || !is_numeric($x[2]) || (float)$x[2] < 0) { $this->go("partner/schedules/index/{$p->id}", 'error', "Format harga salah: \"$line\". Gunakan: kode | label | harga"); }
            $prices[$x[0]] = [$x[1], (float)$x[2]];
        }
        if (!$prices) { $this->go("partner/schedules/index/{$p->id}", 'error', 'Isi minimal satu harga.'); }
        $this->db->trans_begin();
        $this->db->insert('package_schedules', [
            'package_id' => $p->id, 'travel_partner_id' => $this->partner_id, 'departure_date' => $dep, 'return_date' => $ret,
            'departure_note' => mb_substr((string)$i->post('departure_note'), 0, 190) ?: null, 'status' => 'open',
        ]);
        $sid = (int)$this->db->insert_id();
        $first = true;
        foreach ($prices as $code => $v) {
            $this->db->insert('schedule_prices', ['schedule_id' => $sid, 'price_code' => $code, 'label' => mb_substr($v[0], 0, 100), 'price' => $v[1], 'is_default' => $first ? 1 : 0]);
            $first = false;
        }
        $this->db->insert('package_quotas', ['schedule_id' => $sid, 'quota_total' => $quota]);
        $this->refresh_base_price($p->id);
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); $this->go("partner/schedules/index/{$p->id}", 'error', 'Gagal menyimpan jadwal.'); }
        $this->db->trans_commit();
        audit_log('schedule.create', 'package_schedules', $sid, null, ['dep' => $dep, 'quota' => $quota], $this->me->id, $this->partner_id);
        $this->go("partner/schedules/index/{$p->id}", 'success', 'Jadwal ditambahkan.');
    }

    protected function own_schedule($sid)
    {
        $s = $this->db->get_where('package_schedules', ['id' => (int)$sid, 'travel_partner_id' => $this->partner_id])->row();
        if (!$s) { show_404(); }
        return $s;
    }

    /** Ubah kuota total (tidak boleh di bawah yang sudah ditahan + terkonfirmasi) */
    public function quota($sid)
    {
        $this->need('schedule.manage'); $this->post_only();
        $s = $this->own_schedule($sid);
        $new = (int)$this->input->post('quota_total');
        $this->db->trans_begin();
        $q = $this->db->query('SELECT * FROM package_quotas WHERE schedule_id = ? FOR UPDATE', [$s->id])->row();
        if ($new < (int)$q->quota_held + (int)$q->quota_booked) {
            $this->db->trans_rollback();
            $this->go("partner/schedules/index/{$s->package_id}", 'error', 'Kuota tidak boleh di bawah ' . ((int)$q->quota_held + (int)$q->quota_booked) . ' (sudah ditahan/terjual).');
        }
        $this->db->where('schedule_id', $s->id)->update('package_quotas', ['quota_total' => $new]);
        $this->db->trans_commit();
        $this->go("partner/schedules/index/{$s->package_id}", 'success', 'Kuota diperbarui.');
    }

    /** Ubah harga (hanya berlaku untuk booking baru; booking lama memakai snapshot) */
    public function prices($sid)
    {
        $this->need('schedule.manage'); $this->post_only();
        $s = $this->own_schedule($sid);
        $this->db->trans_begin();
        foreach ((array)$this->input->post('price') as $pid => $val) {
            if (!is_numeric($val) || (float)$val < 0) { continue; }
            $this->db->query('UPDATE schedule_prices SET price = ? WHERE id = ? AND schedule_id = ?', [(float)$val, (int)$pid, $s->id]);
        }
        $this->refresh_base_price($s->package_id);
        $this->db->trans_commit();
        audit_log('schedule.prices', 'package_schedules', (int)$s->id, null, null, $this->me->id, $this->partner_id);
        $this->go("partner/schedules/index/{$s->package_id}", 'success', 'Harga diperbarui (berlaku untuk booking baru).');
    }

    public function status($sid)
    {
        $this->need('schedule.manage'); $this->post_only();
        $s = $this->own_schedule($sid);
        $st = $this->input->post('status');
        if (!in_array($st, ['open', 'closed', 'cancelled'], true)) { show_error('Status tidak valid', 400); }
        if ($st === 'cancelled') {
            $act = $this->db->query("SELECT COUNT(*) c FROM bookings WHERE schedule_id = ? AND status IN ('pending','partially_paid','paid')", [$s->id])->row()->c;
            if ($act) { $this->go("partner/schedules/index/{$s->package_id}", 'error', 'Masih ada booking aktif. Batalkan booking terlebih dahulu.'); }
        }
        $this->db->where('id', $s->id)->update('package_schedules', ['status' => $st]);
        $this->refresh_base_price($s->package_id);
        $this->go("partner/schedules/index/{$s->package_id}", 'success', 'Status jadwal diperbarui.');
    }

    /** Cache harga terendah paket (untuk listing) */
    protected function refresh_base_price($package_id)
    {
        $this->db->query(
            "UPDATE travel_packages p SET base_price = COALESCE((SELECT MIN(sp.price) FROM package_schedules s JOIN schedule_prices sp ON sp.schedule_id = s.id
                 WHERE s.package_id = p.id AND s.status = 'open' AND sp.status = 'active' AND sp.price > 0), 0) WHERE p.id = ? AND p.travel_partner_id = ?", [(int)$package_id, $this->partner_id]);
    }
}
