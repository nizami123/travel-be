<div class="row g-3"><div class="col-lg-8"><div class="card p-4">
 <div class="d-flex justify-content-between"><h5><?= e($p->name) ?></h5><div><?= badge($p->approval_status) ?> <?= badge($p->status) ?></div></div>
 <p class="text-muted"><?= e($p->partner_name) ?> • <?= e($p->category_name) ?> • <?= (int)$p->duration_days ?>H/<?= (int)$p->duration_nights ?>M • DP <?= (float)$p->dp_percent ?>% • pelunasan H-<?= (int)$p->full_payment_days_before ?></p>
 <?php if ($p->cover_path): ?><img src="<?= e(media_url($p->cover_path)) ?>" class="img-fluid rounded mb-3" style="max-height:260px"><?php endif; ?>
 <p><?= nl2br(e($p->description)) ?></p>
 <h6>Itinerary</h6><ol><?php foreach ($itin as $i): ?><li><strong><?= e($i->title) ?></strong> <?= e($i->description) ?></li><?php endforeach; ?></ol>
 <h6>Jadwal</h6><table class="table table-sm"><thead><tr><th>Berangkat</th><th>Pulang</th><th>Kuota</th><th>Ditahan</th><th>Terjual</th><th>Status</th></tr></thead><tbody>
 <?php foreach ($schedules as $s): ?><tr><td><?= fdate($s->departure_date) ?></td><td><?= fdate($s->return_date) ?></td><td><?= (int)$s->quota_total ?></td><td><?= (int)$s->quota_held ?></td><td><?= (int)$s->quota_booked ?></td><td><?= badge($s->status) ?></td></tr><?php endforeach; ?></tbody></table>
</div></div>
<div class="col-lg-4"><div class="card p-4"><h6>Tindakan</h6>
 <?php if ($p->approval_note): ?><div class="alert alert-light small">Catatan: <?= e($p->approval_note) ?></div><?php endif; ?>
 <?php if (in_array('package.approve', $perms)): ?>
  <div class="d-grid gap-2">
  <?= post_btn('admin/packages/review/' . $p->id, 'Setujui & Tayangkan', 'btn-success', ['action' => 'approve'], 'Setujui paket ini?') ?>
  <?= post_note('admin/packages/review/' . $p->id, 'Tolak', 'btn-outline-danger', ['action' => 'reject'], 'Alasan penolakan') ?>
  <?= post_btn('admin/packages/review/' . $p->id, 'Nonaktifkan', 'btn-outline-secondary', ['action' => 'deactivate']) ?>
  <?= post_btn('admin/packages/review/' . $p->id, 'Aktifkan', 'btn-outline-success', ['action' => 'activate']) ?>
  </div>
 <?php endif; ?></div></div></div>
