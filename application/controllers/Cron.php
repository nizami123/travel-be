<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Jalankan via CLI saja:  php index.php cron run
 * Crontab (tiap 5 menit):  *\/5 * * * * php /path/MULTI_TRAVEL_BACKEND/index.php cron run
 */
class Cron extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) { show_404(); }
        $this->load->helper(['money', 'audit']);
        $app = $this->config->item('app');
        date_default_timezone_set($app['timezone']);
        $this->db->query('SET time_zone = ?', [$app['tz_offset']]);
    }

    public function run()
    {
        $this->load->library('booking_service');
        $n = $this->booking_service->expire_due();
        $this->db->query('DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $this->db->query('DELETE FROM otp_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $this->db->query('DELETE FROM api_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        echo date('c') . " selesai. booking kedaluwarsa: {$n}\n";
    }
}
