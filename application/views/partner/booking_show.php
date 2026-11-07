<?php
$side = '<div class="card p-4 mb-3"><h6>Customer</h6><div>' . e($cust->full_name) . '<br><small class="text-muted">' . e($cust->email) . ' / ' . e($cust->phone) . '</small></div></div>';
if (in_array('booking.confirm', $perms)) {
    $side .= '<div class="card p-4 mb-3"><h6>Tindakan</h6><div class="d-grid gap-2">';
    if (in_array($b->status, ['partially_paid', 'paid'], true)) { $side .= post_note('partner/bookings/confirm/' . $b->id, 'Konfirmasi', 'btn-success', [], 'Catatan internal'); }
    if (in_array($b->status, ['pending', 'partially_paid', 'paid'], true)) { $side .= post_note('partner/bookings/cancel/' . $b->id, 'Batalkan', 'btn-outline-danger', [], 'Alasan', 'reason', true); }
    $side .= '</div></div>';
}
// Dokumen
$dh = '<div class="card p-4 mb-3"><h6>Dokumen</h6><table class="table table-sm">';
foreach ($docs as $d) {
    $dh .= '<tr><td>' . e($d->doc_type) . '<br><small class="text-muted">' . ($d->direction === 'partner_upload' ? 'dari travel' : 'dari customer') . '</small></td><td>' . badge($d->status) . '</td><td><a target="_blank" href="' . site_url('partner/files/booking_document/' . $d->id) . '">Lihat</a></td><td>';
    if ($d->direction === 'customer_upload' && $d->status === 'pending' && in_array('document.manage', $perms)) {
        $dh .= post_btn('partner/bookings/doc_review/' . $d->id, 'OK', 'btn-success', ['action' => 'approve']) . ' ' . post_note('partner/bookings/doc_review/' . $d->id, 'Tolak', 'btn-outline-danger', ['action' => 'reject'], 'Alasan');
    }
    $dh .= '</td></tr>';
}
if (!$docs) { $dh .= '<tr><td class="text-muted">Belum ada dokumen.</td></tr>'; }
$dh .= '</table>';
if (in_array('document.manage', $perms)) {
    $dh .= '<form method="post" enctype="multipart/form-data" action="' . site_url('partner/bookings/upload_doc/' . $b->id) . '" class="row g-2">' . csrf_field()
        . '<div class="col-12"><select name="doc_type" class="form-select form-select-sm"><option value="eticket">E-ticket</option><option value="voucher">Voucher</option><option value="visa">Visa</option><option value="itinerary">Itinerary</option><option value="other">Lainnya</option></select></div>'
        . '<div class="col-12"><input type="file" name="file" class="form-control form-control-sm" required></div><div class="col-12"><button class="btn btn-sm btn-primary">Unggah dokumen perjalanan</button></div></form>';
}
$dh .= '</div>';
$side .= $dh;
echo $this->load->view('shared_booking_detail', compact('b', 'participants', 'payments', 'commission', 'side'), true);
