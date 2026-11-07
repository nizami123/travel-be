<div class="row g-3 mb-4">
 <?= stat_card('Total Customer', number_format($s['customers']), 'people') ?>
 <?= stat_card('Travel Mitra', number_format($s['partners']), 'buildings', '#14b8c4') ?>
 <?= stat_card('Destinasi', number_format($s['destinations']), 'geo-alt') ?>
 <?= stat_card('Paket', number_format($s['packages']), 'luggage', '#14b8c4') ?>
 <?= stat_card('Booking', number_format($s['bookings']), 'journal-check') ?>
 <?= stat_card('Total Transaksi', rupiah($s['transactions']), 'cash-stack', '#14b8c4') ?>
 <?= stat_card('Total Komisi', rupiah($s['commission']), 'percent', '#ff8a1f') ?>
 <?= stat_card('Pendapatan Platform', rupiah($s['platform_revenue']), 'graph-up-arrow', '#ff8a1f') ?>
</div>
<div class="row g-3 mb-4">
 <div class="col-md-3"><a class="text-decoration-none" href="<?= site_url('admin/partners') ?>"><div class="card p-3"><div class="text-muted small">Mitra menunggu verifikasi</div><div class="fs-4 fw-bold"><?= $s['pending_partners'] ?></div></div></a></div>
 <div class="col-md-3"><a class="text-decoration-none" href="<?= site_url('admin/packages') ?>"><div class="card p-3"><div class="text-muted small">Paket menunggu persetujuan</div><div class="fs-4 fw-bold"><?= $s['pending_packages'] ?></div></div></a></div>
 <div class="col-md-3"><a class="text-decoration-none" href="<?= site_url('admin/finance/payouts') ?>"><div class="card p-3"><div class="text-muted small">Pencairan diajukan</div><div class="fs-4 fw-bold"><?= $s['pending_payouts'] ?></div></div></a></div>
 <div class="col-md-3"><a class="text-decoration-none" href="<?= site_url('admin/finance/refunds') ?>"><div class="card p-3"><div class="text-muted small">Refund diajukan</div><div class="fs-4 fw-bold"><?= $s['pending_refunds'] ?></div></div></a></div>
</div>
<div class="card p-3"><h6>Booking &amp; Transaksi 30 Hari Terakhir</h6><canvas id="ch" height="90"></canvas></div>
<script>
$(function(){ $.getJSON('<?= site_url('admin/dashboard/chart') ?>', function(d){
 new Chart(document.getElementById('ch'),{type:'bar',data:{labels:d.labels,datasets:[
  {type:'line',label:'Transaksi (Rp)',data:d.transactions,borderColor:'#14b8c4',backgroundColor:'#14b8c4',yAxisID:'y1',tension:.3},
  {label:'Booking',data:d.bookings,backgroundColor:'#0b3d91',yAxisID:'y'}]},
  options:{scales:{y:{beginAtZero:true,ticks:{precision:0}},y1:{position:'right',beginAtZero:true,grid:{drawOnChartArea:false}}}}});
});});
</script>
