<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Akses file privat untuk admin platform (dengan izin). */
class Files extends Admin_Controller
{
    public function __construct() { parent::__construct(); $this->load->library('uploader'); }

    public function payment_proof($id)
    {
        $this->need('payment.view');
        $r = $this->db->get_where('payment_proofs', ['id' => (int)$id])->row(); if (!$r) { show_404(); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
    public function booking_document($id)
    {
        $this->need('booking.view');
        $r = $this->db->get_where('booking_documents', ['id' => (int)$id])->row(); if (!$r) { show_404(); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
    public function partner_doc($id)
    {
        $this->need('partner.view');
        $r = $this->db->get_where('travel_partner_documents', ['id' => (int)$id])->row(); if (!$r) { show_404(); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
    public function payout_proof($id)
    {
        $this->need('payout.manage');
        $r = $this->db->get_where('partner_payouts', ['id' => (int)$id])->row(); if (!$r || !$r->transfer_proof_path) { show_404(); }
        $this->uploader->stream_private($r->transfer_proof_path);
    }
}
