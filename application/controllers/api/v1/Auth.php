<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends API_Controller
{
    protected function key() { return $this->app['encryption_key']; }

    protected function make_otp($target, $purpose, $user_id = null)
    {
        $this->throttle('otp:' . $target, 5, 600);
        $ttl = (int)$this->setting_model->get('otp_ttl_minutes', 5);
        $code = (string)random_int(100000, 999999);
        $this->db->query("UPDATE otp_codes SET used_at = NOW() WHERE target = ? AND purpose = ? AND used_at IS NULL", [$target, $purpose]);
        $this->db->insert('otp_codes', [
            'user_id' => $user_id, 'target' => $target, 'purpose' => $purpose,
            'code_hash' => hash_hmac('sha256', $code, $this->key()),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl * 60),
        ]);
        $this->notifier->send_otp($target, $purpose, $code);
        return $code;
    }

    /** @return bool */
    protected function check_otp($target, $purpose, $code)
    {
        $row = $this->db->query(
            'SELECT * FROM otp_codes WHERE target = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$target, $purpose])->row();
        if (!$row) { return false; }
        $max = (int)$this->setting_model->get('otp_max_attempts', 5);
        if ((int)$row->attempts >= $max) { return false; }
        $this->db->query('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row->id]);
        if (!hash_equals($row->code_hash, hash_hmac('sha256', (string)$code, $this->key()))) { return false; }
        $this->db->query('UPDATE otp_codes SET used_at = NOW() WHERE id = ?', [$row->id]);
        return true;
    }

    protected function user_payload($u)
    {
        $p = $this->db->get_where('user_profiles', ['user_id' => $u->id])->row();
        return [
            'id' => (int)$u->id, 'full_name' => $u->full_name, 'email' => $u->email, 'phone' => $u->phone,
            'email_verified' => !empty($u->email_verified_at), 'phone_verified' => !empty($u->phone_verified_at),
            'avatar_url' => $p ? media_url($p->avatar_path) : null,
        ];
    }

    protected function find_by_identifier($id)
    {
        $id = trim((string)$id);
        if (strpos($id, '@') !== false) { return $this->db->get_where('users', ['email' => strtolower($id), 'deleted_at' => null])->row(); }
        return $this->db->get_where('users', ['phone' => $this->normalize_phone($id), 'deleted_at' => null])->row();
    }

    public function __construct()
    {
        parent::__construct();
        $this->load->library('notifier');
        $this->load->model('setting_model');
        $this->throttle('auth:' . $this->input->ip_address(), 20, 60);
    }

    public function register()
    {
        $this->body['phone'] = $this->normalize_phone($this->in('phone'));
        $this->body['email'] = strtolower(trim((string)$this->in('email')));
        $this->validate([
            'full_name' => 'required|min_length[3]|max_length[150]',
            'email' => 'required|valid_email|max_length[150]|is_unique[users.email]',
            'phone' => 'required|regex_match[/^62\d{8,13}$/]|is_unique[users.phone]',
            'password' => 'required|min_length[8]|max_length[72]',
        ]);
        $pw = (string)$this->in('password');
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) { api_error('Validasi gagal', 422, ['password' => 'Password harus mengandung huruf dan angka.']); }

        $this->db->trans_begin();
        $this->db->insert('users', [
            'user_type' => 'customer', 'full_name' => trim($this->in('full_name')), 'email' => $this->in('email'),
            'phone' => $this->in('phone'), 'password_hash' => password_hash($pw, PASSWORD_BCRYPT), 'status' => 'pending',
        ]);
        $uid = (int)$this->db->insert_id();
        $this->db->insert('user_profiles', ['user_id' => $uid]);
        $this->db->query("INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = 'customer'", [$uid]);
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); api_error('Gagal mendaftar.', 500); }
        $this->db->trans_commit();

        $target = $this->in('phone');
        $code = $this->make_otp($target, 'register', $uid);
        $data = ['user_id' => $uid, 'otp_target' => $target];
        if (!empty($this->app['otp_debug'])) { $data['otp_debug'] = $code; }
        api_ok($data, 'Registrasi berhasil. Masukkan kode OTP yang dikirim ke WhatsApp Anda.', null, 201);
    }

    public function verify_otp()
    {
        $this->validate(['identifier' => 'required', 'code' => 'required|exact_length[6]|numeric']);
        $u = $this->find_by_identifier($this->in('identifier'));
        if (!$u) { api_error('Kode OTP salah atau kedaluwarsa.', 422); }
        $target = $u->phone;
        if (!$this->check_otp($target, 'register', $this->in('code'))) { api_error('Kode OTP salah atau kedaluwarsa.', 422); }
        $this->db->where('id', $u->id)->update('users', ['status' => 'active', 'phone_verified_at' => date('Y-m-d H:i:s')]);
        $u->status = 'active';
        $token = $this->token_lib->issue($u->id, $this->in('device_name'));
        audit_log('user.verify', 'users', (int)$u->id, null, null, $u->id);
        api_ok(['token' => $token, 'user' => $this->user_payload($u)], 'Akun terverifikasi.');
    }

    public function resend_otp()
    {
        $this->validate(['identifier' => 'required']);
        $u = $this->find_by_identifier($this->in('identifier'));
        $data = null;
        if ($u && $u->status === 'pending') {
            $code = $this->make_otp($u->phone, 'register', $u->id);
            if (!empty($this->app['otp_debug'])) { $data = ['otp_debug' => $code]; }
        }
        api_ok($data, 'Jika akun ditemukan, kode OTP baru telah dikirim.');
    }

    public function login()
    {
        $this->throttle('login:' . $this->input->ip_address(), 10, 60);
        $this->validate(['identifier' => 'required', 'password' => 'required']);
        $u = $this->find_by_identifier($this->in('identifier'));
        $fail = 'Email/nomor HP atau password salah.';
        if (!$u || $u->user_type !== 'customer') { password_verify('x', '$2y$10$abcdefghijklmnopqrstuuT0n1Pq5G0n1nqk4oYb7lXo7bq8y3q6'); api_error($fail, 401); }
        if ($u->locked_until && $u->locked_until > date('Y-m-d H:i:s')) { api_error('Akun terkunci sementara. Coba lagi nanti.', 423); }
        if (!password_verify((string)$this->in('password'), $u->password_hash)) {
            $max = (int)$this->setting_model->get('login_max_attempts', 5);
            $cnt = (int)$u->failed_login_count + 1;
            $upd = ['failed_login_count' => $cnt];
            if ($cnt >= $max) { $upd['locked_until'] = date('Y-m-d H:i:s', time() + 60 * (int)$this->setting_model->get('login_lock_minutes', 15)); $upd['failed_login_count'] = 0; }
            $this->db->where('id', $u->id)->update('users', $upd);
            api_error($fail, 401);
        }
        if ($u->status === 'pending') { api_error('Akun belum diverifikasi. Masukkan kode OTP.', 403, ['code' => 'ACCOUNT_NOT_VERIFIED']); }
        if ($u->status !== 'active') { api_error('Akun tidak aktif.', 403); }
        $this->db->where('id', $u->id)->update('users', ['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => date('Y-m-d H:i:s')]);
        $token = $this->token_lib->issue($u->id, $this->in('device_name'));
        api_ok(['token' => $token, 'user' => $this->user_payload($u)], 'Login berhasil.');
    }

    public function logout()
    {
        $this->require_auth();
        $this->token_lib->revoke($this->user->token_id);
        api_ok(null, 'Logout berhasil.');
    }

    public function forgot_password()
    {
        $this->validate(['identifier' => 'required']);
        $u = $this->find_by_identifier($this->in('identifier'));
        $data = null;
        if ($u && $u->user_type === 'customer' && $u->status !== 'suspended') {
            $code = $this->make_otp($u->phone, 'reset_password', $u->id);
            if (!empty($this->app['otp_debug'])) { $data = ['otp_debug' => $code]; }
        }
        api_ok($data, 'Jika akun ditemukan, kode OTP telah dikirim.');
    }

    public function reset_password()
    {
        $this->validate(['identifier' => 'required', 'code' => 'required|exact_length[6]|numeric', 'new_password' => 'required|min_length[8]|max_length[72]']);
        $u = $this->find_by_identifier($this->in('identifier'));
        if (!$u || !$this->check_otp($u->phone, 'reset_password', $this->in('code'))) { api_error('Kode OTP salah atau kedaluwarsa.', 422); }
        $this->db->where('id', $u->id)->update('users', ['password_hash' => password_hash((string)$this->in('new_password'), PASSWORD_BCRYPT), 'failed_login_count' => 0, 'locked_until' => null]);
        $this->token_lib->revoke_all($u->id);
        audit_log('user.reset_password', 'users', (int)$u->id, null, null, $u->id);
        api_ok(null, 'Password berhasil diubah. Silakan login.');
    }
}
