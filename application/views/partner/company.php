<div class="row g-3"><div class="col-lg-7"><div class="card p-4"><div class="d-flex justify-content-between"><h6>Profil Perusahaan</h6><?= badge($partner->verification_status) ?></div>
<?php if ($partner->rejection_note): ?><div class="alert alert-warning py-2 small">Catatan platform: <?= e($partner->rejection_note) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url('partner/company/save') ?>" class="row g-3"><?= csrf_field() ?>
 <div class="col-md-6"><label class="form-label">Nama *</label><input name="name" class="form-control" value="<?= e($partner->name) ?>" required></div>
 <div class="col-md-6"><label class="form-label">Email *</label><input name="email" type="email" class="form-control" value="<?= e($partner->email) ?>" required></div>
 <div class="col-md-4"><label class="form-label">Telepon *</label><input name="phone" class="form-control" value="<?= e($partner->phone) ?>" required></div>
 <div class="col-md-4"><label class="form-label">WhatsApp</label><input name="whatsapp" class="form-control" value="<?= e($partner->whatsapp) ?>"></div>
 <div class="col-md-4"><label class="form-label">Website</label><input name="website" class="form-control" value="<?= e($partner->website) ?>"></div>
 <div class="col-md-6"><label class="form-label">Kota</label><input name="city" class="form-control" value="<?= e($partner->city) ?>"></div>
 <div class="col-md-6"><label class="form-label">Provinsi</label><input name="province" class="form-control" value="<?= e($partner->province) ?>"></div>
 <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" rows="2" class="form-control"><?= e($partner->address) ?></textarea></div>
 <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="description" rows="3" class="form-control"><?= e($partner->description) ?></textarea></div>
 <div class="col-md-4"><label class="form-label">No. NIB/SIUP</label><input name="business_license_no" class="form-control" value="<?= e($partner->business_license_no) ?>"></div>
 <div class="col-md-4"><label class="form-label">No. PPIU</label><input name="ppiu_number" class="form-control" value="<?= e($partner->ppiu_number) ?>"></div>
 <div class="col-md-4"><label class="form-label">No. PIHK</label><input name="pihk_number" class="form-control" value="<?= e($partner->pihk_number) ?>"></div>
 <div class="col-md-6"><label class="form-label">Logo</label><input type="file" name="logo" accept="image/*" class="form-control"><?php if ($partner->logo_path): ?><img src="<?= e(media_url($partner->logo_path)) ?>" style="height:50px" class="mt-2"><?php endif; ?></div>
 <div class="col-md-6"><label class="form-label">Cover</label><input type="file" name="cover" accept="image/*" class="form-control"></div>
 <?php if (in_array($partner->verification_status, ['pending', 'rejected'])): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="submit_review" value="1" id="sr"><label for="sr" class="form-check-label">Ajukan untuk diverifikasi platform (pastikan dokumen legalitas sudah diunggah)</label></div></div><?php endif; ?>
 <div class="col-12"><button class="btn btn-primary">Simpan</button></div></form></div></div>
<div class="col-lg-5"><div class="card p-4 mb-3"><h6>Dokumen Legalitas</h6><table class="table table-sm"><?php foreach ($docs as $d): ?><tr><td><?= e(strtoupper($d->doc_type)) ?> <small class="text-muted"><?= e($d->doc_number) ?></small></td><td><?= badge($d->status) ?></td><td><a target="_blank" href="<?= site_url('partner/files/partner_doc/' . $d->id) ?>">Lihat</a></td></tr><?php endforeach; ?></table>
 <form method="post" enctype="multipart/form-data" action="<?= site_url('partner/company/upload_doc') ?>" class="row g-2"><?= csrf_field() ?>
  <div class="col-6"><select name="doc_type" class="form-select form-select-sm"><?php foreach (['nib', 'siup', 'npwp', 'akta', 'ppiu', 'pihk', 'iata', 'other'] as $t): ?><option value="<?= $t ?>"><?= strtoupper($t) ?></option><?php endforeach; ?></select></div>
  <div class="col-6"><input name="doc_number" class="form-control form-control-sm" placeholder="Nomor"></div><div class="col-6"><input type="date" name="expires_on" class="form-control form-control-sm"></div>
  <div class="col-6"><input type="file" name="file" class="form-control form-control-sm" required></div><div class="col-12"><button class="btn btn-sm btn-outline-primary">Unggah</button></div></form></div>
 <div class="card p-4"><h6>Rekening</h6><ul class="list-unstyled small"><?php foreach ($banks as $b): ?><li class="mb-2"><b><?= e($b->bank_name) ?></b> <?= e($b->account_number) ?> a.n. <?= e($b->account_holder) ?> <?= $b->is_primary ? '<span class="badge text-bg-primary">utama</span>' : '' ?> <?= badge($b->status) ?> <small class="text-muted">(<?= e($b->purpose) ?>)</small>
 <?php if ($b->status === 'active'): ?><?= post_btn('partner/company/bank_delete/' . $b->id, 'Hapus', 'btn-outline-danger py-0', [], 'Nonaktifkan rekening?') ?><?php endif; ?></li><?php endforeach; ?></ul>
 <form method="post" action="<?= site_url('partner/company/bank_save') ?>" class="row g-2"><?= csrf_field() ?>
  <div class="col-6"><input name="bank_name" class="form-control form-control-sm" placeholder="Bank" required></div><div class="col-6"><input name="account_number" class="form-control form-control-sm" placeholder="No. rekening" required></div>
  <div class="col-12"><input name="account_holder" class="form-control form-control-sm" placeholder="Atas nama" required></div>
  <div class="col-6"><select name="purpose" class="form-select form-select-sm"><option value="both">Pencairan &amp; transfer pelanggan</option><option value="payout">Pencairan saja</option><option value="manual_transfer">Transfer pelanggan saja</option></select></div>
  <div class="col-6 pt-1"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_primary" value="1" id="ip"><label for="ip" class="form-check-label small">Jadikan utama</label></div></div>
  <div class="col-12"><button class="btn btn-sm btn-outline-primary">Tambah rekening</button></div></form></div></div></div>
