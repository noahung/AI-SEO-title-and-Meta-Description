<?php
/*
Plugin Name: Bulk SEO Generator
Description: Generate and manage SEO titles and meta descriptions for pages using OpenAI API and Yoast SEO
Version: 1.0
Author: Noah
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if Yoast SEO is active
function bsg_is_yoast_active() {
    return is_plugin_active('wordpress-seo/wp-seo.php');
}

// Add top-level menu and submenu items
function bsg_add_menu_items() {
    $icon_url = plugin_dir_url(__FILE__) . 'plugin-icon.png';
    if (!file_exists(plugin_dir_path(__FILE__) . 'plugin-icon.png')) {
        $icon_url = 'dashicons-admin-page';
    }

    add_menu_page(
        'SEO Generator AI',
        'SEO Generator AI',
        'manage_options',
        'bulk-seo-generator',
        'bsg_admin_page',
        $icon_url,
        26
    );

    add_submenu_page(
        'bulk-seo-generator',
        'Generate SEO Content',
        'Generate SEO Content',
        'manage_options',
        'bulk-seo-generator',
        'bsg_admin_page'
    );

    add_submenu_page(
        'bulk-seo-generator',
        'Settings',
        'Settings',
        'manage_options',
        'bulk-seo-settings',
        'bsg_settings_page'
    );
}
add_action('admin_menu', 'bsg_add_menu_items');

// Enqueue scripts and styles
function bsg_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_bulk-seo-generator') {
        return;
    }

    wp_enqueue_style('bsg_styles', plugin_dir_url(__FILE__) . 'css/style.css');
    wp_enqueue_script('bsg_script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);

    wp_localize_script('bsg_script', 'bsg_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bsg_nonce'),
        'api_key' => get_option('bsg_openai_api_key', ''),
    ));
}
add_action('admin_enqueue_scripts', 'bsg_enqueue_scripts');

// Admin page content
function bsg_admin_page() {
    if (!bsg_is_yoast_active()) {
        echo '<div class="notice notice-error"><p><strong>Bulk SEO Generator requires Yoast SEO plugin to be installed and active.</strong> Please install and activate Yoast SEO.</p></div>';
        return;
    }
    ?>
    <div class="wrap">
        <h1 class="bsg-title">SEO Generator AI</h1>
        
        <div id="bsg-container">
            <div class="bsg-actions">
                <button id="bsg-select-pages" class="button button-primary bsg-button">Select Pages <span class="dashicons dashicons-admin-page"></span></button>
                <button id="bsg-preview" class="button button-primary bsg-button" disabled>Generate SEO Content <span class="dashicons dashicons-visibility"></span></button>
                <div id="bsg-loading" class="bsg-loading" style="display: none;">
                    <span class="spinner"></span>
                    <p>AI is analyzing the pages and generating SEO content...</p>
                </div>
            </div>
            
            <!-- Modal for page selection -->
            <div id="bsg-page-modal" class="bsg-modal" style="display: none;">
                <div class="bsg-modal-content">
                    <span id="bsg-modal-close" class="bsg-modal-close">×</span>
                    <h2>Select Pages</h2>
                    <div id="bsg-page-list" class="bsg-page-list"></div>
                    <button id="bsg-modal-confirm" class="button button-primary">Confirm Selection</button>
                </div>
            </div>
            
            <div id="bsg-selected-pages" class="bsg-section" style="display: none;">
                <h3>Selected Pages <span id="bsg-page-count"></span></h3>
                <div id="bsg-page-preview" class="bsg-page-list"></div>
                <p class="bsg-help-text">Select pages to generate SEO titles and meta descriptions.</p>
            </div>
            
            <div id="bsg-results" class="bsg-section" style="display: none;">
                <div class="bsg-table-wrapper">
                    <table class="wp-list-table widefat fixed striped bsg-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Current SEO Title / Meta Description</th>
                                <th>Generated SEO Title / Meta Description</th>
                            </tr>
                        </thead>
                        <tbody id="bsg-table-body"></tbody>
                    </table>
                </div>
                <button id="bsg-save" class="button button-primary bsg-button" style="display: none; margin-top: 10px;">Save SEO Content <span class="dashicons dashicons-save"></span></button>
                <div id="bsg-error" class="bsg-error" style="display: none;"></div>
            </div>
        </div>
    </div>
    <?php
}

// Settings page content
function bsg_settings_page() {
    ?>
    <div class="wrap">
        <h1>SEO Generator AI - Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('bsg_settings_group'); ?>
            <?php do_settings_sections('bsg_settings_group'); ?>
            <table class="form-table bsg-settings-table">
                <tr>
                    <th><label for="bsg_openai_api_key">OpenAI API Key</label></th>
                    <td>
                        <input type="text" name="bsg_openai_api_key" id="bsg_openai_api_key" value="<?php echo esc_attr(get_option('bsg_openai_api_key')); ?>" class="regular-text" />
                        <p class="description">Enter your OpenAI API key from <a href="https://platform.openai.com/account/api-keys" target="_blank">OpenAI</a>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bsg_chatgpt_prompt">ChatGPT Prompt</label></th>
                    <td>
                        <textarea name="bsg_chatgpt_prompt" id="bsg_chatgpt_prompt" rows="5" class="large-text"><?php echo esc_textarea(get_option('bsg_chatgpt_prompt', 'Generate an SEO-optimized title (up to 60 characters) and a meta description (up to 160 characters) for this page based on its content. Ensure both a title and a description are provided.')); ?></textarea>
                        <p class="description">Customize the prompt for generating SEO titles and meta descriptions. The response format (Title: [Your Title]\nDescription: [Your Description]) is automatically enforced.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

// Register settings
function bsg_register_settings() {
    register_setting('bsg_settings_group', 'bsg_openai_api_key');
    register_setting('bsg_settings_group', 'bsg_chatgpt_prompt');
}
add_action('admin_init', 'bsg_register_settings');

// AJAX handler to fetch pages
function bsg_fetch_pages() {
    check_ajax_referer('bsg_nonce', 'nonce');

    $args = array(
        'post_type' => 'page',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
    );

    $pages = get_posts($args);
    $page_list = array();

    foreach ($pages as $page) {
        $page_list[] = array(
            'id' => $page->ID,
            'title' => $page->post_title,
        );
    }

    wp_send_json_success($page_list);
}
add_action('wp_ajax_bsg_fetch_pages', 'bsg_fetch_pages');

// AJAX handler for generating SEO content
function bsg_generate_seo_content() {
    check_ajax_referer('bsg_nonce', 'nonce');

    $page_ids = isset($_POST['page_ids']) ? array_map('intval', $_POST['page_ids']) : array();
    $keyphrases = isset($_POST['keyphrases']) ? array_map('sanitize_text_field', (array)$_POST['keyphrases']) : array();

    if (empty($page_ids)) {
        wp_send_json_error('No pages selected.');
        return;
    }

    $api_key = get_option('bsg_openai_api_key');
    if (empty($api_key)) {
        wp_send_json_error('Please set your OpenAI API key in the settings.');
        return;
    }

    $results = array();

    foreach ($page_ids as $index => $page_id) {
        $page = get_post($page_id);
        $current_title = get_post_meta($page_id, '_yoast_wpseo_title', true);
        $current_desc = get_post_meta($page_id, '_yoast_wpseo_metadesc', true);

        $page_content = $page->post_title . ' ' . $page->post_excerpt . ' ' . wp_strip_all_tags($page->post_content);
        $keyphrase = isset($keyphrases[$page_id]) ? $keyphrases[$page_id] : '';

        $generated_content = bsg_generate_seo_from_openai($page_content, $api_key, $keyphrase);

        $results[] = array(
            'id' => $page_id,
            'title' => $page->post_title,
            'permalink' => get_permalink($page_id),
            'current_title' => $current_title,
            'current_desc' => $current_desc,
            'generated_title' => $generated_content['title'],
            'generated_desc' => $generated_content['desc'],
            'keyphrase' => $keyphrase // Include the keyphrase in the response
        );
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_bsg_generate_seo_content', 'bsg_generate_seo_content');

// AJAX handler for saving SEO content
function bsg_save_seo_content() {
    check_ajax_referer('bsg_nonce', 'nonce');

    $seo_content = isset($_POST['seo_content']) ? (array)$_POST['seo_content'] : array();

    foreach ($seo_content as $item) {
        $page_id = intval($item['id']);
        $seo_title = sanitize_text_field($item['seo_title']);
        $meta_desc = sanitize_text_field($item['meta_desc']);
        update_post_meta($page_id, '_yoast_wpseo_title', $seo_title);
        update_post_meta($page_id, '_yoast_wpseo_metadesc', $meta_desc);
    }

    wp_send_json_success();
}
add_action('wp_ajax_bsg_save_seo_content', 'bsg_save_seo_content');

// OpenAI API integration
function bsg_generate_seo_from_openai($content, $api_key, $keyphrase = '') {
    $base_prompt = get_option('bsg_chatgpt_prompt', 'Generate an SEO-optimized title (up to 60 characters) and a meta description (up to 160 characters) for this page based on its content. Ensure both a title and a description are provided.');
    
    // Add the keyphrase to the prompt if provided
    $keyphrase_prompt = !empty($keyphrase) ? " Use the focused keyphrase: \"$keyphrase\" in the title and description." : '';
    
    // Hardcode the format instruction
    $format_instruction = " Return the response in the following format:\nTitle: [Your Title]\nDescription: [Your Description]";
    
    $prompt = $base_prompt . $keyphrase_prompt . $format_instruction;
    
    // Log the prompt for debugging
    error_log('OpenAI Prompt: ' . $prompt);
    error_log('Page Content: ' . $content);

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt . "\n\nContent: " . $content
                )
            ),
            'max_tokens' => 200,
            'temperature' => 0.7
        )),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('OpenAI API Error: ' . $error_message);
        return array('title' => 'API Error', 'desc' => $error_message);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Log the full API response for debugging
    error_log('OpenAI API Response Code: ' . $response_code);
    error_log('OpenAI API Response Body: ' . print_r($body, true));

    if ($response_code !== 200 || isset($body['error'])) {
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        error_log('OpenAI API Response Error: ' . $error_message);
        return array('title' => 'API Error', 'desc' => $error_message);
    }

    $generated = isset($body['choices'][0]['message']['content']) ? $body['choices'][0]['message']['content'] : '';
    
    // Log the raw generated content
    error_log('Generated Content: ' . $generated);

    // Parse the response
    $title = 'Failed to generate title';
    $desc = 'Failed to generate description';

    if (!empty($generated)) {
        $lines = explode("\n", trim($generated));
        foreach ($lines as $line) {
            if (strpos($line, 'Title:') === 0) {
                $title = trim(substr($line, strlen('Title:')));
            } elseif (strpos($line, 'Description:') === 0) {
                $desc = trim(substr($line, strlen('Description:')));
            }
        }

        // Fallback: If description is missing but title is present, try to extract description from remaining text
        if ($desc === 'Failed to generate description' && count($lines) > 1) {
            foreach ($lines as $line) {
                if (strpos($line, 'Title:') !== 0 && !empty(trim($line))) {
                    $desc = trim($line);
                    break;
                }
            }
        }
    }

    return array('title' => $title, 'desc' => $desc);
}
