<div class="row g-3"><div class="col-lg-4"><div class="card p-4"><h6>Tambah Jadwal</h6>
<form method="post" action="<?= site_url('partner/schedules/save/' . $p->id) ?>" class="row g-2"><?= csrf_field() ?>
 <div class="col-6"><label class="form-label small">Berangkat *</label><input type="date" name="departure_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="form-control" required></div>
 <div class="col-6"><label class="form-label small">Pulang <small>(otomatis)</small></label><input type="date" name="return_date" class="form-control"></div>
 <div class="col-12"><label class="form-label small">Kuota total *</label><input type="number" name="quota_total" min="1" class="form-control" required></div>
 <div class="col-12"><label class="form-label small">Harga — satu baris: <code>kode | label | harga</code></label><textarea name="prices" rows="4" class="form-control" placeholder="adult | Dewasa | 1750000&#10;child | Anak (2-11 th) | 1400000" required></textarea></div>
 <div class="col-12"><input name="departure_note" class="form-control" placeholder="Catatan (titik kumpul, dll.)"></div>
 <div class="col-12"><button class="btn btn-primary w-100">Tambah Jadwal</button></div></form>
 <p class="small text-muted mt-2">Baris pertama menjadi harga utama. Perubahan harga hanya berlaku untuk booking baru.</p></div></div>
<div class="col-lg-8"><?php foreach ($rows as $s): $left = (int)$s->quota_total - (int)$s->quota_held - (int)$s->quota_booked; ?>
<div class="card p-3 mb-3"><div class="d-flex justify-content-between"><div><b><?= fdate($s->departure_date) ?></b> → <?= fdate($s->return_date) ?> <?= badge($s->status) ?><div class="small text-muted"><?= e($s->departure_note) ?></div></div>
 <div class="text-end small">Kuota <b><?= (int)$s->quota_total ?></b> • ditahan <?= (int)$s->quota_held ?> • terjual <?= (int)$s->quota_booked ?> • <b class="text-success">sisa <?= $left ?></b></div></div>
 <form method="post" action="<?= site_url('partner/schedules/prices/' . $s->id) ?>" class="row g-2 mt-1"><?= csrf_field() ?>
  <?php foreach ($s->prices as $pr): ?><div class="col-md-4"><label class="small text-muted"><?= e($pr->label) ?> (<?= e($pr->price_code) ?>)</label><input type="number" min="0" name="price[<?= $pr->id ?>]" value="<?= (float)$pr->price ?>" class="form-control form-control-sm"></div><?php endforeach; ?>
  <div class="col-12"><button class="btn btn-sm btn-outline-primary">Simpan harga</button></div></form>
 <div class="d-flex flex-wrap gap-2 mt-2">
  <form method="post" action="<?= site_url('partner/schedules/quota/' . $s->id) ?>" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="number" name="quota_total" min="<?= (int)$s->quota_held + (int)$s->quota_booked ?>" value="<?= (int)$s->quota_total ?>" class="form-control form-control-sm" style="width:90px"><button class="btn btn-sm btn-outline-secondary">Ubah kuota</button></form>
  <?php if ($s->status === 'open'): ?><?= post_btn('partner/schedules/status/' . $s->id, 'Tutup penjualan', 'btn-outline-warning', ['status' => 'closed']) ?><?php elseif ($s->status === 'closed'): ?><?= post_btn('partner/schedules/status/' . $s->id, 'Buka lagi', 'btn-outline-success', ['status' => 'open']) ?><?php endif; ?>
  <?php if (in_array($s->status, ['open', 'closed', 'draft'])): ?><?= post_btn('partner/schedules/status/' . $s->id, 'Batalkan jadwal', 'btn-outline-danger', ['status' => 'cancelled'], 'Batalkan jadwal ini?') ?><?php endif; ?>
  <?php if (in_array('participant.view', $perms)): ?><a class="btn btn-sm btn-outline-info" href="<?= site_url('partner/bookings/export_participants/' . $s->id) ?>">Export peserta (CSV)</a><?php endif; ?></div></div>
<?php endforeach; if (!$rows): ?><div class="card p-4 text-muted">Belum ada jadwal.</div><?php endif; ?></div></div>
