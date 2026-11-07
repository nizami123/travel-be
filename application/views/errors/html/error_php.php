<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production'): ?>
<div style="border:1px solid #c33;padding:8px;margin:8px;font-family:monospace">
<b>PHP Error</b> <?php echo $severity; ?>: <?php echo $message; ?> — <?php echo $filepath; ?>:<?php echo $line; ?></div>
<?php endif; ?>
