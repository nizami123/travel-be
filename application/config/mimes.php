<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Whitelist MIME untuk upload (library Upload CI3). Tambahkan hanya jika benar-benar dibutuhkan. */
return [
    'jpg'  => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'jpe'  => ['image/jpeg', 'image/pjpeg'],
    'png'  => ['image/png', 'image/x-png'],
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => ['application/pdf', 'application/force-download', 'application/x-download'],
];
