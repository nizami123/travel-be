<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('api_json')) {
    function api_json($payload, $code = 200)
    {
        $CI =& get_instance();
        $CI->output
            ->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $CI->output->_display();
        exit;
    }
}
if (!function_exists('api_ok')) {
    function api_ok($data = null, $message = 'OK', $meta = null, $code = 200)
    {
        api_json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => $meta, 'errors' => null], $code);
    }
}
if (!function_exists('api_error')) {
    function api_error($message, $code = 400, $errors = null)
    {
        api_json(['success' => false, 'message' => $message, 'data' => null, 'meta' => null, 'errors' => $errors], $code);
    }
}
if (!function_exists('api_meta')) {
    function api_meta($page, $per, $total)
    {
        return ['page' => (int)$page, 'per_page' => (int)$per, 'total' => (int)$total, 'total_pages' => (int)ceil($total / max(1, $per))];
    }
}
if (!function_exists('media_url')) {
    /** URL absolut file publik (uploads/public). */
    function media_url($path)
    {
        return $path ? base_url('uploads/public/' . ltrim($path, '/')) : null;
    }
}
if (!function_exists('e')) {
    function e($v) { return html_escape((string)$v); }
}
