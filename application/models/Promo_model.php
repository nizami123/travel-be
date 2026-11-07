<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Promo_model extends CI_Model
{
    protected $autos = null;

    public function discount_for($promo, $amount)
    {
        $d = ($promo->discount_type === 'percent') ? $amount * (float)$promo->discount_value / 100 : (float)$promo->discount_value;
        if ($promo->discount_type === 'percent' && $promo->max_discount !== null) { $d = min($d, (float)$promo->max_discount); }
        return money(max(0, min($amount, $d)));
    }

    protected function applicable($promo, $package_id, $partner_id)
    {
        $map = $this->db->query('SELECT 1 FROM promotion_packages WHERE promotion_id = ? AND package_id = ?', [$promo->id, $package_id])->row();
        if ($map) { return true; }
        if ($promo->applies_to_all) {
            return $promo->created_by_partner_id === null || (int)$promo->created_by_partner_id === (int)$partner_id;
        }
        return false;
    }

    /** Promo otomatis (tanpa kode) yang aktif. */
    protected function autos()
    {
        if ($this->autos === null) {
            $this->autos = $this->db->query(
                "SELECT pr.*, GROUP_CONCAT(pp.package_id) AS pkg_ids FROM promotions pr
                   LEFT JOIN promotion_packages pp ON pp.promotion_id = pr.id
                  WHERE pr.status = 'active' AND pr.code IS NULL AND NOW() BETWEEN pr.starts_at AND pr.ends_at
                    AND (pr.quota IS NULL OR pr.used_count < pr.quota)
                  GROUP BY pr.id")->result();
        }
        return $this->autos;
    }

    /** Promo terbaik untuk listing: [original, discounted, label] atau null */
    public function best_for_listing($package_id, $partner_id, $price)
    {
        $best = null; $bd = 0;
        foreach ($this->autos() as $p) {
            $ids = $p->pkg_ids ? explode(',', $p->pkg_ids) : [];
            $ok = in_array((string)$package_id, $ids, true)
               || ($p->applies_to_all && ($p->created_by_partner_id === null || (int)$p->created_by_partner_id === (int)$partner_id));
            if (!$ok || (float)$price < (float)$p->min_purchase) { continue; }
            $d = $this->discount_for($p, $price);
            if ($d > $bd) { $bd = $d; $best = $p; }
        }
        if (!$best) { return null; }
        return [
            'promotion_id' => (int)$best->id, 'title' => $best->title,
            'original_price' => (float)$price, 'discounted_price' => (float)$price - $bd,
            'label' => $best->discount_type === 'percent' ? rtrim(rtrim(number_format($best->discount_value, 2, '.', ''), '0'), '.') . '% OFF' : 'Hemat ' . rupiah($best->discount_value),
        ];
    }

    /**
     * Evaluasi promo untuk booking (dipanggil di dalam transaksi bila $lock).
     * @return array|null [promotion, discount]
     * @throws Exception jika kode diberikan tapi tidak valid
     */
    public function evaluate($code, $package_id, $partner_id, $subtotal, $user_id, $lock = false)
    {
        $lockSql = $lock ? ' FOR UPDATE' : '';
        if ($code !== null && $code !== '') {
            $p = $this->db->query(
                "SELECT * FROM promotions WHERE code = ? AND status = 'active' AND NOW() BETWEEN starts_at AND ends_at" . $lockSql,
                [strtoupper(trim($code))])->row();
            if (!$p) { throw new Exception('Kode promo tidak valid atau sudah berakhir.', 422); }
            $cands = [$p];
            $strict = true;
        } else {
            $cands = $this->autos();
            $strict = false;
        }
        $best = null; $bd = 0;
        foreach ($cands as $p) {
            try {
                $this->check($p, $package_id, $partner_id, $subtotal, $user_id);
            } catch (Exception $e) {
                if ($strict) { throw $e; }
                continue;
            }
            $d = $this->discount_for($p, $subtotal);
            if ($d > $bd) { $bd = $d; $best = $p; }
        }
        if ($lock && $best && !$strict) {   // kunci baris promo otomatis yang dipakai
            $best = $this->db->query('SELECT * FROM promotions WHERE id = ? FOR UPDATE', [$best->id])->row();
            if ($best->quota !== null && $best->used_count >= $best->quota) { return null; }
        }
        return $best ? ['promotion' => $best, 'discount' => $bd] : null;
    }

    protected function check($p, $package_id, $partner_id, $subtotal, $user_id)
    {
        if (!$this->applicable($p, $package_id, $partner_id)) { throw new Exception('Promo tidak berlaku untuk paket ini.', 422); }
        if ($subtotal < (float)$p->min_purchase) { throw new Exception('Minimal transaksi promo ' . rupiah($p->min_purchase) . '.', 422); }
        if ($p->quota !== null && $p->used_count >= $p->quota) { throw new Exception('Kuota promo sudah habis.', 422); }
        $used = (int)$this->db->query(
            "SELECT COUNT(*) c FROM bookings WHERE user_id = ? AND promotion_id = ? AND status NOT IN ('cancelled','expired','refunded')",
            [$user_id, $p->id])->row()->c;
        if ($used >= (int)$p->per_user_limit) { throw new Exception('Batas penggunaan promo untuk akun Anda sudah tercapai.', 422); }
    }
}
