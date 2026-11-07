<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Klien Xendit (Invoice API). Satu invoice mendukung VA, QRIS, e-wallet.
 * PENTING: verifikasi parameter terhadap dokumentasi Xendit terbaru sebelum produksi
 * (saya tidak dapat menguji panggilan ini tanpa akun/API key Anda).
 */
class Xendit_client
{
    protected $CI;
    protected $app;
    public function __construct() { $this->CI =& get_instance(); $this->app = $this->CI->config->item('app'); }

    public function configured() { return !empty($this->app['xendit_secret_key']); }

    /** @return array hasil Xendit ['id','invoice_url','expiry_date',...] @throws Exception */
    public function create_invoice(array $p)
    {
        if (!$this->configured()) { throw new Exception('Xendit belum dikonfigurasi (isi xendit_secret_key di application/config/local.php).'); }
        $payload = [
            'external_id' => $p['external_id'], 'amount' => (int)round($p['amount']), 'currency' => 'IDR',
            'description' => mb_substr($p['description'], 0, 200), 'invoice_duration' => max(60, (int)$p['duration_seconds']),
        ];
        if (!empty($p['email'])) { $payload['payer_email'] = $p['email']; }
        if (!empty($p['channels'])) { $payload['payment_methods'] = $p['channels']; }
        if (!empty($this->app['payment_return_url'])) {
            $payload['success_redirect_url'] = $this->app['payment_return_url'];
            $payload['failure_redirect_url'] = $this->app['payment_return_url'];
        }
        $ch = curl_init(rtrim($this->app['xendit_base_url'], '/') . '/v2/invoices');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_USERPWD => $this->app['xendit_secret_key'] . ':',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) { throw new Exception('Koneksi ke Xendit gagal: ' . $err); }
        $json = json_decode($res, true);
        if ($code < 200 || $code >= 300 || !is_array($json) || empty($json['id'])) {
            log_message('error', 'Xendit error HTTP ' . $code . ': ' . $res);
            throw new Exception('Xendit menolak permintaan' . (isset($json['message']) ? ': ' . $json['message'] : '.'));
        }
        return $json;
    }

    /** Verifikasi token callback (header x-callback-token). */
    public function verify_callback($header_token)
    {
        $expected = (string)($this->app['xendit_callback_token'] ?? '');
        return $expected !== '' && is_string($header_token) && hash_equals($expected, $header_token);
    }
}
