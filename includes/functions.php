<?php

/**
 * Helper functions for Indie500 Manager
 */

function indie500_get_user_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function indie500_format_number($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

function indie500_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return __('just now', 'indie500-manager');
    if ($time < 3600) return sprintf(__('%d minutes ago', 'indie500-manager'), floor($time/60));
    if ($time < 86400) return sprintf(__('%d hours ago', 'indie500-manager'), floor($time/3600));
    if ($time < 2592000) return sprintf(__('%d days ago', 'indie500-manager'), floor($time/86400));
    
    return date('M j, Y', strtotime($datetime));
}
