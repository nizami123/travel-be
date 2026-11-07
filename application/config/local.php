<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * KONFIGURASI LOKAL - edit file ini saja.
 * Jangan commit file ini ke git (berisi rahasia).
 */
return [
    // Kosongkan = deteksi otomatis. Di produksi ISI dengan URL tetap, mis. https://api.domainanda.com/
    'base_url'   => '',

    // Database (default XAMPP)
    'db_host'    => 'localhost',
    'db_user'    => 'root',
    'db_pass'    => '',
    'db_name'    => 'multi_travel',

    // WAJIB diganti: string acak >= 32 karakter (dipakai untuk HMAC OTP)
    'encryption_key' => 'GANTI-DENGAN-STRING-ACAK-MINIMAL-32-KARAKTER-1234567890',

    'timezone'   => 'Asia/Jakarta',
    'tz_offset'  => '+07:00',

    // true = OTP ikut dikembalikan di respons API (HANYA untuk development!). Produksi: false.
    'otp_debug'  => true,
    'token_ttl_days' => 30,

    // Xendit (isi dari dashboard Xendit; JANGAN dikirim ke Android)
    'xendit_secret_key'     => '',
    'xendit_callback_token' => '',
    'xendit_base_url'       => 'https://api.xendit.co',
    'payment_return_url'    => '',   // halaman tujuan setelah bayar (opsional)
];
