<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if (!function_exists('rupiah')) {
    function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
}
if (!function_exists('money')) {
    /** Pembulatan rupiah (tanpa desimal) */
    function money($n) { return (float)round((float)$n, 0); }
}
