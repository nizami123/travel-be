<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Token API: token acak 256-bit, di DB hanya disimpan hash SHA-256. */
class Token_lib
{
    protected $CI;
    public function __construct() { $this->CI =& get_instance(); }

    public function issue($user_id, $device = null)
    {
        $app = $this->CI->config->item('app');
        $token = bin2hex(random_bytes(32));
        $this->CI->db->insert('api_tokens', [
            'user_id' => $user_id,
            'token_hash' => hash('sha256', $token),
            'device_name' => $device ? substr($device, 0, 120) : null,
            'ip_address' => $this->CI->input->ip_address(),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400 * (int)$app['token_ttl_days']),
        ]);
        return $token;
    }

    /** @return object|null user (+ token_id) */
    public function validate($token)
    {
        $row = $this->CI->db->query(
            "SELECT u.*, t.id AS token_id, t.last_used_at
               FROM api_tokens t JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > NOW()
                AND u.status = 'active' AND u.deleted_at IS NULL", [hash('sha256', $token)])->row();
        if ($row && (!$row->last_used_at || strtotime($row->last_used_at) < time() - 300)) {
            $this->CI->db->query('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?', [$row->token_id]);
        }
        return $row ?: null;
    }

    public function revoke($token_id)
    {
        $this->CI->db->query('UPDATE api_tokens SET revoked_at = NOW() WHERE id = ?', [$token_id]);
    }

    public function revoke_all($user_id)
    {
        $this->CI->db->query('UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$user_id]);
    }
}
