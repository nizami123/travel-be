<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Partner_Controller
{
    public function index()
    {
        $this->need('dashboard.view');
        $pid = $this->partner_id;
        $q = function ($sql, $p = []) { return $this->db->query($sql, $p)->row()->v; };
        $s = [
            'packages' => (int)$q('SELECT COUNT(*) v FROM travel_packages WHERE travel_partner_id = ? AND deleted_at IS NULL', [$pid]),
            'bookings' => (int)$q('SELECT COUNT(*) v FROM bookings WHERE travel_partner_id = ?', [$pid]),
            'active' => (int)$q("SELECT COUNT(*) v FROM bookings WHERE travel_partner_id = ? AND status IN ('pending','partially_paid','paid')", [$pid]),
            'to_verify' => (int)$q("SELECT COUNT(*) v FROM payments WHERE travel_partner_id = ? AND status = 'waiting_verification'", [$pid]),
            'revenue' => (float)$q("SELECT COALESCE(SUM(partner_amount),0) v FROM commissions WHERE travel_partner_id = ? AND status IN ('earned','available','paid_out')", [$pid]),
            'available' => (float)$q("SELECT COALESCE(SUM(c.partner_amount),0) v FROM commissions c WHERE c.travel_partner_id = ? AND c.status = 'available' AND NOT EXISTS (SELECT 1 FROM payout_items pi WHERE pi.commission_id = c.id)", [$pid]),
        ];
        $up = $this->db->query(
            "SELECT s.id, s.departure_date, p.name, q.quota_total, q.quota_held, q.quota_booked FROM package_schedules s
               JOIN travel_packages p ON p.id = s.package_id JOIN package_quotas q ON q.schedule_id = s.id
              WHERE s.travel_partner_id = ? AND s.status = 'open' AND s.departure_date >= CURDATE() ORDER BY s.departure_date LIMIT 8", [$pid])->result();
        $this->render('partner/dashboard', ['s' => $s, 'up' => $up], 'Dashboard');
    }

    public function chart()
    {
        $this->need('dashboard.view');
        $days = 30; $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $b = $this->db->query('SELECT DATE(created_at) d, COUNT(*) c FROM bookings WHERE travel_partner_id = ? AND created_at >= ? GROUP BY DATE(created_at)', [$this->partner_id, $from])->result();
        $p = $this->db->query("SELECT DATE(paid_at) d, SUM(amount) a FROM payments WHERE travel_partner_id = ? AND status = 'paid' AND paid_at >= ? GROUP BY DATE(paid_at)", [$this->partner_id, $from])->result();
        $bm = []; foreach ($b as $r) { $bm[$r->d] = (int)$r->c; }
        $pm = []; foreach ($p as $r) { $pm[$r->d] = (float)$r->a; }
        $labels = $bk = $tx = [];
        for ($i = 0; $i < $days; $i++) { $d = date('Y-m-d', strtotime($from . " +$i days")); $labels[] = date('d M', strtotime($d)); $bk[] = $bm[$d] ?? 0; $tx[] = $pm[$d] ?? 0; }
        $this->json(['labels' => $labels, 'bookings' => $bk, 'transactions' => $tx]);
    }
}
