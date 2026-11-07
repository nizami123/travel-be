<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Role-Based Access Control. Staff travel: irisan permission role & yang diberikan Admin Travel. */
class Rbac
{
    protected $CI;
    protected $cache = [];
    public function __construct() { $this->CI =& get_instance(); }

    public function permissions($user_id, $user_type, $partner_user_id = null)
    {
        $k = $user_id . ':' . $user_type;
        if (isset($this->cache[$k])) { return $this->cache[$k]; }
        if ($user_type === 'super_admin') {
            $rows = $this->CI->db->query('SELECT code FROM permissions')->result();
        } else {
            $rows = $this->CI->db->query(
                'SELECT DISTINCT p.code FROM user_roles ur
                   JOIN role_permissions rp ON rp.role_id = ur.role_id
                   JOIN permissions p ON p.id = rp.permission_id
                  WHERE ur.user_id = ?', [$user_id])->result();
        }
        $codes = array_map(function ($r) { return $r->code; }, $rows);
        if ($user_type === 'staff_travel') {
            $granted = $this->CI->db->query(
                'SELECT p.code FROM partner_staff_permissions psp JOIN permissions p ON p.id = psp.permission_id
                  WHERE psp.travel_partner_user_id = ?', [(int)$partner_user_id])->result();
            $granted = array_map(function ($r) { return $r->code; }, $granted);
            $codes = array_values(array_intersect($codes, $granted));
        }
        return $this->cache[$k] = $codes;
    }
}
