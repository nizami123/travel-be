<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Packages extends Admin_Controller
{
    public function index()
    {
        $this->need('package.view');
        $rows = $this->db->query(
            "SELECT p.*, tp.name AS partner_name, pc.name AS category_name FROM travel_packages p
               JOIN travel_partners tp ON tp.id = p.travel_partner_id JOIN package_categories pc ON pc.id = p.category_id
              WHERE p.deleted_at IS NULL ORDER BY FIELD(p.approval_status,'pending','approved','draft','rejected'), p.id DESC")->result();
        $this->render('admin/packages', ['rows' => $rows], 'Paket Perjalanan');
    }

    public function show($id)
    {
        $this->need('package.view');
        $p = $this->db->query('SELECT p.*, tp.name AS partner_name, pc.name AS category_name FROM travel_packages p JOIN travel_partners tp ON tp.id = p.travel_partner_id JOIN package_categories pc ON pc.id = p.category_id WHERE p.id = ?', [(int)$id])->row();
        if (!$p) { show_404(); }
        $data = [
            'p' => $p,
            'schedules' => $this->db->query('SELECT s.*, q.quota_total, q.quota_held, q.quota_booked FROM package_schedules s JOIN package_quotas q ON q.schedule_id = s.id WHERE s.package_id = ? ORDER BY s.departure_date', [$p->id])->result(),
            'itin' => $this->db->query('SELECT * FROM package_itineraries WHERE package_id = ? ORDER BY day_number', [$p->id])->result(),
        ];
        $this->render('admin/package_show', $data, $p->name);
    }

    public function review($id)
    {
        $this->need('package.approve'); $this->post_only();
        $p = $this->db->get_where('travel_packages', ['id' => (int)$id])->row(); if (!$p) { show_404(); }
        $act = $this->input->post('action'); $note = mb_substr((string)$this->input->post('note'), 0, 255) ?: null;
        $this->load->library('notifier');
        if ($act === 'approve') {
            $this->db->where('id', $p->id)->update('travel_packages', ['approval_status' => 'approved', 'status' => 'active', 'approved_by' => $this->me->id, 'approved_at' => date('Y-m-d H:i:s'), 'approval_note' => $note]);
            $this->notifier->push_partner($p->travel_partner_id, 'system', 'Paket disetujui', "Paket {$p->name} disetujui dan tayang.");
        } elseif ($act === 'reject') {
            $this->db->where('id', $p->id)->update('travel_packages', ['approval_status' => 'rejected', 'status' => 'inactive', 'approval_note' => $note]);
            $this->notifier->push_partner($p->travel_partner_id, 'system', 'Paket ditolak', "Paket {$p->name} ditolak" . ($note ? ": $note" : '.'));
        } elseif ($act === 'deactivate') {
            $this->db->where('id', $p->id)->update('travel_packages', ['status' => 'inactive']);
        } elseif ($act === 'activate' && $p->approval_status === 'approved') {
            $this->db->where('id', $p->id)->update('travel_packages', ['status' => 'active']);
        } else { show_error('Aksi tidak valid', 400); }
        audit_log('package.' . $act, 'travel_packages', (int)$p->id, ['approval' => $p->approval_status, 'status' => $p->status], ['action' => $act, 'note' => $note], $this->me->id, (int)$p->travel_partner_id);
        $this->go('admin/packages/show/' . $p->id, 'success', 'Paket diperbarui.');
    }
}
