<?php
if (!defined("ABSPATH")) {
    exit();
}

/* ---------------- FIELD DISCOVERY ---------------- */
function colby_cludo_get_available_fields_for_type($post_type)
{
    $fields = [];

    // ---------------- CORE ----------------
    $core_fields = [
        "post_type",
        "post_title",
        "post_excerpt",
        "post_content",
        "post_date",
        "post_name",
        "post_author",
        "permalink",
    ];

    foreach ($core_fields as $key) {
        $fields[] = "core:$key";
    }

    // ---------------- TAXONOMIES ----------------
    $taxonomies = get_object_taxonomies($post_type, "objects");
    $yoast_active = class_exists("WPSEO_Primary_Term");

    foreach ($taxonomies as $tax) {
        $fields[] = "taxonomies:{$tax->name}";

        if ($yoast_active && $tax->public) {
            $fields[] = "yoast:{$tax->name}";
        }
    }

    // ---------------- META ----------------
    global $wpdb;
    $meta_keys = $wpdb->get_col(
        "SELECT DISTINCT meta_key FROM $wpdb->postmeta"
    );

    foreach ($meta_keys as $meta_key) {
        if (strpos($meta_key, "_") === 0) {
            continue;
        }
        $fields[] = "meta:$meta_key";
    }

    // ---------------- CUSTOM FIELDS ----------------
    $custom_fields = colby_cludo_get_custom_fields($post_type);
    foreach ($custom_fields as $key => $label) {
        $fields[] = "custom:$key";
    }

    return $fields;
}

/* ---------------- ADMIN MENU ---------------- */
add_action("admin_menu", function () {
    add_options_page(
        "Colby Cludo Settings",
        "Colby Cludo",
        "manage_options",
        "colby-cludo",
        "colby_cludo_render_page"
    );
});

//* ---------------- SETTINGS ---------------- */
add_action("admin_init", function () {
    // INDEX TAB
    register_setting("colby_cludo_index", "colby_cludo_post_types", [
        "sanitize_callback" => fn($v) => is_array($v)
            ? array_map("sanitize_text_field", $v)
            : [],
    ]);
    register_setting("colby_cludo_index", "colby_cludo_field_mapping", [
        "sanitize_callback" => fn($v) => is_array($v) ? $v : [],
    ]);

    // API TAB
    register_setting("colby_cludo_api", "colby_cludo_customer_id", [
        "sanitize_callback" => "sanitize_text_field",
    ]);
    register_setting("colby_cludo_api", "colby_cludo_api_key", [
        "sanitize_callback" => "sanitize_text_field",
    ]);
    register_setting("colby_cludo_api", "colby_cludo_api_host", [
        "sanitize_callback" => "sanitize_text_field",
    ]);
    register_setting("colby_cludo_api", "colby_cludo_crawler_id", [
        "sanitize_callback" => "sanitize_text_field",
    ]);
    register_setting("colby_cludo_api", "colby_cludo_engine_id", [
        "sanitize_callback" => "sanitize_text_field",
    ]);

    // NOTIFICATIONS TAB
    register_setting("colby_cludo_notifications", "colby_cludo_emails");
    register_setting("colby_cludo_notifications", "colby_cludo_notif_types");
});

/* ---------------- PAGE ---------------- */
function colby_cludo_render_page()
{
    $tab = $_GET["tab"] ?? "plugin_settings"; ?>
<div class="wrap">
<h1>Colby Cludo Settings</h1>

<h2 class="nav-tab-wrapper">
<a href="?page=colby-cludo&tab=plugin_settings" class="nav-tab <?= $tab ===
"plugin_settings"
    ? "nav-tab-active"
    : "" ?>">Index Settings</a>
<a href="?page=colby-cludo&tab=api_configuration" class="nav-tab <?= $tab ===
"api_configuration"
    ? "nav-tab-active"
    : "" ?>">API Configuration</a>
<a href="?page=colby-cludo&tab=notifications" class="nav-tab <?= $tab ===
"notifications"
    ? "nav-tab-active"
    : "" ?>">Notifications</a>
</h2>

<?php if ($tab === "plugin_settings"): ?>
<form method="post" action="options.php">
<?php settings_fields("colby_cludo_index"); ?>
<?php colby_cludo_render_index_settings(); ?>
<?php submit_button(); ?>
</form>

<?php elseif ($tab === "api_configuration"): ?>
<form method="post" action="options.php">
<?php settings_fields("colby_cludo_api"); ?>
<?php colby_cludo_render_api_settings(); ?>
<?php submit_button(); ?>
</form>

<?php else: ?>
<form method="post" action="options.php">
<?php settings_fields("colby_cludo_notifications"); ?>
<?php colby_cludo_render_notifications(); ?>
<?php submit_button(); ?>
</form>
<?php endif; ?>

</div>
<?php
} /* ---------------- INDEX SETTINGS ---------------- */
function colby_cludo_render_index_settings()
{
    $post_types = get_post_types(["public" => true], "objects");
    unset($post_types["attachment"]);
    $selected_post_types = get_option("colby_cludo_post_types", []);
    $mapping = get_option("colby_cludo_field_mapping", []);
    ?>

<h2>Post Types to Index</h2>
<div class="cludo-accordion">

<?php foreach ($post_types as $post_type):

    $type = $post_type->name;
    $is_checked = in_array($type, $selected_post_types);
    $type_mapping = $mapping[$type] ?? [];
    $available_fields = colby_cludo_get_available_fields_for_type($type);
    ?>

<div class="cludo-accordion-item">

<div class="cludo-accordion-header">
<label>
<input type="checkbox" name="colby_cludo_post_types[]" value="<?php echo esc_attr(
    $type
); ?>" <?php checked($is_checked); ?>>
<strong><?php echo esc_html($post_type->label); ?></strong>
</label>
</div>

<div class="cludo-accordion-body" style="<?php echo $is_checked
    ? "display:block;"
    : ""; ?>">

<div class="cludo-field-mapper" data-post-type="<?php echo esc_attr(
    $type
); ?>" style="display:flex;gap:20px;">

<div style="width:45%;">
<h4>Available Fields</h4>
<input type="text" class="cludo-field-filter" placeholder="Search fields..." style="width:50%;margin-bottom:5px;padding:3px;">
<select class="cludo-available-fields" size="12" style="width:100%;">
<?php foreach ($available_fields as $key):
    if (!isset($type_mapping[$key])): ?>
<option value="<?php echo esc_attr($key); ?>">
    <?php echo esc_html($key); ?>
</option>
<?php endif;
endforeach; ?>
</select>
<button type="button" class="button cludo-add-field">Add →</button>
</div>

<div style="width:55%;">
<h4>Field Mapping</h4>

<style>
/* GRID LAYOUT FOR HEADERS + ROWS */
.cludo-map-grid {
    display: grid;
    grid-template-columns: 32px 1fr 1fr;
    gap: 8px;
    align-items: center;
}

.cludo-map-headers {
    font-weight: 600;
    margin-bottom: 6px;
}

.cludo-map-row {
    margin-bottom: 6px;
}

.cludo-map-row input[type="text"] {
    width: 100%;
}

.cludo-map-row input[readonly] {
    background: #f6f7f7;
}
</style>

<!-- COLUMN HEADERS -->
<div class="cludo-map-headers cludo-map-grid">
    <div></div>
    <div>Field</div>
    <div>Label</div>
</div>

<div class="cludo-mapping-area" style="margin-bottom:1rem;border:1px solid #ccc;padding:10px;max-height:250px;overflow:auto;">
<?php foreach ($type_mapping as $key => $data): ?>
<div class="cludo-map-row cludo-map-grid">

    <input type="checkbox" class="cludo-delete-check">

    <input type="text"
           readonly
           value="<?php echo esc_attr($key); ?>">

    <input type="text"
           name="colby_cludo_field_mapping[<?php echo esc_attr(
               $type
           ); ?>][<?php echo esc_attr($key); ?>][display]"
           value="<?php echo esc_attr($data["display"] ?? $key); ?>">

    <input type="hidden"
           name="colby_cludo_field_mapping[<?php echo esc_attr(
               $type
           ); ?>][<?php echo esc_attr($key); ?>][source]"
           value="<?php echo esc_attr($key); ?>">
</div>
<?php endforeach; ?>
</div>

<button type="button" class="button cludo-delete-field">Delete Selected</button>
</div>

</div>
</div>
</div>

<?php
endforeach; ?>
</div>

<style>
.cludo-accordion-item{border:1px solid #ccd0d4;margin-bottom:10px;background:#fff;}
.cludo-accordion-header{padding:10px;cursor:pointer;background:#f6f7f7;}
.cludo-accordion-body{display:none;padding:15px;border-top:1px solid #ccd0d4;}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){

document.querySelectorAll('.cludo-accordion-header').forEach(h=>{
h.addEventListener('click',e=>{
if(e.target.tagName==='INPUT')return;
let body=h.nextElementSibling;
body.style.display=body.style.display==='block'?'none':'block';
});
});

// Filter Available Fields in each mapper
document.querySelectorAll('.cludo-field-mapper').forEach(mapper => {
    const filterInput = mapper.querySelector('.cludo-field-filter');
    const select = mapper.querySelector('.cludo-available-fields');

    if (!filterInput || !select) return;

    filterInput.addEventListener('input', () => {
        const query = filterInput.value.toLowerCase();

        Array.from(select.options).forEach(option => {
            option.style.display = option.text.toLowerCase().includes(query) ? 'block' : 'none';
        });
    });
});


document.querySelectorAll('.cludo-field-mapper').forEach(mapper=>{
let add=mapper.querySelector('.cludo-add-field');
let del=mapper.querySelector('.cludo-delete-field');
let sel=mapper.querySelector('.cludo-available-fields');
let area=mapper.querySelector('.cludo-mapping-area');
let type=mapper.dataset.postType;

add.addEventListener('click',()=>{
    let opt = sel.options[sel.selectedIndex];
    if(!opt) return;

    let key = opt.value;
    opt.remove();

    let row = document.createElement('div');
    row.className = 'cludo-map-row cludo-map-grid'; // IMPORTANT

    row.innerHTML = `
        <input type="checkbox" class="cludo-delete-check">

        <input type="text" readonly value="${key}">

        <input type="text"
            name="colby_cludo_field_mapping[${type}][${key}][display]"
            value="${key}">

        <input type="hidden"
            name="colby_cludo_field_mapping[${type}][${key}][source]"
            value="${key}">
    `;

    area.appendChild(row);
});

del.addEventListener('click',()=>{
area.querySelectorAll('.cludo-delete-check:checked').forEach(chk=>{
let row=chk.closest('.cludo-map-row');
let key=row.querySelector('input[type=hidden]').value;
let label=row.querySelector('input[type=text]').value||key;
let opt=document.createElement('option');
opt.value=key; opt.text=label;
sel.appendChild(opt);
row.remove();
});
});
});

});
</script>

<?php
} /**
 * API Configuration tab content
 */
function colby_cludo_render_api_settings()
{
    ?>
    <h2>API Settings</h2>
    <p>API details can be found in the Cludo dashboard <a href="https://my.cludo.com/" target="_blank" rel="noopener noreferrer">here</a>.</p>
    <table class="form-table">
        <tr>
            <th scope="row">Customer ID</th>
            <td>
                <input type="text" name="colby_cludo_customer_id" value="<?php echo esc_attr(
                    get_option("colby_cludo_customer_id")
                ); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">API Key</th>
            <td>
                <input type="password" name="colby_cludo_api_key" value="<?php echo esc_attr(
                    get_option("colby_cludo_api_key")
                ); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">API Host Name</th>
            <td>
                <input type="text" name="colby_cludo_api_host" value="<?php echo esc_attr(
                    get_option("colby_cludo_api_host")
                ); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">Crawler ID</th>
            <td>
                <input type="text" name="colby_cludo_crawler_id" value="<?php echo esc_attr(
                    get_option("colby_cludo_crawler_id")
                ); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">Engine ID</th>
            <td>
                <input type="text" name="colby_cludo_engine_id" value="<?php echo esc_attr(
                    get_option("colby_cludo_engine_id")
                ); ?>" class="regular-text" />
            </td>
        </tr>
    </table>
    <?php
}
function colby_cludo_render_add_blacklist()
{
    ?>
    <h2>Add Posts to be Indexed</h2>

    <hr />

    <h2>Blacklist Posts from Being Indexed</h2>
    <?php
} /**
 * Notifications tab content
 */
function colby_cludo_render_notifications()
{
    $emails = get_option("colby_cludo_emails", "");
    $selected_types = get_option("colby_cludo_notif_types", []);
    ?>
    <h2>Notification Settings</h2>
    <p>Configure your notification preferences.</p>
    <table class="form-table">
        <tr>
            <th scope="row">Email Recipients</th>
            <td>
                <input type="text" name="colby_cludo_emails" value="<?php echo esc_attr(
                    $emails
                ); ?>" class="regular-text" placeholder="user@example.com, admin@example.com" />
                <p class="description">Enter one or more email addresses separated by commas.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Notification Types</th>
            <td>
                <fieldset>
                    <label>
                        <input type="checkbox" name="colby_cludo_notif_types[]" value="run_success" <?php checked(
                            in_array("run_success", $selected_types)
                        ); ?> />
                        Scheduled run success
                    </label><br>
                    <label>
                        <input type="checkbox" name="colby_cludo_notif_types[]" value="run_error" <?php checked(
                            in_array("run_error", $selected_types)
                        ); ?> />
                        Scheduled run error
                    </label>
                </fieldset>
            </td>
        </tr>
    </table>
    <?php
} /**
 * Debug Log tab content
 */
function colby_cludo_render_debug_log()
{
    // Example: show log from a file
    $log_file = WP_CONTENT_DIR . "/colby-cludo-debug.log";
    $logs = file_exists($log_file)
        ? file_get_contents($log_file)
        : "No logs found.";
    ?>
    <h2>Debug Log</h2>
    <textarea readonly style="width:100%;height:300px;"><?php echo esc_textarea(
        $logs
    ); ?></textarea>
    <div style="height:2rem;display:flex;justify-content:space-between;">
        <p>Note: Logs are stored for 30 days</p>
        <button style="cursor:pointer;">Clear Logs</button>
    </div>
    <?php
}
