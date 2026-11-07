<?php /* dipakai admin & partner: $b, $participants, $payments, $commission */ ?>
<div class="row g-3"><div class="col-lg-8">
 <div class="card p-4 mb-3"><div class="d-flex justify-content-between"><h5><?= e($b->booking_code) ?></h5><?= badge($b->status) ?></div>
  <table class="table table-sm"><tr><th>Paket</th><td><?= e($b->package_name_snapshot) ?></td><th>Travel</th><td><?= e($b->partner_name_snapshot) ?></td></tr>
  <tr><th>Berangkat</th><td><?= fdate($b->departure_date) ?></td><th>Pulang</th><td><?= fdate($b->return_date) ?></td></tr>
  <tr><th>Kontak</th><td><?= e($b->contact_name) ?><br><small><?= e($b->contact_email) ?> / <?= e($b->contact_phone) ?></small></td><th>Peserta</th><td><?= (int)$b->participant_count ?></td></tr>
  <tr><th>Subtotal</th><td><?= rupiah($b->subtotal) ?></td><th>Diskon</th><td><?= rupiah($b->discount_amount) ?> <?= $b->promo_code_snapshot ? '(' . e($b->promo_code_snapshot) . ')' : '' ?></td></tr>
  <tr><th>Total</th><td class="fw-bold"><?= rupiah($b->total_amount) ?></td><th>Skema</th><td><?= $b->payment_option === 'dp' ? 'DP ' . (float)$b->dp_percent_snapshot . '% (' . rupiah($b->dp_amount) . '), lunas sebelum ' . fdate($b->full_payment_due_date) : 'Bayar lunas' ?></td></tr>
  <tr><th>Terbayar</th><td><?= rupiah($b->paid_amount) ?></td><th>Sisa</th><td><?= rupiah($b->remaining_amount) ?></td></tr>
  <tr><th>Batas bayar</th><td><?= fdt($b->payment_expires_at) ?></td><th>Dibuat</th><td><?= fdt($b->created_at) ?></td></tr>
  <?php if ($b->cancel_reason): ?><tr><th>Alasan batal</th><td colspan="3"><?= e($b->cancel_reason) ?></td></tr><?php endif; ?></table></div>
 <?php if (!empty($participants)): ?><div class="card p-4 mb-3"><h6>Peserta</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Nama</th><th>Gender</th><th>Tgl lahir</th><th>NIK</th><th>Paspor</th><th>Tipe</th><th>Harga</th></tr></thead><tbody>
  <?php foreach ($participants as $p): ?><tr><td><?= e($p->full_name) ?></td><td><?= e($p->gender) ?></td><td><?= fdate($p->birth_date) ?></td><td><?= e($p->id_number) ?></td><td><?= e($p->passport_number) ?> <small class="text-muted"><?= $p->passport_expiry ? '(s/d ' . fdate($p->passport_expiry) . ')' : '' ?></small></td><td><?= e($p->price_label_snapshot) ?></td><td><?= rupiah($p->unit_price) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
 <div class="card p-4"><h6>Pembayaran</h6><table class="table table-sm"><thead><tr><th>Kode</th><th>Jenis</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Waktu</th></tr></thead><tbody>
  <?php foreach ($payments as $p): ?><tr><td><?= e($p->payment_code) ?></td><td><?= e($p->kind) ?></td><td><?= e($p->method_name) ?></td><td><?= rupiah($p->amount) ?></td><td><?= badge($p->status) ?></td><td><?= fdt($p->paid_at ?: $p->created_at) ?></td></tr><?php endforeach; if (!$payments): ?><tr><td colspan="6" class="text-muted">Belum ada pembayaran.</td></tr><?php endif; ?></tbody></table></div>
</div>
<div class="col-lg-4">
 <?php if (!empty($commission)): ?><div class="card p-4 mb-3"><h6>Komisi (snapshot)</h6><table class="table table-sm mb-0">
  <tr><th>Aturan</th><td><?= e($commission->model_snapshot) ?> <?= (float)$commission->percentage_snapshot ?>% + <?= rupiah($commission->fixed_amount_snapshot) ?> <small class="text-muted">(<?= e($commission->rule_scope_snapshot) ?>)</small></td></tr>
  <tr><th>Dasar</th><td><?= rupiah($commission->base_amount) ?></td></tr><tr><th>Komisi platform</th><td><?= rupiah($commission->commission_amount) ?></td></tr><tr><th>Bagian travel</th><td><?= rupiah($commission->partner_amount) ?></td></tr><tr><th>Status</th><td><?= badge($commission->status) ?></td></tr></table></div><?php endif; ?>
 <?= isset($side) ? $side : '' ?>
</div></div>
