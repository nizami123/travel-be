<div class="row g-3">
 <div class="col-lg-7"><div class="card p-4 mb-3">
  <div class="d-flex justify-content-between"><h5><?= e($p->name) ?> <small class="text-muted"><?= e($p->code) ?></small></h5><div><?= badge($p->verification_status) ?> <?= badge($p->status) ?></div></div>
  <table class="table table-sm mt-2"><tr><th>Email</th><td><?= e($p->email) ?></td><th>Telepon</th><td><?= e($p->phone) ?></td></tr>
  <tr><th>Kota</th><td><?= e($p->city) ?></td><th>NIB/SIUP</th><td><?= e($p->business_license_no) ?></td></tr>
  <tr><th>PPIU</th><td><?= e($p->ppiu_number) ?></td><th>PIHK</th><td><?= e($p->pihk_number) ?></td></tr>
  <tr><th>Booking</th><td><?= (int)$perf->bookings ?></td><th>Pendapatan masuk</th><td><?= rupiah($perf->revenue) ?></td></tr></table>
  <?php if ($p->rejection_note): ?><div class="alert alert-warning py-2">Catatan penolakan: <?= e($p->rejection_note) ?></div><?php endif; ?>
  <div class="d-flex flex-wrap gap-2">
   <?php if ($this->rbac && in_array('partner.verify', $perms)): ?>
     <?= post_btn('admin/partners/verify/' . $p->id, 'Verifikasi & Aktifkan', 'btn-success', ['action' => 'approve'], 'Verifikasi travel ini?') ?>
     <?= post_note('admin/partners/verify/' . $p->id, 'Tolak', 'btn-outline-danger', ['action' => 'reject'], 'Alasan penolakan') ?>
   <?php endif; ?>
   <?php if (in_array('partner.delete', $perms)): ?>
     <?= post_btn('admin/partners/status/' . $p->id, 'Nonaktifkan', 'btn-outline-secondary', ['status' => 'inactive']) ?>
     <?= post_btn('admin/partners/status/' . $p->id, 'Tangguhkan', 'btn-outline-warning', ['status' => 'suspended'], 'Tangguhkan travel (tidak bisa login)?') ?>
     <?= post_btn('admin/partners/status/' . $p->id, 'Aktifkan', 'btn-outline-success', ['status' => 'active']) ?>
   <?php endif; ?>
  </div></div>
  <div class="card p-4"><h6>Dokumen Legalitas</h6>
   <table class="table table-sm"><thead><tr><th>Jenis</th><th>No.</th><th>Status</th><th>File</th><th></th></tr></thead><tbody>
   <?php foreach ($docs as $d): ?><tr><td><?= e(strtoupper($d->doc_type)) ?></td><td><?= e($d->doc_number) ?></td><td><?= badge($d->status) ?></td>
    <td><a target="_blank" href="<?= site_url('admin/files/partner_doc/' . $d->id) ?>">Lihat</a></td>
    <td><?php if ($d->status === 'pending' && in_array('partner.verify', $perms)): ?><?= post_btn('admin/partners/doc_review/' . $d->id, 'Setujui', 'btn-success', ['action' => 'approve']) ?> <?= post_btn('admin/partners/doc_review/' . $d->id, 'Tolak', 'btn-outline-danger', ['action' => 'reject']) ?><?php endif; ?></td></tr>
   <?php endforeach; if (!$docs): ?><tr><td colspan="5" class="text-muted">Belum ada dokumen.</td></tr><?php endif; ?></tbody></table></div>
 </div>
 <div class="col-lg-5">
  <div class="card p-4 mb-3"><h6>Komisi Travel</h6>
   <p class="small text-muted mb-2">Aktif: <?= $rule ? e($rule->model) . ' ' . (float)$rule->percentage . '% + ' . rupiah($rule->fixed_amount) : 'mengikuti global (' . ($global_rule ? (float)$global_rule->percentage . '% + ' . rupiah($global_rule->fixed_amount) : '-') . ')' ?></p>
   <?php if (in_array('commission.manage', $perms)): ?>
   <form method="post" action="<?= site_url('admin/partners/commission/' . $p->id) ?>" class="row g-2"><?= csrf_field() ?>
    <div class="col-12"><select name="model" class="form-select"><option value="percentage">Persentase</option><option value="fixed">Nominal tetap</option><option value="percentage_fixed">Persentase + nominal</option></select></div>
    <div class="col-6"><input name="percentage" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="% komisi"></div>
    <div class="col-6"><input name="fixed_amount" type="number" min="0" class="form-control" placeholder="Nominal Rp"></div>
    <div class="col-12"><button class="btn btn-primary btn-sm">Simpan aturan travel</button></div></form>
   <?php endif; ?></div>
  <div class="card p-4"><h6>Pengguna Travel</h6><ul class="list-unstyled mb-0 small">
   <?php foreach ($users as $u): ?><li class="mb-1"><?= e($u->full_name) ?> <span class="text-muted">(<?= e($u->email) ?>)</span> <?= $u->is_owner ? '<span class="badge text-bg-primary">owner</span>' : '' ?> <?= badge($u->status) ?></li><?php endforeach; ?></ul></div>
 </div>
</div>
