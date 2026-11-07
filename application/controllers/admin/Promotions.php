<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Promo_manager.php';

class Promotions extends Admin_Controller
{
    use Promo_manager;
    protected $promo_scope_partner_id = null;   // promo platform
}
