<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reviews extends API_Controller
{
    public function __construct() { parent::__construct(); $this->require_auth(); }

    public function create()
    {
        $this->validate(['booking_id' => 'required|integer', 'rating' => 'required|integer|greater_than[0]|less_than[6]']);
        $bk = $this->db->get_where('bookings', ['id' => (int)$this->in('booking_id'), 'user_id' => $this->user->id])->row();
        if (!$bk) { api_error('Booking tidak ditemukan.', 404); }
        if ($bk->status !== 'completed') { api_error('Review hanya untuk perjalanan yang sudah selesai.', 422); }
        if ($this->db->get_where('reviews', ['booking_id' => $bk->id])->row()) { api_error('Booking ini sudah direview.', 409); }
        $this->db->trans_begin();
        $this->db->insert('reviews', [
            'booking_id' => $bk->id, 'user_id' => $this->user->id, 'package_id' => $bk->package_id, 'travel_partner_id' => $bk->travel_partner_id,
            'rating' => (int)$this->in('rating'), 'title' => mb_substr((string)$this->in('title'), 0, 150) ?: null,
            'comment' => mb_substr((string)$this->in('comment'), 0, 2000) ?: null, 'status' => 'published',
        ]);
        $this->db->query("UPDATE travel_packages SET rating_count = (SELECT COUNT(*) FROM reviews WHERE package_id = ? AND status='published'),
                          rating_avg = COALESCE((SELECT AVG(rating) FROM reviews WHERE package_id = ? AND status='published'),0) WHERE id = ?", [$bk->package_id, $bk->package_id, $bk->package_id]);
        $this->db->query("UPDATE travel_partners SET rating_count = (SELECT COUNT(*) FROM reviews WHERE travel_partner_id = ? AND status='published'),
                          rating_avg = COALESCE((SELECT AVG(rating) FROM reviews WHERE travel_partner_id = ? AND status='published'),0) WHERE id = ?", [$bk->travel_partner_id, $bk->travel_partner_id, $bk->travel_partner_id]);
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); api_error('Gagal menyimpan review.', 500); }
        $this->db->trans_commit();
        api_ok(null, 'Terima kasih atas ulasan Anda.', null, 201);
    }
}
