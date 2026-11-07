<div class="row g-3"><div class="col-lg-4"><div class="card p-4"><h6>Tambah / Ganti Aturan</h6>
<form method="post" action="<?= site_url('admin/finance/commission_save') ?>" class="row g-2"><?= csrf_field() ?>
 <div class="col-12"><label class="form-label small">Cakupan</label><select name="scope" id="scope" class="form-select"><option value="global">Global platform</option><option value="partner">Per travel</option><option value="package">Per paket</option></select></div>
 <div class="col-12 sc sc-partner d-none"><select name="travel_partner_id" class="form-select select2"><option value="">Pilih travel</option><?php foreach ($partners as $p): ?><option value="<?= $p->id ?>"><?= e($p->name) ?></option><?php endforeach; ?></select></div>
 <div class="col-12 sc sc-package d-none"><select name="package_id" class="form-select select2"><option value="">Pilih paket</option><?php foreach ($packages as $p): ?><option value="<?= $p->id ?>"><?= e($p->partner_name . ' - ' . $p->name) ?></option><?php endforeach; ?></select></div>
 <div class="col-12"><label class="form-label small">Model</label><select name="model" class="form-select"><option value="percentage">Persentase</option><option value="fixed">Nominal tetap</option><option value="percentage_fixed">Persentase + nominal tetap</option></select></div>
 <div class="col-6"><input name="percentage" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="% (mis. 5)"></div><div class="col-6"><input name="fixed_amount" type="number" min="0" class="form-control" placeholder="Rp (mis. 50000)"></div>
 <div class="col-12"><input name="note" class="form-control" placeholder="Catatan"></div>
 <div class="col-12"><button class="btn btn-primary">Simpan</button></div></form>
 <p class="small text-muted mt-3">Prioritas: <b>paket &gt; travel &gt; global</b>. Contoh 5% + Rp50.000 dari Rp5.000.000 = Rp300.000. Perubahan hanya berlaku untuk booking baru.</p></div></div>
<div class="col-lg-8"><div class="card p-3"><table class="table table-sm"><thead><tr><th>Cakupan</th><th>Target</th><th>Model</th><th>%</th><th>Nominal</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r->scope) ?></td><td><?= e($r->partner_name ?: $r->package_name ?: '-') ?></td><td><?= e($r->model) ?></td><td><?= (float)$r->percentage ?></td><td><?= rupiah($r->fixed_amount) ?></td><td><?= $r->is_active ? badge('active') : badge('inactive') ?></td>
<td><?= post_btn('admin/finance/commission_toggle/' . $r->id, $r->is_active ? 'Nonaktifkan' : 'Aktifkan', 'btn-outline-secondary') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<script>$(function(){ $('#scope').on('change',function(){ $('.sc').addClass('d-none'); $('.sc-'+this.value).removeClass('d-none'); }); });</script>
