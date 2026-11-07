<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Komisi hybrid: persentase / nominal tetap / keduanya. Prioritas: paket > travel > global. */
class Commission_calculator
{
    protected $CI;
    public function __construct() { $this->CI =& get_instance(); }

    public function resolve_rule($partner_id, $package_id)
    {
        return $this->CI->db->query(
            "SELECT * FROM commission_rules
              WHERE is_active = 1 AND effective_from <= NOW() AND (effective_until IS NULL OR effective_until >= NOW())
                AND ((scope = 'package' AND package_id = ?) OR (scope = 'partner' AND travel_partner_id = ?) OR scope = 'global')
              ORDER BY FIELD(scope, 'package', 'partner', 'global'), effective_from DESC LIMIT 1",
            [(int)$package_id, (int)$partner_id])->row();
    }

    /** @return array [commission, partner_amount] (rupiah bulat) */
    public function calculate($rule, $base)
    {
        $base = (float)$base;
        $pct = ($rule->model === 'fixed') ? 0 : (float)$rule->percentage;
        $fix = ($rule->model === 'percentage') ? 0 : (float)$rule->fixed_amount;
        $c = round($base * $pct / 100) + $fix;
        $c = max(0, min($base, $c));
        return [$c, $base - $c];
    }
}
