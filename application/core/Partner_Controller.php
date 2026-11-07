<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Partner_Controller extends Dashboard_Controller
{
    public function __construct() { parent::__construct(); $this->authenticate('partner'); }

    /** Paket milik travel ini (404 jika bukan miliknya) */
    protected function own_package($id)
    {
        $p = $this->db->get_where('travel_packages', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id, 'deleted_at' => null])->row();
        if (!$p) { show_404(); }
        return $p;
    }

    protected function own_booking($id)
    {
        $b = $this->db->get_where('bookings', ['id' => (int)$id, 'travel_partner_id' => $this->partner_id])->row();
        if (!$b) { show_404(); }
        return $b;
    }

    /** Aksi publikasi hanya untuk travel yang sudah terverifikasi & aktif */
    protected function need_verified()
    {
        if ($this->partner->status !== 'active' || $this->partner->verification_status !== 'verified') {
            $this->go('partner/company', 'warning', 'Akun travel Anda belum terverifikasi. Lengkapi profil dan dokumen legalitas; tunggu verifikasi platform.');
        }
    }
}
