<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifier
{
    protected $CI;
    public function __construct() { $this->CI =& get_instance(); }

    /**
     * Kirim OTP. Integrasi pengiriman nyata (WhatsApp gateway / SMTP) BELUM disambungkan:
     * sambungkan di sini. Saat ini OTP dicatat ke log (level error agar tertulis) HANYA jika otp_debug aktif.
     */
    public function send_otp($target, $purpose, $code)
    {
        $app = $this->CI->config->item('app');
        if (!empty($app['otp_debug'])) {
            log_message('error', "[OTP-DEBUG] {$purpose} untuk {$target}: {$code}");
        }
        // TODO produksi: kirim via WhatsApp API / email di sini.
    }

    /** Simpan notifikasi in-app (dibaca Android lewat /notifications). */
    public function push($user_id, $type, $title, $body, array $data = [])
    {
        $this->CI->db->insert('notifications', [
            'user_id' => $user_id, 'type' => $type, 'title' => $title,
            'body' => mb_substr($body, 0, 500), 'data' => $data ? json_encode($data) : null,
        ]);
    }

    /** Notifikasi ke semua user aktif sebuah travel. */
    public function push_partner($partner_id, $type, $title, $body, array $data = [])
    {
        $rows = $this->CI->db->query(
            "SELECT user_id FROM travel_partner_users WHERE travel_partner_id = ? AND status = 'active'", [$partner_id])->result();
        foreach ($rows as $r) { $this->push($r->user_id, $type, $title, $body, $data); }
    }
}
