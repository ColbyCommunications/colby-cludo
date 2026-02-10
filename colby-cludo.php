<?php
/**
 * Plugin Name: Colby Cludo
 * Description: Integrates Cludo search for Colby College.
 * Author: Colby Communications
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings_path = __DIR__ . '/Settings.php';
if ( file_exists( $settings_path ) ) {
    require_once $settings_path;
}

$api_path = __DIR__ . '/api/ColbyCludoAPI.php';
if ( file_exists( $api_path ) ) {
    require_once $api_path;
}

$custom_fields_path = __DIR__ . '/utils/get-custom-fields.php';
if ( file_exists( $custom_fields_path ) ) {
    require_once $custom_fields_path;
}

// Load admin settings ONLY in wp-admin
if ( is_admin() ) {
    require_once __DIR__ . '/admin/settings.php';
}

add_filter(
    'plugin_action_links_colby-cludo/colby-cludo.php',
    function ( $links ) {
        $settings_link = '<a href="options-general.php?page=colby-cludo">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once __DIR__ . '/cli/command.php';
}

// Adds documentation link to plugin actions
add_filter('plugin_action_links_colby-cludo/colby-cludo.php', function ($links) {
    $links[] = '<a href="https://github.com/ColbyCommunications/colby-cludo" target="_blank">Documentation</a>';
    return $links;
});