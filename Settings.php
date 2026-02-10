<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_customer_id() {
        return get_option( 'colby_cludo_customer_id', '' );
    }

    public function get_api_key() {
        return get_option( 'colby_cludo_api_key', '' );
    }

    public function get_api_host() {
        return get_option( 'colby_cludo_api_host', '' );
    }

    public function get_crawler_id() {
        return get_option( 'colby_cludo_crawler_id', '' );
    }

    public function get_engine_id() {
        return get_option( 'colby_cludo_engine_id', '' );
    }

    public function get_all() {
        return [
            'customer_id' => $this->get_customer_id(),
            'api_key'     => $this->get_api_key(),
            'api_host'    => $this->get_api_host(),
            'crawler_id'  => $this->get_crawler_id(),
            'engine_id'   => $this->get_engine_id(),
        ];
    }

    private function __construct() {}
}

Settings::instance()->get_customer_id();
