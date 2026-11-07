<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends Admin_Controller
{
    public function index()
    {
        $this->need('setting.manage');
        $rows = $this->db->query('SELECT * FROM settings ORDER BY setting_group, setting_key')->result();
        $this->render('admin/settings', ['rows' => $rows], 'Pengaturan Sistem');
    }

    public function save()
    {
        $this->need('setting.manage'); $this->post_only();
        $vals = (array)$this->input->post('s');
        foreach ($this->db->get('settings')->result() as $r) {
            if (!array_key_exists($r->setting_key, $vals)) { continue; }
            $v = trim((string)$vals[$r->setting_key]);
            if (in_array($r->value_type, ['int', 'decimal'], true) && !is_numeric($v)) { $this->go('admin/settings', 'error', "Nilai {$r->setting_key} harus angka."); }
            if ($v !== (string)$r->setting_value) {
                $this->db->where('id', $r->id)->update('settings', ['setting_value' => $v]);
                audit_log('setting.update', 'settings', (int)$r->id, ['v' => $r->setting_value], ['v' => $v], $this->me->id);
            }
        }
        $this->go('admin/settings', 'success', 'Pengaturan disimpan.');
    }
}
