<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance extends Admin_Controller
{
    public function index()
    {
        $this->need('payment.view');
        $rows = $this->db->query(
            "SELECT tp.id, tp.name,
                    COUNT(DISTINCT CASE WHEN c.status IN ('earned','available','paid_out') THEN c.booking_id END) AS bookings,
                    COALESCE(SUM(CASE WHEN c.status IN ('earned','available','paid_out') THEN c.base_amount END),0) AS gross,
                    COALESCE(SUM(CASE WHEN c.status IN ('earned','available','paid_out') THEN c.commission_amount END),0) AS commission,
                    COALESCE(SUM(CASE WHEN c.status IN ('earned','available','paid_out') THEN c.partner_amount END),0) AS partner_amount,
                    COALESCE(SUM(CASE WHEN c.status = 'paid_out' THEN c.partner_amount END),0) AS paid_out,
                    COALESCE(SUM(CASE WHEN c.status = 'available' THEN c.partner_amount END),0) AS available
               FROM travel_partners tp LEFT JOIN commissions c ON c.travel_partner_id = tp.id
              WHERE tp.deleted_at IS NULL GROUP BY tp.id, tp.name ORDER BY commission DESC")->result();
        $tot = $this->db->query(
            "SELECT (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid') AS received,
                    (SELECT COALESCE(SUM(refund_amount),0) FROM refunds WHERE status = 'completed') AS refunded,
                    (SELECT COALESCE(SUM(net_amount),0) FROM partner_payouts WHERE status = 'paid') AS payouts")->row();
        $this->render('admin/finance', ['rows' => $rows, 'tot' => $tot], 'Keuangan');
    }

    // ------------------------------------------------------------ PENCAIRAN
    public function payouts()
    {
        $this->need('payout.manage');
        $rows = $this->db->query('SELECT po.*, tp.name AS partner_name FROM partner_payouts po JOIN travel_partners tp ON tp.id = po.travel_partner_id ORDER BY FIELD(po.status,"requested","approved","processing") , po.id DESC')->result();
        $this->render('admin/payouts', ['rows' => $rows], 'Pencairan Dana Mitra');
    }

    public function payout_action($id)
    {
        $this->need('payout.manage'); $this->post_only();
        $db = $this->db; $act = $this->input->post('action');
        $db->trans_begin();
        $po = $db->query('SELECT * FROM partner_payouts WHERE id = ? FOR UPDATE', [(int)$id])->row();
        if (!$po) { $db->trans_rollback(); show_404(); }
        $note = mb_substr((string)$this->input->post('note'), 0, 255) ?: null;
        $ok = true;
        if ($act === 'approve' && $po->status === 'requested') {
            $db->where('id', $po->id)->update('partner_payouts', ['status' => 'approved', 'reviewed_by' => $this->me->id, 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => $note]);
        } elseif ($act === 'reject' && in_array($po->status, ['requested', 'approved'], true)) {
            $db->delete('payout_items', ['payout_id' => $po->id]);   // komisi kembali tersedia
            $db->where('id', $po->id)->update('partner_payouts', ['status' => 'rejected', 'reviewed_by' => $this->me->id, 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => $note]);
        } elseif ($act === 'paid' && in_array($po->status, ['approved', 'processing'], true)) {
            $db->query("UPDATE commissions c JOIN payout_items pi ON pi.commission_id = c.id SET c.status = 'paid_out' WHERE pi.payout_id = ?", [$po->id]);
            $path = null;
            if (!empty($_FILES['proof']['name'])) {
                $this->load->library('uploader');
                $f = $this->uploader->save('proof', 'partner', true, 'jpg|jpeg|png|pdf', 5120);
                if ($f) { $path = 'partner/' . basename($f['path']); }
            }
            $db->where('id', $po->id)->update('partner_payouts', ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'), 'transfer_proof_path' => $path, 'review_note' => $note ?: $po->review_note]);
        } else { $ok = false; }
        if (!$ok || $db->trans_status() === false) { $db->trans_rollback(); $this->go('admin/finance/payouts', 'error', 'Aksi tidak dapat dilakukan pada status ini.'); }
        $db->trans_commit();
        $this->load->library('notifier');
        $this->notifier->push_partner($po->travel_partner_id, 'payout', 'Pencairan ' . $act, "Pencairan {$po->payout_code}: {$act}.");
        audit_log('payout.' . $act, 'partner_payouts', (int)$po->id, ['status' => $po->status], ['action' => $act], $this->me->id, (int)$po->travel_partner_id);
        $this->go('admin/finance/payouts', 'success', 'Pencairan diperbarui.');
    }

    // ------------------------------------------------------------ REFUND
    public function refunds()
    {
        $this->need('refund.view');
        $rows = $this->db->query('SELECT r.*, b.booking_code, tp.name AS partner_name FROM refunds r JOIN bookings b ON b.id = r.booking_id JOIN travel_partners tp ON tp.id = r.travel_partner_id ORDER BY FIELD(r.status,"requested","approved","processing"), r.id DESC')->result();
        $this->render('admin/refunds', ['rows' => $rows], 'Refund');
    }

    public function refund_action($id)
    {
        $this->need('refund.process'); $this->post_only();
        $db = $this->db; $act = $this->input->post('action');
        $db->trans_begin();
        $r = $db->query('SELECT * FROM refunds WHERE id = ? FOR UPDATE', [(int)$id])->row();
        if (!$r) { $db->trans_rollback(); show_404(); }
        $note = mb_substr((string)$this->input->post('note'), 0, 255) ?: null;
        $upd = null;
        if ($act === 'approve' && $r->status === 'requested') {
            $fee = max(0, (float)$this->input->post('cancellation_fee'));
            $amt = max(0, (float)$r->paid_amount_snapshot - $fee);
            $upd = ['status' => 'approved', 'cancellation_fee' => $fee, 'refund_amount' => $amt];
        } elseif ($act === 'reject' && $r->status === 'requested') { $upd = ['status' => 'rejected'];
        } elseif ($act === 'complete' && in_array($r->status, ['approved', 'processing'], true)) {
            $upd = ['status' => 'completed', 'processed_at' => date('Y-m-d H:i:s')];
            $db->query("UPDATE bookings SET status = 'refunded' WHERE id = ? AND status = 'cancelled'", [$r->booking_id]);
            if ($r->payment_id) { $db->query("UPDATE payments SET status = 'refunded' WHERE id = ?", [$r->payment_id]); }
        }
        if (!$upd) { $db->trans_rollback(); $this->go('admin/finance/refunds', 'error', 'Aksi tidak dapat dilakukan pada status ini.'); }
        $upd += ['reviewed_by' => $this->me->id, 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => $note];
        $db->where('id', $r->id)->update('refunds', $upd);
        $db->trans_commit();
        audit_log('refund.' . $act, 'refunds', (int)$r->id, ['status' => $r->status], $upd, $this->me->id, (int)$r->travel_partner_id);
        $this->go('admin/finance/refunds', 'success', 'Refund diperbarui. Transfer dana ke customer dilakukan manual/di luar sistem.');
    }

    // ------------------------------------------------------------ ATURAN KOMISI
    public function commissions()
    {
        $this->need('commission.manage');
        $rows = $this->db->query(
            "SELECT cr.*, tp.name AS partner_name, p.name AS package_name FROM commission_rules cr
               LEFT JOIN travel_partners tp ON tp.id = cr.travel_partner_id LEFT JOIN travel_packages p ON p.id = cr.package_id
              ORDER BY cr.is_active DESC, FIELD(cr.scope,'global','partner','package'), cr.id DESC")->result();
        $partners = $this->db->query('SELECT id, name FROM travel_partners WHERE deleted_at IS NULL ORDER BY name')->result();
        $packages = $this->db->query('SELECT p.id, p.name, tp.name AS partner_name FROM travel_packages p JOIN travel_partners tp ON tp.id = p.travel_partner_id WHERE p.deleted_at IS NULL ORDER BY tp.name, p.name')->result();
        $this->render('admin/commissions', ['rows' => $rows, 'partners' => $partners, 'packages' => $packages], 'Aturan Komisi');
    }

    public function commission_save()
    {
        $this->need('commission.manage'); $this->post_only();
        $scope = $this->input->post('scope'); $model = $this->input->post('model');
        if (!in_array($scope, ['global', 'partner', 'package'], true) || !in_array($model, ['percentage', 'fixed', 'percentage_fixed'], true)) { show_error('Input tidak valid', 400); }
        $pid = $scope === 'partner' ? (int)$this->input->post('travel_partner_id') : null;
        $pkg = $scope === 'package' ? (int)$this->input->post('package_id') : null;
        if (($scope === 'partner' && !$pid) || ($scope === 'package' && !$pkg)) { $this->go('admin/finance/commissions', 'error', 'Pilih travel/paket.'); }
        $pct = max(0, min(100, (float)$this->input->post('percentage'))); $fix = max(0, (float)$this->input->post('fixed_amount'));
        $this->db->trans_begin();
        $q = "UPDATE commission_rules SET is_active = 0, effective_until = NOW() WHERE scope = ? AND is_active = 1";
        $args = [$scope];
        if ($scope === 'partner') { $q .= ' AND travel_partner_id = ?'; $args[] = $pid; }
        if ($scope === 'package') { $q .= ' AND package_id = ?'; $args[] = $pkg; }
        $this->db->query($q, $args);
        $this->db->insert('commission_rules', ['scope' => $scope, 'travel_partner_id' => $pid, 'package_id' => $pkg, 'model' => $model,
            'percentage' => $model === 'fixed' ? 0 : $pct, 'fixed_amount' => $model === 'percentage' ? 0 : $fix,
            'note' => mb_substr((string)$this->input->post('note'), 0, 255) ?: null, 'created_by' => $this->me->id]);
        $this->db->trans_commit();
        audit_log('commission.set', 'commission_rules', (int)$this->db->insert_id(), null, ['scope' => $scope, 'model' => $model, 'pct' => $pct, 'fixed' => $fix], $this->me->id);
        $this->go('admin/finance/commissions', 'success', 'Aturan komisi disimpan (berlaku untuk booking baru; booking lama tetap memakai snapshot).');
    }

    public function commission_toggle($id)
    {
        $this->need('commission.manage'); $this->post_only();
        $r = $this->db->get_where('commission_rules', ['id' => (int)$id])->row(); if (!$r) { show_404(); }
        if ($r->scope === 'global' && $r->is_active) {
            $other = $this->db->query("SELECT COUNT(*) c FROM commission_rules WHERE scope = 'global' AND is_active = 1 AND id <> ?", [$r->id])->row()->c;
            if (!$other) { $this->go('admin/finance/commissions', 'error', 'Harus ada minimal satu aturan komisi global aktif.'); }
        }
        $this->db->where('id', $r->id)->update('commission_rules', ['is_active' => $r->is_active ? 0 : 1]);
        $this->go('admin/finance/commissions', 'success', 'Aturan diperbarui.');
    }

    // ------------------------------------------------------------ EXPORT
    /** CSV (dapat dibuka di Excel). Export XLSX/PDF native belum disertakan. */
    public function export()
    {
        $this->need('report.export');
        $from = $this->input->get('from') ?: date('Y-m-01'); $to = $this->input->get('to') ?: date('Y-m-d');
        $rows = $this->db->query(
            "SELECT p.payment_code, p.paid_at, b.booking_code, tp.name AS partner, p.kind, p.amount, c.commission_amount, c.partner_amount, pm.name AS method
               FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN travel_partners tp ON tp.id = p.travel_partner_id
               JOIN payment_methods pm ON pm.id = p.payment_method_id LEFT JOIN commissions c ON c.booking_id = b.id
              WHERE p.status = 'paid' AND DATE(p.paid_at) BETWEEN ? AND ? ORDER BY p.paid_at", [$from, $to])->result_array();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transaksi_' . $from . '_' . $to . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Kode Pembayaran', 'Tanggal Bayar', 'Kode Booking', 'Travel', 'Jenis', 'Nominal', 'Komisi (per booking)', 'Bagian Travel (per booking)', 'Metode']);
        foreach ($rows as $r) {
            foreach ($r as $k => $v) { if (is_string($v) && preg_match('/^[=+\-@]/', $v)) { $r[$k] = "'" . $v; } }   // cegah CSV injection
            fputcsv($out, array_values($r));
        }
        fclose($out); exit;
    }
}
