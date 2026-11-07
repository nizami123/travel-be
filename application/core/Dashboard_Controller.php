<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Basis dashboard web (session + CSRF + RBAC). */
class Dashboard_Controller extends CI_Controller
{
    protected $app;
    protected $me;
    protected $perms = [];
    protected $area = 'admin';
    protected $partner = null;
    protected $partner_id = null;     // SELALU dari session user terautentikasi, bukan dari input
    protected $tpu = null;

    public function __construct()
    {
        parent::__construct();
        $this->app = $this->config->item('app');
        date_default_timezone_set($this->app['timezone']);
        $this->load->helper(['url', 'text', 'form', 'response', 'money', 'audit', 'ui']);
        $this->db->query('SET time_zone = ?', [$this->app['tz_offset']]);
        $this->load->library(['session', 'rbac', 'form_validation']);
        $this->output->set_header('X-Frame-Options: SAMEORIGIN');
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_header('Referrer-Policy: same-origin');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    protected function authenticate($area)
    {
        $this->area = $area;
        $uid = (int)$this->session->userdata('uid');
        if (!$uid) { redirect('login'); }
        $u = $this->db->get_where('users', ['id' => $uid, 'status' => 'active', 'deleted_at' => null])->row();
        $types = $area === 'admin' ? ['super_admin', 'admin_platform'] : ['admin_travel', 'staff_travel'];
        if (!$u || !in_array($u->user_type, $types, true)) { $this->session->sess_destroy(); redirect('login'); }
        $this->me = $u;
        if ($area === 'partner') {
            $this->tpu = $this->db->get_where('travel_partner_users', ['user_id' => $u->id, 'status' => 'active'])->row();
            $this->partner = $this->tpu ? $this->db->get_where('travel_partners', ['id' => $this->tpu->travel_partner_id, 'deleted_at' => null])->row() : null;
            if (!$this->partner || $this->partner->status === 'suspended') {
                $this->session->sess_destroy();
                redirect('login?e=partner');
            }
            $this->partner_id = (int)$this->partner->id;
        }
        $this->perms = $this->rbac->permissions($u->id, $u->user_type, $this->tpu ? $this->tpu->id : null);
    }

    protected function can($p) { return in_array($p, $this->perms, true); }

    protected function need($p)
    {
        if (!$this->can($p)) { show_error('Anda tidak memiliki izin untuk tindakan ini (' . html_escape($p) . ').', 403, 'Akses ditolak'); }
    }

    protected function post_only()
    {
        if ($this->input->method() !== 'post') { show_error('Metode tidak diizinkan.', 405); }
    }

    protected function flash($type, $msg) { $this->session->set_flashdata('flash', ['type' => $type, 'msg' => $msg]); }

    protected function go($url, $type = null, $msg = null)
    {
        if ($type) { $this->flash($type, $msg); }
        redirect($url);
    }

    protected function json($data, $code = 200)
    {
        $this->output->set_status_header($code)->set_content_type('application/json', 'utf-8')->set_output(json_encode($data));
    }

    protected function menu()
    {
        if ($this->area === 'admin') {
            $m = [
                ['Dashboard', 'admin/dashboard', 'speedometer2', 'dashboard.view'],
                ['Travel Mitra', 'admin/partners', 'buildings', 'partner.view'],
                ['Destinasi', 'admin/destinations', 'geo-alt', 'destination.view'],
                ['Paket', 'admin/packages', 'luggage', 'package.view'],
                ['Booking', 'admin/bookings', 'journal-check', 'booking.view'],
                ['Keuangan', 'admin/finance', 'cash-coin', 'payment.view'],
                ['Komisi', 'admin/finance/commissions', 'percent', 'commission.manage'],
                ['Promo', 'admin/promotions', 'tag', 'promotion.manage'],
                ['Customer', 'admin/customers', 'people', 'customer.view'],
                ['Banner', 'admin/content/index/banners', 'images', 'content.manage'],
                ['Artikel', 'admin/content/index/articles', 'newspaper', 'content.manage'],
                ['FAQ', 'admin/content/index/faqs', 'question-circle', 'content.manage'],
                ['Pengaturan', 'admin/settings', 'gear', 'setting.manage'],
                ['Audit Log', 'admin/audit', 'shield-check', 'audit.view'],
            ];
        } else {
            $m = [
                ['Dashboard', 'partner/dashboard', 'speedometer2', 'dashboard.view'],
                ['Paket', 'partner/packages', 'luggage', 'package.view'],
                ['Booking', 'partner/bookings', 'journal-check', 'booking.view'],
                ['Pembayaran', 'partner/payments', 'credit-card', 'payment.view'],
                ['Promo', 'partner/promotions', 'tag', 'promotion.manage'],
                ['Keuangan', 'partner/finance', 'cash-coin', 'commission.view'],
                ['Perusahaan', 'partner/company', 'building', 'company.update'],
                ['Staff', 'partner/staff', 'people', 'staff.manage'],
            ];
        }
        return array_values(array_filter($m, function ($i) { return $this->can($i[3]); }));
    }

    protected function render($view, array $data = [], $title = '')
    {
        $data += ['title' => $title, 'me' => $this->me, 'area' => $this->area, 'partner' => $this->partner, 'perms' => $this->perms, 'menu' => $this->menu()];
        $data['content'] = $this->load->view($view, $data, true);
        $this->load->view('layouts/main', $data);
    }
}
