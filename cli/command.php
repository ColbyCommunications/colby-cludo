<?php
/*
 *
 * ## EXAMPLE USAGE
 *
 * wp colby-cludo <function name>
 *
 */

class Colby_Cludo_CLI_Command
{
    protected array $field_mapping = [];

    public function __construct()
    {
        $this->field_mapping = get_option('colby_cludo_field_mapping', []);
    }

    /* --------------------------------------------------------------------------
     * SETTINGS
     * -------------------------------------------------------------------------- */

    public function show_settings($args, $assoc_args)
    {
        $settings = Settings::instance();

        WP_CLI::line("Customer ID: " . $settings->get_customer_id());
        WP_CLI::line("API Key: " . ($settings->get_api_key() ?: "(not set)"));
        WP_CLI::line("API Host: " . $settings->get_api_host());
        WP_CLI::line("Crawler ID: " . $settings->get_crawler_id());
    }

    /* --------------------------------------------------------------------------
     * INDEX ALL
     * -------------------------------------------------------------------------- */

    public function index_all($args, $assoc_args)
    {
        $settings = Settings::instance();

        $customer_id = $settings->get_customer_id();
        $api_key     = $settings->get_api_key();
        $api_host    = rtrim($settings->get_api_host(), "/");
        $crawler_id  = $settings->get_crawler_id();

        if (!$customer_id || !$api_key || !$api_host) {
            WP_CLI::error("Missing settings.");
        }

        // Single source of truth
        $selected_post_types = array_keys($this->field_mapping);
        $batch_size = 500;

        foreach ($selected_post_types as $post_type) {
            WP_CLI::line("Processing type: $post_type...");
            $paged = 1;

            while (true) {
                $posts = get_posts([
                    "post_type"      => $post_type,
                    "post_status"    => "publish",
                    "posts_per_page" => $batch_size,
                    "paged"          => $paged,
                    "fields"         => "ids",
                ]);

                if (empty($posts)) {
                    break;
                }

                $payload = [];

                foreach ($posts as $post_id) {

                    $fields = $this->process_post_fields($post_id, $this->field_mapping);
                    if (empty($fields)) {
                        continue;
                    }

                    $payload[] = [
                        'id'     => (string)$post_id,
                        'fields' => $fields,
                    ];

                    WP_CLI::line(
                        "Processed post ID: $post_id, fields: " .
                        implode(', ', array_keys($fields))
                    );
                }

                if (!empty($payload)) {
                    $this->send_to_cludo(
                        "documents",
                        $payload,
                        "PUT",
                        $api_host,
                        $customer_id,
                        $api_key,
                        $crawler_id
                    );

                    WP_CLI::success("Sent batch $paged for $post_type (" . count($payload) . " items).");
                }

                $paged++;
            }
        }
    }

    /* --------------------------------------------------------------------------
     * DELETE ALL
     * -------------------------------------------------------------------------- */

    public function delete_all($args, $assoc_args)
    {
        $settings = Settings::instance();

        $customer_id = $settings->get_customer_id();
        $api_key     = $settings->get_api_key();
        $api_host    = rtrim($settings->get_api_host(), "/");
        $crawler_id  = $settings->get_crawler_id();

        if (!$customer_id || !$api_key || !$api_host) {
            WP_CLI::error("Missing settings.");
        }

        $payload = [
            "Title" => [
                "operator" => "Exists",
                "values"   => [],
            ],
        ];

        $success = $this->send_to_cludo(
            "documents/bulk-delete",
            $payload,
            "POST",
            $api_host,
            $customer_id,
            $api_key,
            $crawler_id
        );

        $success
            ? WP_CLI::success("Bulk delete completed successfully.")
            : WP_CLI::error("Bulk delete failed.");
    }

    /* --------------------------------------------------------------------------
     * FIELD PROCESSOR
     * -------------------------------------------------------------------------- */

    public function process_post_fields(int $post_id, array $field_mapping): ?array
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        $post_type = $post->post_type;

        if (!isset($field_mapping[$post_type])) {
            return null;
        }

        $mapping = $field_mapping[$post_type];
        $output  = [];

        foreach ($mapping as $map_data) {

            $display = $map_data['display'] ?? '';
            $source  = $map_data['source'] ?? '';

            if (!$display || !$source) {
                continue;
            }

            $value = null;

            /* ---------- CORE ---------- */
            if (str_starts_with($source, 'core:')) {
                $field = substr($source, 5);

                switch ($field) {
                    case 'permalink':
                        $value = get_permalink($post_id);
                        break;

                    case 'post_type':
                        $value = $post->post_type;
                        break;

                    case 'post_content':
                        $value = wp_strip_all_tags($post->post_content);
                        break;

                    default:
                        $value = $post->{$field} ?? null;
                }
            }

            /* ---------- META ---------- */
            elseif (str_starts_with($source, 'meta:')) {
                $meta_key = substr($source, 5);
                $value = get_post_meta($post_id, $meta_key, true);
            }

            /* ---------- TAX ---------- */
            elseif (str_starts_with($source, 'taxonomies:')) {
                $tax = substr($source, 11);
                $terms = wp_get_post_terms($post_id, $tax);

                if (!is_wp_error($terms)) {
                    $value = wp_list_pluck($terms, 'name');
                }
            }

            /* ---------- YOAST ---------- */
            elseif (str_starts_with($source, 'yoast:')) {
                $tax = substr($source, 6);

                if (taxonomy_exists($tax)) {
                    $terms = wp_get_post_terms($post_id, $tax);

                    if (is_wp_error($terms)) {
                        $value = [];
                    } else {
                        $value = wp_list_pluck($terms, 'name');
                    }
                } else {
                    $value = [];
                }
            }

            /* ---------- CUSTOM FIELD ---------- */
            if (str_starts_with($source, 'custom:')) {
                $custom_key = substr($source, 7); // extract key from source

                $custom_fields = colby_cludo_get_custom_fields($post_type);
                if (isset($custom_fields[$custom_key])) {
                    $all_registry = apply_filters('colby_cludo_register_custom_field', []);
                    if (!empty($all_registry[$custom_key]['callback'])) {
                        $value = $all_registry[$custom_key]['callback']($post_id);
                    }
                }
            }

            /* ---------- FLATTEN ---------- */
            // Normalize value for Cludo
            if (is_array($value)) {

                // Remove empty/null values
                $value = array_values(array_filter($value, function ($v) {
                    return $v !== null && $v !== '';
                }));

                if (!empty($value)) {
                    $output[$display] = $value;
                }

            } elseif (!is_null($value) && $value !== '') {
                $output[$display] = $value;
            }

        }

        return $output;
    }

    /* --------------------------------------------------------------------------
     * SEND
     * -------------------------------------------------------------------------- */

    private function send_to_cludo(
        string $endpoint_path,
        array $payload,
        string $method,
        string $host,
        string $cust_id,
        string $key,
        int|string $crawler
    ) {
        $endpoint = "{$host}/api/v4/{$cust_id}/index/{$crawler}/{$endpoint_path}";
        $json = wp_json_encode($payload);
        $auth = base64_encode(trim($cust_id) . ":" . trim($key));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic {$auth}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($json),
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            WP_CLI::warning("cURL error: {$curl_error}");
            return false;
        }

        if ($http_code < 200 || $http_code >= 300) {
            WP_CLI::warning("Request failed ({$http_code}): {$response}");
            return false;
        }

        return true;
    }
}

if (defined("WP_CLI") && WP_CLI) {
    WP_CLI::add_command("colby-cludo", "Colby_Cludo_CLI_Command");
}
