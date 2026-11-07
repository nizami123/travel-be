<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Callback Xendit (Invoice). Diverifikasi dengan header x-callback-token. Idempoten. */
class Xendit extends API_Controller
{
    public function invoice()
    {
        $this->load->library(['xendit_client', 'booking_service']);
        $token = $this->input->get_request_header('x-callback-token');
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true);
        $valid = $this->xendit_client->verify_callback($token);
        $ev = is_array($d) ? (string)($d['id'] ?? '') : '';
        $status = is_array($d) ? strtoupper((string)($d['status'] ?? '')) : '';

        $this->db->query('INSERT IGNORE INTO webhook_logs (provider, event_key, event_type, signature_valid, payload, ip_address) VALUES ("xendit", ?, ?, ?, ?, ?)',
            [$ev !== '' ? $ev : 'invalid-' . bin2hex(random_bytes(4)), $status, $valid ? 1 : 0, json_encode(is_array($d) ? $d : ['raw' => mb_substr($raw, 0, 2000)]), $this->input->ip_address()]);
        $inserted = $this->db->affected_rows() > 0;
        $logid = $inserted ? (int)$this->db->insert_id() : 0;

        if (!$valid) { api_error('Token callback tidak valid.', 401); }
        if (!is_array($d) || $ev === '') { api_error('Payload tidak valid.', 400); }
        if (!$inserted) { api_ok(null, 'Duplikat diabaikan.'); }      // idempotensi

        $pay = $this->db->get_where('payments', ['payment_code' => (string)($d['external_id'] ?? ''), 'provider' => 'xendit'])->row();
        if (!$pay) { $this->mark($logid, 'ignored', 'Payment tidak ditemukan'); api_ok(null, 'Diabaikan.'); }

        try {
            if (in_array($status, ['PAID', 'SETTLED'], true)) {
                $paid = (float)($d['paid_amount'] ?? $d['amount'] ?? 0);
                if ($paid + 0.5 < (float)$pay->total_charge) {          // nominal kurang -> jangan dianggap lunas
                    $this->mark($logid, 'failed', 'Nominal tidak sesuai');
                    api_ok(null, 'Nominal tidak sesuai; ditahan untuk pemeriksaan.');
                }
                $res = $this->booking_service->mark_payment_paid($pay->id, 'webhook');
                $this->mark($logid, 'processed', $res);
            } elseif ($status === 'EXPIRED') {
                if ($pay->status === 'pending') {
                    $this->db->where('id', $pay->id)->update('payments', ['status' => 'expired']);
                    $this->booking_service->history($pay->id, 'pending', 'expired', 'webhook');
                }
                $this->mark($logid, 'processed', 'expired');
            } else {
                $this->mark($logid, 'ignored', 'status ' . $status);
            }
        } catch (Exception $e) {
            $this->mark($logid, 'failed', mb_substr($e->getMessage(), 0, 250));
            api_error('Gagal memproses; Xendit akan mencoba ulang.', 500);   // 5xx => Xendit retry
        }
        api_ok(null, 'OK');
    }

    protected function mark($id, $st, $msg)
    {
        $this->db->where('id', $id)->update('webhook_logs', ['process_status' => $st, 'error_message' => mb_substr((string)$msg, 0, 250), 'processed_at' => date('Y-m-d H:i:s')]);
    }
}
