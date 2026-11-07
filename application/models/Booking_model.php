<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Pembacaan booking untuk customer (milik sendiri saja). */
class Booking_model extends CI_Model
{
    public function summary_row($b)
    {
        return [
            'id' => (int)$b->id, 'booking_code' => $b->booking_code, 'status' => $b->status,
            'package_name' => $b->package_name_snapshot, 'partner_name' => $b->partner_name_snapshot,
            'cover_url' => media_url($b->cover_path ?? null),
            'departure_date' => $b->departure_date, 'return_date' => $b->return_date,
            'participant_count' => (int)$b->participant_count, 'total_amount' => (float)$b->total_amount,
            'paid_amount' => (float)$b->paid_amount, 'remaining_amount' => (float)$b->remaining_amount,
            'payment_option' => $b->payment_option, 'payment_expires_at' => $b->payment_expires_at,
            'full_payment_due_date' => $b->full_payment_due_date, 'created_at' => $b->created_at,
        ];
    }

    public function list_for_user($user_id, $status, $limit, $offset)
    {
        $w = 'b.user_id = ?'; $p = [$user_id];
        if ($status === 'active') { $w .= " AND b.status IN ('pending','partially_paid','paid')"; }
        elseif ($status === 'done') { $w .= " AND b.status IN ('completed','cancelled','expired','refunded')"; }
        elseif ($status) { $w .= ' AND b.status = ?'; $p[] = $status; }
        $total = (int)$this->db->query("SELECT COUNT(*) c FROM bookings b WHERE $w", $p)->row()->c;
        $rows = $this->db->query(
            "SELECT b.*, pk.cover_path FROM bookings b JOIN travel_packages pk ON pk.id = b.package_id WHERE $w
              ORDER BY b.id DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset, $p)->result();
        return [array_map([$this, 'summary_row'], $rows), $total];
    }

    public function detail($id, $user_id)
    {
        $b = $this->db->query(
            'SELECT b.*, pk.cover_path, tp.whatsapp AS partner_whatsapp FROM bookings b
               JOIN travel_packages pk ON pk.id = b.package_id JOIN travel_partners tp ON tp.id = b.travel_partner_id
              WHERE b.id = ? AND b.user_id = ?', [(int)$id, (int)$user_id])->row();
        if (!$b) { return null; }
        $o = $this->summary_row($b);
        $o += [
            'package_id' => (int)$b->package_id, 'schedule_id' => (int)$b->schedule_id,
            'subtotal' => (float)$b->subtotal, 'discount_amount' => (float)$b->discount_amount, 'promo_code' => $b->promo_code_snapshot,
            'dp_percent' => $b->dp_percent_snapshot === null ? null : (float)$b->dp_percent_snapshot, 'dp_amount' => (float)$b->dp_amount,
            'contact' => ['name' => $b->contact_name, 'email' => $b->contact_email, 'phone' => $b->contact_phone],
            'customer_note' => $b->customer_note, 'cancel_reason' => $b->cancel_reason,
            'partner_whatsapp' => $b->partner_whatsapp,
        ];
        $o['participants'] = array_map(function ($p) {
            return ['id' => (int)$p->id, 'full_name' => $p->full_name, 'gender' => $p->gender, 'birth_date' => $p->birth_date,
                    'id_number' => $p->id_number ? str_repeat('*', max(0, strlen($p->id_number) - 4)) . substr($p->id_number, -4) : null,
                    'passport_number' => $p->passport_number, 'passport_expiry' => $p->passport_expiry,
                    'price_type' => $p->price_label_snapshot, 'unit_price' => (float)$p->unit_price, 'room_type' => $p->room_type];
        }, $this->db->query('SELECT * FROM booking_participants WHERE booking_id = ? ORDER BY id', [$b->id])->result());
        $o['payments'] = array_map(function ($p) {
            return ['id' => (int)$p->id, 'payment_code' => $p->payment_code, 'kind' => $p->kind, 'amount' => (float)$p->amount,
                    'total_charge' => (float)$p->total_charge, 'status' => $p->status, 'provider' => $p->provider,
                    'payment_url' => ($p->status === 'pending') ? $p->payment_url : null,
                    'expires_at' => $p->expires_at, 'paid_at' => $p->paid_at, 'reject_reason' => $p->reject_reason];
        }, $this->db->query('SELECT * FROM payments WHERE booking_id = ? ORDER BY id', [$b->id])->result());
        $o['documents'] = array_map(function ($d) {
            return ['id' => (int)$d->id, 'participant_id' => $d->participant_id ? (int)$d->participant_id : null, 'doc_type' => $d->doc_type,
                    'direction' => $d->direction, 'status' => $d->status, 'review_note' => $d->review_note, 'original_name' => $d->original_name,
                    'file_url' => base_url('api/v1/files/booking-document/' . $d->id)];
        }, $this->db->query('SELECT * FROM booking_documents WHERE booking_id = ? ORDER BY id', [$b->id])->result());
        $o['can_review'] = ($b->status === 'completed') && !$this->db->get_where('reviews', ['booking_id' => $b->id])->row();
        return $o;
    }
}
