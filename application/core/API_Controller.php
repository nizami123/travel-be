<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Base controller REST API (Android). Token Bearer, JSON konsisten. */
class API_Controller extends CI_Controller
{
    /** @var object|null user terautentikasi */
    protected $user = null;
    protected $body = [];
    protected $app;

    public function __construct()
    {
        parent::__construct();
        $this->app = $this->config->item('app');
        date_default_timezone_set($this->app['timezone']);
        $this->load->helper(['response', 'money', 'audit']);
        $this->db->query('SET time_zone = ?', [$this->app['tz_offset']]);
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_header('Cache-Control: no-store');
        $this->load->library(['rate_limiter', 'token_lib', 'form_validation']);
        $this->body = $this->parse_body();
        $this->throttle('api:' . $this->input->ip_address(), 240, 60);
    }

    protected function parse_body()
    {
        $ct = (string)$this->input->get_request_header('Content-Type');
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw === '' || $raw === false) { return []; }
            $d = json_decode($raw, true);
            if (!is_array($d)) { api_error('Body JSON tidak valid', 400); }
            return $d;
        }
        return $this->input->post() ?: [];
    }

    protected function in($key, $default = null)
    {
        return array_key_exists($key, $this->body) ? $this->body[$key] : $default;
    }

    protected function validate(array $rules, array $data = null)
    {
        $this->form_validation->reset_validation();
        $this->form_validation->set_data($data === null ? $this->body : $data);
        foreach ($rules as $field => $rule) {
            $this->form_validation->set_rules($field, $field, $rule);
        }
        if (!$this->form_validation->run()) {
            api_error('Validasi gagal', 422, $this->form_validation->error_array());
        }
    }

    protected function throttle($key, $max, $seconds)
    {
        if (!$this->rate_limiter->hit($key, $max, $seconds)) {
            $this->output->set_header('Retry-After: ' . $seconds);
            api_error('Terlalu banyak permintaan. Coba lagi beberapa saat lagi.', 429);
        }
    }

    protected function bearer()
    {
        $h = $this->input->get_request_header('Authorization');
        if (!$h && isset($_SERVER['HTTP_AUTHORIZATION'])) { $h = $_SERVER['HTTP_AUTHORIZATION']; }
        if (!$h && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) { $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION']; }
        if ($h && preg_match('/^Bearer\s+(\S+)$/i', trim($h), $m)) { return $m[1]; }
        return null;
    }

    protected function optional_auth()
    {
        $t = $this->bearer();
        if ($t) { $this->user = $this->token_lib->validate($t); }
        return $this->user;
    }

    protected function require_auth()
    {
        $this->optional_auth();
        if (!$this->user) { api_error('Sesi tidak valid. Silakan login kembali.', 401); }
        if ($this->user->user_type !== 'customer') { api_error('Akses ditolak.', 403); }
        return $this->user;
    }

    protected function paging($default = 20, $max = 50)
    {
        $page = max(1, (int)$this->input->get('page'));
        $per = (int)$this->input->get('per_page');
        $per = $per > 0 ? min($max, $per) : $default;
        return [$page, $per, ($page - 1) * $per];
    }

    /** Normalisasi nomor WA ke 62xxxxxxxxxx */
    protected function normalize_phone($p)
    {
        $p = preg_replace('/\D+/', '', (string)$p);
        if (strpos($p, '0') === 0) { $p = '62' . substr($p, 1); }
        elseif (strpos($p, '8') === 0) { $p = '62' . $p; }
        return $p;
    }
}
