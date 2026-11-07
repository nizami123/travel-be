# Dokumentasi Database - MultiTravel

## Instalasi
1. Buka phpMyAdmin (MySQL 8.x, bukan MariaDB lama; `CHECK` dan `JSON` dipakai).
2. Import `database.sql` (membuat database `multi_travel` + 51 tabel + 1 view).
3. Import `seeders.sql` (role, permission, kategori, komisi global 5%, metode pembayaran, setting, FAQ, akun Super Admin).
4. Login awal: `admin@multitravel.local` / `Admin#12345`. **Ganti password segera.** Ganti juga email dan nomor telepon.

Jika file terlalu besar untuk phpMyAdmin, naikkan `upload_max_filesize` dan `post_max_size`, atau import via CLI: `mysql -u USER -p < database.sql`.

## Aturan Bisnis yang Didukung Skema

### Kuota (anti overselling)
Saat booking: `SELECT ... FROM package_quotas WHERE schedule_id=? FOR UPDATE` dalam transaksi, cek `quota_total - quota_held - quota_booked >= peserta`, lalu `quota_held += peserta`. Constraint `chk_quota_limit` menjadi pengaman terakhir di level database.

### DP dan Pelunasan
- Paket menyimpan `dp_percent` dan `full_payment_days_before`.
- Booking menyimpan snapshot `dp_percent_snapshot`, `dp_amount`, `paid_amount`, `remaining_amount`, `full_payment_due_date`.
- Pembayaran: `kind='dp'` lalu `kind='settlement'`, atau `kind='full'`.
- Status booking: `pending` -> `partially_paid` (DP masuk) -> `paid` (lunas) -> `completed`.

### Komisi hybrid
Prioritas aturan: **package > partner > global** (aturan aktif dan masih berlaku).
`komisi = base * persen/100 + nominal_tetap`. Contoh: 5.000.000 x 5% + 50.000 = 300.000, bagian travel 4.700.000.
`base_amount` = total setelah diskon yang ditanggung travel (`discount_funded_by`). Hasil dan aturan disalin ke `commissions` sebagai snapshot.

### Pencairan
Komisi berstatus `available` dapat diajukan di `partner_payouts`. `payout_items.commission_id` UNIQUE sehingga satu transaksi tidak bisa dicairkan dua kali.

## Catatan Teknis Penting
- **Isolasi data mitra:** semua query dashboard mitra wajib `WHERE travel_partner_id = <dari session>`. Kolom ini sengaja didenormalisasi di tabel jadwal, booking, payment, refund, komisi, payout, review.
- **MySQL 8 error 3823:** CHECK tidak boleh memakai kolom yang punya FK `CASCADE`/`SET NULL`. Karena itu validasi "scope komisi" dan "tipe wishlist" ditegakkan di aplikasi (model CI3), bukan constraint SQL.
- **Idempotensi webhook:** `webhook_logs` UNIQUE `(provider, event_key, event_type)`; callback yang sama tidak diproses dua kali.
- **Token:** hanya hash SHA-256 token yang disimpan (`api_tokens.token_hash`).
- **Dokumen pribadi:** simpan di `uploads/private/`, tidak dapat diakses via URL langsung, hanya lewat controller yang memeriksa kepemilikan.
- **Data sensitif peserta** (NIK, paspor): disimpan apa adanya. Untuk produksi, pertimbangkan enkripsi level aplikasi (`encryption` library CI3) dan kebijakan retensi.
- **Kredensial:** API key Xendit, callback token, dan FCM key TIDAK ada di database/seeder. Simpan di `application/config/` (di luar git) atau environment.
- Biaya channel pembayaran di seeder bernilai 0; isi sesuai kontrak Xendit.

## Daftar Tabel (51)
**Akses:** users, user_profiles, roles, permissions, user_roles, role_permissions, api_tokens, otp_codes, rate_limits
**Mitra:** travel_partners, travel_partner_users, partner_staff_permissions, travel_partner_documents, partner_bank_accounts
**Destinasi:** destination_categories, destinations, destination_media
**Paket:** package_categories, travel_packages, package_destinations, package_media, package_itineraries, package_facilities, package_hajj_umrah_details, package_schedules, schedule_prices, package_quotas
**Promo & komisi:** promotions, promotion_packages, commission_rules, commissions, partner_payouts, payout_items
**Transaksi:** bookings, booking_participants, booking_documents, payment_methods, payments, payment_status_history, payment_proofs, webhook_logs, refunds
**Engagement:** reviews, wishlists, notifications, device_tokens
**Konten & sistem:** banners, articles, faqs, settings, audit_logs
**View:** v_schedule_availability
