<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bookings extends Partner_Controller
{
    public function index()
    {
        $this->need('booking.view');
        $w = ['b.travel_partner_id = ?']; $p = [$this->partner_id];
        $g = $this->input->get();
        if (!empty($g['status'])) { $w[] = 'b.status = ?'; $p[] = $g['status']; }
        if (!empty($g['schedule_id'])) { $w[] = 'b.schedule_id = ?'; $p[] = (int)$g['schedule_id']; }
        if (!empty($g['from'])) { $w[] = 'DATE(b.created_at) >= ?'; $p[] = $g['from']; }
        if (!empty($g['to'])) { $w[] = 'DATE(b.created_at) <= ?'; $p[] = $g['to']; }
        $rows = $this->db->query('SELECT b.*, u.full_name AS customer FROM bookings b JOIN users u ON u.id = b.user_id WHERE ' . implode(' AND ', $w) . ' ORDER BY b.id DESC LIMIT 2000', $p)->result();
        $this->render('partner/bookings', ['rows' => $rows, 'f' => $g], 'Booking Masuk');
    }

    public function show($id)
    {
        $this->need('booking.view');
        $b = $this->own_booking($id);
        $cust = $this->db->query('SELECT full_name, email, phone FROM users WHERE id = ?', [$b->user_id])->row();
        $data = [
            'b' => $b, 'cust' => $cust,
            'participants' => $this->can('participant.view') ? $this->db->query('SELECT * FROM booking_participants WHERE booking_id = ?', [$b->id])->result() : [],
            'payments' => $this->db->query('SELECT p.*, pm.name AS method_name FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.booking_id = ? AND p.travel_partner_id = ? ORDER BY p.id', [$b->id, $this->partner_id])->result(),
            'docs' => $this->db->query('SELECT * FROM booking_documents WHERE booking_id = ? ORDER BY id DESC', [$b->id])->result(),
            'commission' => $this->db->get_where('commissions', ['booking_id' => $b->id, 'travel_partner_id' => $this->partner_id])->row(),
        ];
        $this->render('partner/booking_show', $data, 'Booking ' . $b->booking_code);
    }

    public function confirm($id)
    {
        $this->need('booking.confirm'); $this->post_only();
        $b = $this->own_booking($id);
        if (!in_array($b->status, ['partially_paid', 'paid'], true)) { $this->go('partner/bookings/show/' . $b->id, 'error', 'Booking belum memiliki pembayaran.'); }
        $this->db->where(['id' => $b->id, 'travel_partner_id' => $this->partner_id])->update('bookings', ['confirmed_by' => $this->me->id, 'confirmed_at' => date('Y-m-d H:i:s'), 'internal_note' => mb_substr((string)$this->input->post('note'), 0, 1000) ?: $b->internal_note]);
        $this->load->library('notifier');
        $this->notifier->push($b->user_id, 'booking', 'Booking dikonfirmasi', "Booking {$b->booking_code} telah dikonfirmasi oleh {$this->partner->name}.", ['booking_id' => (int)$b->id]);
        audit_log('booking.confirm', 'bookings', (int)$b->id, null, null, $this->me->id, $this->partner_id);
        $this->go('partner/bookings/show/' . $b->id, 'success', 'Booking dikonfirmasi.');
    }

    public function cancel($id)
    {
        $this->need('booking.confirm'); $this->post_only();
        $b = $this->own_booking($id);
        $this->load->library('booking_service');
        try {
            $this->booking_service->cancel_booking($b->id, $this->me->id, (string)$this->input->post('reason') ?: 'Dibatalkan oleh travel', false);
            $this->go('partner/bookings/show/' . $b->id, 'success', 'Booking dibatalkan. Jika sudah ada pembayaran, permintaan refund dikirim ke platform.');
        } catch (Booking_exception $e) { $this->go('partner/bookings/show/' . $b->id, 'error', $e->getMessage()); }
    }

    /** Upload dokumen perjalanan (e-ticket, visa, voucher...) untuk customer */
    public function upload_doc($id)
    {
        $this->need('document.manage'); $this->post_only();
        $b = $this->own_booking($id);
        $type = $this->input->post('doc_type');
        if (!in_array($type, ['eticket', 'voucher', 'visa', 'itinerary', 'other'], true)) { show_error('Jenis dokumen tidak valid', 400); }
        $pid = (int)$this->input->post('participant_id') ?: null;
        if ($pid && !$this->db->get_where('booking_participants', ['id' => $pid, 'booking_id' => $b->id])->row()) { show_404(); }
        $this->load->library('uploader');
        $f = $this->uploader->save('file', 'docs', true, 'jpg|jpeg|png|pdf', 8192);
        if (!$f) { $this->go('partner/bookings/show/' . $b->id, 'error', $this->uploader->error); }
        $this->db->insert('booking_documents', ['booking_id' => $b->id, 'participant_id' => $pid, 'uploaded_by' => $this->me->id, 'direction' => 'partner_upload',
            'doc_type' => $type, 'file_path' => 'docs/' . basename($f['path']), 'original_name' => $f['original'], 'mime_type' => $f['mime'], 'file_size' => $f['size'], 'status' => 'approved']);
        $this->load->library('notifier');
        $this->notifier->push($b->user_id, 'document', 'Dokumen perjalanan tersedia', "Dokumen baru ({$type}) untuk booking {$b->booking_code}.", ['booking_id' => (int)$b->id]);
        $this->go('partner/bookings/show/' . $b->id, 'success', 'Dokumen diunggah.');
    }

    public function doc_review($doc_id)
    {
        $this->need('document.manage'); $this->post_only();
        $d = $this->db->query('SELECT d.* FROM booking_documents d JOIN bookings b ON b.id = d.booking_id WHERE d.id = ? AND b.travel_partner_id = ?', [(int)$doc_id, $this->partner_id])->row();
        if (!$d) { show_404(); }
        $st = $this->input->post('action') === 'approve' ? 'approved' : 'rejected';
        $this->db->where('id', $d->id)->update('booking_documents', ['status' => $st, 'reviewed_by' => $this->me->id, 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => mb_substr((string)$this->input->post('note'), 0, 255) ?: null]);
        $this->go('partner/bookings/show/' . $d->booking_id, 'success', 'Dokumen diperbarui.');
    }

    /** Daftar peserta satu jadwal (CSV) */
    public function export_participants($schedule_id)
    {
        $this->need('participant.view');
        $s = $this->db->get_where('package_schedules', ['id' => (int)$schedule_id, 'travel_partner_id' => $this->partner_id])->row();
        if (!$s) { show_404(); }
        $rows = $this->db->query(
            "SELECT b.booking_code, bp.full_name, bp.gender, bp.birth_date, bp.id_number, bp.passport_number, bp.passport_expiry, bp.phone, bp.room_type, b.status
               FROM booking_participants bp JOIN bookings b ON b.id = bp.booking_id
              WHERE b.schedule_id = ? AND b.travel_partner_id = ? AND b.status IN ('partially_paid','paid','completed') ORDER BY b.booking_code, bp.id", [$s->id, $this->partner_id])->result_array();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="peserta_' . $s->departure_date . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Booking', 'Nama', 'Gender', 'Tgl Lahir', 'NIK', 'Paspor', 'Berlaku s/d', 'HP', 'Kamar', 'Status']);
        foreach ($rows as $r) {
            foreach ($r as $k => $v) { if (is_string($v) && preg_match('/^[=+\-@]/', $v)) { $r[$k] = "'" . $v; } }
            fputcsv($out, array_values($r));
        }
        fclose($out); exit;
    }
}
