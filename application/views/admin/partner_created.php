<div class="card p-4" style="max-width:600px"><h5>Travel "<?= e($cred['partner']) ?>" berhasil dibuat</h5>
<p>Berikan kredensial ini kepada admin travel. <strong>Password hanya ditampilkan sekali.</strong></p>
<table class="table"><tr><th>URL login</th><td><?= site_url('login') ?></td></tr><tr><th>Email</th><td><?= e($cred['email']) ?></td></tr><tr><th>Password sementara</th><td><code class="fs-5"><?= e($cred['password']) ?></code></td></tr></table>
<a class="btn btn-primary" href="<?= site_url('admin/partners/show/' . (int)$cred['id']) ?>">Buka detail travel</a></div>
