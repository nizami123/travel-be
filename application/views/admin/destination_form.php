<?php $g = function ($k, $d = '') use ($row) { return $row && isset($row->$k) ? $row->$k : $d; }; ?>
<div class="card p-4"><form method="post" enctype="multipart/form-data" action="<?= site_url('admin/destinations/save' . ($row ? '/' . $row->id : '')) ?>"><?= csrf_field() ?>
<div class="row g-3">
 <div class="col-md-6"><label class="form-label">Nama *</label><input name="name" class="form-control" value="<?= e($g('name')) ?>" required></div>
 <div class="col-md-3"><label class="form-label">Cakupan *</label><select name="scope" class="form-select"><?php foreach (['local' => 'Lokal', 'international' => 'Luar Negeri', 'hajj_umrah' => 'Haji/Umroh'] as $k => $v): ?><option value="<?= $k ?>"<?= sel($g('scope', 'local'), $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
 <div class="col-md-3"><label class="form-label">Kategori</label><select name="category_id" class="form-select"><option value="">-</option><?php foreach ($cats as $c): ?><option value="<?= $c->id ?>"<?= sel($g('category_id'), $c->id) ?>><?= e($c->name) ?></option><?php endforeach; ?></select></div>
 <div class="col-md-4"><label class="form-label">Negara</label><input name="country" class="form-control" value="<?= e($g('country', 'Indonesia')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Provinsi</label><input name="province" class="form-control" value="<?= e($g('province')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Kabupaten/Kota</label><input name="city" class="form-control" value="<?= e($g('city')) ?>"></div>
 <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"><?= e($g('address')) ?></textarea></div>
 <div class="col-md-3"><label class="form-label">Latitude</label><input name="latitude" class="form-control" value="<?= e($g('latitude')) ?>"></div>
 <div class="col-md-3"><label class="form-label">Longitude</label><input name="longitude" class="form-control" value="<?= e($g('longitude')) ?>"></div>
 <div class="col-md-6"><label class="form-label">Link Google Maps</label><input name="google_maps_url" class="form-control" value="<?= e($g('google_maps_url')) ?>"></div>
 <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="4"><?= e($g('description')) ?></textarea></div>
 <div class="col-md-6"><label class="form-label">Fasilitas (satu per baris)</label><textarea name="facilities" class="form-control" rows="4"><?= e($row && $row->facilities ? implode("\n", json_decode($row->facilities, true) ?: []) : '') ?></textarea></div>
 <div class="col-md-6"><label class="form-label">Informasi tambahan</label><textarea name="additional_info" class="form-control" rows="4"><?= e($g('additional_info')) ?></textarea></div>
 <div class="col-md-4"><label class="form-label">Foto utama</label><input type="file" name="cover" accept="image/*" class="form-control"><?php if ($g('cover_path')): ?><img src="<?= e(media_url($g('cover_path'))) ?>" class="mt-2 rounded" style="height:70px"><?php endif; ?></div>
 <div class="col-md-4"><label class="form-label">Tambah galeri (maks 10)</label><input type="file" name="gallery[]" multiple accept="image/*" class="form-control"></div>
 <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['draft', 'published', 'archived'] as $s): ?><option<?= sel($g('status', 'published'), $s) ?>><?= $s ?></option><?php endforeach; ?></select></div>
 <div class="col-md-2 pt-4"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_popular" value="1" id="pop"<?= $g('is_popular') ? ' checked' : '' ?>><label for="pop" class="form-check-label">Populer</label></div></div>
</div>
<button class="btn btn-primary mt-3">Simpan</button> <a href="<?= site_url('admin/destinations') ?>" class="btn btn-light mt-3">Batal</a></form>
<?php if (!empty($media)): ?><hr><h6>Galeri</h6><div class="d-flex flex-wrap gap-3"><?php foreach ($media as $m): ?><div><img src="<?= e(media_url($m->file_path)) ?>" style="height:90px;border-radius:8px"><br><?= post_btn('admin/destinations/media_delete/' . $m->id, 'Hapus', 'btn-outline-danger mt-1', [], 'Hapus foto?') ?></div><?php endforeach; ?></div><?php endif; ?></div>
