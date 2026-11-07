<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends API_Controller
{
    public function __construct() { parent::__construct(); $this->require_auth(); }

    protected function payload()
    {
        $u = $this->db->get_where('users', ['id' => $this->user->id])->row();
        $p = $this->db->get_where('user_profiles', ['user_id' => $u->id])->row();
        $c = (int)$this->db->query("SELECT COUNT(*) c FROM bookings WHERE user_id = ?", [$u->id])->row()->c;
        $unread = (int)$this->db->query('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND read_at IS NULL', [$u->id])->row()->c;
        return [
            'id' => (int)$u->id, 'full_name' => $u->full_name, 'email' => $u->email, 'phone' => $u->phone,
            'gender' => $p->gender ?? null, 'birth_date' => $p->birth_date ?? null, 'birth_place' => $p->birth_place ?? null,
            'address' => $p->address ?? null, 'city' => $p->city ?? null, 'province' => $p->province ?? null,
            'avatar_url' => $p ? media_url($p->avatar_path) : null, 'booking_count' => $c, 'unread_notifications' => $unread,
        ];
    }

    public function show() { api_ok($this->payload()); }

    public function update()
    {
        $this->validate(['full_name' => 'required|min_length[3]|max_length[150]']);
        $this->db->where('id', $this->user->id)->update('users', ['full_name' => trim($this->in('full_name'))]);
        $g = $this->in('gender');
        $upd = [
            'gender' => in_array($g, ['male', 'female'], true) ? $g : null,
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->in('birth_date')) ? $this->in('birth_date') : null,
            'birth_place' => mb_substr((string)$this->in('birth_place'), 0, 100) ?: null,
            'address' => mb_substr((string)$this->in('address'), 0, 1000) ?: null,
            'city' => mb_substr((string)$this->in('city'), 0, 100) ?: null,
            'province' => mb_substr((string)$this->in('province'), 0, 100) ?: null,
        ];
        if (!empty($_FILES['avatar']['name'])) {
            $this->load->library('uploader');
            $f = $this->uploader->save('avatar', 'avatars', false, 'jpg|jpeg|png|webp', 2048);
            if (!$f) { api_error($this->uploader->error, 422); }
            $upd['avatar_path'] = $f['path'];
        }
        $this->db->where('user_id', $this->user->id)->update('user_profiles', $upd);
        api_ok($this->payload(), 'Profil diperbarui.');
    }

    public function password()
    {
        $this->validate(['current_password' => 'required', 'new_password' => 'required|min_length[8]|max_length[72]']);
        $u = $this->db->get_where('users', ['id' => $this->user->id])->row();
        if (!password_verify((string)$this->in('current_password'), $u->password_hash)) { api_error('Password saat ini salah.', 422); }
        $this->db->where('id', $u->id)->update('users', ['password_hash' => password_hash((string)$this->in('new_password'), PASSWORD_BCRYPT)]);
        $this->db->query('UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = ? AND id <> ? AND revoked_at IS NULL', [$u->id, $this->user->token_id]);
        api_ok(null, 'Password berhasil diubah.');
    }

    public function device_token()
    {
        $this->validate(['fcm_token' => 'required|max_length[255]']);
        $this->db->query('INSERT INTO device_tokens (user_id, fcm_token, platform, last_seen_at) VALUES (?, ?, "android", NOW())
                          ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_seen_at = NOW()', [$this->user->id, $this->in('fcm_token')]);
        api_ok(null, 'OK');
    }
}
