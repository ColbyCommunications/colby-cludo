<?php

function colby_cludo_get_custom_fields($post_type = null) {
    $all_fields = apply_filters('colby_cludo_register_custom_field', []);
    $custom_fields = [];

    foreach ($all_fields as $key => $data) {
        $label = $data['label'] ?? $key;
        $types = $data['post_types'] ?? [];

        if (empty($types) || ($post_type && in_array($post_type, $types, true))) {
            $custom_fields[$key] = $label;
        }
    }

    return $custom_fields;
}


