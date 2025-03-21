<?php
/**
 * Admin functions for GA4 to Nutshell Integration
 *
 * This file contains functions specific to the WordPress admin area
 * including settings validation, admin notices, and other admin-specific functionality.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add plugin action links
 *
 * @param array $links Existing plugin action links
 * @return array Modified plugin action links
 */
function ga4_to_nutshell_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ga4-to-nutshell') . '">' . __('Settings', 'ga4-to-nutshell') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_ga4-to-nutshell/ga4-to-nutshell.php', 'ga4_to_nutshell_plugin_action_links');

/**
 * Validate settings before saving
 *
 * @param array $input Settings input
 * @return array Validated settings
 */
function ga4_to_nutshell_validate_settings($input) {
    $output = array();
    
    // Sanitize API credentials
    $output['nutshell_username'] = isset($input['nutshell_username']) ? sanitize_text_field($input['nutshell_username']) : '';
    $output['nutshell_api_key'] = isset($input['nutshell_api_key']) ? sanitize_text_field($input['nutshell_api_key']) : '';
    
    // Debug mode
    $output['debug_mode'] = isset($input['debug_mode']) ? 1 : 0;
    
    // Form types and event triggers
    $output['enabled_form_types'] = isset($input['enabled_form_types']) && is_array($input['enabled_form_types']) ? 
        array_map('sanitize_text_field', $input['enabled_form_types']) : ['ninja_forms'];
    
    $output['event_triggers'] = isset($input['event_triggers']) && is_array($input['event_triggers']) ? 
        array_map('sanitize_text_field', $input['event_triggers']) : ['book_a_demo', 'nfFormSubmitResponse'];
    
    $output['custom_event_trigger'] = isset($input['custom_event_trigger']) ? 
        sanitize_text_field($input['custom_event_trigger']) : '';
    
    // Process form-to-user mappings
    $output['form_user_mappings'] = array();
    
    // Process form mappings
    if (isset($input['form_user_mappings'])) {
        // If it's structured as form_id => array and user_id => array 
        if (isset($input['form_user_mappings']['form_id']) && is_array($input['form_user_mappings']['form_id']) &&
            isset($input['form_user_mappings']['user_id']) && is_array($input['form_user_mappings']['user_id'])) {
            
            $form_ids = $input['form_user_mappings']['form_id'];
            $user_ids = $input['form_user_mappings']['user_id'];
            
            // Make sure arrays are the same length
            $count = min(count($form_ids), count($user_ids));
            
            for ($i = 0; $i < $count; $i++) {
                if (!empty($form_ids[$i]) && !empty($user_ids[$i])) {
                    $output['form_user_mappings'][] = array(
                        'form_id' => sanitize_text_field($form_ids[$i]),
                        'user_id' => sanitize_text_field($user_ids[$i])
                    );
                }
            }
        } 
        // If it's structured as an array of mappings
        else if (is_array($input['form_user_mappings'])) {
            foreach ($input['form_user_mappings'] as $mapping) {
                if (isset($mapping['form_id']) && !empty($mapping['form_id']) && 
                    isset($mapping['user_id']) && !empty($mapping['user_id'])) {
                    $output['form_user_mappings'][] = array(
                        'form_id' => sanitize_text_field($mapping['form_id']),
                        'user_id' => sanitize_text_field($mapping['user_id'])
                    );
                }
            }
        }
    }
    
    // Process field mappings
    if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
        $output['field_mappings'] = array();
        
        foreach ($input['field_mappings'] as $form_id => $fields) {
            $output['field_mappings'][$form_id] = array();
            
            foreach ($fields as $nutshell_field => $ninja_field) {
                if (!empty($ninja_field)) {
                    $output['field_mappings'][$form_id][$nutshell_field] = sanitize_text_field($ninja_field);
                }
            }
        }
    }
    
    return $output;
}
add_filter('pre_update_option_ga4_to_nutshell_settings', 'ga4_to_nutshell_validate_settings');


/**
 * Debug hook to log form submissions
 */
function ga4_to_nutshell_debug_options_save() {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    if (isset($_POST['option_page']) && $_POST['option_page'] === 'ga4_to_nutshell_settings') {
        error_log('GA4 to Nutshell: Settings form submitted with data: ' . print_r($_POST, true));
    }
}
add_action('admin_init', 'ga4_to_nutshell_debug_options_save', 5);

/**
 * Also add this to show the current saved mappings on the settings page
 */
function ga4_to_nutshell_debug_display_mappings() {
    $screen = get_current_screen();
    
    if ($screen->id !== 'settings_page_ga4-to-nutshell' || !defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $settings = get_option('ga4_to_nutshell_settings', []);
    
    if (isset($settings['form_user_mappings']) && !empty($settings['form_user_mappings'])) {
        echo '<div class="notice notice-info">';
        echo '<p><strong>Debug:</strong> Current saved mappings:</p>';
        echo '<pre>' . esc_html(print_r($settings['form_user_mappings'], true)) . '</pre>';
        echo '</div>';
    } else {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>Debug:</strong> No mappings currently saved.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'ga4_to_nutshell_debug_display_mappings');
/**
 * Display admin notices
 */
function ga4_to_nutshell_admin_notices() {
    $screen = get_current_screen();
    
    // Only show on plugin settings page or plugins page
    if ($screen->id !== 'settings_page_ga4-to-nutshell' && $screen->id !== 'plugins') {
        return;
    }
    
    $settings = get_option('ga4_to_nutshell_settings', []);
    
    // Check if Ninja Forms is active
    if (!class_exists('Ninja_Forms')) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . __('GA4 to Nutshell CRM Integration requires Ninja Forms to be installed and activated.', 'ga4-to-nutshell') . '</p>';
        echo '</div>';
    }
    
    // Check if API credentials are set
    if (empty($settings['nutshell_username']) || empty($settings['nutshell_api_key'])) {
        if ($screen->id === 'plugins') {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . __('GA4 to Nutshell CRM Integration requires configuration. Please visit the', 'ga4-to-nutshell');
            echo ' <a href="' . admin_url('options-general.php?page=ga4-to-nutshell') . '">' . __('settings page', 'ga4-to-nutshell') . '</a>.';
            echo '</p></div>';
        }
    }
}
add_action('admin_notices', 'ga4_to_nutshell_admin_notices');

/**
 * Display a help tab on the settings page
 */
function ga4_to_nutshell_add_help_tab() {
    $screen = get_current_screen();
    
    if ($screen->id !== 'settings_page_ga4-to-nutshell') {
        return;
    }
    
    $screen->add_help_tab([
        'id'      => 'ga4-to-nutshell-help-tab',
        'title'   => __('Help', 'ga4-to-nutshell'),
        'content' => '<h2>' . __('GA4 to Nutshell CRM Integration', 'ga4-to-nutshell') . '</h2>' .
                     '<p>' . __('This plugin integrates GA4 datalayer events with Nutshell CRM.', 'ga4-to-nutshell') . '</p>' .
                     '<h3>' . __('Setup Steps:', 'ga4-to-nutshell') . '</h3>' .
                     '<ol>' .
                     '<li>' . __('Enter your Nutshell API credentials', 'ga4-to-nutshell') . '</li>' .
                     '<li>' . __('Map your Ninja Forms to Nutshell users', 'ga4-to-nutshell') . '</li>' .
                     '<li>' . __('Ensure your website triggers the "book_a_demo" event', 'ga4-to-nutshell') . '</li>' .
                     '</ol>' .
                     '<p>' . __('For more detailed instructions, please refer to the plugin documentation.', 'ga4-to-nutshell') . '</p>'
    ]);
    
    $screen->add_help_tab([
        'id'      => 'ga4-to-nutshell-events-tab',
        'title'   => __('GA4 Events', 'ga4-to-nutshell'),
        'content' => '<h2>' . __('GA4 Events Configuration', 'ga4-to-nutshell') . '</h2>' .
                     '<p>' . __('This plugin listens for the following GA4 datalayer events:', 'ga4-to-nutshell') . '</p>' .
                     '<ul>' .
                     '<li><code>book_a_demo</code> - ' . __('Triggered by Ninja Forms submissions', 'ga4-to-nutshell') . '</li>' .
                     '<li><code>ninjaFormSubmission</code> - ' . __('Direct form submission event', 'ga4-to-nutshell') . '</li>' .
                     '</ul>' .
                     '<p>' . __('You may need to configure Google Tag Manager to trigger these events when forms are submitted.', 'ga4-to-nutshell') . '</p>'
    ]);
}
add_action('admin_head', 'ga4_to_nutshell_add_help_tab');

/**
 * Check for plugin dependencies and display error if needed
 */
function ga4_to_nutshell_check_dependencies() {
    if (!class_exists('Ninja_Forms')) {
        deactivate_plugins(plugin_basename(GA4_TO_NUTSHELL_PLUGIN_DIR . 'ga4-to-nutshell.php'));
        wp_die(__('GA4 to Nutshell CRM Integration requires Ninja Forms to be installed and activated.', 'ga4-to-nutshell'));
    }
}
register_activation_hook(GA4_TO_NUTSHELL_PLUGIN_DIR . 'ga4-to-nutshell.php', 'ga4_to_nutshell_check_dependencies');

/**
 * Initialize the logging system
 */
function ga4_to_nutshell_init_logging() {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    if (!defined('GA4_TO_NUTSHELL_LOG_FILE')) {
        define('GA4_TO_NUTSHELL_LOG_FILE', WP_CONTENT_DIR . '/ga4-to-nutshell-debug.log');
    }
}
add_action('plugins_loaded', 'ga4_to_nutshell_init_logging');

/**
 * Enhanced logging function with different log levels
 *
 * @param string $message Message to log
 * @param mixed $data Optional data to include
 * @param string $level Log level (debug, info, warning, error)
 */
function ga4_to_nutshell_log($message, $data = null, $level = 'info') {
    // Get settings
    $settings = get_option('ga4_to_nutshell_settings', []);
    
    // Check if debug mode is enabled
    $debug_mode = isset($settings['debug_mode']) && $settings['debug_mode'];
    
    // Determine if we should log based on level and debug mode
    $should_log = $debug_mode || $level === 'error' || $level === 'warning';
    
    // If debug is not enabled and this is not an error/warning, skip logging
    if (!$should_log) {
        return;
    }
    
    // Only log debug level messages if debug_mode is enabled
    if ($level === 'debug' && !$debug_mode) {
        return;
    }
    
    $timestamp = current_time('mysql');
    $formatted_message = "[$timestamp] [$level] $message";
    
    // Format data if provided
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            // Check if it's Ninja Forms data structure and simplify it
            if (is_array($data) && 
                (strpos($message, 'Ninja Form') !== false || 
                 strpos($message, 'ninja_form') !== false || 
                 isset($data['response']) && isset($data['response']['data']) && isset($data['response']['data']['form_id']))) {
                
                // For Ninja Forms, only log essential data
                $simplified_data = array();
                
                // Log form ID if available
                if (isset($data['form_id'])) {
                    $simplified_data['form_id'] = $data['form_id'];
                } elseif (isset($data['response']) && isset($data['response']['data']) && isset($data['response']['data']['form_id'])) {
                    $simplified_data['form_id'] = $data['response']['data']['form_id'];
                }
                
                // Log field count instead of all fields
                if (isset($data['fields']) && is_array($data['fields'])) {
                    $simplified_data['field_count'] = count($data['fields']);
                    $simplified_data['field_keys'] = array_keys($data['fields']);
                } elseif (isset($data['response']) && isset($data['response']['data']) && isset($data['response']['data']['fields'])) {
                    $simplified_data['field_count'] = count($data['response']['data']['fields']);
                }
                
                $data_string = '[Ninja Forms data simplified] ' . print_r($simplified_data, true);
            } else {
                // For other arrays/objects, limit size
                $data_string = print_r($data, true);
                
                // Truncate large data
                if (strlen($data_string) > 10000) {
                    $data_string = substr($data_string, 0, 10000) . '... [truncated]';
                }
            }
            
            $formatted_message .= ' ' . $data_string;
        } else {
            $formatted_message .= ' ' . $data;
        }
    }
    
    // Log to debug.log
    error_log($formatted_message);
    
    // Also log to a specific file for this plugin
    $log_file = WP_CONTENT_DIR . '/ga4-to-nutshell-debug.log';
    error_log($formatted_message . PHP_EOL, 3, $log_file);
    
    // For errors, log to a separate file for easier troubleshooting
    if ($level === 'error') {
        $error_log_file = WP_CONTENT_DIR . '/ga4-to-nutshell-errors.log';
        error_log($formatted_message . PHP_EOL, 3, $error_log_file);
    }
}
/**
 * Add logs tab to the settings page
 */
function ga4_to_nutshell_add_settings_tabs() {
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
    $tabs = [
        'settings' => __('Settings', 'ga4-to-nutshell'),
        'logs' => __('Debug Logs', 'ga4-to-nutshell'),
        'test' => __('Test Tools', 'ga4-to-nutshell'),
    ];
    
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $tab_key => $tab_label) {
        $active = $current_tab === $tab_key ? 'nav-tab-active' : '';
        echo '<a href="?page=ga4-to-nutshell&tab=' . $tab_key . '" class="nav-tab ' . $active . '">' . $tab_label . '</a>';
    }
    echo '</h2>';
    
    return $current_tab;
}

/**
 * Display logs content
 */
function ga4_to_nutshell_display_logs() {
    $log_file = WP_CONTENT_DIR . '/ga4-to-nutshell-debug.log';
    
    echo '<div class="wrap">';
    echo '<h1>' . __('GA4 to Nutshell CRM - Debug Logs', 'ga4-to-nutshell') . '</h1>';
    
    // Add refresh button and clear logs button
    echo '<div class="actions" style="margin-bottom: 15px;">';
    echo '<a href="?page=ga4-to-nutshell&tab=logs" class="button">' . __('Refresh Logs', 'ga4-to-nutshell') . '</a> ';
    echo '<a href="?page=ga4-to-nutshell&tab=logs&clear=1" class="button">' . __('Clear Logs', 'ga4-to-nutshell') . '</a>';
    echo '</div>';
    
    // Clear logs if requested
    if (isset($_GET['clear']) && $_GET['clear'] == 1) {
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            echo '<div class="notice notice-success"><p>' . __('Logs cleared successfully!', 'ga4-to-nutshell') . '</p></div>';
        }
    }
    
    // Display logs
    echo '<div class="logs-container" style="background: #f8f8f8; border: 1px solid #ddd; padding: 15px; height: 500px; overflow: auto; font-family: monospace; white-space: pre-wrap;">';
    
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        
        if (empty($logs)) {
            echo '<p>' . __('No logs found.', 'ga4-to-nutshell') . '</p>';
        } else {
            // Colorize log entries based on level
            $logs = preg_replace('/\[(error|warning)\]/i', '<span style="color: red;">[$1]</span>', $logs);
            $logs = preg_replace('/\[(info)\]/i', '<span style="color: blue;">[$1]</span>', $logs);
            
            // Highlight timestamps
            $logs = preg_replace('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', '<span style="color: #666;">$0</span>', $logs);
            
            echo $logs;
        }
    } else {
        echo '<p>' . __('Log file does not exist yet.', 'ga4-to-nutshell') . '</p>';
    }
    
    echo '</div>';
    echo '</div>';
}
/**
 * Handle API errors more effectively
 * 
 * @param WP_Error|array $response The response from wp_remote_request
 * @param string $context Context description of what we were trying to do
 * @param array $request_data The data that was sent in the request
 * @return array|WP_Error Parsed response or WP_Error
 */
function ga4_to_nutshell_handle_api_response($response, $context, $request_data = []) {
    // Check for WP_Error first
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        ga4_to_nutshell_log("API Error in $context", $error_message, 'error');
        
        // Log the request data for debugging
        ga4_to_nutshell_log("Request data for failed $context", $request_data, 'debug');
        
        return new WP_Error('api_request_failed', "API request failed ($context): $error_message");
    }
    
    // Check HTTP status code
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code < 200 || $status_code >= 300) {
        $error_message = "HTTP error $status_code for $context";
        ga4_to_nutshell_log($error_message, wp_remote_retrieve_body($response), 'error');
        
        return new WP_Error('api_http_error', $error_message);
    }
    
    // Try to parse the response body
    $body = wp_remote_retrieve_body($response);
    $parsed_body = json_decode($body, true);
    
    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "Invalid JSON response from $context: " . json_last_error_msg();
        ga4_to_nutshell_log($error_message, $body, 'error');
        
        return new WP_Error('api_invalid_json', $error_message);
    }
    
    // Check for API error in response
    if (isset($parsed_body['error'])) {
        $error_info = $parsed_body['error'];
        $error_message = isset($error_info['message']) ? $error_info['message'] : 'Unknown API error';
        $error_code = isset($error_info['code']) ? $error_info['code'] : 'api_error';
        $error_data = isset($error_info['data']) ? $error_info['data'] : null;
        
        ga4_to_nutshell_log("API Error in $context", [
            'message' => $error_message,
            'code' => $error_code,
            'data' => $error_data
        ], 'error');
        
        // Provide recovery suggestions based on error
        $recovery_suggestion = ga4_to_nutshell_get_error_recovery_suggestion($error_message, $error_code, $context);
        
        return new WP_Error($error_code, "$error_message. $recovery_suggestion", $error_data);
    }
    
    // Success! Log response for debugging if needed
    ga4_to_nutshell_log("Successful API response for $context", $parsed_body, 'debug');
    
    return $parsed_body;
}
/**
 * Get recovery suggestion based on error message and context
 * 
 * @param string $error_message The error message from the API
 * @param string $error_code The error code if available
 * @param string $context The context of the API request
 * @return string A suggestion for recovery
 */
function ga4_to_nutshell_get_error_recovery_suggestion($error_message, $error_code, $context) {
    // Default suggestion
    $suggestion = "Please check your request and try again.";
    
    // Authentication errors
    if (strpos($error_message, 'authentication') !== false || 
        strpos($error_message, 'credentials') !== false || 
        strpos($error_message, 'unauthorized') !== false || 
        $error_code === 'auth_error') {
        
        return "Please verify your Nutshell API credentials in the plugin settings.";
    }
    
    // Permission errors
    if (strpos($error_message, 'permission') !== false || 
        strpos($error_message, 'not allowed') !== false) {
        
        return "Your Nutshell API user may not have permission to perform this action. Please contact your Nutshell administrator.";
    }
    
    // Missing field errors
    if (strpos($error_message, 'missing') !== false || 
        strpos($error_message, 'required') !== false) {
        
        return "A required field is missing. Please check your form mappings and make sure all required Nutshell fields are mapped to form fields.";
    }
    
    // Validation errors
    if (strpos($error_message, 'validation') !== false) {
        return "There was a validation error with your data. Please check that your form data matches what Nutshell expects.";
    }
    
    // Entity not found
    if (strpos($error_message, 'not found') !== false) {
        if (strpos($context, 'contact') !== false) {
            return "The contact could not be found. Make sure your form collects valid contact information.";
        } else if (strpos($context, 'account') !== false) {
            return "The company/account could not be found. Make sure your form collects valid company information.";
        } else if (strpos($context, 'user') !== false) {
            return "The assigned user could not be found. Check your form-to-user mappings in the plugin settings.";
        }
    }
    
    // Rate limiting or API quotas
    if (strpos($error_message, 'rate limit') !== false || 
        strpos($error_message, 'quota') !== false || 
        $error_code === 'too_many_requests') {
        
        return "You have reached the Nutshell API rate limit. Please wait a few minutes and try again.";
    }
    
    // Context-specific suggestions
    if (strpos($context, 'creating lead') !== false) {
        return "There was an error creating the lead. Make sure your form collects all required information and that your form mappings are correct.";
    } else if (strpos($context, 'creating contact') !== false) {
        return "There was an error creating the contact. Make sure your form collects valid contact information such as name and email.";
    } else if (strpos($context, 'creating account') !== false) {
        return "There was an error creating the company/account. Make sure your form collects valid company information.";
    }
    
    return $suggestion;
}

/**
 * Display admin notice for error during form submission
 * 
 * @param WP_Error $error The error object
 */
function ga4_to_nutshell_admin_notice_error($error) {
    // Only show to admins
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $error_message = $error->get_error_message();
    $error_code = $error->get_error_code();
    
    // Add to admin notices
    add_action('admin_notices', function() use ($error_message, $error_code) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>GA4 to Nutshell Error:</strong> <?php echo esc_html($error_message); ?> (Code: <?php echo esc_html($error_code); ?>)</p>
            <p>Check the <a href="<?php echo admin_url('options-general.php?page=ga4-to-nutshell&tab=logs'); ?>">error logs</a> for more details.</p>
        </div>
        <?php
    });
}
/**
 * Display test tools
 */
function ga4_to_nutshell_display_test_tools() {
    echo '<div class="wrap">';
    echo '<h1>' . __('GA4 to Nutshell CRM - Test Tools', 'ga4-to-nutshell') . '</h1>';
    
    echo '<div class="test-tools">';
    
    // Test form submission
    echo '<div class="test-section" style="margin-bottom: 30px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd;">';
    echo '<h2>' . __('Test Form Submission', 'ga4-to-nutshell') . '</h2>';
    echo '<p>' . __('Use this form to simulate a Ninja Form submission and test the integration with Nutshell CRM.', 'ga4-to-nutshell') . '</p>';
    
    echo '<form id="test-submission-form">';
    
    // Form selection
    echo '<div style="margin-bottom: 15px;">';
    echo '<label style="display: block; margin-bottom: 5px;"><strong>' . __('Select Form:', 'ga4-to-nutshell') . '</strong></label>';
    echo '<select id="test-form-id" style="width: 100%;">';
    echo '<option value="">' . __('-- Select a Form --', 'ga4-to-nutshell') . '</option>';
    
    // Add forms dynamically with JavaScript
    echo '</select>';
    echo '</div>';
    
    // Standard fields
    $test_fields = [
        'name' => __('Name', 'ga4-to-nutshell'),
        'email' => __('Email', 'ga4-to-nutshell'),
        'phone' => __('Phone', 'ga4-to-nutshell'),
        'company' => __('Company', 'ga4-to-nutshell'),
        'country' => __('Country', 'ga4-to-nutshell'),
        'message' => __('Message', 'ga4-to-nutshell'),
    ];
    
    foreach ($test_fields as $field_id => $field_label) {
        echo '<div style="margin-bottom: 15px;">';
        echo '<label style="display: block; margin-bottom: 5px;"><strong>' . $field_label . ':</strong></label>';
        
        if ($field_id === 'message') {
            echo '<textarea id="test-field-' . $field_id . '" style="width: 100%;" rows="4"></textarea>';
        } else {
            echo '<input type="text" id="test-field-' . $field_id . '" style="width: 100%;" />';
        }
        
        echo '</div>';
    }
    
    // Submit button
    echo '<button type="submit" class="button button-primary">' . __('Submit Test Form', 'ga4-to-nutshell') . '</button>';
    
    echo '</form>';
    
    // Results display
    echo '<div id="test-results" style="margin-top: 15px; display: none;">';
    echo '<h3>' . __('Test Results', 'ga4-to-nutshell') . '</h3>';
    echo '<pre id="test-results-content" style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow: auto; max-height: 300px;"></pre>';
    echo '</div>';
    
    echo '</div>'; // End test section
    
    echo '</div>'; // End test tools
    echo '</div>'; // End wrap
    
    // Add JavaScript for test form
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Load forms for test form dropdown
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ga4_to_nutshell_get_ninja_forms',
                nonce: '<?php echo wp_create_nonce('ga4-to-nutshell-nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.forms) {
                    var formSelect = $('#test-form-id');
                    
                    response.data.forms.forEach(function(form) {
                        $('<option>').attr('value', form.id).text(form.title).appendTo(formSelect);
                    });
                }
            }
        });
        
        // Handle test form submission
        $('#test-submission-form').on('submit', function(e) {
            e.preventDefault();
            
            var formId = $('#test-form-id').val();
            if (!formId) {
                alert('Please select a form.');
                return;
            }
            
            var formData = {};
            
            // Collect field values
            <?php foreach ($test_fields as $field_id => $field_label) : ?>
            formData['<?php echo $field_id; ?>'] = $('#test-field-<?php echo $field_id; ?>').val();
            <?php endforeach; ?>
            
            // Show loading indicator
            $('#test-results').show();
            $('#test-results-content').html('Sending test data...');
            
            // Send test data
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ga4_to_nutshell_test_submission',
                    nonce: '<?php echo wp_create_nonce('ga4-to-nutshell-nonce'); ?>',
                    form_id: formId,
                    form_data: formData
                },
                success: function(response) {
                    $('#test-results-content').html(JSON.stringify(response, null, 2));
                },
                error: function(xhr, status, error) {
                    $('#test-results-content').html('Error: ' + error + '\n\nResponse: ' + xhr.responseText);
                }
            });
        });
    });
    </script>
    <?php
}
/**
 * Handle the settings page tabs
 */
function ga4_to_nutshell_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $current_tab = ga4_to_nutshell_add_settings_tabs();
    
    switch ($current_tab) {
        case 'logs':
            ga4_to_nutshell_display_logs();
            break;
        
        case 'test':
            ga4_to_nutshell_display_test_tools();
            break;
        
        default:
            // Regular settings page content handled by the main plugin
            break;
    }
}

/**
 * Add this AJAX handler for test submissions to the main plugin file
 */
add_action('wp_ajax_ga4_to_nutshell_test_submission', 'ga4_to_nutshell_test_submission');

/**
 * Handle test form submission
 */
function ga4_to_nutshell_test_submission() {
    check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }
    
    $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
    $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : [];
    
    // Sanitize form data
    $sanitized_form_data = [];
    foreach ($form_data as $key => $value) {
        $sanitized_form_data[$key] = sanitize_text_field($value);
    }
    
    ga4_to_nutshell_log('Processing test submission', [
        'form_id' => $form_id,
        'form_data' => $sanitized_form_data
    ]);
    
    // Get settings
    $settings = get_option('ga4_to_nutshell_settings', []);
    
    // Find assigned user for this form
    $assigned_user_id = '';
    if (isset($settings['form_user_mappings']) && is_array($settings['form_user_mappings'])) {
        foreach ($settings['form_user_mappings'] as $mapping) {
            if ($mapping['form_id'] == $form_id) {
                $assigned_user_id = $mapping['user_id'];
                break;
            }
        }
    }
    
    // Send to Nutshell
    $result = ga4_to_nutshell_send_to_nutshell(
        $settings,
        $sanitized_form_data,
        'Test Form ' . $form_id,
        $assigned_user_id,
        'test',
        'test',
        admin_url('options-general.php?page=ga4-to-nutshell&tab=test'),
        $form_id
    );
    
    if (is_wp_error($result)) {
        wp_send_json_error([
            'message' => $result->get_error_message(),
            'form_id' => $form_id,
            'form_data' => $sanitized_form_data,
            'assigned_user_id' => $assigned_user_id
        ]);
    } else {
        wp_send_json_success([
            'message' => 'Test data sent to Nutshell successfully',
            'lead_id' => $result,
            'form_id' => $form_id,
            'form_data' => $sanitized_form_data,
            'assigned_user_id' => $assigned_user_id
        ]);
    }
}
