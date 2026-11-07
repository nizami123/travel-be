<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit extends Admin_Controller
{
    public function index()
    {
        $this->need('audit.view');
        $rows = $this->db->query('SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 500')->result();
        $this->render('admin/audit', ['rows' => $rows], 'Audit Log');
    }
}
