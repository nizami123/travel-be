<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $app = $this->config->item('app');
        date_default_timezone_set($app['timezone']);
        $this->load->helper(['url', 'form', 'response', 'ui']);
        $this->db->query('SET time_zone = ?', [$app['tz_offset']]);
        $this->load->library(['session', 'rate_limiter']);
        $this->output->set_header('X-Frame-Options: DENY');
        $this->output->set_header('Cache-Control: no-store');
    }

    protected function home_for($type)
    {
        return in_array($type, ['super_admin', 'admin_platform'], true) ? 'admin/dashboard' : 'partner/dashboard';
    }

    public function index()
    {
        if ($uid = $this->session->userdata('uid')) {
            $u = $this->db->get_where('users', ['id' => (int)$uid, 'status' => 'active'])->row();
            if ($u && $u->user_type !== 'customer') { redirect($this->home_for($u->user_type)); }
        }
        $this->load->view('login', ['error' => $this->session->flashdata('login_error'), 'info' => $this->input->get('e') === 'partner' ? 'Akun travel tidak aktif/ditangguhkan.' : null]);
    }

    public function attempt()
    {
        if ($this->input->method() !== 'post') { redirect('login'); }
        if (!$this->rate_limiter->hit('dlogin:' . $this->input->ip_address(), 10, 300)) {
            $this->session->set_flashdata('login_error', 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.'); redirect('login');
        }
        $email = strtolower(trim((string)$this->input->post('email')));
        $pw = (string)$this->input->post('password');
        $u = $this->db->get_where('users', ['email' => $email, 'deleted_at' => null])->row();
        $fail = 'Email atau password salah.';
        if (!$u || $u->user_type === 'customer') { $this->session->set_flashdata('login_error', $fail); redirect('login'); }
        if ($u->locked_until && $u->locked_until > date('Y-m-d H:i:s')) { $this->session->set_flashdata('login_error', 'Akun terkunci sementara.'); redirect('login'); }
        if (!password_verify($pw, $u->password_hash)) {
            $cnt = (int)$u->failed_login_count + 1;
            $upd = ['failed_login_count' => $cnt];
            if ($cnt >= 5) { $upd['failed_login_count'] = 0; $upd['locked_until'] = date('Y-m-d H:i:s', time() + 900); }
            $this->db->where('id', $u->id)->update('users', $upd);
            $this->session->set_flashdata('login_error', $fail); redirect('login');
        }
        if ($u->status !== 'active') { $this->session->set_flashdata('login_error', 'Akun tidak aktif.'); redirect('login'); }
        $this->db->where('id', $u->id)->update('users', ['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => date('Y-m-d H:i:s')]);
        $this->session->sess_regenerate(true);          // cegah session fixation
        $this->session->set_userdata('uid', (int)$u->id);
        $this->load->helper('audit');
        audit_log('user.login', 'users', (int)$u->id, null, null, $u->id);
        redirect($this->home_for($u->user_type));
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect('login');
    }
}
