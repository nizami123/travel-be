<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Staff extends Partner_Controller
{
    /** Permission yang boleh diberikan ke staff = batas role staff_travel */
    protected function grantable()
    {
        return $this->db->query("SELECT p.id, p.code, p.name FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'staff_travel' ORDER BY p.module, p.code")->result();
    }

    public function index()
    {
        $this->need('staff.manage');
        $rows = $this->db->query(
            "SELECT tpu.id AS tpu_id, tpu.status AS tpu_status, tpu.is_owner, u.full_name, u.email, u.phone, u.user_type,
                    (SELECT GROUP_CONCAT(p.code) FROM partner_staff_permissions psp JOIN permissions p ON p.id = psp.permission_id WHERE psp.travel_partner_user_id = tpu.id) AS perms
               FROM travel_partner_users tpu JOIN users u ON u.id = tpu.user_id WHERE tpu.travel_partner_id = ? ORDER BY tpu.is_owner DESC, tpu.id", [$this->partner_id])->result();
        $this->render('partner/staff', ['rows' => $rows, 'grantable' => $this->grantable()], 'Staff & Hak Akses');
    }

    public function create()
    {
        $this->need('staff.manage'); $this->post_only();
        $this->form_validation->set_rules('full_name', 'Nama', 'required|max_length[150]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('phone', 'HP', 'required|regex_match[/^(0|62)8\d{7,12}$/]|is_unique[users.phone]');
        if (!$this->form_validation->run()) { $this->go('partner/staff', 'error', strip_tags(validation_errors(' ', ' '))); }
        $pw = substr(bin2hex(random_bytes(6)), 0, 10) . 'Aa1';
        $phone = preg_replace('/\D+/', '', $this->input->post('phone')); if (strpos($phone, '0') === 0) { $phone = '62' . substr($phone, 1); }
        $this->db->trans_begin();
        $this->db->insert('users', ['user_type' => 'staff_travel', 'full_name' => trim($this->input->post('full_name')), 'email' => strtolower(trim($this->input->post('email'))),
            'phone' => $phone, 'password_hash' => password_hash($pw, PASSWORD_BCRYPT), 'status' => 'active', 'email_verified_at' => date('Y-m-d H:i:s'), 'phone_verified_at' => date('Y-m-d H:i:s')]);
        $uid = (int)$this->db->insert_id();
        $this->db->insert('user_profiles', ['user_id' => $uid]);
        $this->db->query("INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = 'staff_travel'", [$uid]);
        $this->db->insert('travel_partner_users', ['travel_partner_id' => $this->partner_id, 'user_id' => $uid, 'position' => mb_substr((string)$this->input->post('position'), 0, 100) ?: 'Staff']);
        $tpu = (int)$this->db->insert_id();
        $this->grant($tpu, (array)$this->input->post('perms'));
        $this->db->trans_commit();
        audit_log('staff.create', 'users', $uid, null, ['email' => $this->input->post('email')], $this->me->id, $this->partner_id);
        $this->go('partner/staff', 'modal', "Staff dibuat. Password sementara (catat sekarang, tidak ditampilkan lagi): $pw");
    }

    protected function grant($tpu, array $codes)
    {
        $this->db->delete('partner_staff_permissions', ['travel_partner_user_id' => $tpu]);
        $ok = array_column(array_map(function ($r) { return (array)$r; }, $this->grantable()), 'id', 'code');
        foreach (array_unique($codes) as $c) {
            if (isset($ok[$c])) { $this->db->insert('partner_staff_permissions', ['travel_partner_user_id' => $tpu, 'permission_id' => $ok[$c], 'granted_by' => $this->me->id]); }
        }
    }

    protected function own_tpu($id)
    {
        $t = $this->db->get_where('travel_partner_users', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id])->row();
        if (!$t || $t->is_owner) { show_404(); }
        return $t;
    }

    public function permissions($tpu_id)
    {
        $this->need('staff.manage'); $this->post_only();
        $t = $this->own_tpu($tpu_id);
        $this->grant($t->id, (array)$this->input->post('perms'));
        audit_log('staff.permissions', 'travel_partner_users', (int)$t->id, null, null, $this->me->id, $this->partner_id);
        $this->go('partner/staff', 'success', 'Hak akses staff diperbarui.');
    }

    public function status($tpu_id)
    {
        $this->need('staff.manage'); $this->post_only();
        $t = $this->own_tpu($tpu_id);
        $new = $t->status === 'active' ? 'inactive' : 'active';
        $this->db->where('id', $t->id)->update('travel_partner_users', ['status' => $new]);
        $this->go('partner/staff', 'success', 'Status staff diperbarui.');
    }
}
