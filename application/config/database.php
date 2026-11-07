<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$_local = include APPPATH . 'config/local.php';
$active_group = 'default';
$query_builder = TRUE;
$db['default'] = [
    'dsn' => '', 'hostname' => $_local['db_host'], 'username' => $_local['db_user'],
    'password' => $_local['db_pass'], 'database' => $_local['db_name'],
    'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => FALSE,
    'db_debug' => FALSE,            // error dicatat di application/logs
    'cache_on' => FALSE, 'cachedir' => '',
    'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci',
    'swap_pre' => '', 'encrypt' => FALSE, 'compress' => FALSE,
    'stricton' => TRUE, 'failover' => [], 'save_queries' => FALSE,
];
