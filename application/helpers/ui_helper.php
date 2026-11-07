<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        $CI =& get_instance();
        return '<input type="hidden" name="' . $CI->security->get_csrf_token_name() . '" value="' . $CI->security->get_csrf_hash() . '">';
    }
}
if (!function_exists('badge')) {
    function badge($status)
    {
        $map = [
            'pending' => 'warning', 'waiting_verification' => 'info', 'partially_paid' => 'info', 'paid' => 'success', 'completed' => 'success',
            'active' => 'success', 'approved' => 'success', 'verified' => 'success', 'published' => 'success', 'open' => 'success', 'earned' => 'success', 'available' => 'primary',
            'cancelled' => 'secondary', 'expired' => 'secondary', 'inactive' => 'secondary', 'draft' => 'secondary', 'closed' => 'secondary',
            'rejected' => 'danger', 'failed' => 'danger', 'suspended' => 'danger', 'reversed' => 'danger', 'refunded' => 'dark',
            'requested' => 'warning', 'processing' => 'info', 'under_review' => 'info', 'paid_out' => 'dark',
        ];
        $c = isset($map[$status]) ? $map[$status] : 'light text-dark';
        return '<span class="badge text-bg-' . $c . '">' . html_escape(str_replace('_', ' ', (string)$status)) . '</span>';
    }
}
if (!function_exists('fdate')) {
    function fdate($s) { return $s ? date('d M Y', strtotime($s)) : '-'; }
}
if (!function_exists('fdt')) {
    function fdt($s) { return $s ? date('d M Y H:i', strtotime($s)) : '-'; }
}
if (!function_exists('sel')) {
    function sel($a, $b) { return (string)$a === (string)$b ? ' selected' : ''; }
}
if (!function_exists('slugify')) {
    function slugify($s) { $x = url_title(convert_accented_characters((string)$s), '-', true); return $x !== '' ? $x : 'item'; }
}

if (!function_exists('post_btn')) {
    /** Tombol aksi POST (dengan CSRF) */
    function post_btn($url, $label, $class = 'btn-outline-primary', array $hidden = [], $confirm = null)
    {
        $h = '';
        foreach ($hidden as $k => $v) { $h .= '<input type="hidden" name="' . html_escape($k) . '" value="' . html_escape($v) . '">'; }
        return '<form method="post" action="' . site_url($url) . '" class="d-inline"' . ($confirm ? ' data-confirm="' . html_escape($confirm) . '"' : '') . '>'
            . csrf_field() . $h . '<button class="btn btn-sm ' . $class . '">' . html_escape($label) . '</button></form>';
    }
}
if (!function_exists('post_note')) {
    /** Form kecil aksi POST dengan kolom catatan/alasan */
    function post_note($url, $label, $class, array $hidden, $placeholder = 'Catatan', $field = 'note', $required = false)
    {
        $h = '';
        foreach ($hidden as $k => $v) { $h .= '<input type="hidden" name="' . html_escape($k) . '" value="' . html_escape($v) . '">'; }
        return '<form method="post" action="' . site_url($url) . '" class="d-inline-flex gap-1 align-items-center">' . csrf_field() . $h
            . '<input name="' . $field . '" class="form-control form-control-sm" style="width:180px" placeholder="' . html_escape($placeholder) . '"' . ($required ? ' required' : '') . '>'
            . '<button class="btn btn-sm ' . $class . '">' . html_escape($label) . '</button></form>';
    }
}
if (!function_exists('stat_card')) {
    function stat_card($label, $value, $icon, $color = '#0b3d91')
    {
        return '<div class="col-6 col-lg-3"><div class="card stat p-3"><div class="d-flex align-items-center gap-3"><div class="icon" style="background:' . $color . '"><i class="bi bi-' . $icon . '"></i></div>'
            . '<div><div class="text-muted small">' . html_escape($label) . '</div><div class="fs-5 fw-bold">' . $value . '</div></div></div></div></div>';
    }
}
