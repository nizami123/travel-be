<?php
/** Front controller MultiTravel (CodeIgniter 3.1.x) */
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
ini_set('display_errors', ENVIRONMENT === 'production' ? '0' : '1');

$system_path = 'system';
$application_folder = 'application';
$view_folder = '';

if (defined('STDIN')) { chdir(dirname(__FILE__)); }

if (($_temp = realpath($system_path)) !== FALSE) {
    $system_path = $_temp . DIRECTORY_SEPARATOR;
} else {
    $system_path = strtr(rtrim($system_path, '/\\'), '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}
if (!is_dir($system_path)) {
    header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
    echo 'Folder "system" belum ada. Salin folder system dari CodeIgniter 3.1.13 ke MULTI_TRAVEL_BACKEND/system (lihat README).';
    exit(3);
}

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', str_replace('\\', '/', $system_path));
define('FCPATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));

if (is_dir($application_folder)) {
    if (($_temp = realpath($application_folder)) !== FALSE) { $application_folder = $_temp; }
    else { $application_folder = strtr(rtrim($application_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR); }
} else {
    header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
    echo 'Folder application tidak ditemukan.';
    exit(3);
}
define('APPPATH', $application_folder . DIRECTORY_SEPARATOR);

if (!isset($view_folder[0]) && is_dir(APPPATH . 'views' . DIRECTORY_SEPARATOR)) {
    $view_folder = APPPATH . 'views';
}
if (($_temp = realpath($view_folder)) !== FALSE) { $view_folder = $_temp . DIRECTORY_SEPARATOR; }
else { $view_folder = rtrim($view_folder, '/\\') . DIRECTORY_SEPARATOR; }
define('VIEWPATH', $view_folder);

require_once BASEPATH . 'core/CodeIgniter.php';
