<div class="card p-4" style="max-width:800px"><form method="post" enctype="multipart/form-data" action="<?= site_url("admin/content/save/$e" . ($row ? '/' . $row->id : '')) ?>"><?= csrf_field() ?>
<?php foreach ($d['fields'] as $f): list($name, $label, $type) = $f; $req = !empty($f[3]); $val = $row && isset($row->$name) ? $row->$name : ''; ?>
 <div class="mb-3"><label class="form-label"><?= e($label) ?><?= $req ? ' *' : '' ?></label>
 <?php if ($type === 'textarea'): ?><textarea name="<?= $name ?>" rows="7" class="form-control"<?= $req ? ' required' : '' ?>><?= e($val) ?></textarea>
 <?php elseif ($type === 'select'): ?><select name="<?= $name ?>" class="form-select"><?php foreach ($f[4] as $o): ?><option<?= sel($val, $o) ?>><?= e($o) ?></option><?php endforeach; ?></select>
 <?php elseif ($type === 'image'): ?><input type="file" name="<?= $name ?>" accept="image/*" class="form-control"><?php if ($val): ?><img src="<?= e(media_url($val)) ?>" style="height:80px" class="mt-2 rounded"><?php endif; ?>
 <?php elseif ($type === 'datetime'): ?><input type="datetime-local" name="<?= $name ?>" class="form-control" value="<?= $val ? date('Y-m-d\TH:i', strtotime($val)) : '' ?>">
 <?php else: ?><input type="<?= $type === 'number' ? 'number' : 'text' ?>" name="<?= $name ?>" class="form-control" value="<?= e($val) ?>"<?= $req ? ' required' : '' ?>><?php endif; ?></div>
<?php endforeach; ?>
<button class="btn btn-primary">Simpan</button> <a href="<?= site_url("admin/content/index/$e") ?>" class="btn btn-light">Batal</a></form></div>
