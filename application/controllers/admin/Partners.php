<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Partners extends Admin_Controller
{
    public function index()
    {
        $this->need('partner.view');
        $rows = $this->db->query(
            "SELECT tp.*, (SELECT COUNT(*) FROM travel_packages p WHERE p.travel_partner_id = tp.id AND p.deleted_at IS NULL) AS pkg_count,
                    (SELECT COUNT(*) FROM bookings b WHERE b.travel_partner_id = tp.id) AS bk_count
               FROM travel_partners tp WHERE tp.deleted_at IS NULL ORDER BY tp.id DESC")->result();
        $this->render('admin/partners', ['rows' => $rows], 'Travel Mitra');
    }

    public function create()
    {
        $this->need('partner.create');
        $this->render('admin/partner_form', ['row' => null], 'Tambah Travel Mitra');
    }

    public function created()
    {
        $this->need('partner.create');
        $cred = $this->session->flashdata('cred');
        if (!$cred) { redirect('admin/partners'); }
        $this->render('admin/partner_created', ['cred' => $cred], 'Akun Travel Dibuat');
    }

    public function store()
    {
        $this->need('partner.create'); $this->post_only();
        $this->form_validation->set_rules('name', 'Nama travel', 'required|max_length[150]');
        $this->form_validation->set_rules('email', 'Email travel', 'required|valid_email');
        $this->form_validation->set_rules('phone', 'Telepon', 'required|max_length[20]');
        $this->form_validation->set_rules('admin_name', 'Nama admin', 'required|max_length[150]');
        $this->form_validation->set_rules('admin_email', 'Email admin', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('admin_phone', 'HP admin', 'required|regex_match[/^(0|62)8\d{7,12}$/]|is_unique[users.phone]');
        if (!$this->form_validation->run()) { $this->go('admin/partners/create', 'error', strip_tags(validation_errors(' ', ' '))); }

        $i = $this->input;
        $this->db->trans_begin();
        $slug = slugify($i->post('name'));
        if ($this->db->get_where('travel_partners', ['slug' => $slug])->row()) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }
        $this->db->insert('travel_partners', [
            'code' => 'TMP' . bin2hex(random_bytes(4)), 'name' => trim($i->post('name')), 'slug' => $slug,
            'email' => strtolower(trim($i->post('email'))), 'phone' => trim($i->post('phone')), 'whatsapp' => trim($i->post('whatsapp')) ?: null,
            'city' => trim($i->post('city')) ?: null, 'province' => trim($i->post('province')) ?: null, 'address' => trim($i->post('address')) ?: null,
            'ppiu_number' => trim($i->post('ppiu_number')) ?: null, 'pihk_number' => trim($i->post('pihk_number')) ?: null,
            'status' => 'inactive', 'verification_status' => 'pending',
        ]);
        $pid = (int)$this->db->insert_id();
        $this->db->where('id', $pid)->update('travel_partners', ['code' => 'TRV' . str_pad($pid, 4, '0', STR_PAD_LEFT)]);

        $pw = substr(bin2hex(random_bytes(6)), 0, 10) . 'Aa1';
        $phone = preg_replace('/\D+/', '', $i->post('admin_phone')); if (strpos($phone, '0') === 0) { $phone = '62' . substr($phone, 1); }
        $this->db->insert('users', [
            'user_type' => 'admin_travel', 'full_name' => trim($i->post('admin_name')), 'email' => strtolower(trim($i->post('admin_email'))),
            'phone' => $phone, 'password_hash' => password_hash($pw, PASSWORD_BCRYPT), 'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'), 'phone_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $uid = (int)$this->db->insert_id();
        $this->db->insert('user_profiles', ['user_id' => $uid]);
        $this->db->query("INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = 'admin_travel'", [$uid]);
        $this->db->insert('travel_partner_users', ['travel_partner_id' => $pid, 'user_id' => $uid, 'position' => 'Admin Travel', 'is_owner' => 1]);
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); $this->go('admin/partners/create', 'error', 'Gagal menyimpan.'); }
        $this->db->trans_commit();
        audit_log('partner.create', 'travel_partners', $pid, null, ['name' => $i->post('name')], $this->me->id, $pid);
        $this->session->set_flashdata('cred', ['partner' => $i->post('name'), 'email' => strtolower(trim($i->post('admin_email'))), 'password' => $pw, 'id' => $pid]);
        redirect('admin/partners/created');
    }

    public function show($id)
    {
        $this->need('partner.view');
        $p = $this->db->get_where('travel_partners', ['id' => (int)$id, 'deleted_at' => null])->row();
        if (!$p) { show_404(); }
        $data = [
            'p' => $p,
            'docs' => $this->db->query('SELECT * FROM travel_partner_documents WHERE travel_partner_id = ? ORDER BY id DESC', [$p->id])->result(),
            'users' => $this->db->query('SELECT u.full_name, u.email, u.user_type, u.status, tpu.is_owner FROM travel_partner_users tpu JOIN users u ON u.id = tpu.user_id WHERE tpu.travel_partner_id = ?', [$p->id])->result(),
            'rule' => $this->db->query("SELECT * FROM commission_rules WHERE scope = 'partner' AND travel_partner_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$p->id])->row(),
            'global_rule' => $this->db->query("SELECT * FROM commission_rules WHERE scope = 'global' AND is_active = 1 ORDER BY id DESC LIMIT 1")->row(),
            'perf' => $this->db->query("SELECT COUNT(*) bookings, COALESCE(SUM(CASE WHEN status IN ('paid','completed','partially_paid') THEN paid_amount END),0) revenue FROM bookings WHERE travel_partner_id = ?", [$p->id])->row(),
        ];
        $this->render('admin/partner_show', $data, $p->name);
    }

    public function verify($id)
    {
        $this->need('partner.verify'); $this->post_only();
        $p = $this->db->get_where('travel_partners', ['id' => (int)$id])->row(); if (!$p) { show_404(); }
        $act = $this->input->post('action');
        if ($act === 'approve') {
            $this->db->where('id', $p->id)->update('travel_partners', ['verification_status' => 'verified', 'status' => 'active', 'verified_by' => $this->me->id, 'verified_at' => date('Y-m-d H:i:s'), 'rejection_note' => null]);
            $this->load->library('notifier'); $this->notifier->push_partner($p->id, 'system', 'Travel terverifikasi', 'Akun travel Anda telah diverifikasi. Anda dapat mempublikasikan paket.');
        } elseif ($act === 'reject') {
            $this->db->where('id', $p->id)->update('travel_partners', ['verification_status' => 'rejected', 'status' => 'inactive', 'rejection_note' => mb_substr((string)$this->input->post('note'), 0, 255)]);
        } else { show_error('Aksi tidak valid', 400); }
        audit_log('partner.' . $act, 'travel_partners', (int)$p->id, ['v' => $p->verification_status], ['v' => $act], $this->me->id, (int)$p->id);
        $this->go('admin/partners/show/' . $p->id, 'success', 'Status verifikasi diperbarui.');
    }

    public function status($id)
    {
        $this->need('partner.delete'); $this->post_only();
        $st = $this->input->post('status');
        if (!in_array($st, ['active', 'inactive', 'suspended'], true)) { show_error('Status tidak valid', 400); }
        $this->db->where('id', (int)$id)->update('travel_partners', ['status' => $st]);
        audit_log('partner.status', 'travel_partners', (int)$id, null, ['status' => $st], $this->me->id, (int)$id);
        $this->go('admin/partners/show/' . (int)$id, 'success', 'Status diperbarui.');
    }

    public function doc_review($doc_id)
    {
        $this->need('partner.verify'); $this->post_only();
        $d = $this->db->get_where('travel_partner_documents', ['id' => (int)$doc_id])->row(); if (!$d) { show_404(); }
        $st = $this->input->post('action') === 'approve' ? 'approved' : 'rejected';
        $this->db->where('id', $d->id)->update('travel_partner_documents', ['status' => $st, 'reviewed_by' => $this->me->id, 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => mb_substr((string)$this->input->post('note'), 0, 255) ?: null]);
        $this->go('admin/partners/show/' . $d->travel_partner_id, 'success', 'Dokumen ' . ($st === 'approved' ? 'disetujui' : 'ditolak') . '.');
    }

    /** Atur komisi khusus travel (snapshot lama tetap utuh; hanya booking baru yang terpengaruh). */
    public function commission($id)
    {
        $this->need('commission.manage'); $this->post_only();
        $model = $this->input->post('model');
        if (!in_array($model, ['percentage', 'fixed', 'percentage_fixed'], true)) { show_error('Model tidak valid', 400); }
        $pct = max(0, min(100, (float)$this->input->post('percentage')));
        $fix = max(0, (float)$this->input->post('fixed_amount'));
        $this->db->trans_begin();
        $this->db->query("UPDATE commission_rules SET is_active = 0, effective_until = NOW() WHERE scope = 'partner' AND travel_partner_id = ? AND is_active = 1", [(int)$id]);
        $this->db->insert('commission_rules', ['scope' => 'partner', 'travel_partner_id' => (int)$id, 'model' => $model,
            'percentage' => $model === 'fixed' ? 0 : $pct, 'fixed_amount' => $model === 'percentage' ? 0 : $fix, 'created_by' => $this->me->id]);
        $this->db->trans_commit();
        audit_log('commission.set', 'travel_partners', (int)$id, null, ['model' => $model, 'pct' => $pct, 'fixed' => $fix], $this->me->id, (int)$id);
        $this->go('admin/partners/show/' . (int)$id, 'success', 'Aturan komisi travel disimpan.');
    }

    public function remove($id)
    {
        $this->need('partner.delete'); $this->post_only();
        $this->db->where('id', (int)$id)->update('travel_partners', ['status' => 'inactive', 'deleted_at' => date('Y-m-d H:i:s')]);
        audit_log('partner.delete', 'travel_partners', (int)$id, null, null, $this->me->id, (int)$id);
        $this->go('admin/partners', 'success', 'Travel dinonaktifkan.');
    }
}
