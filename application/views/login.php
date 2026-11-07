<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - MultiTravel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
 :root{--navy:#0b3d91;--turq:#14b8c4;--orange:#ff8a1f}
 body{min-height:100vh;background:linear-gradient(160deg,#eaf6ff,#fff);display:flex;align-items:center}
 .card{border:0;border-radius:20px;box-shadow:0 20px 50px rgba(11,61,145,.15)}
 .brand{color:var(--navy);font-weight:800;font-size:1.9rem}.brand span{color:var(--turq)}
 .btn-primary{background:var(--navy);border-color:var(--navy);border-radius:12px;padding:.7rem}
 .form-control{border-radius:12px;padding:.7rem 1rem}
</style></head>
<body><div class="container" style="max-width:420px">
 <div class="card p-4 p-md-5">
  <div class="text-center mb-4"><div class="brand">Multi<span>Travel</span></div><div class="text-muted small">Dashboard Admin &amp; Travel Mitra</div></div>
  <?php if (!empty($error)): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <?php if (!empty($info)): ?><div class="alert alert-warning py-2"><?= e($info) ?></div><?php endif; ?>
  <form method="post" action="<?= site_url('login/attempt') ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
    <div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
    <button class="btn btn-primary w-100">Masuk</button>
  </form>
 </div>
</div></body></html>
