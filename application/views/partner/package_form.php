<?php
$g = function ($k, $d = '') use ($row) { return $row && isset($row->$k) ? $row->$k : $d; };
$reqdocs = $row && $row->required_documents ? (json_decode($row->required_documents, true) ?: []) : [];
$itin_text = implode("\n", array_map(function ($i) { return $i->title . ' | ' . $i->description . ' | ' . $i->meals; }, $itin));
$hv = function ($k, $d = '') use ($hu) { return $hu && isset($hu->$k) ? $hu->$k : $d; };
?>
<form method="post" enctype="multipart/form-data" action="<?= site_url('partner/packages/save' . ($row ? '/' . $row->id : '')) ?>"><?= csrf_field() ?>
<div class="row g-3"><div class="col-lg-8"><div class="card p-4 mb-3"><h6>Informasi Paket</h6><div class="row g-3">
 <div class="col-md-8"><label class="form-label">Nama paket *</label><input name="name" class="form-control" value="<?= e($g('name')) ?>" required></div>
 <div class="col-md-4"><label class="form-label">Kategori *</label><select name="category_id" id="cat" class="form-select" required><option value="">Pilih</option><?php foreach ($cats as $c): ?><option data-type="<?= e($c->type) ?>" value="<?= $c->id ?>"<?= sel($g('category_id'), $c->id) ?>><?= e($c->name) ?></option><?php endforeach; ?></select></div>
 <div class="col-12"><label class="form-label">Ringkasan</label><input name="summary" maxlength="300" class="form-control" value="<?= e($g('summary')) ?>"></div>
 <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="description" rows="5" class="form-control"><?= e($g('description')) ?></textarea></div>
 <div class="col-md-3"><label class="form-label">Durasi (hari) *</label><input name="duration_days" type="number" min="1" class="form-control" value="<?= e($g('duration_days', 3)) ?>" required></div>
 <div class="col-md-3"><label class="form-label">Malam</label><input name="duration_nights" type="number" min="0" class="form-control" value="<?= e($g('duration_nights', 2)) ?>"></div>
 <div class="col-md-3"><label class="form-label">Kota berangkat</label><input name="start_city" class="form-control" value="<?= e($g('start_city')) ?>"></div>
 <div class="col-md-3"><label class="form-label">Min. peserta</label><input name="min_participants" type="number" min="1" class="form-control" value="<?= e($g('min_participants', 1)) ?>"></div>
 <div class="col-md-3"><label class="form-label">Maks. peserta/booking</label><input name="max_participants_per_booking" type="number" min="1" class="form-control" value="<?= e($g('max_participants_per_booking', 10)) ?>"></div>
 <div class="col-md-3"><label class="form-label">DP minimum (%) *</label><input name="dp_percent" type="number" step="0.01" class="form-control" value="<?= e($g('dp_percent', 30)) ?>" required></div>
 <div class="col-md-3"><label class="form-label">Pelunasan H- *</label><input name="full_payment_days_before" type="number" min="0" class="form-control" value="<?= e($g('full_payment_days_before', 14)) ?>" required></div>
 <div class="col-md-3"><label class="form-label">WhatsApp paket</label><input name="whatsapp_number" class="form-control" value="<?= e($g('whatsapp_number')) ?>"></div>
 <div class="col-md-6"><label class="form-label">Transportasi</label><textarea name="transportation" rows="2" class="form-control"><?= e($g('transportation')) ?></textarea></div>
 <div class="col-md-6"><label class="form-label">Akomodasi</label><textarea name="accommodation" rows="2" class="form-control"><?= e($g('accommodation')) ?></textarea></div>
 <div class="col-12"><label class="form-label">Destinasi</label><select name="destinations[]" class="form-select select2" multiple><?php foreach ($dests as $dd): ?><option value="<?= $dd->id ?>"<?= in_array((int)$dd->id, $dest, true) ? ' selected' : '' ?>><?= e($dd->name) ?></option><?php endforeach; ?></select></div>
</div></div>
<div class="card p-4 mb-3"><h6>Itinerary &amp; Fasilitas</h6><div class="row g-3">
 <div class="col-12"><label class="form-label">Itinerary — satu baris per hari: <code>Judul | Deskripsi | Makan (B,L,D)</code></label><textarea name="itinerary" rows="6" class="form-control"><?= e($itin_text) ?></textarea></div>
 <div class="col-md-6"><label class="form-label">Fasilitas termasuk (satu per baris)</label><textarea name="included" rows="5" class="form-control"><?= e(implode("\n", $inc)) ?></textarea></div>
 <div class="col-md-6"><label class="form-label">Tidak termasuk</label><textarea name="excluded" rows="5" class="form-control"><?= e(implode("\n", $exc)) ?></textarea></div>
 <div class="col-12"><label class="form-label">Syarat &amp; ketentuan</label><textarea name="terms_conditions" rows="4" class="form-control"><?= e($g('terms_conditions')) ?></textarea></div>
 <div class="col-12"><label class="form-label">Kebijakan pembatalan</label><textarea name="cancellation_policy" rows="3" class="form-control"><?= e($g('cancellation_policy')) ?></textarea></div>
 <div class="col-12"><label class="form-label d-block">Dokumen wajib peserta</label><?php foreach (['ktp' => 'KTP', 'kk' => 'KK', 'passport' => 'Paspor', 'photo' => 'Pas foto', 'vaccine' => 'Vaksin', 'mahram_letter' => 'Surat mahram', 'visa' => 'Visa'] as $k => $v): ?><div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" name="required_documents[]" value="<?= $k ?>" id="rd<?= $k ?>"<?= in_array($k, $reqdocs) ? ' checked' : '' ?>><label class="form-check-label" for="rd<?= $k ?>"><?= $v ?></label></div><?php endforeach; ?></div>
</div></div>
<div class="card p-4 mb-3" id="huBox" style="display:none"><h6>Khusus Haji &amp; Umroh</h6><div class="row g-3">
 <div class="col-md-4"><label class="form-label">Jenis program</label><select name="program_type" class="form-select"><?php foreach (['umrah_reguler', 'umrah_plus', 'umrah_vip', 'haji_khusus', 'haji_furoda', 'lainnya'] as $t): ?><option<?= sel($hv('program_type'), $t) ?>><?= $t ?></option><?php endforeach; ?></select></div>
 <div class="col-md-4"><label class="form-label">No. izin PPIU/PIHK</label><input name="ppiu_pihk_number" class="form-control" value="<?= e($hv('ppiu_pihk_number')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Nama PPIU/PIHK</label><input name="ppiu_pihk_name" class="form-control" value="<?= e($hv('ppiu_pihk_name')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Maskapai</label><input name="airline" class="form-control" value="<?= e($hv('airline')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Bandara berangkat</label><input name="departure_airport" class="form-control" value="<?= e($hv('departure_airport')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Pembimbing/Mutawwif</label><input name="mutawwif_name" class="form-control" value="<?= e($hv('mutawwif_name')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Hotel Makkah</label><input name="hotel_makkah" class="form-control" value="<?= e($hv('hotel_makkah')) ?>"></div>
 <div class="col-md-2"><label class="form-label">Bintang</label><input name="hotel_makkah_star" type="number" min="1" max="5" class="form-control" value="<?= e($hv('hotel_makkah_star')) ?>"></div>
 <div class="col-md-4"><label class="form-label">Hotel Madinah</label><input name="hotel_madinah" class="form-control" value="<?= e($hv('hotel_madinah')) ?>"></div>
 <div class="col-md-2"><label class="form-label">Bintang</label><input name="hotel_madinah_star" type="number" min="1" max="5" class="form-control" value="<?= e($hv('hotel_madinah_star')) ?>"></div>
 <div class="col-md-6"><label class="form-label">Jenis kamar (pisah koma)</label><input name="room_types" class="form-control" placeholder="quad, triple, double" value="<?= e($hu && $hu->room_types ? implode(', ', json_decode($hu->room_types, true) ?: []) : '') ?>"></div>
 <div class="col-md-6"><label class="form-label">Fasilitas tambahan</label><input name="extra_facilities" class="form-control" value="<?= e($hv('extra_facilities')) ?>"></div>
 <div class="col-md-6"><label class="form-label">Manasik</label><textarea name="manasik_info" rows="2" class="form-control"><?= e($hv('manasik_info')) ?></textarea></div>
 <div class="col-md-6"><label class="form-label">Informasi visa</label><textarea name="visa_info" rows="2" class="form-control"><?= e($hv('visa_info')) ?></textarea></div>
</div></div></div>
<div class="col-lg-4"><div class="card p-4 mb-3"><h6>Foto</h6><label class="form-label">Foto utama</label><input type="file" name="cover" accept="image/*" class="form-control"><?php if ($g('cover_path')): ?><img src="<?= e(media_url($g('cover_path'))) ?>" class="img-fluid rounded mt-2"><?php endif; ?>
 <label class="form-label mt-3">Tambah galeri (maks 10)</label><input type="file" name="gallery[]" multiple accept="image/*" class="form-control">
 <?php if ($media): ?><div class="d-flex flex-wrap gap-2 mt-3"><?php foreach ($media as $m): ?><div class="text-center"><img src="<?= e(media_url($m->file_path)) ?>" style="height:60px;border-radius:6px"></div><?php endforeach; ?></div><?php endif; ?></div>
<button class="btn btn-primary w-100 mb-2">Simpan Paket</button><a href="<?= site_url('partner/packages') ?>" class="btn btn-light w-100">Kembali</a></div></div></form>
<?php if ($media): ?><div class="card p-3 mt-3"><h6>Hapus foto galeri</h6><div class="d-flex flex-wrap gap-3"><?php foreach ($media as $m): ?><div><img src="<?= e(media_url($m->file_path)) ?>" style="height:70px;border-radius:6px"><br><?= post_btn('partner/packages/media_delete/' . $m->id, 'Hapus', 'btn-outline-danger mt-1', [], 'Hapus foto?') ?></div><?php endforeach; ?></div></div><?php endif; ?>
<script>$(function(){ function t(){ var ty=$('#cat option:selected').data('type'); $('#huBox').toggle(ty==='hajj'||ty==='umrah'); } $('#cat').on('change',t); t(); });</script>
