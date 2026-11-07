<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends API_Controller
{
    public function health()
    {
        api_ok(['status' => 'up', 'time' => date('c')], 'OK');
    }

    public function index()
    {
        $this->load->model(['catalog_model', 'setting_model']);
        $cm = $this->catalog_model;

        $banners = $this->db->query(
            "SELECT * FROM banners WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
              ORDER BY sort_order, id LIMIT 10")->result();
        $banners = array_map(function ($b) {
            return ['id' => (int)$b->id, 'title' => $b->title, 'subtitle' => $b->subtitle, 'image_url' => media_url($b->image_path),
                    'action_type' => $b->action_type, 'action_value' => $b->action_value, 'cta_label' => $b->cta_label];
        }, $banners);

        $cats = $this->db->query("SELECT id, type, name, slug, icon FROM package_categories WHERE status = 'active' ORDER BY sort_order")->result();

        list($dest) = $cm->search_destinations(['popular' => 1], 8, 0);
        if (!$dest) { list($dest) = $cm->search_destinations([], 8, 0); }
        list($pop) = $cm->search_packages(['sort' => 'popular'], 8, 0);
        list($newest) = $cm->search_packages(['sort' => 'newest'], 6, 0);

        // paket promo: yang punya promo otomatis aktif
        list($all) = $cm->search_packages(['sort' => 'popular'], 30, 0);
        $promo = [];
        foreach ($all as $r) { $f = $cm->format_package($r); if ($f['promo']) { $promo[] = $f; } if (count($promo) >= 8) { break; } }

        $partners = $this->db->query(
            "SELECT id, name, logo_path, city, rating_avg, rating_count FROM travel_partners
              WHERE status = 'active' AND verification_status = 'verified' AND deleted_at IS NULL
              ORDER BY is_featured DESC, rating_avg DESC LIMIT 8")->result();
        $partners = array_map(function ($p) { return ['id' => (int)$p->id, 'name' => $p->name, 'logo_url' => media_url($p->logo_path), 'city' => $p->city, 'rating' => (float)$p->rating_avg, 'review_count' => (int)$p->rating_count]; }, $partners);

        $upcoming = $this->db->query(
            "SELECT s.id AS schedule_id, s.departure_date, p.id AS package_id, p.name, p.cover_path, p.base_price, tp.name AS partner_name,
                    (q.quota_total - q.quota_held - q.quota_booked) AS quota_available
               FROM package_schedules s JOIN package_quotas q ON q.schedule_id = s.id JOIN travel_packages p ON p.id = s.package_id
               JOIN travel_partners tp ON tp.id = p.travel_partner_id
              WHERE s.status = 'open' AND s.departure_date > CURDATE() AND (q.quota_total - q.quota_held - q.quota_booked) > 0
                AND " . Catalog_model::PKG_BASE_WHERE . "
              ORDER BY s.departure_date LIMIT 8")->result();
        $upcoming = array_map(function ($u) {
            return ['schedule_id' => (int)$u->schedule_id, 'package_id' => (int)$u->package_id, 'name' => $u->name, 'partner_name' => $u->partner_name,
                    'cover_url' => media_url($u->cover_path), 'price' => (float)$u->base_price, 'departure_date' => $u->departure_date, 'quota_available' => (int)$u->quota_available];
        }, $upcoming);

        $articles = $this->db->query("SELECT id, title, slug, excerpt, cover_path, published_at FROM articles WHERE status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 6")->result();
        $articles = array_map(function ($a) { return ['id' => (int)$a->id, 'title' => $a->title, 'slug' => $a->slug, 'excerpt' => $a->excerpt, 'cover_url' => media_url($a->cover_path), 'published_at' => $a->published_at]; }, $articles);

        api_ok([
            'banners' => $banners, 'categories' => $cats,
            'popular_destinations' => array_map([$cm, 'format_destination'], $dest),
            'popular_packages' => array_map([$cm, 'format_package'], $pop),
            'promo_packages' => $promo,
            'new_packages' => array_map([$cm, 'format_package'], $newest),
            'featured_partners' => $partners, 'upcoming_departures' => $upcoming, 'articles' => $articles,
            'settings' => $this->setting_model->public_settings(),
        ]);
    }

    public function categories()
    {
        api_ok([
            'package_categories' => $this->db->query("SELECT id, type, name, slug, icon FROM package_categories WHERE status = 'active' ORDER BY sort_order")->result(),
            'destination_categories' => $this->db->query("SELECT id, name, slug, icon FROM destination_categories WHERE status = 'active' ORDER BY sort_order")->result(),
        ]);
    }

    public function articles()
    {
        list($page, $per, $off) = $this->paging(10);
        $total = (int)$this->db->query("SELECT COUNT(*) c FROM articles WHERE status = 'published' AND deleted_at IS NULL")->row()->c;
        $rows = $this->db->query("SELECT id, title, slug, excerpt, content, cover_path, published_at FROM articles WHERE status = 'published' AND deleted_at IS NULL
                                  ORDER BY published_at DESC LIMIT $per OFFSET $off")->result();
        $rows = array_map(function ($a) { return ['id' => (int)$a->id, 'title' => $a->title, 'slug' => $a->slug, 'excerpt' => $a->excerpt, 'content' => $a->content, 'cover_url' => media_url($a->cover_path), 'published_at' => $a->published_at]; }, $rows);
        api_ok($rows, 'OK', api_meta($page, $per, $total));
    }

    public function faqs()
    {
        api_ok($this->db->query("SELECT category, question, answer FROM faqs WHERE status = 'active' ORDER BY category, sort_order")->result());
    }
}
