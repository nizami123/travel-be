<div class="card p-4" style="max-width:850px"><form method="post" action="<?= site_url('admin/settings/save') ?>"><?= csrf_field() ?>
<?php $grp = ''; foreach ($rows as $r): if ($r->setting_group !== $grp): $grp = $r->setting_group; ?><h6 class="mt-3 text-uppercase text-muted small"><?= e($grp) ?></h6><?php endif; ?>
<div class="row mb-2 align-items-center"><label class="col-md-5 small"><code><?= e($r->setting_key) ?></code><br><span class="text-muted"><?= e($r->description) ?></span></label><div class="col-md-7"><input name="s[<?= e($r->setting_key) ?>]" class="form-control" value="<?= e($r->setting_value) ?>"></div></div>
<?php endforeach; ?><button class="btn btn-primary mt-3">Simpan Pengaturan</button></form>
<p class="small text-muted mt-3">API key Xendit &amp; kunci rahasia lain TIDAK diatur di sini; simpan di <code>application/config/local.php</code>.</p></div>
