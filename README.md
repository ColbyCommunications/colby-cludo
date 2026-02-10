# Colby Cludo

**Colby Cludo** is a custom WordPress plugin that integrates **Cludo** with WordPress websites.  
It provides a way to connect your WordPress content with the Cludo search platform using the Cludo API for indexing.

#### Note: This plugin is designed to work with only one Cludo engine per site.

- [Features](#features)
- [Requirements](#requirements)
- [Installation and Setup](#installation-and-setup)
- [Usage](#usage)
  - [Supported Field Types](#supported-field-types)
  - [Register Custom Fields](#register-custom-fields)
- [Cron Scheduling](#cron-scheduling)
- [Email Notifications](#email-notifications)
- [Changelog](#changelog)

---

## Features

- Automatic post type and field detection for fast integration
- Cron scheduling for automatic indexing and synchronization
- Registration of custom fields for complex data structures
- Email notifications for success and failure runs

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Active Cludo account and API credentials

### Optional
- Yoast SEO plugin

---

## Installation and Setup

1. Download or clone this repository into your WordPress plugins directory:

2. Activate the plugin from the WordPress Admin → **Plugins** page.
3. Navigate to **Settings → Colby Cludo** in the WordPress admin.
4. Enter your Cludo API, crawler, and engine credentials and configuration values in the **API Configuration tab**.
5. Save settings.

---

## Usage

After installing and configuring the plugin, you can set up your indexing and field mappings in the **Index Settings** tab.

Colby Cludo automatically detects all post types and their respective fields. You can:

- Select the fields you want to send to Cludo
- Rename the field labels (these appear in the Cludo dashboard and API responses)
- Add custom fields if needed

---

### Supported Field Types

Colby Cludo detects four native field types and allows the addition of **custom fields**:

| Field Type           | Description                                                                 |
|---------------------|-----------------------------------------------------------------------------|
| **Core Fields**      | Common post fields such as `post_title`, `post_date`, `permalink`, `post_type`, etc. |
| **Taxonomies**       | Automatically detects associated taxonomies like categories and tags        |
| **Meta Fields**      | Standard post meta data                                                      |
| **Yoast SEO Fields** | Detected if the Yoast SEO plugin is installed                                |
| **Custom Fields**    | Manually register additional fields not detected automatically              |

**Example Field Mapping:**

| Field                | Label            |
|----------------------|----------------|
| `core:post_title`    | Title          |
| `core:post_date`     | Published Date |
| `core:permalink`     | URL            |
| `core:post_type`     | Post Type      |
| `core:post_excerpt`  | Post Excerpt   |
| `meta:author`        | Post Author    |

> **Note:** Post ID is automatically included with each post type when indexed.

---

### Register Custom Fields

Colby Cludo allows developers to register **custom fields** that are not automatically detected by the plugin.

You can register custom fields using the `colby_cludo_register_custom_field` filter. Here’s an example:

```php
add_filter('colby_cludo_register_custom_field', function ($field_registry) {
    $field_registry['yoast_primary_category'] = [
        'label'      => 'Primary Category',       // The field label shown in Cludo dashboard and API responses
        'post_types' => ['post'],                 // Optional: scope to specific post types (empty array for all)
        'callback'   => 'get_yoast_primary_category', // Function that returns the field value
    ];

    return $field_registry;
});
```
---

## Cron Scheduling
---

## Email Notifications
---


## Changelog

### v1.0

- Initial release
