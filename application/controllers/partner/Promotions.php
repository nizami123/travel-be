<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Promo_manager.php';

class Promotions extends Partner_Controller
{
    use Promo_manager;
    protected $promo_scope_partner_id = null;
    public function __construct() { parent::__construct(); $this->promo_scope_partner_id = $this->partner_id; }
}
