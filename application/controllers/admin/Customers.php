<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Customers extends Admin_Controller
{
    public function index()
    {
        $this->need('customer.view');
        $rows = $this->db->query("SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS bk FROM users u WHERE u.user_type = 'customer' AND u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 3000")->result();
        $this->render('admin/customers', ['rows' => $rows], 'Customer');
    }

    public function status($id)
    {
        $this->need('customer.update'); $this->post_only();
        $st = $this->input->post('status');
        if (!in_array($st, ['active', 'suspended'], true)) { show_error('Status tidak valid', 400); }
        $this->db->where(['id' => (int)$id, 'user_type' => 'customer'])->update('users', ['status' => $st]);
        if ($st === 'suspended') { $this->db->query('UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [(int)$id]); }
        audit_log('customer.status', 'users', (int)$id, null, ['status' => $st], $this->me->id);
        $this->go('admin/customers', 'success', 'Status customer diperbarui.');
    }
}
