<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Upload file aman: whitelist ekstensi+MIME, nama acak, private/public. */
class Uploader
{
    protected $CI;
    public $error = '';
    public function __construct() { $this->CI =& get_instance(); }

    /**
     * @param string $field  nama field file
     * @param string $subdir mis. 'packages' | 'proofs'
     * @param bool   $private true => uploads/private (tidak bisa diakses via URL)
     * @param string $types  mis. 'jpg|jpeg|png|webp' atau 'jpg|jpeg|png|pdf'
     * @return array|null [path(relative), original, mime, size]
     */
    public function save($field, $subdir, $private = false, $types = 'jpg|jpeg|png|webp', $max_kb = 5120)
    {
        $this->error = '';
        if (empty($_FILES[$field]['name'])) { $this->error = 'File tidak ditemukan.'; return null; }
        $base = FCPATH . 'uploads/' . ($private ? 'private/' : 'public/') . $subdir;
        if (!is_dir($base)) { @mkdir($base, 0755, true); }
        $this->CI->load->library('upload');
        $this->CI->upload->initialize([
            'upload_path' => $base, 'allowed_types' => $types, 'max_size' => $max_kb,
            'encrypt_name' => true, 'file_ext_tolower' => true, 'detect_mime' => true,
            'mod_mime_fix' => true, 'remove_spaces' => true,
        ], true);
        if (!$this->CI->upload->do_upload($field)) {
            $this->error = strip_tags($this->CI->upload->display_errors('', ''));
            return null;
        }
        $d = $this->CI->upload->data();
        return [
            'path' => $subdir . '/' . $d['file_name'],
            'original' => substr($d['client_name'], 0, 190),
            'mime' => $d['file_type'],
            'size' => (int)round($d['file_size'] * 1024),
        ];
    }

    /** Kirim file private ke browser/aplikasi (setelah pemeriksaan kepemilikan oleh pemanggil). */
    public function stream_private($relative, $mime = null, $name = null)
    {
        $full = realpath(FCPATH . 'uploads/private/' . $relative);
        $root = realpath(FCPATH . 'uploads/private');
        if (!$full || strpos($full, $root) !== 0 || !is_file($full)) { show_404(); }
        $mime = $mime ?: (function_exists('mime_content_type') ? mime_content_type($full) : 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($full));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . rawurlencode($name ?: basename($full)) . '"');
        readfile($full);
        exit;
    }
}
