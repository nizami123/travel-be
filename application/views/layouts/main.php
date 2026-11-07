<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?: 'Dashboard') ?> - MultiTravel</title>
<meta name="csrf-name" content="<?= $this->security->get_csrf_token_name() ?>"><meta name="csrf-hash" content="<?= $this->security->get_csrf_hash() ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<style>
 :root{--navy:#0b3d91;--turq:#14b8c4;--orange:#ff8a1f;--bg:#f4f8fc}
 body{background:var(--bg)}
 .sidebar{width:250px;min-height:100vh;background:var(--navy);position:fixed;top:0;left:0;z-index:100;overflow-y:auto}
 .sidebar .brand{color:#fff;font-weight:800;font-size:1.4rem;padding:1.2rem 1.5rem}.sidebar .brand span{color:var(--turq)}
 .sidebar a.nav-link{color:#cfe0ff;padding:.65rem 1.5rem;border-left:4px solid transparent}
 .sidebar a.nav-link:hover,.sidebar a.nav-link.active{color:#fff;background:rgba(255,255,255,.08);border-left-color:var(--turq)}
 .main{margin-left:250px}.topbar{background:#fff;border-bottom:1px solid #e6edf5}
 .card{border:0;border-radius:16px;box-shadow:0 4px 18px rgba(11,61,145,.06)}
 .stat .icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;background:var(--navy)}
 .btn-primary{background:var(--navy);border-color:var(--navy)}.btn-primary:hover{background:#093076}
 .btn-accent{background:var(--orange);border-color:var(--orange);color:#fff}
 @media(max-width:991px){.sidebar{display:none}.main{margin-left:0}}
</style></head>
<body>
<?php $cur = uri_string(); ?>
<aside class="sidebar">
  <div class="brand">Multi<span>Travel</span></div>
  <nav class="nav flex-column">
   <?php foreach ($menu as $m): $active = (strpos($cur, $m[1]) === 0) ? 'active' : ''; ?>
     <a class="nav-link <?= $active ?>" href="<?= site_url($m[1]) ?>"><i class="bi bi-<?= $m[2] ?> me-2"></i><?= e($m[0]) ?></a>
   <?php endforeach; ?>
  </nav>
</aside>
<div class="main">
 <div class="topbar d-flex justify-content-between align-items-center px-4 py-3">
   <div><strong><?= e($title) ?></strong><?php if ($partner): ?> <span class="text-muted">| <?= e($partner->name) ?></span><?php endif; ?></div>
   <div class="d-flex align-items-center gap-3"><span class="small text-muted"><?= e($me->full_name) ?> (<?= e(str_replace('_', ' ', $me->user_type)) ?>)</span>
     <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('login/logout') ?>">Keluar</a></div>
 </div>
 <main class="p-4"><?= $content ?></main>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
 window.CSRF = {name: $('meta[name=csrf-name]').attr('content'), hash: $('meta[name=csrf-hash]').attr('content')};
 $(function(){
   $('.dt').DataTable({pageLength:25, order:[], language:{search:'Cari:', lengthMenu:'Tampil _MENU_', info:'_START_–_END_ dari _TOTAL_', paginate:{previous:'‹',next:'›'}, zeroRecords:'Tidak ada data'}});
   $('.select2').select2({theme:'bootstrap-5', width:'100%'});
   // konfirmasi pada form/tombol dengan data-confirm
   $(document).on('submit','form[data-confirm]',function(e){
     if(this.dataset.ok) return; e.preventDefault(); var f=this;
     Swal.fire({title:f.dataset.confirm, icon:'question', showCancelButton:true, confirmButtonText:'Ya', cancelButtonText:'Batal'}).then(function(r){ if(r.isConfirmed){ f.dataset.ok=1; f.submit(); }});
   });
   <?php $fl = $this->session->flashdata('flash'); if ($fl): ?>
   <?php if ($fl['type'] === 'modal'): ?>
   Swal.fire({icon:'success', title:'Berhasil', text:<?= json_encode($fl['msg']) ?>, confirmButtonText:'Saya sudah mencatat'});
   <?php else: ?>
   Swal.fire({toast:true, position:'top-end', timer:4500, showConfirmButton:false, icon:<?= json_encode($fl['type']) ?>, title:<?= json_encode($fl['msg']) ?>});
   <?php endif; ?>
   <?php endif; ?>
 });
</script>
</body></html>
