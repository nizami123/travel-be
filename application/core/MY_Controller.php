<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Autoloader untuk base controller kustom di application/core
 * (API_Controller, Dashboard_Controller, Admin_Controller, Partner_Controller).
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'CI_') === 0 || strpos($class, 'MY_') === 0) { return; }
    if (preg_match('/^[A-Za-z0-9_]+_Controller$/', $class)) {
        $f = APPPATH . 'core/' . $class . '.php';
        if (is_file($f)) { require_once $f; }
    }
});

class MY_Controller extends CI_Controller
{
}
