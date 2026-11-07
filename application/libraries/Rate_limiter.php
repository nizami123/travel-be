<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rate_limiter
{
    protected $CI;
    public function __construct() { $this->CI =& get_instance(); }

    /** @return bool true = masih boleh */
    public function hit($key, $max, $seconds)
    {
        $key = substr($key, 0, 190);
        $db = $this->CI->db;
        $row = $db->query('SELECT id, hits, window_start FROM rate_limits WHERE limit_key = ?', [$key])->row();
        $now = time();
        if (!$row || strtotime($row->window_start) + $seconds < $now) {
            $db->query('INSERT INTO rate_limits (limit_key, hits, window_start) VALUES (?, 1, NOW())
                        ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()', [$key]);
            return true;
        }
        if ((int)$row->hits >= $max) { return false; }
        $db->query('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row->id]);
        return true;
    }
}
