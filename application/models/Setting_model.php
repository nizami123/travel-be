<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Setting_model extends CI_Model
{
    protected static $all = null;

    public function all()
    {
        if (self::$all === null) {
            self::$all = [];
            foreach ($this->db->get('settings')->result() as $r) { self::$all[$r->setting_key] = $r; }
        }
        return self::$all;
    }

    public function get($key, $default = null)
    {
        $a = $this->all();
        if (!isset($a[$key])) { return $default; }
        $v = $a[$key]->setting_value;
        switch ($a[$key]->value_type) {
            case 'int': return (int)$v;
            case 'decimal': return (float)$v;
            case 'bool': return (bool)$v;
            case 'json': return json_decode($v, true);
            default: return $v;
        }
    }

    public function public_settings()
    {
        $out = [];
        foreach ($this->all() as $k => $r) { if ($r->is_public) { $out[$k] = $this->get($k); } }
        return $out;
    }
}
