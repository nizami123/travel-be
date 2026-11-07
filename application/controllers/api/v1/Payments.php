<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payments extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->library(['payment_service', 'booking_service']);
    }

    public function methods()
    {
        $rows = $this->db->query("SELECT id, code, name, group_type, provider, fee_fixed, fee_percent, icon FROM payment_methods WHERE status = 'active' ORDER BY sort_order")->result();
        foreach ($rows as $r) { $r->id = (int)$r->id; $r->fee_fixed = (float)$r->fee_fixed; $r->fee_percent = (float)$r->fee_percent; }
        api_ok($rows);
    }

    /** Jenis & nominal (DP / lunas / pelunasan) ditentukan server dari status booking. */
    public function create($booking_id)
    {
        $this->throttle('pay:' . $this->user->id, 10, 60);
        $this->validate(['payment_method_id' => 'required|integer']);
        try {
            $pid = $this->payment_service->create_payment($this->user, $booking_id, $this->in('payment_method_id'));
            $pay = $this->db->get_where('payments', ['id' => $pid])->row();
            api_ok($this->payment_service->format_payment($pay), 'Tagihan pembayaran dibuat.', null, 201);
        } catch (Booking_exception $e) {
            api_error($e->getMessage(), $e->http >= 400 && $e->http < 600 ? $e->http : 422, $e->errors);
        }
    }

    public function show($id)
    {
        $pay = $this->db->get_where('payments', ['id' => (int)$id, 'user_id' => $this->user->id])->row();
        if (!$pay) { api_error('Pembayaran tidak ditemukan.', 404); }
        api_ok($this->payment_service->format_payment($pay));
    }

    /** Upload bukti transfer manual. */
    public function proof($id)
    {
        $pay = $this->db->query("SELECT * FROM payments WHERE id = ? AND user_id = ? AND provider = 'manual'", [(int)$id, $this->user->id])->row();
        if (!$pay) { api_error('Pembayaran tidak ditemukan.', 404); }
        if (!in_array($pay->status, ['pending', 'rejected'], true)) { api_error('Pembayaran tidak menerima bukti transfer (status: ' . $pay->status . ').', 422); }
        if ($pay->expires_at && $pay->expires_at < date('Y-m-d H:i:s')) { api_error('Batas waktu pembayaran sudah lewat.', 422); }
        $this->load->library('uploader');
        $f = $this->uploader->save('proof', 'proofs', true, 'jpg|jpeg|png|pdf', 5120);
        if (!$f) { api_error($this->uploader->error, 422); }
        $date = (string)$this->in('transfer_date');
        $this->db->trans_begin();
        $this->db->insert('payment_proofs', [
            'payment_id' => $pay->id, 'file_path' => 'proofs/' . basename($f['path']), 'original_name' => $f['original'],
            'mime_type' => $f['mime'], 'file_size' => $f['size'],
            'sender_bank' => mb_substr((string)$this->in('sender_bank'), 0, 100) ?: null,
            'sender_name' => mb_substr((string)$this->in('sender_name'), 0, 150) ?: null,
            'transfer_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'transfer_amount' => is_numeric($this->in('transfer_amount')) ? (float)$this->in('transfer_amount') : null,
            'note' => mb_substr((string)$this->in('note'), 0, 255) ?: null,
        ]);
        $this->db->where('id', $pay->id)->update('payments', ['status' => 'waiting_verification', 'reject_reason' => null]);
        $this->booking_service->history($pay->id, $pay->status, 'waiting_verification', 'customer', $this->user->id);
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); api_error('Gagal menyimpan bukti.', 500); }
        $this->db->trans_commit();
        $this->load->library('notifier');
        $this->notifier->push_partner($pay->travel_partner_id, 'payment', 'Bukti transfer masuk', 'Ada bukti transfer yang perlu diverifikasi.', ['booking_id' => (int)$pay->booking_id]);
        api_ok($this->payment_service->format_payment($this->db->get_where('payments', ['id' => $pay->id])->row()), 'Bukti transfer dikirim. Menunggu verifikasi travel.');
    }
}
