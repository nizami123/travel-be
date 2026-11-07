<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Akses file privat milik customer (cek kepemilikan). */
class Files extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->library('uploader');
    }

    public function payment_proof($id)
    {
        $r = $this->db->query('SELECT pr.* FROM payment_proofs pr JOIN payments p ON p.id = pr.payment_id WHERE pr.id = ? AND p.user_id = ?', [(int)$id, $this->user->id])->row();
        if (!$r) { api_error('File tidak ditemukan.', 404); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }

    public function booking_document($id)
    {
        $r = $this->db->query('SELECT d.* FROM booking_documents d JOIN bookings b ON b.id = d.booking_id WHERE d.id = ? AND b.user_id = ?', [(int)$id, $this->user->id])->row();
        if (!$r) { api_error('File tidak ditemukan.', 404); }
        $this->uploader->stream_private($r->file_path, $r->mime_type, $r->original_name);
    }
}
