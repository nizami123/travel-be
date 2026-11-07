<div class="row g-3 mb-3">
 <?= stat_card('Dana Diterima (paid)', rupiah($tot->received), 'cash-stack') ?>
 <?= stat_card('Refund Selesai', rupiah($tot->refunded), 'arrow-counterclockwise', '#ff8a1f') ?>
 <?= stat_card('Pencairan ke Mitra', rupiah($tot->payouts), 'bank', '#14b8c4') ?>
 <div class="col-6 col-lg-3 d-flex align-items-center gap-2">
  <a class="btn btn-outline-primary" href="<?= site_url('admin/finance/payouts') ?>">Pencairan</a><a class="btn btn-outline-primary" href="<?= site_url('admin/finance/refunds') ?>">Refund</a></div></div>
<?php if (in_array('report.export', $perms)): ?><form class="row g-2 mb-3" method="get" action="<?= site_url('admin/finance/export') ?>"><div class="col-auto"><input type="date" name="from" class="form-control" value="<?= date('Y-m-01') ?>"></div><div class="col-auto"><input type="date" name="to" class="form-control" value="<?= date('Y-m-d') ?>"></div><div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Export CSV transaksi</button></div></form><?php endif; ?>
<div class="card p-3"><h6>Rekap per Travel</h6><table class="table table-hover dt"><thead><tr><th>Travel</th><th>Booking</th><th>Pendapatan Kotor</th><th>Komisi Platform</th><th>Bagian Travel</th><th>Tersedia dicairkan</th><th>Sudah dicairkan</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r->name) ?></td><td><?= (int)$r->bookings ?></td><td><?= rupiah($r->gross) ?></td><td><?= rupiah($r->commission) ?></td><td><?= rupiah($r->partner_amount) ?></td><td><?= rupiah($r->available) ?></td><td><?= rupiah($r->paid_out) ?></td></tr><?php endforeach; ?></tbody></table></div>
