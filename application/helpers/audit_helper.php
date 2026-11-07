<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if (!function_exists('audit_log')) {
    function audit_log($action, $entity_type = null, $entity_id = null, $old = null, $new = null, $user_id = null, $partner_id = null)
    {
        $CI =& get_instance();
        $CI->db->insert('audit_logs', [
            'user_id' => $user_id,
            'travel_partner_id' => $partner_id,
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            'ip_address' => $CI->input->ip_address(),
            'user_agent' => substr((string)$CI->input->user_agent(), 0, 255),
        ]);
    }
}
