<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_service
{
    protected $CI;
    protected $db;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->db = $this->CI->db;
        $this->CI->load->library(['xendit_client', 'booking_service']);
        $this->CI->load->model('setting_model');
    }

    /**
     * Buat tagihan untuk booking. Jenis (dp/full/settlement) dan nominal ditentukan SERVER.
     */
    public function create_payment($user, $booking_id, $method_id)
    {
        $db = $this->db;
        $db->trans_begin();
        try {
            $bk = $db->query('SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE', [(int)$booking_id, $user->id])->row();
            if (!$bk) { throw new Booking_exception('Booking tidak ditemukan.', 404); }
            $m = $db->query("SELECT * FROM payment_methods WHERE id = ? AND status = 'active'", [(int)$method_id])->row();
            if (!$m) { throw new Booking_exception('Metode pembayaran tidak tersedia.', 422); }

            $now = date('Y-m-d H:i:s');
            if ($bk->status === 'pending') {
                if ($bk->payment_expires_at < $now) { throw new Booking_exception('Booking sudah kedaluwarsa.', 422); }
                $kind = $bk->payment_option === 'dp' ? 'dp' : 'full';
                $amount = $kind === 'dp' ? (float)$bk->dp_amount : (float)$bk->total_amount;
                $expires = $bk->payment_expires_at;
            } elseif ($bk->status === 'partially_paid') {
                $kind = 'settlement';
                $amount = (float)$bk->remaining_amount;
                $hours = (int)$this->CI->setting_model->get('payment_expiry_hours', 24);
                $expires = date('Y-m-d H:i:s', time() + $hours * 3600);
                if ($bk->full_payment_due_date) {
                    $limit = $bk->full_payment_due_date . ' 23:59:59';
                    if ($limit < $now) { throw new Booking_exception('Batas pelunasan sudah lewat. Hubungi travel.', 422); }
                    $expires = min($expires, $limit);
                }
            } else {
                throw new Booking_exception('Booking tidak dapat dibayar (status: ' . $bk->status . ').', 422);
            }
            if ($amount <= 0) { throw new Booking_exception('Tidak ada tagihan.', 422); }

            // pembayaran aktif sebelumnya: kembalikan bila sama, selain itu kedaluwarsakan
            $active = $db->query("SELECT * FROM payments WHERE booking_id = ? AND status IN ('pending','waiting_verification') FOR UPDATE", [$bk->id])->result();
            foreach ($active as $a) {
                if ($a->status === 'waiting_verification') { throw new Booking_exception('Masih ada pembayaran yang menunggu verifikasi.', 409); }
                if ((int)$a->payment_method_id === (int)$m->id && $a->kind === $kind && (!$a->expires_at || $a->expires_at > $now)) {
                    $db->trans_commit();
                    return $a->id;
                }
                $db->where('id', $a->id)->update('payments', ['status' => 'expired']);
                $this->CI->booking_service->history($a->id, $a->status, 'expired', 'system', null, 'Diganti metode lain');
            }

            $fee = money($m->fee_fixed + ceil($amount * (float)$m->fee_percent / 100));
            $total_charge = $amount + $fee;
            $code = 'PAY' . date('ymdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $row = [
                'payment_code' => $code, 'booking_id' => $bk->id, 'user_id' => $user->id, 'travel_partner_id' => $bk->travel_partner_id,
                'payment_method_id' => $m->id, 'kind' => $kind, 'amount' => $amount, 'fee_amount' => $fee, 'total_charge' => $total_charge,
                'status' => 'pending', 'provider' => $m->provider, 'expires_at' => $expires,
            ];

            if ($m->provider === 'manual') {
                $bank = $db->query("SELECT id FROM partner_bank_accounts WHERE travel_partner_id = ? AND status = 'active' AND purpose IN ('manual_transfer','both')
                                    ORDER BY is_primary DESC, id LIMIT 1", [$bk->travel_partner_id])->row();
                if (!$bank) { throw new Booking_exception('Travel ini belum menyediakan rekening transfer manual.', 422); }
                $row['manual_bank_account_id'] = $bank->id;
            } else {
                $secs = max(60, strtotime($expires) - time());
                $inv = $this->CI->xendit_client->create_invoice([
                    'external_id' => $code, 'amount' => $total_charge,
                    'description' => ucfirst($kind === 'settlement' ? 'Pelunasan' : ($kind === 'dp' ? 'DP' : 'Pembayaran')) . ' ' . $bk->booking_code . ' - ' . $bk->package_name_snapshot,
                    'duration_seconds' => $secs, 'email' => $bk->contact_email,
                    'channels' => $m->provider_code ? [$m->provider_code] : null,
                ]);
                $row['provider_ref_id'] = $inv['id'];
                $row['payment_url'] = $inv['invoice_url'] ?? null;
                $row['raw_response'] = json_encode($inv);
            }
            if (!$db->insert('payments', $row)) { throw new Exception('Gagal menyimpan pembayaran.'); }
            $pid = (int)$db->insert_id();
            $this->CI->booking_service->history($pid, null, 'pending', 'customer', $user->id);
            if ($db->trans_status() === false) { throw new Exception('Transaksi gagal.'); }
            $db->trans_commit();
            return $pid;
        } catch (Exception $e) {
            $db->trans_rollback();
            if ($e instanceof Booking_exception) { throw $e; }
            log_message('error', 'create_payment: ' . $e->getMessage());
            throw new Booking_exception($e->getMessage(), 502);
        }
    }

    public function format_payment($pay)
    {
        $o = [
            'id' => (int)$pay->id, 'payment_code' => $pay->payment_code, 'booking_id' => (int)$pay->booking_id, 'kind' => $pay->kind,
            'amount' => (float)$pay->amount, 'fee_amount' => (float)$pay->fee_amount, 'total_charge' => (float)$pay->total_charge,
            'status' => $pay->status, 'provider' => $pay->provider, 'payment_url' => $pay->payment_url,
            'expires_at' => $pay->expires_at, 'paid_at' => $pay->paid_at, 'reject_reason' => $pay->reject_reason,
        ];
        if ($pay->provider === 'manual' && $pay->manual_bank_account_id) {
            $b = $this->db->query('SELECT bank_name, account_number, account_holder FROM partner_bank_accounts WHERE id = ?', [$pay->manual_bank_account_id])->row();
            $o['bank_account'] = $b;
        }
        return $o;
    }
}
