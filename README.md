# MultiTravel — Marketplace Travel Multi-Vendor

```
MultiTravel/
├── MULTI_TRAVEL_DATABASE/   database.sql (51 tabel), seeders.sql, erd.md, documentation.md
├── MULTI_TRAVEL_BACKEND/    CodeIgniter 3: REST API + dashboard Super Admin + dashboard Travel Mitra
├── MULTI_TRAVEL_ANDROID/    Kotlin + Jetpack Compose (MVVM, Retrofit, DataStore)
└── API.md                   ringkasan endpoint
```

> **STATUS JUJUR — baca dulu.** Seluruh kode ditulis lengkap, tetapi **belum pernah dijalankan/dikompilasi**
> (lingkungan pembuatan tidak punya PHP, MySQL, maupun Android SDK). Pemeriksaan yang dilakukan hanya statis
> (keseimbangan tanda kurung, referensi tabel/FK). Harapkan beberapa error kecil saat pertama kali jalan;
> kirimkan pesan errornya ke saya dan saya perbaiki. Panduan pengujian ada di bagian 6.

---
## 1. Kebutuhan
- PHP **7.4 – 8.1** (ekstensi: mysqli, curl, mbstring, json, fileinfo, gd/exif opsional), Apache dengan **mod_rewrite**
- MySQL **8.x** (bukan MariaDB lama — memakai CHECK, JSON, FOR UPDATE)
- XAMPP/Laragon cukup untuk lokal. Android Studio (Koala+), JDK 17.
- Folder `system/` CodeIgniter **tidak disertakan** (tidak ada akses internet saat paket dibuat) → langkah 2.

## 2. Pasang Backend
1. Salin `MULTI_TRAVEL_BACKEND` ke `htdocs/` (hasil: `http://localhost/MULTI_TRAVEL_BACKEND/`).
2. Unduh folder `system` CodeIgniter 3.1.13, pilih salah satu:
   - Linux/Mac: `bash MULTI_TRAVEL_BACKEND/setup.sh`
   - Windows: PowerShell → `./MULTI_TRAVEL_BACKEND/setup.ps1`
   - Manual: unduh https://github.com/bcit-ci/CodeIgniter/archive/refs/tags/3.1.13.zip lalu salin folder `system` ke `MULTI_TRAVEL_BACKEND/system`
3. phpMyAdmin → import `MULTI_TRAVEL_DATABASE/database.sql` lalu `seeders.sql`.
4. Edit `application/config/local.php` (DB, `encryption_key` acak ≥32 karakter, kunci Xendit).
5. Pastikan folder bisa ditulis: `application/cache/sessions`, `application/logs`, `uploads/` (Linux: `chmod -R 775`).
6. Apache: `AllowOverride All` untuk folder tersebut (agar `.htaccess` berjalan).
7. Uji: buka `http://localhost/MULTI_TRAVEL_BACKEND/api/v1/health` → JSON `success: true`.
8. Dashboard: `http://localhost/MULTI_TRAVEL_BACKEND/login` — **admin@multitravel.local / Admin#12345** (GANTI segera).

### Cron (wajib untuk pembebasan kuota)
```
*/5 * * * * php /path/MULTI_TRAVEL_BACKEND/index.php cron run
```
Windows: Task Scheduler tiap 5 menit → `php.exe C:\xampp\htdocs\MULTI_TRAVEL_BACKEND\index.php cron run`.
Cron meng-expire booking belum bayar (kuota kembali), menandai jadwal/booking selesai, dan membuka komisi untuk dicairkan.

## 3. Alur Kerja Pertama Kali
1. Login Super Admin → **Travel Mitra → Tambah** (password sementara tampil sekali) → buka detail → **Verifikasi & Aktifkan**.
2. Admin: **Destinasi** → tambah destinasi (status `published`), tambah **Banner**.
3. Login sebagai admin travel → **Perusahaan** (isi profil, rekening dengan tujuan "transfer pelanggan" bila ingin transfer manual) → **Paket → Tambah** → **Jadwal** (tanggal, kuota, harga `adult | Dewasa | 1750000`) → **Ajukan**.
4. Admin: **Paket → Tinjau → Setujui**. Paket tampil di aplikasi.
5. Aplikasi: daftar → OTP (mode dev: OTP muncul di layar & `application/logs`) → pesan paket.

## 4. Xendit
- Isi `xendit_secret_key` dan `xendit_callback_token` di `local.php`.
- Di dashboard Xendit, daftarkan callback **Invoice paid/expired** ke `https://DOMAIN/MULTI_TRAVEL_BACKEND/webhook/xendit` dan salin *callback verification token* ke `local.php`.
- Implementasi memakai **Invoice API** (`POST /v2/invoices`): satu halaman bayar berisi VA/QRIS/e-wallet sesuai metode terpilih. **Saya tidak dapat menguji panggilan ini**; cocokkan parameter dengan dokumentasi Xendit terbaru sebelum produksi. Nominal callback divalidasi terhadap tagihan; callback diproses idempoten (`webhook_logs`).
- Untuk lokal, expose webhook dengan tunnel (mis. ngrok).

## 5. Android
1. Android Studio → **Open** `MULTI_TRAVEL_ANDROID` (biarkan Gradle sync; wrapper akan diunduh).
2. `app/build.gradle.kts` → `BASE_URL` debug sudah `http://10.0.2.2/MULTI_TRAVEL_BACKEND/api/v1/` (emulator → PC). HP fisik: pakai IP LAN PC + tambahkan di `res/xml/network_security_config.xml`.
3. Produksi: ganti `BASE_URL` release ke `https://domain-anda/…` (HTTP otomatis diblokir di release).
4. Pembayaran Xendit dibuka di browser (Custom Tab/Chrome); status diperbarui otomatis saat kembali ke aplikasi.

## 6. Checklist Uji (urut)
1. `/api/v1/health` OK → import DB tanpa error.
2. Login dashboard admin; buat travel; verifikasi; buat paket+jadwal; setujui.
3. `GET /api/v1/packages` mengembalikan paket.
4. Register → OTP → login → `POST /bookings/preview` → `POST /bookings`.
5. **Uji kuota**: kuota 1, dua akun memesan bersamaan → hanya satu berhasil.
6. Transfer manual: buat tagihan → unggah bukti → travel verifikasi → status `paid`, komisi `earned`.
7. DP: pilih DP → bayar DP (`partially_paid`) → bayar pelunasan (`paid`).
8. Biarkan booking tak dibayar melewati batas → jalankan cron → status `expired`, kuota kembali.
9. Xendit sandbox: bayar invoice → webhook → booking `paid`.
10. Isolasi: login travel A, coba buka `/partner/bookings/show/{id booking travel B}` → harus 404.

## 7. Yang SUDAH ada
Autentikasi token + OTP, RBAC (5 peran, staff dengan permission per orang), katalog (destinasi/paket + filter/sort/pagination), booking multi-peserta dengan **transaksi + lock kuota**, harga/diskon/promo/DP/komisi hybrid (snapshot) di server, pembayaran Xendit + transfer manual + verifikasi, kedaluwarsa & pembebasan kuota (cron), refund (permintaan→setuju→selesai), pencairan mitra, review, wishlist, notifikasi in-app, audit log, rate limiting, CSRF (dashboard), upload tervalidasi, file pribadi hanya via controller dengan cek kepemilikan, dashboard admin & mitra (CRUD, grafik, DataTables), Android (splash, login/daftar/OTP/lupa password, beranda, jelajah+filter, destinasi, detail paket termasuk Haji/Umroh, alur booking 4 langkah, bayar, bukti transfer, riwayat/detail booking, favorit, notifikasi, profil).

## 8. BELUM ada / perlu Anda tangani
- **Pengiriman OTP nyata** (WhatsApp gateway/SMTP): `libraries/Notifier.php::send_otp` masih stub (OTP hanya di log/response saat `otp_debug=true`). **Matikan `otp_debug` di produksi.**
- **Push FCM**: token perangkat disimpan, tetapi pengiriman FCM (butuh service account) belum disambungkan; notifikasi tersedia in-app.
- Export **Excel/PDF** native (saat ini CSV); halaman laporan lebih rinci.
- Reset password staff/admin dari dashboard, 2FA, unggah dokumen peserta dari aplikasi (API ada, UI Android belum), unduh e-ticket di aplikasi (API ada).
- Layer `domain` Android hanya `UiState` (model UI = DTO); tanpa use case terpisah. Tanpa unit/UI test.
- Refund & pencairan: **transfer uang dilakukan manual di luar sistem**; sistem mencatat status/bukti.
- Kebijakan potongan pembatalan otomatis (saat ini admin menentukan potongan saat menyetujui refund).
- Tinjau hukum/regulasi (PPIU/PIHK, perlindungan data pribadi/UU PDP) dan enkripsi kolom NIK/paspor sebelum produksi.

## 9. Keamanan produksi (ringkas)
HTTPS wajib; set `base_url` tetap; `CI_ENV=production` (`SetEnv CI_ENV production` di Apache); `otp_debug=false`; ganti `encryption_key` & password admin; folder `application/` & `uploads/private/` tidak boleh dapat diakses web (sudah ada `.htaccess`, pastikan berfungsi — uji dengan membuka URL-nya); batasi akses phpMyAdmin; backup DB terjadwal; pantau `application/logs`.
