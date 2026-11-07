-- =====================================================================
-- MULTI TRAVEL - SEEDERS (data master awal)
-- Jalankan SETELAH database.sql. Tidak berisi data dummy transaksi.
-- =====================================================================
SET NAMES utf8mb4;
USE `multi_travel`;

-- ---------------- ROLES ----------------
INSERT INTO `roles` (`code`,`name`,`scope`,`description`) VALUES
('super_admin','Super Admin','platform','Akses penuh ke seluruh sistem'),
('admin_platform','Admin Platform','platform','Mengelola marketplace (tanpa pengaturan sistem & role)'),
('admin_travel','Admin Travel','partner','Mengelola seluruh data milik satu travel'),
('staff_travel','Staff Travel','partner','Akses sesuai permission dari Admin Travel'),
('customer','Customer','customer','Pengguna aplikasi Android');

-- ---------------- PERMISSIONS ----------------
INSERT INTO `permissions` (`code`,`module`,`name`,`scope`) VALUES
('dashboard.view','dashboard','Lihat dashboard','both'),
-- platform
('partner.view','partner','Lihat travel mitra','platform'),
('partner.create','partner','Tambah travel mitra','platform'),
('partner.update','partner','Ubah travel mitra','platform'),
('partner.delete','partner','Nonaktifkan travel mitra','platform'),
('partner.verify','partner','Verifikasi mitra & dokumen legalitas','platform'),
('customer.view','customer','Lihat customer','platform'),
('customer.update','customer','Ubah / blokir customer','platform'),
('user.view','user','Lihat user admin','platform'),
('user.manage','user','Kelola user admin','platform'),
('role.manage','role','Kelola role & permission','platform'),
('category.manage','category','Kelola kategori destinasi & paket','platform'),
('package.approve','package','Setujui / tolak paket','platform'),
('commission.view','commission','Lihat komisi','both'),
('commission.manage','commission','Atur aturan komisi','platform'),
('payout.manage','payout','Proses pencairan mitra','platform'),
('content.manage','content','Kelola banner, artikel, FAQ','platform'),
('setting.manage','setting','Pengaturan sistem','platform'),
('audit.view','audit','Lihat audit log','platform'),
-- bersama (platform = semua mitra, partner = miliknya)
('destination.view','destination','Lihat destinasi','both'),
('destination.create','destination','Tambah destinasi','both'),
('destination.update','destination','Ubah destinasi','both'),
('destination.delete','destination','Hapus destinasi','both'),
('package.view','package','Lihat paket','both'),
('package.create','package','Tambah paket','both'),
('package.update','package','Ubah paket','both'),
('package.delete','package','Hapus / nonaktifkan paket','both'),
('schedule.manage','schedule','Kelola jadwal, harga & kuota','both'),
('promotion.manage','promotion','Kelola promo','both'),
('booking.view','booking','Lihat booking','both'),
('booking.confirm','booking','Konfirmasi / batalkan booking','both'),
('participant.view','booking','Lihat data peserta','both'),
('document.manage','booking','Kelola dokumen perjalanan','both'),
('payment.view','payment','Lihat pembayaran','both'),
('payment.verify','payment','Verifikasi pembayaran manual','both'),
('refund.view','refund','Lihat refund','both'),
('refund.process','refund','Proses refund','both'),
('report.view','report','Lihat laporan','both'),
('report.export','report','Export laporan Excel/PDF','both'),
('review.reply','review','Balas review','both'),
-- khusus mitra
('company.update','company','Ubah profil perusahaan','partner'),
('bank.manage','company','Kelola rekening','partner'),
('payout.request','payout','Ajukan pencairan','partner'),
('staff.manage','staff','Kelola staff & permission','partner');

-- ---------------- ROLE -> PERMISSION ----------------
-- Super Admin: semua
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.code='super_admin';

-- Admin Platform: semua yang bukan khusus mitra & bukan pengaturan sistem/role
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code='admin_platform' AND p.scope IN ('platform','both')
  AND p.code NOT IN ('role.manage','setting.manage');

-- Admin Travel: semua permission scope partner/both, kecuali yang hanya untuk platform
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code='admin_travel' AND p.scope IN ('partner','both');

-- Staff Travel: batas maksimum permission yang BOLEH diberikan Admin Travel
-- (pemberian nyata per staff disimpan di partner_staff_permissions)
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code='staff_travel' AND p.code IN (
 'dashboard.view','destination.view','destination.create','destination.update',
 'package.view','package.create','package.update','schedule.manage','promotion.manage',
 'booking.view','booking.confirm','participant.view','document.manage',
 'payment.view','payment.verify','refund.view','report.view','review.reply','commission.view');

-- ---------------- KATEGORI ----------------
INSERT INTO `package_categories` (`type`,`name`,`slug`,`icon`,`sort_order`) VALUES
('local','Wisata Lokal','wisata-lokal','ic_local',1),
('international','Wisata Luar Negeri','wisata-luar-negeri','ic_international',2),
('hajj','Haji','haji','ic_hajj',3),
('umrah','Umroh','umroh','ic_umrah',4);

INSERT INTO `destination_categories` (`name`,`slug`,`icon`,`sort_order`) VALUES
('Pantai','pantai','ic_beach',1),
('Gunung','gunung','ic_mountain',2),
('Budaya & Sejarah','budaya-sejarah','ic_culture',3),
('Kota','kota','ic_city',4),
('Alam & Taman Nasional','alam-taman-nasional','ic_nature',5),
('Religi','religi','ic_religion',6),
('Kuliner','kuliner','ic_food',7);

-- ---------------- KOMISI GLOBAL (default 5%) ----------------
INSERT INTO `commission_rules` (`scope`,`model`,`percentage`,`fixed_amount`,`is_active`,`note`)
VALUES ('global','percentage',5.00,0.00,1,'Aturan komisi default platform');

-- ---------------- METODE PEMBAYARAN ----------------
INSERT INTO `payment_methods` (`code`,`name`,`group_type`,`provider`,`provider_code`,`fee_fixed`,`fee_percent`,`sort_order`) VALUES
('VA_BCA','Virtual Account BCA','virtual_account','xendit','BCA',0,0,1),
('VA_BNI','Virtual Account BNI','virtual_account','xendit','BNI',0,0,2),
('VA_BRI','Virtual Account BRI','virtual_account','xendit','BRI',0,0,3),
('VA_MANDIRI','Virtual Account Mandiri','virtual_account','xendit','MANDIRI',0,0,4),
('QRIS','QRIS','qris','xendit','QRIS',0,0,5),
('EWALLET_OVO','OVO','ewallet','xendit','OVO',0,0,6),
('EWALLET_DANA','DANA','ewallet','xendit','DANA',0,0,7),
('EWALLET_SHOPEEPAY','ShopeePay','ewallet','xendit','SHOPEEPAY',0,0,8),
('MANUAL_TRANSFER','Transfer Bank Manual','bank_transfer','manual',NULL,0,0,9);
-- Biaya channel diisi 0; sesuaikan dengan kontrak Xendit Anda sebelum produksi.

-- ---------------- SETTINGS ----------------
INSERT INTO `settings` (`setting_group`,`setting_key`,`setting_value`,`value_type`,`is_public`,`description`) VALUES
('general','app_name','MultiTravel','string',1,'Nama aplikasi'),
('general','app_tagline','Jelajahi Dunia, Wujudkan Impian','string',1,'Tagline'),
('general','support_whatsapp','','string',1,'Nomor WhatsApp bantuan (isi sebelum rilis)'),
('general','support_email','','string',1,'Email bantuan (isi sebelum rilis)'),
('booking','payment_expiry_hours','24','int',1,'Batas waktu pembayaran pertama (jam)'),
('booking','default_dp_percent','30','decimal',1,'DP minimum default (%)'),
('booking','default_full_payment_days_before','14','int',1,'Pelunasan paling lambat H-n'),
('booking','max_participants_per_booking','10','int',1,'Maksimal peserta per booking'),
('booking','expire_cron_interval_minutes','5','int',0,'Interval cron pembebasan kuota'),
('commission','payout_hold_days','7','int',0,'Hari tahan komisi setelah perjalanan selesai sebelum dapat dicairkan'),
('commission','min_payout_amount','500000','decimal',0,'Minimal pencairan'),
('security','api_token_ttl_days','30','int',0,'Masa berlaku token API'),
('security','otp_ttl_minutes','5','int',0,'Masa berlaku OTP'),
('security','otp_max_attempts','5','int',0,'Maksimal percobaan OTP'),
('security','login_max_attempts','5','int',0,'Maksimal gagal login sebelum dikunci'),
('security','login_lock_minutes','15','int',0,'Durasi kunci akun'),
('payment','xendit_environment','sandbox','string',0,'sandbox | production (API key & webhook token diisi di config/.env, bukan di database)');

-- ---------------- FAQ AWAL ----------------
INSERT INTO `faqs` (`category`,`question`,`answer`,`sort_order`) VALUES
('booking','Berapa lama batas waktu pembayaran?','Pembayaran pertama harus dilakukan sebelum batas waktu yang tertera pada detail booking. Jika terlewat, booking otomatis kedaluwarsa dan kuota dilepas.',1),
('pembayaran','Apakah bisa membayar DP terlebih dahulu?','Bisa. Pilih opsi DP saat booking, lalu lakukan pelunasan sebelum batas tanggal yang tertera.',2),
('pembayaran','Bagaimana jika saya transfer manual?','Unggah bukti transfer pada halaman pembayaran. Pihak travel akan memverifikasi pembayaran Anda.');

-- ---------------- AKUN SUPER ADMIN AWAL ----------------
-- Email   : admin@multitravel.local
-- Password: Admin#12345   (hash bcrypt di bawah; WAJIB diganti saat login pertama)
INSERT INTO `users` (`user_type`,`full_name`,`email`,`phone`,`password_hash`,`status`,`email_verified_at`,`phone_verified_at`)
VALUES ('super_admin','Super Admin','admin@multitravel.local','6280000000000',
        '$2b$10$WBgkS59S.kvepi/BUHggW.FKEZt36zfiwnyFJdyezyj3dr7/0tjR6','active',NOW(),NOW());

INSERT INTO `user_profiles` (`user_id`) SELECT id FROM users WHERE email='admin@multitravel.local';

INSERT INTO `user_roles` (`user_id`,`role_id`)
SELECT u.id, r.id FROM users u JOIN roles r ON r.code='super_admin' WHERE u.email='admin@multitravel.local';
