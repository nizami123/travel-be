<div class="row g-3 mb-3">
 <?= stat_card('Pendapatan Kotor', rupiah($sum->gross), 'cash-stack') ?>
 <?= stat_card('Komisi Platform', rupiah($sum->commission), 'percent', '#ff8a1f') ?>
 <?= stat_card('Pendapatan Bersih', rupiah($sum->net), 'graph-up', '#14b8c4') ?>
 <?= stat_card('Dapat Dicairkan', rupiah($avail), 'wallet2') ?>
 <?= stat_card('Menunggu (perjalanan belum selesai)', rupiah($sum->on_hold), 'hourglass-split', '#14b8c4') ?>
 <?= stat_card('Sudah Dicairkan', rupiah($sum->paid_out), 'bank') ?></div>
<?php if (in_array('payout.request', $perms)): ?><div class="card p-3 mb-3"><h6>Ajukan Pencairan</h6>
<form method="post" action="<?= site_url('partner/finance/request_payout') ?>" class="row g-2 align-items-end" data-confirm="Ajukan pencairan seluruh saldo tersedia (<?= rupiah($avail) ?>)?"><?= csrf_field() ?>
 <div class="col-md-5"><label class="form-label small">Rekening tujuan</label><select name="bank_id" class="form-select" required><option value="">Pilih rekening</option><?php foreach ($banks as $b): ?><option value="<?= $b->id ?>"><?= e($b->bank_name . ' ' . $b->account_number . ' a.n. ' . $b->account_holder) ?></option><?php endforeach; ?></select></div>
 <div class="col-md-3"><button class="btn btn-primary">Ajukan <?= rupiah($avail) ?></button></div><div class="col-md-4 small text-muted">Minimal <?= rupiah($min) ?>. Dana tersedia setelah perjalanan selesai + masa tahan.</div></form></div><?php endif; ?>
<div class="row g-3"><div class="col-lg-6"><div class="card p-3"><h6>Riwayat Pencairan</h6><table class="table table-sm"><thead><tr><th>Kode</th><th>Nominal</th><th>Status</th><th>Tanggal</th></tr></thead><tbody>
<?php foreach ($payouts as $p): ?><tr><td><?= e($p->payout_code) ?></td><td><?= rupiah($p->net_amount) ?></td><td><?= badge($p->status) ?></td><td><?= fdate($p->created_at) ?><?= $p->transfer_proof_path ? ' <a target="_blank" href="' . site_url('partner/files/payout_proof/' . $p->id) . '">bukti</a>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="col-lg-6"><div class="card p-3"><h6>Rincian Komisi per Booking</h6><table class="table table-sm dt"><thead><tr><th>Booking</th><th>Komisi</th><th>Bagian Anda</th><th>Status</th></tr></thead><tbody>
<?php foreach ($comms as $c): ?><tr><td><?= e($c->booking_code) ?></td><td><?= rupiah($c->commission_amount) ?></td><td><?= rupiah($c->partner_amount) ?></td><td><?= badge($c->status) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
