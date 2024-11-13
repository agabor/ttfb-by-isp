<?php
/**
 * Plugin Name: TTFB by ISP
 * Description: Logs Time to First Byte (TTFB), ISP, URL, and User Agent information.
 * Version: 1.0.4
 * Author: Gabor Angyal
 * Author URI: https://codesharp.dev
 */

if (!defined('ABSPATH')) {
    exit;
}

// Create the custom table on plugin activation
register_activation_hook(__FILE__, 'ttfb_logger_create_table');

function ttfb_logger_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ttfb_logs';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ttfb float NOT NULL,
        isp varchar(255) DEFAULT '' NOT NULL,
        user_type varchar(255) DEFAULT '' NOT NULL,
        url text NOT NULL,
        user_agent text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


function ttfb_logger_delete_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ttfb_logs';

    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query($sql);

    if ($wpdb->last_error) {
        error_log("Error deleting table: " . $wpdb->last_error);
    } else {
        error_log("Table deleted successfully.");
    }
}

register_deactivation_hook(__FILE__, 'ttfb_logger_delete_table');

// Enqueue the script that calculates and sends TTFB
add_action('wp_enqueue_scripts', 'ttfb_logger_enqueue_script');

function ttfb_logger_enqueue_script() {
    wp_enqueue_script('ttfb-logger', plugin_dir_url(__FILE__) . 'ttfb-logger.js', [], '1.3', true);
    wp_localize_script('ttfb-logger', 'ttfbLogger', [
        'ajaxUrl' => admin_url('admin-ajax.php')
    ]);
}

// Handle AJAX request to log TTFB, ISP, URL, and User Agent
add_action('wp_ajax_nopriv_log_ttfb', 'ttfb_logger_log_ttfb');
add_action('wp_ajax_log_ttfb', 'ttfb_logger_log_ttfb');

function ttfb_logger_log_ttfb() {
    $ttfb = isset($_POST['ttfb']) ? floatval($_POST['ttfb']) : null;
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    $user_type = isset($_POST['userType']) ? sanitize_text_field($_POST['userType']) : '';

    if ($ttfb === null || empty($url)) {
        wp_send_json_error('Invalid data received.');
    }

    // Get client IP behind Cloudflare
    $client_ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];

    // Get ISP using gethostbyaddr
    $isp = gethostbyaddr($client_ip);

    if ($isp !== $client_ip) {
        // Get User Agent from server-side
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Save TTFB, ISP, URL, and User Agent to the custom table
        global $wpdb;
        $table_name = $wpdb->prefix . 'ttfb_logs';
        $wpdb->insert($table_name, [
            'ttfb' => $ttfb,
	    'isp' => $isp,
	    'user_type' => $user_type,
            'url' => $url,
            'user_agent' => $user_agent,
            'created_at' => current_time('mysql')
        ]);
    }
    wp_send_json_success('TTFB, URL, and User Agent logged successfully.');
}
