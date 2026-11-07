<div class="card p-4" style="max-width:800px"><form method="post" action="<?= site_url('admin/partners/store') ?>"><?= csrf_field() ?>
<h6 class="mb-3">Data Travel</h6>
<div class="row g-3">
 <div class="col-md-6"><label class="form-label">Nama travel *</label><input name="name" class="form-control" required></div>
 <div class="col-md-6"><label class="form-label">Email travel *</label><input type="email" name="email" class="form-control" required></div>
 <div class="col-md-4"><label class="form-label">Telepon *</label><input name="phone" class="form-control" required></div>
 <div class="col-md-4"><label class="form-label">WhatsApp</label><input name="whatsapp" class="form-control"></div>
 <div class="col-md-4"><label class="form-label">Kota</label><input name="city" class="form-control"></div>
 <div class="col-md-6"><label class="form-label">No. izin PPIU (Umroh)</label><input name="ppiu_number" class="form-control"></div>
 <div class="col-md-6"><label class="form-label">No. izin PIHK (Haji)</label><input name="pihk_number" class="form-control"></div>
 <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
</div>
<h6 class="mt-4 mb-3">Akun Admin Travel</h6>
<div class="row g-3">
 <div class="col-md-4"><label class="form-label">Nama *</label><input name="admin_name" class="form-control" required></div>
 <div class="col-md-4"><label class="form-label">Email login *</label><input type="email" name="admin_email" class="form-control" required></div>
 <div class="col-md-4"><label class="form-label">No. HP *</label><input name="admin_phone" class="form-control" placeholder="08xxxxxxxxxx" required></div>
</div>
<p class="text-muted small mt-3">Password sementara dibuat otomatis dan ditampilkan sekali setelah disimpan.</p>
<button class="btn btn-primary">Simpan</button> <a class="btn btn-light" href="<?= site_url('admin/partners') ?>">Batal</a>
</form></div>
