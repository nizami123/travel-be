<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payments extends Partner_Controller
{
    public function index()
    {
        $this->need('payment.view');
        $rows = $this->db->query(
            "SELECT p.*, b.booking_code, pm.name AS method_name,
                    (SELECT id FROM payment_proofs pr WHERE pr.payment_id = p.id ORDER BY pr.id DESC LIMIT 1) AS proof_id
               FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN payment_methods pm ON pm.id = p.payment_method_id
              WHERE p.travel_partner_id = ? ORDER BY FIELD(p.status,'waiting_verification','pending') , p.id DESC LIMIT 2000", [$this->partner_id])->result();
        $this->render('partner/payments', ['rows' => $rows], 'Pembayaran');
    }

    public function verify($id)
    {
        $this->need('payment.verify'); $this->post_only();
        $p = $this->db->get_where('payments', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id, 'provider' => 'manual'])->row();
        if (!$p) { show_404(); }
        if ($p->status !== 'waiting_verification') { $this->go('partner/payments', 'error', 'Pembayaran tidak menunggu verifikasi.'); }
        $this->load->library('booking_service');
        try {
            $res = $this->booking_service->mark_payment_paid($p->id, 'partner', $this->me->id);
            $this->go('partner/payments', 'success', $res === 'refund_created' ? 'Pembayaran diterima tetapi booking sudah tidak aktif; permintaan refund dibuat.' : 'Pembayaran diverifikasi.');
        } catch (Exception $e) { $this->go('partner/payments', 'error', 'Gagal memverifikasi pembayaran.'); }
    }

    public function reject($id)
    {
        $this->need('payment.verify'); $this->post_only();
        $p = $this->db->get_where('payments', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id, 'provider' => 'manual'])->row();
        if (!$p || $p->status !== 'waiting_verification') { show_404(); }
        $reason = mb_substr(trim((string)$this->input->post('reason')), 0, 255);
        if ($reason === '') { $this->go('partner/payments', 'error', 'Alasan penolakan wajib diisi.'); }
        $this->load->library(['booking_service', 'notifier']);
        $this->db->where('id', $p->id)->update('payments', ['status' => 'rejected', 'reject_reason' => $reason, 'verified_by' => $this->me->id, 'verified_at' => date('Y-m-d H:i:s')]);
        $this->booking_service->history($p->id, 'waiting_verification', 'rejected', 'partner', $this->me->id, $reason);
        $this->notifier->push($p->user_id, 'payment', 'Bukti transfer ditolak', "Pembayaran ditolak: $reason. Silakan unggah ulang bukti yang benar.", ['booking_id' => (int)$p->booking_id]);
        audit_log('payment.reject', 'payments', (int)$p->id, null, ['reason' => $reason], $this->me->id, $this->partner_id);
        $this->go('partner/payments', 'success', 'Pembayaran ditolak; customer diberi tahu.');
    }
}
