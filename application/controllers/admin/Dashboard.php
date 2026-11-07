<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Admin_Controller
{
    public function index()
    {
        $this->need('dashboard.view');
        $q = function ($sql) { return $this->db->query($sql)->row()->v; };
        $s = [
            'customers' => (int)$q("SELECT COUNT(*) v FROM users WHERE user_type = 'customer' AND deleted_at IS NULL"),
            'partners' => (int)$q("SELECT COUNT(*) v FROM travel_partners WHERE deleted_at IS NULL"),
            'destinations' => (int)$q("SELECT COUNT(*) v FROM destinations WHERE deleted_at IS NULL"),
            'packages' => (int)$q("SELECT COUNT(*) v FROM travel_packages WHERE deleted_at IS NULL"),
            'bookings' => (int)$q("SELECT COUNT(*) v FROM bookings"),
            'transactions' => (float)$q("SELECT COALESCE(SUM(amount),0) v FROM payments WHERE status = 'paid'"),
            'commission' => (float)$q("SELECT COALESCE(SUM(commission_amount),0) v FROM commissions WHERE status IN ('earned','available','paid_out')"),
            'pending_partners' => (int)$q("SELECT COUNT(*) v FROM travel_partners WHERE verification_status IN ('pending','under_review') AND deleted_at IS NULL"),
            'pending_packages' => (int)$q("SELECT COUNT(*) v FROM travel_packages WHERE approval_status = 'pending' AND deleted_at IS NULL"),
            'pending_payouts' => (int)$q("SELECT COUNT(*) v FROM partner_payouts WHERE status = 'requested'"),
            'pending_refunds' => (int)$q("SELECT COUNT(*) v FROM refunds WHERE status = 'requested'"),
        ];
        $s['platform_revenue'] = $s['commission'];
        $this->render('admin/dashboard', ['s' => $s], 'Dashboard');
    }

    public function chart()
    {
        $this->need('dashboard.view');
        $days = 30; $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $b = $this->db->query('SELECT DATE(created_at) d, COUNT(*) c FROM bookings WHERE created_at >= ? GROUP BY DATE(created_at)', [$from])->result();
        $p = $this->db->query("SELECT DATE(paid_at) d, SUM(amount) a FROM payments WHERE status = 'paid' AND paid_at >= ? GROUP BY DATE(paid_at)", [$from])->result();
        $bm = []; foreach ($b as $r) { $bm[$r->d] = (int)$r->c; }
        $pm = []; foreach ($p as $r) { $pm[$r->d] = (float)$r->a; }
        $labels = $bk = $tx = [];
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime($from . " +$i days"));
            $labels[] = date('d M', strtotime($d)); $bk[] = $bm[$d] ?? 0; $tx[] = $pm[$d] ?? 0;
        }
        $this->json(['labels' => $labels, 'bookings' => $bk, 'transactions' => $tx]);
    }
}
