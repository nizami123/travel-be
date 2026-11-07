# Ringkasan REST API v1

Base URL: `http(s)://HOST/MULTI_TRAVEL_BACKEND/api/v1/`
Header auth: `Authorization: Bearer <token>` (token dari login / verify-otp).
Respons: `{ "success": bool, "message": str, "data": ..., "meta": {page,per_page,total,total_pages}, "errors": {...}|null }`

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | /health | - | cek server |
| GET | /home | opsional | banner, kategori, destinasi populer, paket populer/promo, mitra, jadwal terdekat, artikel |
| POST | /auth/register | - | nama, email, phone, password → OTP |
| POST | /auth/verify-otp | - | identifier, code → token |
| POST | /auth/resend-otp | - | |
| POST | /auth/login | - | identifier(email/HP), password |
| POST | /auth/logout | ✓ | |
| POST | /auth/forgot-password, /auth/reset-password | - | OTP |
| GET/PUT | /profile | ✓ | |
| POST | /profile/password | ✓ | |
| GET | /destinations | - | q, scope, country, province, city, category_id, popular, page, per_page |
| GET | /destinations/{id} | - | detail + galeri + paket terkait |
| GET | /packages | - | q, category(local\|international\|hajj\|umrah\|id), location, destination_id, partner_id, min_price, max_price, min_days, max_days, date_from, date_to, sort(popular\|price_asc\|price_desc\|newest\|rating) |
| GET | /packages/{id} | opsional | detail + jadwal + harga + kuota + itinerary + info haji/umroh |
| GET | /packages/{id}/reviews | - | |
| GET | /partners/{id} | - | profil travel + paketnya |
| POST | /bookings/preview | ✓ | hitung harga/diskon/DP (server) tanpa menyimpan |
| POST | /bookings | ✓ | buat booking (transaksi + lock kuota) |
| GET | /bookings, /bookings/{id} | ✓ | |
| POST | /bookings/{id}/cancel | ✓ | |
| POST | /bookings/{id}/documents | ✓ | multipart: doc_type, participant_id, file |
| GET | /payment-methods | ✓ | |
| POST | /bookings/{id}/payments | ✓ | payment_method_id → tagihan (DP/lunas/pelunasan ditentukan server) |
| GET | /payments/{id} | ✓ | |
| POST | /payments/{id}/proof | ✓ | multipart `proof` (transfer manual) |
| GET | /wishlist, POST /wishlist/toggle | ✓ | |
| POST | /reviews | ✓ | booking selesai saja |
| GET | /notifications, POST /notifications/read-all | ✓ | |
| POST | /device-token | ✓ | simpan token FCM |
| POST | /webhook/xendit | token callback | dipanggil Xendit |

## Contoh body booking
```json
{
  "schedule_id": 1,
  "payment_option": "dp",
  "promo_code": "HEMAT10",
  "participants": [
    {"full_name":"Andi Pratama","gender":"male","birth_date":"1990-05-01","id_number":"3524...","price_code":"adult"}
  ]
}
```
Harga, diskon, kuota, DP, dan komisi **tidak pernah** dipercaya dari klien.
