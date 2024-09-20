<?php
/**
* Plugin name: Rocket Arash
* Description: Simple Caching Version of Arash Law
* Version: 1.0
* Requires PHP: 7.3
* Author: Jeff
* GitHub Plugin URI: https://github.com/jeffzjade09/rocket-arash
* GitHub Branch: main  // or whichever branch you want to track
* Licence: Custom built not totally Licensed
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Define cache directory
define('ROCKET_ARASH_CACHE_DIR', WP_CONTENT_DIR . '/rocket-arash-cache');

// Register the top-level admin menu
add_action('admin_menu', 'rocket_arash_add_admin_menu');
add_action('admin_init', 'rocket_arash_settings_init');

// Hook into WordPress to start caching
add_action('init', 'rocket_arash_start_caching', 1);

// Add the top-level menu
function rocket_arash_add_admin_menu() {
    add_menu_page(
        'Rocket Arash Cache',            // Page title
        'Rocket Arash',                  // Menu title
        'manage_options',                // Capability
        'rocket-arash',                  // Menu slug
        'rocket_arash_options_page',     // Function to display content
        'dashicons-performance',         // Dashicon
        3                                // Position (Top of the admin menu)
    );
}

// Register the settings
function rocket_arash_settings_init() {
    // Register the setting for caching options
    register_setting('rocketArashSettings', 'rocket_arash_options', [
        'sanitize_callback' => 'rocket_arash_sanitize_options'
    ]);

    // Add a section to the settings page
    add_settings_section(
        'rocket_arash_section', 
        __('Cache Settings', 'rocket-arash'), 
        null, 
        'rocket-arash'
    );

    // Add the enable/disable cache field
    add_settings_field(
        'rocket_arash_enable_cache', 
        __('Enable Cache', 'rocket-arash'), 
        'rocket_arash_enable_cache_render', 
        'rocket-arash', 
        'rocket_arash_section'
    );
}

// Sanitize the input data
function rocket_arash_sanitize_options($input) {
    $sanitized_input = [];
    $sanitized_input['enable_cache'] = isset($input['enable_cache']) ? 1 : 0;
    return $sanitized_input;
}

// Render the checkbox field
function rocket_arash_enable_cache_render() {
    $options = get_option('rocket_arash_options');
    $enable_cache = isset($options['enable_cache']) ? $options['enable_cache'] : 0;
    ?>
    <input type='checkbox' name='rocket_arash_options[enable_cache]' 
    <?php checked($enable_cache, 1); ?> value='1'>
    <?php
}

// Display the settings page
function rocket_arash_options_page() {
    ?>
    <div class="wrap">
        <h2>Rocket Arash Dashboard</h2>
        <form action='options.php' method='post'>
            <?php
            settings_fields('rocketArashSettings');
            do_settings_sections('rocket-arash');
            submit_button();
            ?>
        </form>
        
        <h3>Clear Cache</h3>
        <form method="post" action="">
            <input type="hidden" name="clear_cache" value="1">
            <?php submit_button('Clear Cache'); ?>
        </form>

        <?php
        // Check if the clear cache action was triggered
        if (isset($_POST['clear_cache']) && $_POST['clear_cache'] == 1) {
            rocket_arash_clear_cache();
            echo '<div class="updated"><p>Cache cleared successfully!</p></div>';
        }
        ?>
    </div>
    <?php
}

// Cache handling functions

// Start caching if enabled
function rocket_arash_start_caching() {
    $options = get_option('rocket_arash_options');
    
    // Check if caching is enabled
    if (isset($options['enable_cache']) && $options['enable_cache'] == 1) {
        // Try serving the cached page
        rocket_arash_serve_cache();

        // Start output buffering to capture the output
        ob_start('rocket_arash_cache_output');
    }
}

// Serve the cached file if it exists and is valid
function rocket_arash_serve_cache() {
    $cache_file = rocket_arash_get_cache_file_path();

    // If the cache file exists and is still valid (not expired)
    if (file_exists($cache_file) && time() - filemtime($cache_file) < 3600) {
        // Serve the cached file and stop further processing
        readfile($cache_file);
        exit;
    }
}

// Save the output to a cache file
function rocket_arash_cache_output($output) {
    // Only cache for non-admin users and regular pages
    if (!is_admin()) {
        $cache_file = rocket_arash_get_cache_file_path();

        // Create the cache directory if it doesn't exist
        if (!is_dir(ROCKET_ARASH_CACHE_DIR)) {
            mkdir(ROCKET_ARASH_CACHE_DIR, 0755, true);
        }

        // Save the output to the cache file
        file_put_contents($cache_file, $output);
    }

    // Return the original output
    return $output;
}

// Get the path to the cache file for the current page
function rocket_arash_get_cache_file_path() {
    // Get the requested URI and sanitize it for use in the file name
    $request_uri = sanitize_file_name($_SERVER['REQUEST_URI']);
    
    // Use index.html for the homepage
    if (empty($request_uri)) {
        $request_uri = 'index';
    }

    return ROCKET_ARASH_CACHE_DIR . '/' . $request_uri . '.html';
}

// Add a function to clear the cache manually
function rocket_arash_clear_cache() {
    // Recursively delete all files in the cache directory
    if (is_dir(ROCKET_ARASH_CACHE_DIR)) {
        $files = glob(ROCKET_ARASH_CACHE_DIR . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}