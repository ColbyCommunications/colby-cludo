<?php

/**
 * This file is responsible for setting up the REST API endpoints and localizing settings data (customer ID and engine ID) for frontend use.
 * It uses the Settings class to retrieve necessary configuration values.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ColbyCludoAPI {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_and_localize']);
    }

    public function register_routes() {
        register_rest_route('colby-cludo/v1', '/settings', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get only the specific settings data needed for the frontend.
     */
    private function get_formatted_settings() {
        
        return [
            'customerId' => Settings::instance()->get_customer_id(),
            'engineId'   => Settings::instance()->get_engine_id(),
        ];
    }

    public function get_settings() {
        return new WP_REST_Response($this->get_formatted_settings(), 200);
    }

    public function enqueue_and_localize() {
        wp_register_script('colby-cludo-data', '');

        wp_localize_script('colby-cludo-data', 'ColbyCludoConfig', [
            'root'     => esc_url_raw(rest_url('colby-cludo/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'settings' => $this->get_formatted_settings() 
        ]);

        wp_enqueue_script('colby-cludo-data');
    }
}
new ColbyCludoAPI();