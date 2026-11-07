-- =====================================================================
-- MULTI TRAVEL - DATA DUMMY UMROH (untuk uji coba aplikasi Android)
-- Jalankan SETELAH database.sql dan seeders.sql, SEKALI SAJA.
-- Isi: 1 travel mitra terverifikasi, 4 destinasi Tanah Suci, 3 paket umroh,
--      jadwal keberangkatan (tanggal relatif dari hari ini), harga per tipe kamar, kuota, 1 banner.
-- Gambar placeholder ada di uploads/public/ (destinations, packages, banners, partners).
-- =====================================================================
SET NAMES utf8mb4;
USE `multi_travel`;

-- ---------------- TRAVEL MITRA ----------------
INSERT INTO `travel_partners`
 (`code`,`name`,`slug`,`logo_path`,`description`,`email`,`phone`,`whatsapp`,`website`,`address`,`city`,`province`,
  `business_license_no`,`ppiu_number`,`pihk_number`,`verification_status`,`verified_at`,`status`,`is_featured`,`rating_avg`,`rating_count`)
VALUES
 ('AL-HIDAYAH','Al-Hidayah Tour & Travel','al-hidayah-tour-travel','partners/al-hidayah-logo.jpg',
  'Penyelenggara perjalanan ibadah umroh sejak 2008. Berizin resmi PPIU Kemenag RI, pembimbing berpengalaman, dan layanan jamaah sepenuh hati.',
  'info@alhidayah-travel.test','0215551234','628123456789','https://alhidayah-travel.test',
  'Jl. Raya Kebayoran Lama No. 88, Jakarta Selatan','Jakarta Selatan','DKI Jakarta',
  'NIB-1234567890123','U.123 Tahun 2023','PIHK.45 Tahun 2023','verified',NOW(),'active',1,4.80,288);
SET @partner = LAST_INSERT_ID();

INSERT INTO `partner_bank_accounts` (`travel_partner_id`,`bank_code`,`bank_name`,`account_number`,`account_holder`,`purpose`,`is_primary`,`status`)
VALUES (@partner,'BCA','Bank BCA','1234567890','PT Al-Hidayah Wisata Ibadah','both',1,'active');

SET @cat_umrah = (SELECT id FROM package_categories WHERE slug='umroh' LIMIT 1);
SET @cat_religi = (SELECT id FROM destination_categories WHERE slug='religi' LIMIT 1);

-- ---------------- DESTINASI ----------------
INSERT INTO `destinations`
 (`category_id`,`scope`,`name`,`slug`,`description`,`country`,`province`,`city`,`address`,`latitude`,`longitude`,`google_maps_url`,
  `facilities`,`additional_info`,`cover_path`,`rating_avg`,`rating_count`,`is_popular`,`status`)
VALUES
 (@cat_religi,'hajj_umrah','Makkah Al-Mukarramah','makkah-al-mukarramah',
  'Kota suci umat Islam, tempat berdirinya Ka\'bah di Masjidil Haram. Tujuan utama ibadah umroh dan haji dengan thawaf, sa\'i antara Shafa dan Marwah, serta ziarah ke situs-situs bersejarah.',
  'Arab Saudi','Makkah','Makkah','Masjidil Haram, Makkah 24231',21.4224779,39.8262012,'https://maps.google.com/?q=Masjidil+Haram',
  JSON_ARRAY('Masjidil Haram','Air Zamzam','Pusat perbelanjaan','Transportasi bus','Hotel dekat Haram'),
  'Pakaian ihram wajib dikenakan dari miqat. Siapkan fisik, banyak berjalan kaki. Suhu siang hari dapat sangat panas.',
  'destinations/makkah.jpg',4.90,512,1,'published'),
 (@cat_religi,'hajj_umrah','Madinah Al-Munawwarah','madinah-al-munawwarah',
  'Kota Rasulullah SAW, tempat Masjid Nabawi dan Raudhah. Jamaah umroh umumnya menetap beberapa hari untuk sholat arbain dan ziarah ke Quba, Uhud, serta Qiblatain.',
  'Arab Saudi','Madinah','Madinah','Masjid Nabawi, Madinah 42311',24.4672132,39.6111724,'https://maps.google.com/?q=Masjid+Nabawi',
  JSON_ARRAY('Masjid Nabawi','Raudhah','Masjid Quba','Pasar kurma','Hotel dekat Masjid'),
  'Jaga adab di Masjid Nabawi. Waktu masuk Raudhah diatur terpisah untuk jamaah pria dan wanita.',
  'destinations/madinah.jpg',4.90,447,1,'published'),
 (@cat_religi,'hajj_umrah','Jeddah','jeddah',
  'Kota pelabuhan di tepi Laut Merah dan gerbang utama jamaah menuju Tanah Suci. Wisata singkat meliputi Corniche, Masjid Terapung, dan pasar Balad.',
  'Arab Saudi','Makkah','Jeddah','Jeddah',21.4858110,39.1925048,'https://maps.google.com/?q=Jeddah+Corniche',
  JSON_ARRAY('Bandara King Abdulaziz','Corniche','Masjid Terapung','Pasar Al-Balad'),
  'Cuaca lembap dan hangat sepanjang tahun.',
  'destinations/jeddah.jpg',4.50,203,0,'published'),
 (@cat_religi,'hajj_umrah','Thaif','thaif',
  'Kota sejuk di pegunungan sekitar 90 km dari Makkah. Terkenal dengan kebun anggur, madu, mawar Thaif, dan cable car Al Hada.',
  'Arab Saudi','Makkah','Thaif','Thaif',21.2703000,40.4158000,'https://maps.google.com/?q=Taif+Al+Hada',
  JSON_ARRAY('Cable car Al Hada','Kebun anggur','Pasar mawar','Masjid Abdullah bin Abbas'),
  'Suhu lebih dingin dari Makkah; bawa jaket tipis.',
  'destinations/thaif.jpg',4.60,176,0,'published');
SET @d_makkah  = (SELECT id FROM destinations WHERE slug='makkah-al-mukarramah');
SET @d_madinah = (SELECT id FROM destinations WHERE slug='madinah-al-munawwarah');
SET @d_jeddah  = (SELECT id FROM destinations WHERE slug='jeddah');
SET @d_thaif   = (SELECT id FROM destinations WHERE slug='thaif');

INSERT INTO `destination_media` (`destination_id`,`media_type`,`file_path`,`caption`,`sort_order`) VALUES
 (@d_makkah,'image','destinations/makkah.jpg','Masjidil Haram',1),
 (@d_madinah,'image','destinations/madinah.jpg','Masjid Nabawi',1),
 (@d_jeddah,'image','destinations/jeddah.jpg','Jeddah',1),
 (@d_thaif,'image','destinations/thaif.jpg','Thaif',1);

-- ======================= PAKET: Umroh Reguler 9 Hari Hemat Berkah =======================
INSERT INTO `travel_packages`
 (`travel_partner_id`,`category_id`,`code`,`name`,`slug`,`summary`,`description`,`duration_days`,`duration_nights`,`start_city`,
  `min_participants`,`max_participants_per_booking`,`transportation`,`accommodation`,`terms_conditions`,`cancellation_policy`,
  `required_documents`,`dp_percent`,`full_payment_days_before`,`whatsapp_number`,`cover_path`,`base_price`,
  `view_count`,`booking_count`,`rating_avg`,`rating_count`,`approval_status`,`approved_at`,`status`)
VALUES
 (@partner,@cat_umrah,'UMR-REG-9H','Umroh Reguler 9 Hari Hemat Berkah','umroh-reguler-9-hari','Paket umroh reguler 9 hari, hotel bintang 4 dekat Masjidil Haram, bimbingan mutawwif berpengalaman.',
  'Program umroh 9 hari 7 malam bersama Al-Hidayah Tour & Travel. Jamaah dibimbing mutawwif berpengalaman sejak manasik di Tanah Air hingga kembali. Termasuk visa, tiket pesawat PP, hotel, makan 3 kali sehari, transportasi bus AC selama di Arab Saudi, dan perlengkapan umroh.',
  9,7,'Jakarta',2,10,
  'Pesawat Saudia Airlines Jakarta - Madinah/Jeddah PP, bus AC selama di Arab Saudi.',
  'Makkah: Elaf Al Mashaer Hotel. Madinah: Dar Al Eiman Royal Hotel.',
  'Peserta wajib berusia minimal 12 tahun, memiliki paspor berlaku minimal 8 bulan, dan sudah vaksin meningitis. Wanita di bawah 45 tahun wajib bersama mahram. Pelunasan paling lambat 30 hari sebelum keberangkatan.',
  'Pembatalan H-60: potong 10%. H-59 s.d. H-30: potong 30%. H-29 s.d. H-14: potong 50%. Kurang dari H-14: tidak ada pengembalian dana. Biaya visa yang sudah terbit tidak dapat dikembalikan.',
  JSON_ARRAY('paspor','ktp','kk','pas_foto','buku_nikah','vaksin_meningitis'),
  30.00,30,'628123456789','packages/umroh-reguler-9h.jpg',28500000,
  1926,214,4.7,128,'approved',NOW(),'active');
SET @p1 = LAST_INSERT_ID();

INSERT INTO `package_hajj_umrah_details`
 (`package_id`,`program_type`,`ppiu_pihk_number`,`ppiu_pihk_name`,`airline`,`departure_airport`,`hotel_makkah`,`hotel_makkah_star`,`hotel_makkah_distance_m`,
  `hotel_madinah`,`hotel_madinah_star`,`hotel_madinah_distance_m`,`mutawwif_name`,`manasik_info`,`visa_info`,`room_types`,`extra_facilities`)
VALUES
 (@p1,'umrah_reguler','U.123 Tahun 2023','Al-Hidayah Tour & Travel','Saudia Airlines','Soekarno-Hatta (CGK), Tangerang',
  'Elaf Al Mashaer Hotel',4,300,'Dar Al Eiman Royal Hotel',4,250,'Ustadz H. Abdurrahman, Lc.',
  'Manasik 2 kali (1 daring, 1 tatap muka) di Jakarta, serta bimbingan langsung selama di Tanah Suci.',
  'Visa umroh diurus travel, terbit paling lambat 14 hari sebelum keberangkatan.',
  JSON_ARRAY('quad','triple','double'),'Koper, tas paspor, kain ihram/mukena, buku doa, air zamzam 5 liter, city tour.');

INSERT INTO `package_destinations` (`package_id`,`destination_id`,`sort_order`) VALUES
 (@p1,@d_makkah,1),
 (@p1,@d_madinah,2),
 (@p1,@d_jeddah,3);
INSERT INTO `package_media` (`package_id`,`media_type`,`file_path`,`caption`,`sort_order`) VALUES
 (@p1,'image','packages/umroh-reguler-9h.jpg','Umroh Reguler 9 Hari Hemat Berkah',1);
INSERT INTO `package_itineraries` (`package_id`,`day_number`,`title`,`description`,`meals`,`accommodation`) VALUES
 (@p1,1,'Berangkat dari Jakarta','Kumpul di Bandara Soekarno-Hatta, check-in, penerbangan menuju Madinah.','D','Pesawat'),
 (@p1,2,'Tiba di Madinah','Check-in hotel, istirahat, sholat berjamaah di Masjid Nabawi.','B,L,D','Hotel Madinah'),
 (@p1,3,'Ziarah Madinah','Raudhah, Masjid Quba, Jabal Uhud, dan kebun kurma.','B,L,D','Hotel Madinah'),
 (@p1,4,'Madinah - Makkah','Perjalanan ke Makkah, miqat di Bir Ali, umroh pertama (thawaf, sa\'i, tahallul).','B,L,D','Hotel Makkah'),
 (@p1,5,'Ibadah di Makkah','Ibadah mandiri di Masjidil Haram, tersedia bimbingan manasik.','B,L,D','Hotel Makkah'),
 (@p1,6,'Ziarah Makkah','Jabal Rahmah, Arafah, Muzdalifah, Mina, Jabal Tsur.','B,L,D','Hotel Makkah'),
 (@p1,7,'Ibadah & Belanja','Ibadah mandiri dan waktu bebas untuk belanja oleh-oleh.','B,L,D','Hotel Makkah'),
 (@p1,8,'Thawaf Wada\' ke Jeddah','Thawaf wada\', city tour Jeddah, menuju Bandara King Abdulaziz.','B,L','Pesawat'),
 (@p1,9,'Tiba di Tanah Air','Tiba di Jakarta, perjalanan berakhir.','-','-');
INSERT INTO `package_facilities` (`package_id`,`facility_type`,`name`,`icon`,`sort_order`) VALUES
 (@p1,'included','Tiket pesawat PP','transport',1),
 (@p1,'included','Visa umroh','document',2),
 (@p1,'included','Hotel Makkah & Madinah','hotel',3),
 (@p1,'included','Makan 3x sehari','meal',4),
 (@p1,'included','Bus AC selama di Arab Saudi','transport',5),
 (@p1,'included','Mutawwif & tour leader','guide',6),
 (@p1,'included','Manasik & perlengkapan umroh','guide',7),
 (@p1,'included','Air zamzam 5 liter','other',8),
 (@p1,'excluded','Pengurusan paspor','document',20),
 (@p1,'excluded','Vaksin meningitis','other',21),
 (@p1,'excluded','Pengeluaran pribadi','other',22),
 (@p1,'excluded','Tips guide & driver','other',23);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p1,@partner,DATE_ADD(CURDATE(),INTERVAL 35 DAY),DATE_ADD(CURDATE(),INTERVAL 43 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 25 DAY),'open');
SET @p1s1 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p1s1,'quad','Quad (4 orang/kamar)',28500000,31000000,1,'active'),
 (@p1s1,'triple','Triple (3 orang/kamar)',30500000,33000000,0,'active'),
 (@p1s1,'double','Double (2 orang/kamar)',33500000,36000000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p1s1,45,0,8);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p1,@partner,DATE_ADD(CURDATE(),INTERVAL 63 DAY),DATE_ADD(CURDATE(),INTERVAL 71 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 53 DAY),'open');
SET @p1s2 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p1s2,'quad','Quad (4 orang/kamar)',28500000,31000000,1,'active'),
 (@p1s2,'triple','Triple (3 orang/kamar)',30500000,33000000,0,'active'),
 (@p1s2,'double','Double (2 orang/kamar)',33500000,36000000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p1s2,45,0,12);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p1,@partner,DATE_ADD(CURDATE(),INTERVAL 98 DAY),DATE_ADD(CURDATE(),INTERVAL 106 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 88 DAY),'open');
SET @p1s3 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p1s3,'quad','Quad (4 orang/kamar)',28500000,31000000,1,'active'),
 (@p1s3,'triple','Triple (3 orang/kamar)',30500000,33000000,0,'active'),
 (@p1s3,'double','Double (2 orang/kamar)',33500000,36000000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p1s3,40,0,3);

-- ======================= PAKET: Umroh Plus Thaif 10 Hari Nyaman =======================
INSERT INTO `travel_packages`
 (`travel_partner_id`,`category_id`,`code`,`name`,`slug`,`summary`,`description`,`duration_days`,`duration_nights`,`start_city`,
  `min_participants`,`max_participants_per_booking`,`transportation`,`accommodation`,`terms_conditions`,`cancellation_policy`,
  `required_documents`,`dp_percent`,`full_payment_days_before`,`whatsapp_number`,`cover_path`,`base_price`,
  `view_count`,`booking_count`,`rating_avg`,`rating_count`,`approval_status`,`approved_at`,`status`)
VALUES
 (@partner,@cat_umrah,'UMR-PLS-10H','Umroh Plus Thaif 10 Hari Nyaman','umroh-plus-thaif-10-hari','Umroh plus wisata Thaif, penerbangan Garuda Indonesia langsung, hotel bintang 5 di kawasan Haram.',
  'Program umroh 10 hari 8 malam bersama Al-Hidayah Tour & Travel. Jamaah dibimbing mutawwif berpengalaman sejak manasik di Tanah Air hingga kembali. Termasuk visa, tiket pesawat PP, hotel, makan 3 kali sehari, transportasi bus AC selama di Arab Saudi, dan perlengkapan umroh.',
  10,8,'Jakarta',2,10,
  'Pesawat Garuda Indonesia Jakarta - Madinah/Jeddah PP, bus AC selama di Arab Saudi.',
  'Makkah: Swissotel Makkah. Madinah: Anwar Al Madinah Movenpick.',
  'Peserta wajib berusia minimal 12 tahun, memiliki paspor berlaku minimal 8 bulan, dan sudah vaksin meningitis. Wanita di bawah 45 tahun wajib bersama mahram. Pelunasan paling lambat 30 hari sebelum keberangkatan.',
  'Pembatalan H-60: potong 10%. H-59 s.d. H-30: potong 30%. H-29 s.d. H-14: potong 50%. Kurang dari H-14: tidak ada pengembalian dana. Biaya visa yang sudah terbit tidak dapat dikembalikan.',
  JSON_ARRAY('paspor','ktp','kk','pas_foto','buku_nikah','vaksin_meningitis'),
  30.00,30,'628123456789','packages/umroh-plus-thaif-10h.jpg',34900000,
  1359,151,4.8,96,'approved',NOW(),'active');
SET @p2 = LAST_INSERT_ID();

INSERT INTO `package_hajj_umrah_details`
 (`package_id`,`program_type`,`ppiu_pihk_number`,`ppiu_pihk_name`,`airline`,`departure_airport`,`hotel_makkah`,`hotel_makkah_star`,`hotel_makkah_distance_m`,
  `hotel_madinah`,`hotel_madinah_star`,`hotel_madinah_distance_m`,`mutawwif_name`,`manasik_info`,`visa_info`,`room_types`,`extra_facilities`)
VALUES
 (@p2,'umrah_plus','U.123 Tahun 2023','Al-Hidayah Tour & Travel','Garuda Indonesia','Soekarno-Hatta (CGK), Tangerang',
  'Swissotel Makkah',5,150,'Anwar Al Madinah Movenpick',4,200,'Ustadz H. Abdurrahman, Lc.',
  'Manasik 2 kali (1 daring, 1 tatap muka) di Jakarta, serta bimbingan langsung selama di Tanah Suci.',
  'Visa umroh diurus travel, terbit paling lambat 14 hari sebelum keberangkatan.',
  JSON_ARRAY('quad','triple','double'),'Koper, tas paspor, kain ihram/mukena, buku doa, air zamzam 5 liter, city tour.');

INSERT INTO `package_destinations` (`package_id`,`destination_id`,`sort_order`) VALUES
 (@p2,@d_makkah,1),
 (@p2,@d_madinah,2),
 (@p2,@d_thaif,3);
INSERT INTO `package_media` (`package_id`,`media_type`,`file_path`,`caption`,`sort_order`) VALUES
 (@p2,'image','packages/umroh-plus-thaif-10h.jpg','Umroh Plus Thaif 10 Hari Nyaman',1);
INSERT INTO `package_itineraries` (`package_id`,`day_number`,`title`,`description`,`meals`,`accommodation`) VALUES
 (@p2,1,'Berangkat dari Jakarta','Kumpul di bandara, penerbangan Garuda Indonesia menuju Jeddah.','D','Pesawat'),
 (@p2,2,'Tiba & Menuju Madinah','Tiba di Jeddah, perjalanan darat ke Madinah, check-in hotel.','B,L,D','Hotel Madinah'),
 (@p2,3,'Madinah','Ibadah di Masjid Nabawi, ziarah Raudhah dan Makam Rasulullah.','B,L,D','Hotel Madinah'),
 (@p2,4,'Ziarah Madinah','Masjid Quba, Jabal Uhud, Masjid Qiblatain, Pasar Kurma.','B,L,D','Hotel Madinah'),
 (@p2,5,'Madinah - Makkah','Miqat Bir Ali, umroh pertama, check-in hotel dekat Haram.','B,L,D','Hotel Makkah'),
 (@p2,6,'Ibadah di Makkah','Ibadah mandiri dan bimbingan manasik di Masjidil Haram.','B,L,D','Hotel Makkah'),
 (@p2,7,'Wisata Thaif','Cable car Al Hada, kebun anggur dan madu, Masjid Abdullah bin Abbas.','B,L,D','Hotel Makkah'),
 (@p2,8,'Ziarah Makkah','Jabal Rahmah, Arafah, Muzdalifah, Mina, Jabal Tsur.','B,L,D','Hotel Makkah'),
 (@p2,9,'Thawaf Wada\' & Jeddah','Thawaf wada\', city tour Jeddah, menuju bandara.','B,L','Pesawat'),
 (@p2,10,'Tiba di Tanah Air','Tiba di Jakarta.','-','-');
INSERT INTO `package_facilities` (`package_id`,`facility_type`,`name`,`icon`,`sort_order`) VALUES
 (@p2,'included','Tiket pesawat PP','transport',1),
 (@p2,'included','Visa umroh','document',2),
 (@p2,'included','Hotel Makkah & Madinah','hotel',3),
 (@p2,'included','Makan 3x sehari','meal',4),
 (@p2,'included','Bus AC selama di Arab Saudi','transport',5),
 (@p2,'included','Mutawwif & tour leader','guide',6),
 (@p2,'included','Manasik & perlengkapan umroh','guide',7),
 (@p2,'included','Air zamzam 5 liter','other',8),
 (@p2,'excluded','Pengurusan paspor','document',20),
 (@p2,'excluded','Vaksin meningitis','other',21),
 (@p2,'excluded','Pengeluaran pribadi','other',22),
 (@p2,'excluded','Tips guide & driver','other',23);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p2,@partner,DATE_ADD(CURDATE(),INTERVAL 42 DAY),DATE_ADD(CURDATE(),INTERVAL 51 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 32 DAY),'open');
SET @p2s1 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p2s1,'quad','Quad (4 orang/kamar)',34900000,37500000,1,'active'),
 (@p2s1,'triple','Triple (3 orang/kamar)',36900000,39500000,0,'active'),
 (@p2s1,'double','Double (2 orang/kamar)',39900000,42500000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p2s1,40,0,8);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p2,@partner,DATE_ADD(CURDATE(),INTERVAL 77 DAY),DATE_ADD(CURDATE(),INTERVAL 86 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 67 DAY),'open');
SET @p2s2 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p2s2,'quad','Quad (4 orang/kamar)',34900000,37500000,1,'active'),
 (@p2s2,'triple','Triple (3 orang/kamar)',36900000,39500000,0,'active'),
 (@p2s2,'double','Double (2 orang/kamar)',39900000,42500000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p2s2,40,0,12);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p2,@partner,DATE_ADD(CURDATE(),INTERVAL 112 DAY),DATE_ADD(CURDATE(),INTERVAL 121 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 102 DAY),'open');
SET @p2s3 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p2s3,'quad','Quad (4 orang/kamar)',34900000,37500000,1,'active'),
 (@p2s3,'triple','Triple (3 orang/kamar)',36900000,39500000,0,'active'),
 (@p2s3,'double','Double (2 orang/kamar)',39900000,42500000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p2s3,36,0,3);

-- ======================= PAKET: Umroh VIP 12 Hari Bintang 5 Eksklusif =======================
INSERT INTO `travel_packages`
 (`travel_partner_id`,`category_id`,`code`,`name`,`slug`,`summary`,`description`,`duration_days`,`duration_nights`,`start_city`,
  `min_participants`,`max_participants_per_booking`,`transportation`,`accommodation`,`terms_conditions`,`cancellation_policy`,
  `required_documents`,`dp_percent`,`full_payment_days_before`,`whatsapp_number`,`cover_path`,`base_price`,
  `view_count`,`booking_count`,`rating_avg`,`rating_count`,`approval_status`,`approved_at`,`status`)
VALUES
 (@partner,@cat_umrah,'UMR-VIP-12H','Umroh VIP 12 Hari Bintang 5 Eksklusif','umroh-vip-12-hari','Umroh VIP 12 hari, hotel bintang 5 tepat di depan Masjidil Haram, kursi bisnis, kuota terbatas.',
  'Program umroh 12 hari 10 malam bersama Al-Hidayah Tour & Travel. Jamaah dibimbing mutawwif berpengalaman sejak manasik di Tanah Air hingga kembali. Termasuk visa, tiket pesawat PP, hotel, makan 3 kali sehari, transportasi bus AC selama di Arab Saudi, dan perlengkapan umroh.',
  12,10,'Jakarta',2,10,
  'Pesawat Garuda Indonesia (Business) Jakarta - Madinah/Jeddah PP, bus AC selama di Arab Saudi.',
  'Makkah: Fairmont Makkah Clock Royal Tower. Madinah: Oberoi Madinah.',
  'Peserta wajib berusia minimal 12 tahun, memiliki paspor berlaku minimal 8 bulan, dan sudah vaksin meningitis. Wanita di bawah 45 tahun wajib bersama mahram. Pelunasan paling lambat 30 hari sebelum keberangkatan.',
  'Pembatalan H-60: potong 10%. H-59 s.d. H-30: potong 30%. H-29 s.d. H-14: potong 50%. Kurang dari H-14: tidak ada pengembalian dana. Biaya visa yang sudah terbit tidak dapat dikembalikan.',
  JSON_ARRAY('paspor','ktp','kk','pas_foto','buku_nikah','vaksin_meningitis'),
  30.00,30,'628123456789','packages/umroh-vip-12h.jpg',52500000,
  648,72,4.9,64,'approved',NOW(),'active');
SET @p3 = LAST_INSERT_ID();

INSERT INTO `package_hajj_umrah_details`
 (`package_id`,`program_type`,`ppiu_pihk_number`,`ppiu_pihk_name`,`airline`,`departure_airport`,`hotel_makkah`,`hotel_makkah_star`,`hotel_makkah_distance_m`,
  `hotel_madinah`,`hotel_madinah_star`,`hotel_madinah_distance_m`,`mutawwif_name`,`manasik_info`,`visa_info`,`room_types`,`extra_facilities`)
VALUES
 (@p3,'umrah_vip','U.123 Tahun 2023','Al-Hidayah Tour & Travel','Garuda Indonesia (Business)','Soekarno-Hatta (CGK), Tangerang',
  'Fairmont Makkah Clock Royal Tower',5,50,'Oberoi Madinah',5,100,'Ustadz H. Abdurrahman, Lc.',
  'Manasik 2 kali (1 daring, 1 tatap muka) di Jakarta, serta bimbingan langsung selama di Tanah Suci.',
  'Visa umroh diurus travel, terbit paling lambat 14 hari sebelum keberangkatan.',
  JSON_ARRAY('quad','triple','double'),'Koper, tas paspor, kain ihram/mukena, buku doa, air zamzam 5 liter, city tour.');

INSERT INTO `package_destinations` (`package_id`,`destination_id`,`sort_order`) VALUES
 (@p3,@d_makkah,1),
 (@p3,@d_madinah,2),
 (@p3,@d_jeddah,3),
 (@p3,@d_thaif,4);
INSERT INTO `package_media` (`package_id`,`media_type`,`file_path`,`caption`,`sort_order`) VALUES
 (@p3,'image','packages/umroh-vip-12h.jpg','Umroh VIP 12 Hari Bintang 5 Eksklusif',1);
INSERT INTO `package_itineraries` (`package_id`,`day_number`,`title`,`description`,`meals`,`accommodation`) VALUES
 (@p3,1,'Berangkat dari Jakarta','Lounge bandara, penerbangan kelas bisnis menuju Madinah.','D','Pesawat'),
 (@p3,2,'Tiba di Madinah','Check-in hotel bintang 5, ibadah di Masjid Nabawi.','B,L,D','Hotel Madinah'),
 (@p3,3,'Ziarah Madinah','Raudhah, Quba, Uhud, Qiblatain, dengan bus VIP.','B,L,D','Hotel Madinah'),
 (@p3,4,'Ibadah Madinah','Ibadah mandiri, kajian bersama ustadz pembimbing.','B,L,D','Hotel Madinah'),
 (@p3,5,'Madinah - Makkah','Miqat, umroh pertama dengan pendampingan khusus.','B,L,D','Hotel Makkah'),
 (@p3,6,'Ibadah di Makkah','Ibadah mandiri di Masjidil Haram.','B,L,D','Hotel Makkah'),
 (@p3,7,'Ziarah Makkah','Jabal Rahmah, Arafah, Muzdalifah, Mina, Jabal Tsur.','B,L,D','Hotel Makkah'),
 (@p3,8,'Wisata Thaif','Cable car, kebun anggur, dan kuliner khas Thaif.','B,L,D','Hotel Makkah'),
 (@p3,9,'Ibadah di Makkah','Ibadah mandiri, umroh sunnah.','B,L,D','Hotel Makkah'),
 (@p3,10,'Belanja & Ibadah','Waktu bebas belanja di mall sekitar Haram.','B,L,D','Hotel Makkah'),
 (@p3,11,'Thawaf Wada\' ke Jeddah','Thawaf wada\', city tour Jeddah, menuju bandara.','B,L','Pesawat'),
 (@p3,12,'Tiba di Tanah Air','Tiba di Jakarta.','-','-');
INSERT INTO `package_facilities` (`package_id`,`facility_type`,`name`,`icon`,`sort_order`) VALUES
 (@p3,'included','Tiket pesawat PP','transport',1),
 (@p3,'included','Visa umroh','document',2),
 (@p3,'included','Hotel Makkah & Madinah','hotel',3),
 (@p3,'included','Makan 3x sehari','meal',4),
 (@p3,'included','Bus AC selama di Arab Saudi','transport',5),
 (@p3,'included','Mutawwif & tour leader','guide',6),
 (@p3,'included','Manasik & perlengkapan umroh','guide',7),
 (@p3,'included','Air zamzam 5 liter','other',8),
 (@p3,'excluded','Pengurusan paspor','document',20),
 (@p3,'excluded','Vaksin meningitis','other',21),
 (@p3,'excluded','Pengeluaran pribadi','other',22),
 (@p3,'excluded','Tips guide & driver','other',23);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p3,@partner,DATE_ADD(CURDATE(),INTERVAL 56 DAY),DATE_ADD(CURDATE(),INTERVAL 67 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 46 DAY),'open');
SET @p3s1 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p3s1,'triple','Triple (3 orang/kamar)',52500000,56000000,0,'active'),
 (@p3s1,'double','Double (2 orang/kamar)',56500000,60000000,1,'active'),
 (@p3s1,'single','Single (1 orang/kamar)',68500000,72000000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p3s1,20,0,8);
INSERT INTO `package_schedules` (`package_id`,`travel_partner_id`,`departure_date`,`return_date`,`departure_note`,`booking_open_at`,`booking_close_at`,`status`)
VALUES (@p3,@partner,DATE_ADD(CURDATE(),INTERVAL 91 DAY),DATE_ADD(CURDATE(),INTERVAL 102 DAY),'Kumpul di Terminal 3 Soekarno-Hatta, 4 jam sebelum terbang',NOW(),DATE_ADD(CURDATE(),INTERVAL 81 DAY),'open');
SET @p3s2 = LAST_INSERT_ID();
INSERT INTO `schedule_prices` (`schedule_id`,`price_code`,`label`,`price`,`original_price`,`is_default`,`status`) VALUES
 (@p3s2,'triple','Triple (3 orang/kamar)',52500000,56000000,0,'active'),
 (@p3s2,'double','Double (2 orang/kamar)',56500000,60000000,1,'active'),
 (@p3s2,'single','Single (1 orang/kamar)',68500000,72000000,0,'active');
INSERT INTO `package_quotas` (`schedule_id`,`quota_total`,`quota_held`,`quota_booked`) VALUES (@p3s2,20,0,12);

-- ---------------- BANNER BERANDA ----------------
INSERT INTO `banners` (`title`,`subtitle`,`image_path`,`action_type`,`action_value`,`cta_label`,`sort_order`,`status`)
VALUES ('Promo Umroh Awal Tahun','Hemat hingga Rp 2.000.000 untuk pendaftaran bulan ini','banners/umroh-promo.jpg','package',CAST(@p2 AS CHAR),'Lihat Paket',1,'active');

-- ---------------- PROMO (diskon 5% paket Umroh Plus) ----------------
INSERT INTO `promotions`
 (`created_by_partner_id`,`code`,`title`,`description`,`discount_type`,`discount_value`,`max_discount`,`min_purchase`,`quota`,`per_user_limit`,`funded_by`,`applies_to_all`,`starts_at`,`ends_at`,`status`)
VALUES
 (@partner,NULL,'Diskon Umroh Plus 10%','Diskon otomatis 10% untuk Umroh Plus Thaif 10 Hari','percent',10.00,3000000,0,NULL,1,'partner',0,
  DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_ADD(NOW(),INTERVAL 90 DAY),'active');
INSERT INTO `promotion_packages` (`promotion_id`,`package_id`) VALUES (LAST_INSERT_ID(),@p2);

INSERT INTO `promotions`
 (`created_by_partner_id`,`code`,`title`,`description`,`discount_type`,`discount_value`,`max_discount`,`min_purchase`,`quota`,`per_user_limit`,`funded_by`,`applies_to_all`,`starts_at`,`ends_at`,`status`)
VALUES
 (@partner,'UMROH5','Voucher Umroh 5%','Masukkan kode UMROH5 saat memesan paket Al-Hidayah','percent',5.00,2000000,10000000,200,1,'partner',1,
  DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_ADD(NOW(),INTERVAL 90 DAY),'active');
