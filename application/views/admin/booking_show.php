<?php
$side = '';
if (in_array('booking.confirm', $perms) && in_array($b->status, ['pending', 'partially_paid', 'paid'], true)) {
    $side .= '<div class="card p-4 mb-3"><h6>Pembatalan</h6>' . post_note('admin/bookings/cancel/' . $b->id, 'Batalkan', 'btn-danger', [], 'Alasan', 'reason', true) . '</div>';
}
if (!empty($refunds)) {
    $side .= '<div class="card p-4"><h6>Refund</h6>';
    foreach ($refunds as $r) { $side .= '<div class="small mb-2">' . e($r->refund_code) . ' - ' . rupiah($r->refund_amount) . ' ' . badge($r->status) . '</div>'; }
    $side .= '<a href="' . site_url('admin/finance/refunds') . '" class="btn btn-sm btn-outline-primary">Kelola refund</a></div>';
}
echo $this->load->view('shared_booking_detail', compact('b', 'participants', 'payments', 'commission', 'side'), true);
?>
