<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Akses file privat milik travel ini saja. */
class Files extends Partner_Controller
{
    public function __construct() { parent::__construct(); $this->load->library('uploader'); }

    public function payment_proof($id)
    {
        $this->need('payment.view');
        $r = $this->db->query('SELECT pr.* FROM payment_proofs pr JOIN payments p ON p.id = pr.payment_id WHERE pr.id = ? AND p.travel_partner_id = ?', [(int)$id, $this->partner_id])->row();
        if (!$r) { show_404(); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
    public function booking_document($id)
    {
        $this->need('booking.view');
        $r = $this->db->query('SELECT d.* FROM booking_documents d JOIN bookings b ON b.id = d.booking_id WHERE d.id = ? AND b.travel_partner_id = ?', [(int)$id, $this->partner_id])->row();
        if (!$r) { show_404(); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
    public function partner_doc($id)
    {
        $this->need('company.update');
        $r = $this->db->get_where('travel_partner_documents', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id])->row();
        if (!$r) { show_404(); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
    public function payout_proof($id)
    {
        $this->need('commission.view');
        $r = $this->db->get_where('partner_payouts', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id])->row();
        if (!$r || !$r->transfer_proof_path) { show_404(); }
        $this->uploader->stream_private($r->transfer_proof_path);
    }
}
