<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div style="font-family:sans-serif;padding:1rem"><h4>Terjadi kesalahan</h4><p><?php echo (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') ? htmlspecialchars($message) : 'Silakan coba lagi nanti.'; ?></p></div>
