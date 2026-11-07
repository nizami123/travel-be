<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Query katalog publik: destinasi & paket. Hanya data published/approved. */
class Catalog_model extends CI_Model
{
    const PKG_BASE_WHERE = "p.approval_status = 'approved' AND p.status = 'active' AND p.deleted_at IS NULL
        AND tp.status = 'active' AND tp.verification_status = 'verified' AND tp.deleted_at IS NULL";

    protected function like($s) { return '%' . $this->db->escape_like_str($s) . '%'; }

    // ------------------------------------------------------------ PAKET
    public function search_packages(array $f, $limit, $offset)
    {
        $w = [self::PKG_BASE_WHERE]; $p = [];
        if (!empty($f['category'])) {
            if (ctype_digit((string)$f['category'])) { $w[] = 'p.category_id = ?'; $p[] = (int)$f['category']; }
            else { $w[] = 'pc.type = ?'; $p[] = $f['category']; }
        }
        if (!empty($f['q'])) {
            $l = $this->like($f['q']);
            $w[] = "(p.name LIKE ? ESCAPE '!' OR p.summary LIKE ? ESCAPE '!' OR EXISTS (
                      SELECT 1 FROM package_destinations pd JOIN destinations d ON d.id = pd.destination_id
                       WHERE pd.package_id = p.id AND (d.name LIKE ? ESCAPE '!' OR d.city LIKE ? ESCAPE '!' OR d.country LIKE ? ESCAPE '!')))";
            array_push($p, $l, $l, $l, $l, $l);
        }
        if (!empty($f['location'])) {
            $l = $this->like($f['location']);
            $w[] = "EXISTS (SELECT 1 FROM package_destinations pd JOIN destinations d ON d.id = pd.destination_id
                     WHERE pd.package_id = p.id AND (d.city LIKE ? ESCAPE '!' OR d.province LIKE ? ESCAPE '!' OR d.country LIKE ? ESCAPE '!'))";
            array_push($p, $l, $l, $l);
        }
        if (!empty($f['destination_id'])) {
            $w[] = 'EXISTS (SELECT 1 FROM package_destinations pd WHERE pd.package_id = p.id AND pd.destination_id = ?)';
            $p[] = (int)$f['destination_id'];
        }
        if (!empty($f['partner_id'])) { $w[] = 'p.travel_partner_id = ?'; $p[] = (int)$f['partner_id']; }
        if (isset($f['min_price']) && $f['min_price'] !== '') { $w[] = 'p.base_price >= ?'; $p[] = (float)$f['min_price']; }
        if (isset($f['max_price']) && $f['max_price'] !== '') { $w[] = 'p.base_price <= ?'; $p[] = (float)$f['max_price']; }
        if (!empty($f['min_days'])) { $w[] = 'p.duration_days >= ?'; $p[] = (int)$f['min_days']; }
        if (!empty($f['max_days'])) { $w[] = 'p.duration_days <= ?'; $p[] = (int)$f['max_days']; }
        if (!empty($f['date_from']) || !empty($f['date_to'])) {
            $from = !empty($f['date_from']) ? $f['date_from'] : date('Y-m-d');
            $to = !empty($f['date_to']) ? $f['date_to'] : '2100-01-01';
            $w[] = "EXISTS (SELECT 1 FROM package_schedules s JOIN package_quotas q ON q.schedule_id = s.id
                     WHERE s.package_id = p.id AND s.status = 'open' AND s.departure_date BETWEEN ? AND ?
                       AND q.quota_total - q.quota_held - q.quota_booked > 0)";
            array_push($p, $from, $to);
        }
        $order = [
            'popular' => 'p.booking_count DESC, p.rating_avg DESC', 'price_asc' => 'p.base_price ASC',
            'price_desc' => 'p.base_price DESC', 'newest' => 'p.id DESC', 'rating' => 'p.rating_avg DESC, p.rating_count DESC',
        ];
        $ord = isset($order[$f['sort'] ?? '']) ? $order[$f['sort']] : $order['popular'];
        $from = 'FROM travel_packages p JOIN travel_partners tp ON tp.id = p.travel_partner_id
                 JOIN package_categories pc ON pc.id = p.category_id WHERE ' . implode(' AND ', $w);

        $total = (int)$this->db->query('SELECT COUNT(*) c ' . $from, $p)->row()->c;
        $rows = $this->db->query(
            "SELECT p.id, p.name, p.slug, p.summary, p.cover_path, p.base_price, p.duration_days, p.duration_nights,
                    p.start_city, p.min_participants, p.rating_avg, p.rating_count, p.booking_count, p.travel_partner_id,
                    pc.type AS category_type, pc.name AS category_name, tp.name AS partner_name, tp.logo_path AS partner_logo,
                    (SELECT MIN(s.departure_date) FROM package_schedules s JOIN package_quotas q ON q.schedule_id = s.id
                      WHERE s.package_id = p.id AND s.status = 'open' AND s.departure_date > CURDATE()
                        AND q.quota_total - q.quota_held - q.quota_booked > 0) AS next_departure
             " . $from . ' ORDER BY ' . $ord . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset, $p)->result();
        return [$rows, $total];
    }

    public function format_package($r)
    {
        $this->load->model('promo_model');
        $promo = $this->promo_model->best_for_listing($r->id, $r->travel_partner_id, $r->base_price);
        return [
            'id' => (int)$r->id, 'name' => $r->name, 'slug' => $r->slug, 'summary' => $r->summary,
            'cover_url' => media_url($r->cover_path),
            'category' => ['type' => $r->category_type, 'name' => $r->category_name],
            'partner' => ['id' => (int)$r->travel_partner_id, 'name' => $r->partner_name, 'logo_url' => media_url($r->partner_logo)],
            'price' => (float)$r->base_price, 'duration_days' => (int)$r->duration_days, 'duration_nights' => (int)$r->duration_nights,
            'start_city' => $r->start_city, 'min_participants' => (int)$r->min_participants,
            'rating' => (float)$r->rating_avg, 'review_count' => (int)$r->rating_count,
            'next_departure' => $r->next_departure, 'promo' => $promo,
        ];
    }

    public function package_detail($id, $user_id = null)
    {
        $r = $this->db->query(
            "SELECT p.*, pc.type AS category_type, pc.name AS category_name,
                    tp.name AS partner_name, tp.logo_path AS partner_logo, tp.description AS partner_description,
                    tp.city AS partner_city, tp.whatsapp AS partner_whatsapp, tp.rating_avg AS partner_rating,
                    tp.ppiu_number, tp.pihk_number
               FROM travel_packages p JOIN travel_partners tp ON tp.id = p.travel_partner_id
               JOIN package_categories pc ON pc.id = p.category_id
              WHERE p.id = ? AND " . self::PKG_BASE_WHERE, [(int)$id])->row();
        if (!$r) { return null; }
        $this->db->query('UPDATE travel_packages SET view_count = view_count + 1 WHERE id = ?', [$r->id]);

        $d = $this->format_package((object)array_merge((array)$r, ['next_departure' => null]));
        $d['description'] = $r->description;
        $d['transportation'] = $r->transportation;
        $d['accommodation'] = $r->accommodation;
        $d['terms_conditions'] = $r->terms_conditions;
        $d['cancellation_policy'] = $r->cancellation_policy;
        $d['required_documents'] = $r->required_documents ? json_decode($r->required_documents, true) : [];
        $d['dp_percent'] = (float)$r->dp_percent;
        $d['full_payment_days_before'] = (int)$r->full_payment_days_before;
        $d['latitude'] = $r->latitude; $d['longitude'] = $r->longitude;
        $d['whatsapp'] = $r->whatsapp_number ?: $r->partner_whatsapp;
        $d['partner']['description'] = $r->partner_description;
        $d['partner']['city'] = $r->partner_city;
        $d['partner']['rating'] = (float)$r->partner_rating;
        $d['partner']['ppiu_number'] = $r->ppiu_number;
        $d['partner']['pihk_number'] = $r->pihk_number;

        $d['media'] = array_map(function ($m) { return ['type' => $m->media_type, 'url' => media_url($m->file_path), 'caption' => $m->caption]; },
            $this->db->query('SELECT * FROM package_media WHERE package_id = ? ORDER BY sort_order, id', [$r->id])->result());
        $d['itinerary'] = $this->db->query('SELECT day_number, title, description, meals, accommodation FROM package_itineraries WHERE package_id = ? ORDER BY day_number', [$r->id])->result();
        $fac = $this->db->query('SELECT facility_type, name, icon FROM package_facilities WHERE package_id = ? ORDER BY sort_order, id', [$r->id])->result();
        $d['facilities_included'] = array_values(array_filter($fac, function ($f) { return $f->facility_type === 'included'; }));
        $d['facilities_excluded'] = array_values(array_filter($fac, function ($f) { return $f->facility_type === 'excluded'; }));
        $d['destinations'] = array_map(function ($x) { return ['id' => (int)$x->id, 'name' => $x->name, 'city' => $x->city, 'country' => $x->country, 'cover_url' => media_url($x->cover_path)]; },
            $this->db->query('SELECT d.id, d.name, d.city, d.country, d.cover_path FROM package_destinations pd JOIN destinations d ON d.id = pd.destination_id WHERE pd.package_id = ? ORDER BY pd.sort_order', [$r->id])->result());

        if (in_array($r->category_type, ['hajj', 'umrah'], true)) {
            $h = $this->db->get_where('package_hajj_umrah_details', ['package_id' => $r->id])->row_array();
            if ($h) { $h['room_types'] = $h['room_types'] ? json_decode($h['room_types'], true) : []; }
            $d['hajj_umrah'] = $h ?: null;
        }

        $sch = $this->db->query(
            "SELECT s.id, s.departure_date, s.return_date, s.departure_note,
                    (q.quota_total - q.quota_held - q.quota_booked) AS quota_available, q.quota_total
               FROM package_schedules s JOIN package_quotas q ON q.schedule_id = s.id
              WHERE s.package_id = ? AND s.status = 'open' AND s.departure_date > CURDATE()
                AND (s.booking_open_at IS NULL OR s.booking_open_at <= NOW()) AND (s.booking_close_at IS NULL OR s.booking_close_at >= NOW())
              ORDER BY s.departure_date", [$r->id])->result();
        foreach ($sch as $s) {
            $s->id = (int)$s->id; $s->quota_available = max(0, (int)$s->quota_available); $s->quota_total = (int)$s->quota_total;
            $s->prices = $this->db->query("SELECT price_code, label, price, original_price FROM schedule_prices WHERE schedule_id = ? AND status = 'active' ORDER BY is_default DESC, id", [$s->id])->result();
            foreach ($s->prices as $pr) { $pr->price = (float)$pr->price; $pr->original_price = $pr->original_price === null ? null : (float)$pr->original_price; }
        }
        $d['schedules'] = $sch;
        $d['next_departure'] = $sch ? $sch[0]->departure_date : null;
        $d['review_summary'] = $this->db->query("SELECT COUNT(*) AS count, COALESCE(ROUND(AVG(rating),2),0) AS average FROM reviews WHERE package_id = ? AND status = 'published'", [$r->id])->row();
        $d['is_wishlisted'] = $user_id ? (bool)$this->db->get_where('wishlists', ['user_id' => $user_id, 'package_id' => $r->id])->row() : false;
        return $d;
    }

    // ------------------------------------------------------------ DESTINASI
    public function search_destinations(array $f, $limit, $offset)
    {
        $w = ["d.status = 'published'", 'd.deleted_at IS NULL']; $p = [];
        if (!empty($f['scope'])) { $w[] = 'd.scope = ?'; $p[] = $f['scope']; }
        if (!empty($f['category_id'])) { $w[] = 'd.category_id = ?'; $p[] = (int)$f['category_id']; }
        foreach (['country', 'province', 'city'] as $k) {
            if (!empty($f[$k])) { $w[] = "d.$k LIKE ? ESCAPE '!'"; $p[] = $this->like($f[$k]); }
        }
        if (!empty($f['q'])) {
            $l = $this->like($f['q']);
            $w[] = "(d.name LIKE ? ESCAPE '!' OR d.city LIKE ? ESCAPE '!' OR d.province LIKE ? ESCAPE '!' OR d.country LIKE ? ESCAPE '!')";
            array_push($p, $l, $l, $l, $l);
        }
        if (!empty($f['popular'])) { $w[] = 'd.is_popular = 1'; }
        $from = 'FROM destinations d LEFT JOIN destination_categories dc ON dc.id = d.category_id WHERE ' . implode(' AND ', $w);
        $total = (int)$this->db->query('SELECT COUNT(*) c ' . $from, $p)->row()->c;
        $ord = (($f['sort'] ?? '') === 'newest') ? 'd.id DESC' : 'd.is_popular DESC, d.view_count DESC, d.name';
        $rows = $this->db->query(
            "SELECT d.id, d.name, d.slug, d.scope, d.country, d.province, d.city, d.cover_path, d.rating_avg, d.rating_count,
                    dc.name AS category_name,
                    (SELECT MIN(p.base_price) FROM package_destinations pd JOIN travel_packages p ON p.id = pd.package_id
                       JOIN travel_partners tp ON tp.id = p.travel_partner_id WHERE pd.destination_id = d.id AND " . self::PKG_BASE_WHERE . ") AS min_price
             " . $from . ' ORDER BY ' . $ord . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset, $p)->result();
        return [$rows, $total];
    }

    public function format_destination($r)
    {
        return [
            'id' => (int)$r->id, 'name' => $r->name, 'slug' => $r->slug, 'scope' => $r->scope,
            'country' => $r->country, 'province' => $r->province, 'city' => $r->city,
            'cover_url' => media_url($r->cover_path), 'rating' => (float)$r->rating_avg, 'review_count' => (int)$r->rating_count,
            'category' => $r->category_name, 'min_price' => $r->min_price === null ? null : (float)$r->min_price,
        ];
    }

    public function destination_detail($id)
    {
        $d = $this->db->query(
            "SELECT d.*, dc.name AS category_name FROM destinations d LEFT JOIN destination_categories dc ON dc.id = d.category_id
              WHERE d.id = ? AND d.status = 'published' AND d.deleted_at IS NULL", [(int)$id])->row();
        if (!$d) { return null; }
        $this->db->query('UPDATE destinations SET view_count = view_count + 1 WHERE id = ?', [$d->id]);
        $o = $this->format_destination((object)array_merge((array)$d, ['min_price' => null]));
        $o['description'] = $d->description; $o['address'] = $d->address;
        $o['latitude'] = $d->latitude === null ? null : (float)$d->latitude;
        $o['longitude'] = $d->longitude === null ? null : (float)$d->longitude;
        $o['google_maps_url'] = $d->google_maps_url ?: ($d->latitude !== null ? 'https://www.google.com/maps?q=' . $d->latitude . ',' . $d->longitude : null);
        $o['facilities'] = $d->facilities ? json_decode($d->facilities, true) : [];
        $o['additional_info'] = $d->additional_info;
        $o['gallery'] = array_map(function ($m) { return ['type' => $m->media_type, 'url' => media_url($m->file_path), 'caption' => $m->caption]; },
            $this->db->query('SELECT * FROM destination_media WHERE destination_id = ? ORDER BY sort_order, id', [$d->id])->result());
        return $o;
    }
}
