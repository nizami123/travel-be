<?php if ($partner->verification_status !== 'verified'): ?>
<div class="alert alert-warning">Status verifikasi: <b><?= e($partner->verification_status) ?></b>. Lengkapi <a href="<?= site_url('partner/company') ?>">profil &amp; dokumen legalitas</a> agar paket Anda dapat tayang.</div>
<?php endif; ?>
<div class="row g-3 mb-4">
 <?= stat_card('Paket', number_format($s['packages']), 'luggage') ?>
 <?= stat_card('Total Booking', number_format($s['bookings']), 'journal-check', '#14b8c4') ?>
 <?= stat_card('Booking Aktif', number_format($s['active']), 'activity') ?>
 <?= stat_card('Perlu Verifikasi Bayar', number_format($s['to_verify']), 'hourglass-split', '#ff8a1f') ?>
 <?= stat_card('Pendapatan Bersih', rupiah($s['revenue']), 'cash-stack', '#14b8c4') ?>
 <?= stat_card('Saldo Dapat Dicairkan', rupiah($s['available']), 'wallet2') ?>
</div>
<div class="row g-3"><div class="col-lg-8"><div class="card p-3"><h6>Booking &amp; Transaksi 30 Hari</h6><canvas id="ch" height="110"></canvas></div></div>
<div class="col-lg-4"><div class="card p-3"><h6>Keberangkatan Terdekat</h6><ul class="list-unstyled small mb-0">
<?php foreach ($up as $u): $left = (int)$u->quota_total - (int)$u->quota_held - (int)$u->quota_booked; ?><li class="mb-2"><b><?= fdate($u->departure_date) ?></b> — <?= e($u->name) ?><br><span class="text-muted">Terjual <?= (int)$u->quota_booked ?> • ditahan <?= (int)$u->quota_held ?> • sisa <?= $left ?></span></li><?php endforeach; if (!$up): ?><li class="text-muted">Belum ada jadwal.</li><?php endif; ?></ul></div></div></div>
<script>$(function(){ $.getJSON('<?= site_url('partner/dashboard/chart') ?>', function(d){ new Chart(document.getElementById('ch'),{type:'bar',data:{labels:d.labels,datasets:[{type:'line',label:'Transaksi (Rp)',data:d.transactions,borderColor:'#14b8c4',backgroundColor:'#14b8c4',yAxisID:'y1',tension:.3},{label:'Booking',data:d.bookings,backgroundColor:'#0b3d91',yAxisID:'y'}]},options:{scales:{y:{beginAtZero:true,ticks:{precision:0}},y1:{position:'right',beginAtZero:true,grid:{drawOnChartArea:false}}}}}); });});</script>
