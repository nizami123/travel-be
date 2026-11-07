<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifications extends API_Controller
{
    public function __construct() { parent::__construct(); $this->require_auth(); }

    public function index()
    {
        list($page, $per, $off) = $this->paging(20);
        $total = (int)$this->db->query('SELECT COUNT(*) c FROM notifications WHERE user_id = ?', [$this->user->id])->row()->c;
        $unread = (int)$this->db->query('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND read_at IS NULL', [$this->user->id])->row()->c;
        $rows = $this->db->query("SELECT id, type, title, body, data, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT $per OFFSET $off", [$this->user->id])->result();
        foreach ($rows as $r) { $r->id = (int)$r->id; $r->data = $r->data ? json_decode($r->data, true) : null; $r->is_read = $r->read_at !== null; }
        $meta = api_meta($page, $per, $total); $meta['unread'] = $unread;
        api_ok($rows, 'OK', $meta);
    }

    public function read($id)
    {
        $this->db->query('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL', [(int)$id, $this->user->id]);
        api_ok(null, 'OK');
    }

    public function read_all()
    {
        $this->db->query('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$this->user->id]);
        api_ok(null, 'OK');
    }
}
