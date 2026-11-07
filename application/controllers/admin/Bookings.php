<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bookings extends Admin_Controller
{
    public function index()
    {
        $this->need('booking.view');
        $w = ['1=1']; $p = [];
        $g = $this->input->get();
        if (!empty($g['partner_id'])) { $w[] = 'b.travel_partner_id = ?'; $p[] = (int)$g['partner_id']; }
        if (!empty($g['status'])) { $w[] = 'b.status = ?'; $p[] = $g['status']; }
        if (!empty($g['category'])) { $w[] = 'pc.type = ?'; $p[] = $g['category']; }
        if (!empty($g['from'])) { $w[] = 'DATE(b.created_at) >= ?'; $p[] = $g['from']; }
        if (!empty($g['to'])) { $w[] = 'DATE(b.created_at) <= ?'; $p[] = $g['to']; }
        $rows = $this->db->query(
            'SELECT b.*, u.full_name AS customer, pc.type AS category_type FROM bookings b JOIN users u ON u.id = b.user_id
               JOIN travel_packages pk ON pk.id = b.package_id JOIN package_categories pc ON pc.id = pk.category_id
              WHERE ' . implode(' AND ', $w) . ' ORDER BY b.id DESC LIMIT 2000', $p)->result();
        $partners = $this->db->query('SELECT id, name FROM travel_partners WHERE deleted_at IS NULL ORDER BY name')->result();
        $this->render('admin/bookings', ['rows' => $rows, 'partners' => $partners, 'f' => $g], 'Booking');
    }

    public function show($id)
    {
        $this->need('booking.view');
        $b = $this->db->query('SELECT b.*, u.full_name AS customer, u.email AS customer_email, u.phone AS customer_phone FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?', [(int)$id])->row();
        if (!$b) { show_404(); }
        $data = [
            'b' => $b,
            'participants' => $this->db->query('SELECT * FROM booking_participants WHERE booking_id = ?', [$b->id])->result(),
            'payments' => $this->db->query('SELECT p.*, pm.name AS method_name FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.booking_id = ? ORDER BY p.id', [$b->id])->result(),
            'commission' => $this->db->get_where('commissions', ['booking_id' => $b->id])->row(),
            'refunds' => $this->db->get_where('refunds', ['booking_id' => $b->id])->result(),
        ];
        $this->render('admin/booking_show', $data, 'Booking ' . $b->booking_code);
    }

    public function cancel($id)
    {
        $this->need('booking.confirm'); $this->post_only();
        $this->load->library('booking_service');
        try {
            $this->booking_service->cancel_booking((int)$id, $this->me->id, (string)$this->input->post('reason') ?: 'Dibatalkan admin', false);
            $this->go('admin/bookings/show/' . (int)$id, 'success', 'Booking dibatalkan. Jika sudah ada pembayaran, permintaan refund dibuat.');
        } catch (Booking_exception $e) { $this->go('admin/bookings/show/' . (int)$id, 'error', $e->getMessage()); }
    }
}
