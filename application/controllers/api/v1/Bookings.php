<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bookings extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->library('booking_service');
        $this->load->model('booking_model');
    }

    protected function fail(Booking_exception $e)
    {
        api_error($e->getMessage(), $e->http >= 400 && $e->http < 600 ? $e->http : 422, $e->errors);
    }

    /** Hitung rincian biaya (tanpa menyimpan). */
    public function preview()
    {
        $this->validate(['schedule_id' => 'required|integer']);
        try {
            $q = $this->booking_service->build_quote($this->user->id, $this->in('schedule_id'), (array)$this->in('participants', []),
                $this->in('promo_code'), $this->in('payment_option', 'full'), false);
            api_ok($this->booking_service->format_quote($q));
        } catch (Booking_exception $e) { $this->fail($e); }
    }

    public function create()
    {
        $this->throttle('booking:' . $this->user->id, 10, 60);
        $this->validate(['schedule_id' => 'required|integer']);
        $c = (array)$this->in('contact', []);
        $contact = [
            'name' => trim((string)($c['name'] ?? $this->user->full_name)),
            'email' => trim((string)($c['email'] ?? $this->user->email)),
            'phone' => $this->normalize_phone($c['phone'] ?? $this->user->phone),
        ];
        if ($contact['name'] === '' || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^62\d{8,13}$/', $contact['phone'])) {
            api_error('Kontak pemesan tidak valid.', 422, ['contact' => 'Nama, email, dan nomor WA (62...) wajib valid.']);
        }
        try {
            $id = $this->booking_service->create_booking($this->user, $this->in('schedule_id'), (array)$this->in('participants', []),
                $this->in('promo_code'), $this->in('payment_option', 'full'), $contact, $this->in('note'));
            api_ok($this->booking_model->detail($id, $this->user->id), 'Booking berhasil dibuat.', null, 201);
        } catch (Booking_exception $e) { $this->fail($e); }
    }

    public function index()
    {
        list($page, $per, $off) = $this->paging(10);
        list($rows, $total) = $this->booking_model->list_for_user($this->user->id, $this->input->get('status'), $per, $off);
        api_ok($rows, 'OK', api_meta($page, $per, $total));
    }

    public function show($id)
    {
        $d = $this->booking_model->detail($id, $this->user->id);
        if (!$d) { api_error('Booking tidak ditemukan.', 404); }
        api_ok($d);
    }

    public function cancel($id)
    {
        $own = $this->db->get_where('bookings', ['id' => (int)$id, 'user_id' => $this->user->id])->row();
        if (!$own) { api_error('Booking tidak ditemukan.', 404); }
        $reason = trim((string)$this->in('reason', 'Dibatalkan oleh pelanggan'));
        try {
            $this->booking_service->cancel_booking($id, $this->user->id, $reason, true);
            api_ok($this->booking_model->detail($id, $this->user->id), 'Booking dibatalkan.');
        } catch (Booking_exception $e) { $this->fail($e); }
    }

    /** Upload dokumen peserta (KTP/paspor/dll). File disimpan private. */
    public function upload_document($id)
    {
        $bk = $this->db->get_where('bookings', ['id' => (int)$id, 'user_id' => $this->user->id])->row();
        if (!$bk) { api_error('Booking tidak ditemukan.', 404); }
        if (in_array($bk->status, ['cancelled', 'expired', 'refunded'], true)) { api_error('Booking tidak aktif.', 422); }
        $types = ['ktp', 'passport', 'kk', 'visa', 'vaccine', 'photo', 'mahram_letter', 'other'];
        $type = (string)$this->in('doc_type');
        if (!in_array($type, $types, true)) { api_error('Jenis dokumen tidak valid.', 422); }
        $pid = $this->in('participant_id');
        if ($pid) {
            if (!$this->db->get_where('booking_participants', ['id' => (int)$pid, 'booking_id' => $bk->id])->row()) { api_error('Peserta tidak ditemukan.', 404); }
        }
        $this->load->library('uploader');
        $f = $this->uploader->save('file', 'docs', true, 'jpg|jpeg|png|pdf', 5120);
        if (!$f) { api_error($this->uploader->error, 422); }
        $this->db->insert('booking_documents', [
            'booking_id' => $bk->id, 'participant_id' => $pid ? (int)$pid : null, 'uploaded_by' => $this->user->id,
            'direction' => 'customer_upload', 'doc_type' => $type, 'file_path' => 'docs/' . basename($f['path']),
            'original_name' => $f['original'], 'mime_type' => $f['mime'], 'file_size' => $f['size'],
        ]);
        $this->load->library('notifier');
        $this->notifier->push_partner($bk->travel_partner_id, 'document', 'Dokumen baru', "Dokumen baru untuk booking {$bk->booking_code}.", ['booking_id' => (int)$bk->id]);
        api_ok($this->booking_model->detail($id, $this->user->id), 'Dokumen diunggah.', null, 201);
    }
}
