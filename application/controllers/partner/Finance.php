<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance extends Partner_Controller
{
    protected function available_rows($lock = false)
    {
        return $this->db->query(
            "SELECT c.id, c.partner_amount FROM commissions c WHERE c.travel_partner_id = ? AND c.status = 'available'
               AND NOT EXISTS (SELECT 1 FROM payout_items pi WHERE pi.commission_id = c.id)" . ($lock ? ' FOR UPDATE' : ''), [$this->partner_id])->result();
    }

    public function index()
    {
        $this->need('commission.view');
        $pid = $this->partner_id;
        $sum = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN status IN ('earned','available','paid_out') THEN base_amount END),0) gross,
                    COALESCE(SUM(CASE WHEN status IN ('earned','available','paid_out') THEN commission_amount END),0) commission,
                    COALESCE(SUM(CASE WHEN status IN ('earned','available','paid_out') THEN partner_amount END),0) net,
                    COALESCE(SUM(CASE WHEN status = 'earned' THEN partner_amount END),0) on_hold,
                    COALESCE(SUM(CASE WHEN status = 'paid_out' THEN partner_amount END),0) paid_out
               FROM commissions WHERE travel_partner_id = ?", [$pid])->row();
        $avail = 0; foreach ($this->available_rows() as $r) { $avail += (float)$r->partner_amount; }
        $data = [
            'sum' => $sum, 'avail' => $avail,
            'comms' => $this->db->query('SELECT c.*, b.booking_code, b.package_name_snapshot FROM commissions c JOIN bookings b ON b.id = c.booking_id WHERE c.travel_partner_id = ? ORDER BY c.id DESC LIMIT 500', [$pid])->result(),
            'payouts' => $this->db->query('SELECT * FROM partner_payouts WHERE travel_partner_id = ? ORDER BY id DESC', [$pid])->result(),
            'banks' => $this->db->query("SELECT * FROM partner_bank_accounts WHERE travel_partner_id = ? AND status = 'active' AND purpose IN ('payout','both')", [$pid])->result(),
            'min' => (float)$this->db->query("SELECT setting_value v FROM settings WHERE setting_key = 'min_payout_amount'")->row()->v,
        ];
        $this->render('partner/finance', $data, 'Keuangan');
    }

    public function request_payout()
    {
        $this->need('payout.request'); $this->post_only(); $this->need_verified();
        $bank = $this->db->get_where('partner_bank_accounts', ['id' => (int)$this->input->post('bank_id'), 'travel_partner_id' => $this->partner_id, 'status' => 'active'])->row();
        if (!$bank || $bank->purpose === 'manual_transfer') { $this->go('partner/finance', 'error', 'Pilih rekening pencairan yang valid.'); }
        $min = (float)$this->db->query("SELECT setting_value v FROM settings WHERE setting_key = 'min_payout_amount'")->row()->v;
        $db = $this->db;
        $db->trans_begin();
        $rows = $this->available_rows(true);
        $gross = 0; foreach ($rows as $r) { $gross += (float)$r->partner_amount; }
        if (!$rows || $gross < $min) { $db->trans_rollback(); $this->go('partner/finance', 'error', 'Saldo tersedia belum mencapai minimal pencairan ' . rupiah($min) . '.'); }
        $db->insert('partner_payouts', [
            'payout_code' => 'PO' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)), 'travel_partner_id' => $this->partner_id, 'bank_account_id' => $bank->id,
            'bank_name_snapshot' => $bank->bank_name, 'account_number_snapshot' => $bank->account_number, 'account_holder_snapshot' => $bank->account_holder,
            'gross_amount' => $gross, 'fee_amount' => 0, 'net_amount' => $gross, 'status' => 'requested', 'requested_by' => $this->me->id,
        ]);
        $poid = (int)$db->insert_id();
        foreach ($rows as $r) { $db->insert('payout_items', ['payout_id' => $poid, 'commission_id' => $r->id, 'amount' => $r->partner_amount]); }
        if ($db->trans_status() === false) { $db->trans_rollback(); $this->go('partner/finance', 'error', 'Gagal mengajukan pencairan (mungkin sedang diproses). Coba lagi.'); }
        $db->trans_commit();
        audit_log('payout.request', 'partner_payouts', $poid, null, ['gross' => $gross], $this->me->id, $this->partner_id);
        $this->go('partner/finance', 'success', 'Pengajuan pencairan dikirim ke platform.');
    }
}
