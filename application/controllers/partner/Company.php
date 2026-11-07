<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Company extends Partner_Controller
{
    public function index()
    {
        $this->need('company.update');
        $data = [
            'docs' => $this->db->query('SELECT * FROM travel_partner_documents WHERE travel_partner_id = ? ORDER BY id DESC', [$this->partner_id])->result(),
            'banks' => $this->db->query('SELECT * FROM partner_bank_accounts WHERE travel_partner_id = ? ORDER BY is_primary DESC, id', [$this->partner_id])->result(),
        ];
        $this->render('partner/company', $data, 'Profil Perusahaan');
    }

    public function save()
    {
        $this->need('company.update'); $this->post_only();
        $this->form_validation->set_rules('name', 'Nama', 'required|max_length[150]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('phone', 'Telepon', 'required|max_length[20]');
        if (!$this->form_validation->run()) { $this->go('partner/company', 'error', strip_tags(validation_errors(' ', ' '))); }
        $i = $this->input;
        $row = [
            'name' => trim($i->post('name')), 'description' => $i->post('description') ?: null, 'email' => strtolower(trim($i->post('email'))),
            'phone' => trim($i->post('phone')), 'whatsapp' => preg_replace('/\D+/', '', (string)$i->post('whatsapp')) ?: null,
            'website' => trim($i->post('website')) ?: null, 'address' => $i->post('address') ?: null, 'city' => trim($i->post('city')) ?: null,
            'province' => trim($i->post('province')) ?: null, 'business_license_no' => trim($i->post('business_license_no')) ?: null,
            'ppiu_number' => trim($i->post('ppiu_number')) ?: null, 'pihk_number' => trim($i->post('pihk_number')) ?: null,
        ];
        $this->load->library('uploader');
        foreach (['logo' => 'logo_path', 'cover' => 'cover_path'] as $field => $col) {
            if (!empty($_FILES[$field]['name'])) {
                $f = $this->uploader->save($field, 'partners', false, 'jpg|jpeg|png|webp', 3072);
                if (!$f) { $this->go('partner/company', 'error', $this->uploader->error); }
                $row[$col] = $f['path'];
            }
        }
        $this->db->where('id', $this->partner_id)->update('travel_partners', $row);
        // pengajuan verifikasi
        if ($i->post('submit_review') && in_array($this->partner->verification_status, ['pending', 'rejected'], true)) {
            $this->db->where('id', $this->partner_id)->update('travel_partners', ['verification_status' => 'under_review', 'rejection_note' => null]);
        }
        audit_log('company.update', 'travel_partners', $this->partner_id, null, null, $this->me->id, $this->partner_id);
        $this->go('partner/company', 'success', 'Profil perusahaan disimpan.');
    }

    public function upload_doc()
    {
        $this->need('company.update'); $this->post_only();
        $type = $this->input->post('doc_type');
        if (!in_array($type, ['nib', 'siup', 'npwp', 'akta', 'ppiu', 'pihk', 'iata', 'other'], true)) { show_error('Jenis dokumen tidak valid', 400); }
        $this->load->library('uploader');
        $f = $this->uploader->save('file', 'partner', true, 'jpg|jpeg|png|pdf', 8192);
        if (!$f) { $this->go('partner/company', 'error', $this->uploader->error); }
        $exp = (string)$this->input->post('expires_on');
        $this->db->insert('travel_partner_documents', ['travel_partner_id' => $this->partner_id, 'doc_type' => $type, 'doc_number' => trim($this->input->post('doc_number')) ?: null,
            'file_path' => 'partner/' . basename($f['path']), 'original_name' => $f['original'], 'mime_type' => $f['mime'], 'file_size' => $f['size'],
            'expires_on' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) ? $exp : null]);
        $this->go('partner/company', 'success', 'Dokumen diunggah dan menunggu review platform.');
    }

    public function bank_save()
    {
        $this->need('bank.manage'); $this->post_only();
        $this->form_validation->set_rules('bank_name', 'Bank', 'required|max_length[100]');
        $this->form_validation->set_rules('account_number', 'No. rekening', 'required|regex_match[/^[0-9]{5,30}$/]');
        $this->form_validation->set_rules('account_holder', 'Atas nama', 'required|max_length[150]');
        if (!$this->form_validation->run()) { $this->go('partner/company', 'error', strip_tags(validation_errors(' ', ' '))); }
        $purpose = in_array($this->input->post('purpose'), ['payout', 'manual_transfer', 'both'], true) ? $this->input->post('purpose') : 'both';
        $this->db->trans_begin();
        $prim = $this->input->post('is_primary') ? 1 : 0;
        if ($prim) { $this->db->where('travel_partner_id', $this->partner_id)->update('partner_bank_accounts', ['is_primary' => 0]); }
        $this->db->insert('partner_bank_accounts', ['travel_partner_id' => $this->partner_id, 'bank_code' => strtoupper(substr(preg_replace('/\W+/', '', $this->input->post('bank_name')), 0, 20)),
            'bank_name' => trim($this->input->post('bank_name')), 'account_number' => $this->input->post('account_number'), 'account_holder' => trim($this->input->post('account_holder')),
            'purpose' => $purpose, 'is_primary' => $prim]);
        $this->db->trans_commit();
        audit_log('bank.add', 'partner_bank_accounts', (int)$this->db->insert_id(), null, ['bank' => $this->input->post('bank_name')], $this->me->id, $this->partner_id);
        $this->go('partner/company', 'success', 'Rekening ditambahkan.');
    }

    public function bank_delete($id)
    {
        $this->need('bank.manage'); $this->post_only();
        $this->db->where(['id' => (int)$id, 'travel_partner_id' => $this->partner_id])->update('partner_bank_accounts', ['status' => 'inactive', 'is_primary' => 0]);
        $this->go('partner/company', 'success', 'Rekening dinonaktifkan.');
    }
}
