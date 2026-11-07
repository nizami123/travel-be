# ERD - MultiTravel (MySQL 8)

Render dengan Mermaid (GitHub, VS Code, mermaid.live).

```mermaid
erDiagram
  users ||--o| user_profiles : has
  users }o--o{ roles : user_roles
  roles }o--o{ permissions : role_permissions
  users ||--o{ api_tokens : has
  users ||--o{ otp_codes : has

  travel_partners ||--o{ travel_partner_users : has
  users ||--o| travel_partner_users : "milik 1 travel"
  travel_partner_users }o--o{ permissions : partner_staff_permissions
  travel_partners ||--o{ travel_partner_documents : has
  travel_partners ||--o{ partner_bank_accounts : has

  destination_categories ||--o{ destinations : has
  destinations ||--o{ destination_media : has

  package_categories ||--o{ travel_packages : has
  travel_partners ||--o{ travel_packages : owns
  travel_packages }o--o{ destinations : package_destinations
  travel_packages ||--o{ package_media : has
  travel_packages ||--o{ package_itineraries : has
  travel_packages ||--o{ package_facilities : has
  travel_packages ||--o| package_hajj_umrah_details : "haji/umroh"
  travel_packages ||--o{ package_schedules : has
  package_schedules ||--o{ schedule_prices : has
  package_schedules ||--|| package_quotas : has

  promotions }o--o{ travel_packages : promotion_packages
  commission_rules }o--o| travel_partners : scope
  commission_rules }o--o| travel_packages : scope

  users ||--o{ bookings : makes
  package_schedules ||--o{ bookings : for
  promotions ||--o{ bookings : applied
  bookings ||--o{ booking_participants : has
  bookings ||--o{ booking_documents : has
  booking_participants ||--o{ booking_documents : has
  bookings ||--o{ payments : has
  payment_methods ||--o{ payments : via
  payments ||--o{ payment_proofs : has
  payments ||--o{ payment_status_history : logs
  bookings ||--o{ refunds : has
  bookings ||--|| commissions : snapshot
  commission_rules ||--o{ commissions : ref
  partner_payouts ||--o{ payout_items : has
  commissions ||--o| payout_items : paid_by
  travel_partners ||--o{ partner_payouts : receives

  bookings ||--o| reviews : "1 review"
  users ||--o{ wishlists : has
  users ||--o{ notifications : receives
  users ||--o{ device_tokens : has
```

## Tabel mandiri
`settings`, `banners`, `articles`, `faqs`, `audit_logs`, `rate_limits`, `webhook_logs`.

## Konsep kunci
- **Kuota:** `quota_available = total - held - booked`. Booking pending menaikkan `held`; setelah DP/lunas, `held` pindah ke `booked`; kedaluwarsa/batal mengurangi `held`/`booked`.
- **Snapshot:** `bookings` (harga, diskon, DP, nama paket/travel), `booking_participants.unit_price`, `commissions` (aturan + hasil hitung).
- **Isolasi tenant:** `travel_partner_id` ada di `travel_packages`, `package_schedules`, `bookings`, `payments`, `refunds`, `commissions`, `partner_payouts`, `reviews`.
- **DP & pelunasan:** `payments.kind` = `full` | `dp` | `settlement`; saldo di `bookings.paid_amount` / `remaining_amount`.
