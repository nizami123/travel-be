<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Booking_exception extends Exception
{
    public $http = 422;
    public $errors = null;
    public function __construct($message, $http = 422, $errors = null)
    {
        parent::__construct($message);
        $this->http = $http;
        $this->errors = $errors;
    }
}

/**
 * Inti bisnis: kuota, harga, promo, komisi, status booking.
 * SEMUA nilai uang dihitung di sini (server). Android hanya mengirim schedule_id, price_code, jumlah & kode promo.
 */
class Booking_service
{
    protected $CI;
    protected $db;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->db = $this->CI->db;
        $this->CI->load->library(['commission_calculator', 'notifier']);
        $this->CI->load->model(['setting_model', 'promo_model']);
    }

    // =====================================================================
    //  QUOTE (preview & create memakai logika yang sama)
    // =====================================================================
    /**
     * @param bool $lock true => dipanggil di dalam transaksi, kunci baris kuota (FOR UPDATE)
     */
    public function build_quote($user_id, $schedule_id, array $participants, $promo_code, $payment_option, $lock = false)
    {
        $schedule_id = (int)$schedule_id;
        // 1) Kunci baris kuota TERLEBIH DAHULU (anti double booking / overselling)
        $quota = $this->db->query(
            'SELECT quota_total, quota_held, quota_booked FROM package_quotas WHERE schedule_id = ?' . ($lock ? ' FOR UPDATE' : ''),
            [$schedule_id])->row();
        if (!$quota) { throw new Booking_exception('Jadwal tidak ditemukan.', 404); }

        $s = $this->db->query(
            "SELECT s.*, p.id AS package_id, p.name AS pkg_name, p.travel_partner_id AS partner_id, p.dp_percent, p.full_payment_days_before,
                    p.payment_expiry_hours, p.max_participants_per_booking, p.approval_status, p.status AS pkg_status, p.deleted_at AS pkg_deleted,
                    pc.type AS category_type, tp.name AS partner_name, tp.status AS partner_status, tp.verification_status
               FROM package_schedules s JOIN travel_packages p ON p.id = s.package_id
               JOIN package_categories pc ON pc.id = p.category_id JOIN travel_partners tp ON tp.id = p.travel_partner_id
              WHERE s.id = ?", [$schedule_id])->row();
        if (!$s) { throw new Booking_exception('Jadwal tidak ditemukan.', 404); }

        $today = date('Y-m-d');
        if ($s->status !== 'open' || $s->departure_date <= $today
            || ($s->booking_open_at && $s->booking_open_at > date('Y-m-d H:i:s'))
            || ($s->booking_close_at && $s->booking_close_at < date('Y-m-d H:i:s'))) {
            throw new Booking_exception('Jadwal ini tidak tersedia untuk dipesan.', 422);
        }
        if ($s->approval_status !== 'approved' || $s->pkg_status !== 'active' || $s->pkg_deleted !== null
            || $s->partner_status !== 'active' || $s->verification_status !== 'verified') {
            throw new Booking_exception('Paket tidak tersedia.', 422);
        }

        // 2) Peserta
        $n = count($participants);
        $max = (int)$s->max_participants_per_booking;
        if ($n < 1) { throw new Booking_exception('Minimal 1 peserta.', 422); }
        if ($n > $max) { throw new Booking_exception("Maksimal {$max} peserta per booking.", 422); }
        $available = (int)$quota->quota_total - (int)$quota->quota_held - (int)$quota->quota_booked;
        if ($available < $n) {
            throw new Booking_exception($available > 0 ? "Kuota tersisa hanya {$available} kursi." : 'Kuota sudah penuh.', 409);
        }

        // 3) Harga dari DB (bukan dari klien)
        $prices = [];
        foreach ($this->db->query("SELECT price_code, label, price FROM schedule_prices WHERE schedule_id = ? AND status = 'active'", [$schedule_id])->result() as $r) {
            $prices[$r->price_code] = $r;
        }
        $international = $s->category_type !== 'local';
        $subtotal = 0; $rows = []; $errors = [];
        foreach (array_values($participants) as $i => $pt) {
            $pfx = "participants.$i.";
            $name = trim((string)($pt['full_name'] ?? ''));
            if ($name === '') { $errors[$pfx . 'full_name'] = 'Nama lengkap wajib diisi.'; }
            $code = (string)($pt['price_code'] ?? '');
            if (!isset($prices[$code])) { $errors[$pfx . 'price_code'] = 'Tipe harga tidak valid.'; continue; }
            $gender = $pt['gender'] ?? null;
            if ($gender !== null && !in_array($gender, ['male', 'female'], true)) { $errors[$pfx . 'gender'] = 'Gender tidak valid.'; }
            $birth = $pt['birth_date'] ?? null;
            if ($birth !== null && $birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) { $errors[$pfx . 'birth_date'] = 'Format tanggal lahir YYYY-MM-DD.'; }
            if ($international) {
                $pass = trim((string)($pt['passport_number'] ?? ''));
                $exp = $pt['passport_expiry'] ?? '';
                if ($pass === '') { $errors[$pfx . 'passport_number'] = 'Nomor paspor wajib untuk paket ini.'; }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$exp)) { $errors[$pfx . 'passport_expiry'] = 'Tanggal berlaku paspor wajib (YYYY-MM-DD).'; }
                elseif ($exp < date('Y-m-d', strtotime($s->departure_date . ' +6 months'))) { $errors[$pfx . 'passport_expiry'] = 'Paspor harus berlaku minimal 6 bulan setelah keberangkatan.'; }
            }
            $price = (float)$prices[$code]->price;
            $subtotal += $price;
            $rows[] = [
                'full_name' => mb_substr($name, 0, 150), 'gender' => $gender, 'birth_date' => ($birth ?: null),
                'birth_place' => isset($pt['birth_place']) ? mb_substr($pt['birth_place'], 0, 100) : null,
                'nationality' => isset($pt['nationality']) ? mb_substr($pt['nationality'], 0, 60) : 'Indonesia',
                'id_number' => isset($pt['id_number']) ? mb_substr(preg_replace('/\s+/', '', $pt['id_number']), 0, 40) : null,
                'passport_number' => isset($pt['passport_number']) ? mb_substr($pt['passport_number'], 0, 40) : null,
                'passport_expiry' => !empty($pt['passport_expiry']) ? $pt['passport_expiry'] : null,
                'phone' => isset($pt['phone']) ? mb_substr($pt['phone'], 0, 20) : null,
                'email' => isset($pt['email']) ? mb_substr($pt['email'], 0, 150) : null,
                'price_code' => $code, 'price_label_snapshot' => $prices[$code]->label, 'unit_price' => $price,
                'room_type' => isset($pt['room_type']) ? mb_substr($pt['room_type'], 0, 30) : null,
                'mahram_name' => isset($pt['mahram_name']) ? mb_substr($pt['mahram_name'], 0, 150) : null,
                'special_request' => isset($pt['special_request']) ? mb_substr($pt['special_request'], 0, 255) : null,
                'is_main_contact' => $i === 0 ? 1 : 0,
            ];
        }
        if ($errors) { throw new Booking_exception('Data peserta belum lengkap.', 422, $errors); }
        $subtotal = money($subtotal);

        // 4) Promo (kode manual, atau promo otomatis terbaik)
        $promo = $this->CI->promo_model->evaluate($promo_code, $s->package_id, $s->partner_id, $subtotal, $user_id, $lock);
        $discount = $promo ? (float)$promo['discount'] : 0.0;
        $promotion = $promo ? $promo['promotion'] : null;
        $total = max(0, $subtotal - $discount);

        // 5) DP & pelunasan
        $payment_option = $payment_option === 'dp' ? 'dp' : 'full';
        $dp_amount = 0; $dp_percent = null; $full_due = null;
        $due = date('Y-m-d', strtotime($s->departure_date . ' -' . (int)$s->full_payment_days_before . ' days'));
        if ($payment_option === 'dp') {
            if ($due <= $today) { throw new Booking_exception('DP tidak tersedia karena keberangkatan sudah dekat. Silakan bayar lunas.', 422); }
            $dp_percent = (float)$s->dp_percent;
            $dp_amount = min($total, money(ceil($total * $dp_percent / 100)));
            $full_due = $due;
        }

        // 6) Komisi (snapshot)
        $partner_discount = ($promotion && $promotion->funded_by === 'partner') ? $discount : 0;
        $base = $subtotal - $partner_discount;
        $rule = $this->CI->commission_calculator->resolve_rule($s->partner_id, $s->package_id);
        if (!$rule) { throw new Booking_exception('Aturan komisi belum dikonfigurasi.', 500); }
        list($commission, $partner_amount) = $this->CI->commission_calculator->calculate($rule, $base);

        $hours = $s->payment_expiry_hours ? (int)$s->payment_expiry_hours : (int)$this->CI->setting_model->get('payment_expiry_hours', 24);

        return [
            'schedule' => $s, 'participants' => $rows, 'participant_count' => $n,
            'subtotal' => $subtotal, 'promotion' => $promotion, 'discount' => $discount, 'total' => $total,
            'payment_option' => $payment_option, 'dp_percent' => $dp_percent, 'dp_amount' => $dp_amount,
            'full_payment_due_date' => $full_due, 'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600),
            'rule' => $rule, 'commission' => $commission, 'partner_amount' => $partner_amount, 'commission_base' => $base,
            'quota_available' => $available,
        ];
    }

    public function format_quote(array $q)
    {
        return [
            'package_name' => $q['schedule']->pkg_name, 'partner_name' => $q['schedule']->partner_name,
            'departure_date' => $q['schedule']->departure_date, 'return_date' => $q['schedule']->return_date,
            'participant_count' => $q['participant_count'],
            'items' => array_map(function ($p) { return ['name' => $p['full_name'], 'type' => $p['price_label_snapshot'], 'price' => $p['unit_price']]; }, $q['participants']),
            'subtotal' => $q['subtotal'], 'discount' => $q['discount'],
            'promo' => $q['promotion'] ? ['id' => (int)$q['promotion']->id, 'title' => $q['promotion']->title, 'code' => $q['promotion']->code] : null,
            'total' => $q['total'], 'payment_option' => $q['payment_option'],
            'dp_percent' => $q['dp_percent'], 'dp_amount' => $q['dp_amount'], 'remaining_after_dp' => $q['total'] - $q['dp_amount'],
            'full_payment_due_date' => $q['full_payment_due_date'],
            'payment_expires_at' => $q['expires_at'], 'quota_available' => $q['quota_available'],
        ];
    }

    // =====================================================================
    //  CREATE BOOKING (transaksi + lock)
    // =====================================================================
    public function create_booking($user, $schedule_id, array $participants, $promo_code, $payment_option, array $contact, $note)
    {
        $db = $this->db;
        $db->trans_begin();
        try {
            $q = $this->build_quote($user->id, $schedule_id, $participants, $promo_code, $payment_option, true);
            $s = $q['schedule'];

            $code = null;
            for ($i = 0; $i < 5; $i++) {
                $try = 'MT' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                if (!$db->query('SELECT 1 FROM bookings WHERE booking_code = ?', [$try])->row()) { $code = $try; break; }
            }
            if (!$code) { throw new Exception('Gagal membuat nomor booking.'); }

            $ok = $db->insert('bookings', [
                'booking_code' => $code, 'user_id' => $user->id, 'travel_partner_id' => $s->partner_id,
                'package_id' => $s->package_id, 'schedule_id' => $s->id, 'status' => 'pending',
                'payment_option' => $q['payment_option'], 'participant_count' => $q['participant_count'],
                'package_name_snapshot' => $s->pkg_name, 'partner_name_snapshot' => $s->partner_name,
                'departure_date' => $s->departure_date, 'return_date' => $s->return_date,
                'subtotal' => $q['subtotal'], 'promotion_id' => $q['promotion'] ? $q['promotion']->id : null,
                'promo_code_snapshot' => $q['promotion'] ? $q['promotion']->code : null,
                'discount_amount' => $q['discount'], 'discount_funded_by' => $q['promotion'] ? $q['promotion']->funded_by : null,
                'total_amount' => $q['total'], 'dp_percent_snapshot' => $q['dp_percent'], 'dp_amount' => $q['dp_amount'],
                'paid_amount' => 0, 'remaining_amount' => $q['total'],
                'payment_expires_at' => $q['expires_at'], 'full_payment_due_date' => $q['full_payment_due_date'],
                'contact_name' => mb_substr($contact['name'], 0, 150), 'contact_email' => mb_substr($contact['email'], 0, 150),
                'contact_phone' => mb_substr($contact['phone'], 0, 20), 'customer_note' => $note ? mb_substr($note, 0, 1000) : null,
            ]);
            if (!$ok) { throw new Exception('Gagal menyimpan booking.'); }
            $booking_id = (int)$db->insert_id();

            foreach ($q['participants'] as $p) {
                $p['booking_id'] = $booking_id;
                if (!$db->insert('booking_participants', $p)) { throw new Exception('Gagal menyimpan peserta.'); }
            }
            // tahan kuota
            $db->query('UPDATE package_quotas SET quota_held = quota_held + ? WHERE schedule_id = ?', [$q['participant_count'], $s->id]);
            if ($db->affected_rows() < 1) { throw new Exception('Gagal menahan kuota.'); }
            if ($q['promotion']) { $db->query('UPDATE promotions SET used_count = used_count + 1 WHERE id = ?', [$q['promotion']->id]); }
            $db->query('UPDATE travel_packages SET booking_count = booking_count + 1 WHERE id = ?', [$s->package_id]);

            $r = $q['rule'];
            if (!$db->insert('commissions', [
                'booking_id' => $booking_id, 'travel_partner_id' => $s->partner_id, 'commission_rule_id' => $r->id,
                'rule_scope_snapshot' => $r->scope, 'model_snapshot' => $r->model,
                'percentage_snapshot' => $r->percentage, 'fixed_amount_snapshot' => $r->fixed_amount,
                'base_amount' => $q['commission_base'], 'commission_amount' => $q['commission'],
                'partner_amount' => $q['partner_amount'], 'status' => 'pending',
            ])) { throw new Exception('Gagal menyimpan komisi.'); }

            if ($db->trans_status() === false) { throw new Exception('Transaksi gagal.'); }
            $db->trans_commit();
        } catch (Exception $e) {
            $db->trans_rollback();
            if ($e instanceof Booking_exception) { throw $e; }
            log_message('error', 'create_booking: ' . $e->getMessage() . ' | ' . $db->error()['message']);
            throw new Booking_exception('Terjadi kesalahan saat membuat booking. Silakan coba lagi.', 500);
        }

        $this->CI->notifier->push($user->id, 'booking', 'Booking dibuat',
            "Booking {$code} dibuat. Selesaikan pembayaran sebelum " . date('d M Y H:i', strtotime($q['expires_at'])) . '.', ['booking_id' => $booking_id]);
        $this->CI->notifier->push_partner($s->partner_id, 'booking', 'Booking baru', "Booking baru {$code} untuk {$s->pkg_name}.", ['booking_id' => $booking_id]);
        audit_log('booking.create', 'bookings', $booking_id, null, ['code' => $code, 'total' => $q['total']], $user->id, $s->partner_id);
        return $booking_id;
    }

    // =====================================================================
    //  PEMBAYARAN DITERIMA (webhook Xendit / verifikasi manual) - idempoten
    // =====================================================================
    public function mark_payment_paid($payment_id, $source, $actor_id = null)
    {
        $db = $this->db;
        $db->trans_begin();
        try {
            $pay = $db->query('SELECT * FROM payments WHERE id = ? FOR UPDATE', [(int)$payment_id])->row();
            if (!$pay) { throw new Exception('Pembayaran tidak ditemukan.'); }
            if ($pay->status === 'paid') { $db->trans_commit(); return 'already_paid'; }

            $bk = $db->query('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$pay->booking_id])->row();
            $now = date('Y-m-d H:i:s');
            $old = $pay->status;
            $db->where('id', $pay->id)->update('payments', [
                'status' => 'paid', 'paid_at' => $now,
                'verified_by' => $actor_id, 'verified_at' => $actor_id ? $now : null, 'reject_reason' => null,
            ]);
            $this->history($pay->id, $old, 'paid', $source, $actor_id);

            $result = 'ok';
            $revived = false;
            if (in_array($bk->status, ['paid', 'completed', 'refunded', 'cancelled'], true)) {
                $this->create_refund($bk, $pay, (float)$pay->amount, 'Pembayaran diterima untuk booking berstatus ' . $bk->status . '.');
                $result = 'refund_created';
            } else {
                if ($bk->status === 'expired') {      // pembayaran terlambat: coba pulihkan kuota
                    $quota = $db->query('SELECT * FROM package_quotas WHERE schedule_id = ? FOR UPDATE', [$bk->schedule_id])->row();
                    if ($quota && ((int)$quota->quota_total - (int)$quota->quota_held - (int)$quota->quota_booked) >= (int)$bk->participant_count) {
                        $db->query('UPDATE package_quotas SET quota_booked = quota_booked + ? WHERE schedule_id = ?', [$bk->participant_count, $bk->schedule_id]);
                        $revived = true;
                    } else {
                        $this->create_refund($bk, $pay, (float)$pay->amount, 'Pembayaran terlambat; booking kedaluwarsa dan kuota sudah terisi.');
                        $result = 'refund_created';
                    }
                }
                if ($result === 'ok') {
                    $paid = (float)$bk->paid_amount + (float)$pay->amount;
                    $remaining = max(0, (float)$bk->total_amount - $paid);
                    $status = $remaining <= 0 ? 'paid' : 'partially_paid';
                    $db->where('id', $bk->id)->update('bookings', [
                        'paid_amount' => $paid, 'remaining_amount' => $remaining, 'status' => $status,
                        'confirmed_at' => $bk->confirmed_at ?: $now,
                    ]);
                    if ($bk->status === 'pending' && !$revived) {   // held -> booked
                        $db->query('UPDATE package_quotas SET quota_held = GREATEST(0, quota_held - ?), quota_booked = quota_booked + ? WHERE schedule_id = ?',
                            [$bk->participant_count, $bk->participant_count, $bk->schedule_id]);
                    }
                    if ($status === 'paid') {
                        $db->query("UPDATE commissions SET status = 'earned', earned_at = NOW() WHERE booking_id = ? AND status = 'pending'", [$bk->id]);
                    }
                    $result = $status;
                }
            }
            if ($db->trans_status() === false) { throw new Exception('Transaksi gagal.'); }
            $db->trans_commit();
        } catch (Exception $e) {
            $db->trans_rollback();
            log_message('error', 'mark_payment_paid: ' . $e->getMessage() . ' | ' . $db->error()['message']);
            throw $e;
        }

        $msg = $result === 'refund_created'
            ? 'Pembayaran diterima namun tidak dapat diterapkan; permintaan refund dibuat otomatis.'
            : ($result === 'paid' ? "Pembayaran lunas untuk booking {$bk->booking_code}. Terima kasih!" : "Pembayaran {$bk->booking_code} diterima. Mohon lunasi sebelum batas waktu.");
        $this->CI->notifier->push($bk->user_id, 'payment', 'Pembayaran diterima', $msg, ['booking_id' => (int)$bk->id]);
        $this->CI->notifier->push_partner($bk->travel_partner_id, 'payment', 'Pembayaran masuk', "Pembayaran {$bk->booking_code} diterima.", ['booking_id' => (int)$bk->id]);
        audit_log('payment.paid', 'payments', (int)$payment_id, ['status' => $old], ['status' => 'paid', 'source' => $source], $actor_id, $bk->travel_partner_id);
        return $result;
    }

    public function history($payment_id, $from, $to, $source, $actor = null, $note = null)
    {
        $this->db->insert('payment_status_history', [
            'payment_id' => $payment_id, 'from_status' => $from, 'to_status' => $to,
            'source' => $source, 'actor_id' => $actor, 'note' => $note,
        ]);
    }

    protected function create_refund($bk, $pay, $amount, $reason)
    {
        $this->db->insert('refunds', [
            'refund_code' => 'RF' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'booking_id' => $bk->id, 'payment_id' => $pay ? $pay->id : null, 'travel_partner_id' => $bk->travel_partner_id,
            'requested_by' => null, 'reason' => mb_substr($reason, 0, 255),
            'paid_amount_snapshot' => $amount, 'cancellation_fee' => 0, 'refund_amount' => $amount, 'status' => 'requested',
        ]);
    }

    // =====================================================================
    //  PEMBATALAN
    // =====================================================================
    /** @param bool $by_customer true = pembatalan oleh customer */
    public function cancel_booking($booking_id, $actor_id, $reason, $by_customer = true)
    {
        $db = $this->db;
        $db->trans_begin();
        try {
            $bk = $db->query('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [(int)$booking_id])->row();
            if (!$bk) { throw new Booking_exception('Booking tidak ditemukan.', 404); }
            if (!in_array($bk->status, ['pending', 'partially_paid', 'paid'], true)) {
                throw new Booking_exception('Booking dengan status ini tidak dapat dibatalkan.', 422);
            }
            if ($by_customer && $bk->status === 'paid' && $bk->departure_date <= date('Y-m-d')) {
                throw new Booking_exception('Perjalanan sudah berjalan, hubungi travel.', 422);
            }
            $db->query('SELECT 1 FROM package_quotas WHERE schedule_id = ? FOR UPDATE', [$bk->schedule_id]);
            if ($bk->status === 'pending') {
                $db->query('UPDATE package_quotas SET quota_held = GREATEST(0, quota_held - ?) WHERE schedule_id = ?', [$bk->participant_count, $bk->schedule_id]);
            } else {
                $db->query('UPDATE package_quotas SET quota_booked = GREATEST(0, quota_booked - ?) WHERE schedule_id = ?', [$bk->participant_count, $bk->schedule_id]);
            }
            if ($bk->promotion_id) { $db->query('UPDATE promotions SET used_count = GREATEST(0, used_count - 1) WHERE id = ?', [$bk->promotion_id]); }
            $db->query("UPDATE payments SET status = 'expired' WHERE booking_id = ? AND status IN ('pending','waiting_verification')", [$bk->id]);
            $db->query("UPDATE commissions SET status = 'reversed', reversed_at = NOW() WHERE booking_id = ? AND status IN ('pending','earned','available')", [$bk->id]);
            $db->where('id', $bk->id)->update('bookings', [
                'status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => mb_substr($reason, 0, 255), 'cancelled_by' => $actor_id,
            ]);
            if ((float)$bk->paid_amount > 0) {   // ada uang masuk -> permintaan refund (diputuskan admin/travel)
                $this->create_refund($bk, null, (float)$bk->paid_amount, 'Pembatalan booking: ' . $reason);
            }
            if ($db->trans_status() === false) { throw new Exception('Transaksi gagal.'); }
            $db->trans_commit();
        } catch (Exception $e) {
            $db->trans_rollback();
            if ($e instanceof Booking_exception) { throw $e; }
            log_message('error', 'cancel_booking: ' . $e->getMessage());
            throw new Booking_exception('Gagal membatalkan booking.', 500);
        }
        $this->CI->notifier->push($bk->user_id, 'booking', 'Booking dibatalkan', "Booking {$bk->booking_code} dibatalkan.", ['booking_id' => (int)$bk->id]);
        $this->CI->notifier->push_partner($bk->travel_partner_id, 'booking', 'Booking dibatalkan', "Booking {$bk->booking_code} dibatalkan.", ['booking_id' => (int)$bk->id]);
        audit_log('booking.cancel', 'bookings', (int)$bk->id, ['status' => $bk->status], ['status' => 'cancelled', 'reason' => $reason], $actor_id, $bk->travel_partner_id);
        return true;
    }

    // =====================================================================
    //  CRON: kedaluwarsa & pembebasan kuota
    // =====================================================================
    public function expire_due()
    {
        $db = $this->db;
        $ids = $db->query("SELECT id FROM bookings WHERE status = 'pending' AND payment_expires_at < NOW() LIMIT 200")->result();
        $count = 0;
        foreach ($ids as $row) {
            $db->trans_begin();
            $bk = $db->query('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$row->id])->row();
            if (!$bk || $bk->status !== 'pending' || $bk->payment_expires_at >= date('Y-m-d H:i:s')) { $db->trans_rollback(); continue; }
            // jika ada bukti transfer yang sedang diverifikasi, jangan kedaluwarsakan
            $wv = $db->query("SELECT 1 FROM payments WHERE booking_id = ? AND status = 'waiting_verification' LIMIT 1", [$bk->id])->row();
            if ($wv) { $db->trans_rollback(); continue; }
            $db->query('SELECT 1 FROM package_quotas WHERE schedule_id = ? FOR UPDATE', [$bk->schedule_id]);
            $db->query('UPDATE package_quotas SET quota_held = GREATEST(0, quota_held - ?) WHERE schedule_id = ?', [$bk->participant_count, $bk->schedule_id]);
            if ($bk->promotion_id) { $db->query('UPDATE promotions SET used_count = GREATEST(0, used_count - 1) WHERE id = ?', [$bk->promotion_id]); }
            $db->query("UPDATE payments SET status = 'expired' WHERE booking_id = ? AND status = 'pending'", [$bk->id]);
            $db->query("UPDATE commissions SET status = 'reversed', reversed_at = NOW() WHERE booking_id = ? AND status = 'pending'", [$bk->id]);
            $db->where('id', $bk->id)->update('bookings', ['status' => 'expired']);
            if ($db->trans_status() === false) { $db->trans_rollback(); continue; }
            $db->trans_commit();
            $this->CI->notifier->push($bk->user_id, 'booking', 'Booking kedaluwarsa', "Booking {$bk->booking_code} kedaluwarsa karena belum dibayar.", ['booking_id' => (int)$bk->id]);
            $count++;
        }
        // payment pending yang sudah lewat batas
        $db->query("UPDATE payments SET status = 'expired' WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()");
        // jadwal yang sudah lewat -> departed ; booking lunas jadwal lewat -> completed
        $db->query("UPDATE package_schedules SET status = 'departed' WHERE status IN ('open','closed') AND departure_date <= CURDATE()");
        $db->query("UPDATE bookings SET status = 'completed', completed_at = NOW() WHERE status = 'paid' AND return_date < CURDATE()");
        $db->query("UPDATE package_schedules SET status = 'completed' WHERE status = 'departed' AND return_date < CURDATE()");
        // komisi: earned -> available setelah hold period selesai perjalanan
        $hold = (int)$this->CI->setting_model->get('payout_hold_days', 7);
        $db->query("UPDATE commissions c JOIN bookings b ON b.id = c.booking_id
                       SET c.status = 'available', c.available_at = NOW()
                     WHERE c.status = 'earned' AND b.status = 'completed' AND b.return_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$hold]);
        return $count;
    }
}
