<div class="d-flex justify-content-between mb-3"><div></div><?php if (in_array('partner.create', $perms)): ?><a href="<?= site_url('admin/partners/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Travel</a><?php endif; ?></div>
<div class="card p-3"><table class="table table-hover dt"><thead><tr><th>Kode</th><th>Nama</th><th>Kota</th><th>Verifikasi</th><th>Status</th><th>Paket</th><th>Booking</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r->code) ?></td><td><?= e($r->name) ?></td><td><?= e($r->city) ?></td><td><?= badge($r->verification_status) ?></td><td><?= badge($r->status) ?></td><td><?= (int)$r->pkg_count ?></td><td><?= (int)$r->bk_count ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/partners/show/' . $r->id) ?>">Detail</a></td></tr><?php endforeach; ?>
</tbody></table></div>
