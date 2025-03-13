<?php

/**
 * AJAX Handler for GA4 to Nutshell WordPress Plugin
 */

// Add the AJAX handler for frontend data processing
add_action('wp_ajax_ga4_to_nutshell_process_data', 'ga4_to_nutshell_process_data');
add_action('wp_ajax_nopriv_ga4_to_nutshell_process_data', 'ga4_to_nutshell_process_data');

/**
 * Process data from frontend and send to Nutshell
 */
function ga4_to_nutshell_process_data()
{
    // Start logging
    ga4_to_nutshell_log('Processing data from frontend', $_POST, 'info');

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ga4-to-nutshell-frontend-nonce')) {
        ga4_to_nutshell_log('Nonce verification failed', $_POST, 'error');
        wp_send_json_error(['message' => 'Security verification failed']);
        return;
    }

    // Get submitted data
    $json_data = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : '';
    if (empty($json_data)) {
        ga4_to_nutshell_log('No data provided', $_POST, 'error');
        wp_send_json_error(['message' => 'No data provided']);
        return;
    }

    // Decode JSON data
    $data = json_decode(stripslashes($json_data), true);
    if (!$data) {
        ga4_to_nutshell_log('Invalid JSON data', $json_data, 'error');
        wp_send_json_error(['message' => 'Invalid JSON data']);
        return;
    }

    ga4_to_nutshell_log('JSON data decoded successfully', $data, 'info');

    // Get settings
    $settings = get_option('ga4_to_nutshell_settings', []);
    ga4_to_nutshell_log('Loaded settings', $settings, 'info');

    // Check for API credentials
    if (empty($settings['nutshell_username']) || empty($settings['nutshell_api_key'])) {
        ga4_to_nutshell_log('Missing Nutshell API credentials', null, 'error');
        wp_send_json_error(['message' => 'Missing Nutshell API credentials']);
        return;
    }

    // Extract data
    $form_data = isset($data['formData']) ? $data['formData'] : [];
    $form_id = isset($data['formId']) ? sanitize_text_field($data['formId']) : '';
    $form_name = isset($data['formName']) ? sanitize_text_field($data['formName']) : '';
    $traffic_source = isset($data['trafficSource']) ? sanitize_text_field($data['trafficSource']) : '';
    $referrer_url = isset($data['referrerUrl']) ? esc_url_raw($data['referrerUrl']) : '';
    $current_url = isset($data['currentUrl']) ? esc_url_raw($data['currentUrl']) : '';
    $assigned_user_id = isset($data['assignedUserId']) ? sanitize_text_field($data['assignedUserId']) : '';

    ga4_to_nutshell_log('Extracted data from request', [
        'form_data' => $form_data,
        'form_id' => $form_id,
        'form_name' => $form_name,
        'traffic_source' => $traffic_source,
        'referrer_url' => $referrer_url,
        'current_url' => $current_url,
        'assigned_user_id' => $assigned_user_id
    ], 'info');

    // Send data to Nutshell
    $result = ga4_to_nutshell_send_to_nutshell(
        $settings,
        $form_data,
        $form_name,
        $assigned_user_id,
        $traffic_source,
        $referrer_url,
        $current_url,
        $form_id  // Added form_id parameter
    );

    if (is_wp_error($result)) {
        ga4_to_nutshell_log('Error sending data to Nutshell', $result->get_error_message(), 'error');
        wp_send_json_error(['message' => $result->get_error_message()]);
        return;
    }

    ga4_to_nutshell_log('Data sent to Nutshell successfully', ['lead_id' => $result], 'info');
    wp_send_json_success(['message' => 'Data sent to Nutshell successfully', 'lead_id' => $result]);
}
/**
 * Updated function to find or create an account (company) in Nutshell
 * This function uses the correct JSON-RPC API structure
 * 
 * @param string $api_url The Nutshell API URL
 * @param string $username The Nutshell API username
 * @param string $api_key The Nutshell API key
 * @param string $company_name The company name to find or create
 * @param array $company_data Additional company data (optional)
 * @return int|WP_Error The account ID or WP_Error on failure
 */
function ga4_to_nutshell_find_or_create_account($api_url, $username, $api_key, $company_name, $company_data = [])
{
    if (empty($company_name)) {
        ga4_to_nutshell_log('No company name provided', null, 'warning');
        return new WP_Error('invalid_company', 'No company name provided');
    }

    ga4_to_nutshell_log('Finding or creating account', ['company_name' => $company_name]);

    // Try to find account by name
    $find_payload = [
        'jsonrpc' => '2.0',
        'method' => 'findAccounts',
        'params' => [
            'query' => [
                'name' => $company_name
            ],
            'limit' => 1
        ],
        'id' => wp_generate_uuid4()
    ];

    ga4_to_nutshell_log('Search account API payload', $find_payload);

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
        ],
        'body' => json_encode($find_payload),
        'timeout' => 30,
        'method' => 'POST'
    ];

    $response = wp_remote_request($api_url, $args);

    if (is_wp_error($response)) {
        ga4_to_nutshell_log('Error searching for account', $response->get_error_message(), 'error');
        return new WP_Error('api_error', 'Error connecting to Nutshell API: ' . $response->get_error_message());
    }

    ga4_to_nutshell_log('Search account API response', [
        'code' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response)
    ]);

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    // If account found, return ID
    if (isset($response_body['result']) && !empty($response_body['result'])) {
        $account_id = $response_body['result'][0]['id'];
        ga4_to_nutshell_log('Found existing account', ['id' => $account_id, 'name' => $company_name]);
        return $account_id;
    }

    ga4_to_nutshell_log('No existing account found, creating new account');

    // Prepare account data - using correct JSON-RPC structure for account creation
    $account_data = [
        'name' => $company_name
    ];

    // Add a description if we have additional data
    if (!empty($company_data['website']) || !empty($company_data['industry'])) {
        $description = '';
        if (!empty($company_data['industry'])) {
            $description .= 'Industry: ' . $company_data['industry'] . "\n";
        }
        if (!empty($company_data['website'])) {
            $description .= 'Website: ' . $company_data['website'];
        }

        if (!empty($description)) {
            $account_data['description'] = $description;
        }
    }

    // Add URL if available
    if (!empty($company_data['website'])) {
        $account_data['url'] = $company_data['website'];
    }

    // Add address if available
    if (
        !empty($company_data['address']) || !empty($company_data['city']) || !empty($company_data['state']) ||
        !empty($company_data['postal_code']) || !empty($company_data['country'])
    ) {

        $address = [];

        if (!empty($company_data['address'])) {
            $address['address_1'] = $company_data['address'];
        }

        if (!empty($company_data['city'])) {
            $address['city'] = $company_data['city'];
        }

        if (!empty($company_data['state'])) {
            $address['state'] = $company_data['state'];
        }

        if (!empty($company_data['postal_code'])) {
            $address['postalCode'] = $company_data['postal_code'];
        }

        if (!empty($company_data['country'])) {
            $address['country'] = $company_data['country'];
        }

        if (!empty($address)) {
            $account_data['address'] = [$address];
        }
    }

    // Create new account
    $create_payload = [
        'jsonrpc' => '2.0',
        'method' => 'newAccount',
        'params' => [
            'account' => $account_data
        ],
        'id' => wp_generate_uuid4()
    ];

    ga4_to_nutshell_log('Create account API payload', $create_payload);

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
        ],
        'body' => json_encode($create_payload),
        'timeout' => 30,
        'method' => 'POST'
    ];

    $response = wp_remote_request($api_url, $args);

    if (is_wp_error($response)) {
        ga4_to_nutshell_log('Error creating account', $response->get_error_message(), 'error');
        return new WP_Error('api_error', 'Error connecting to Nutshell API: ' . $response->get_error_message());
    }

    ga4_to_nutshell_log('Create account API response', [
        'code' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response)
    ]);

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['error'])) {
        ga4_to_nutshell_log('Nutshell API error when creating account', $response_body['error'], 'error');
        return new WP_Error('api_error', 'Nutshell API error: ' . $response_body['error']['message']);
    }

    $new_account_id = $response_body['result']['id'];
    ga4_to_nutshell_log('Successfully created new account', ['id' => $new_account_id, 'name' => $company_name]);
    return $new_account_id;
}
/**
 * Detect the form type based on form ID and data
 * This helps with better field detection and mapping
 *
 * @param string $form_id The form ID
 * @param array $form_data The form data
 * @return string The detected form type or empty string if unknown
 */
function ga4_to_nutshell_detect_form_type($form_id, $form_data)
{
    ga4_to_nutshell_log('Detecting form type', [
        'form_id' => $form_id,
        'form_data_keys' => array_keys($form_data)
    ]);

    // Check settings to see if form type is already mapped
    $settings = get_option('ga4_to_nutshell_settings', []);
    if (isset($settings['form_user_mappings']) && is_array($settings['form_user_mappings'])) {
        foreach ($settings['form_user_mappings'] as $mapping) {
            if ($mapping['form_id'] == $form_id && !empty($mapping['form_type'])) {
                ga4_to_nutshell_log('Found form type in mappings', ['form_type' => $mapping['form_type']]);
                return $mapping['form_type'];
            }
        }
    }

    // Look for form plugin-specific patterns in the form data

    // Check for Ninja Forms patterns
    $ninja_forms_pattern = false;
    foreach (array_keys($form_data) as $key) {
        if (
            strpos($key, 'ninja_forms') !== false ||
            strpos($key, 'nf_') !== false ||
            preg_match('/^field_\d+$/', $key)
        ) {
            $ninja_forms_pattern = true;
            break;
        }
    }

    if ($ninja_forms_pattern) {
        ga4_to_nutshell_log('Detected Ninja Forms pattern in form data', null);
        return 'ninja_forms';
    }

    // Check for Contact Form 7 patterns
    $cf7_pattern = false;
    foreach (array_keys($form_data) as $key) {
        if (
            strpos($key, 'your-') === 0 ||
            strpos($key, 'wpcf7') !== false
        ) {
            $cf7_pattern = true;
            break;
        }
    }

    if ($cf7_pattern) {
        ga4_to_nutshell_log('Detected Contact Form 7 pattern in form data', null);
        return 'contact_form_7';
    }

    // Check for Gravity Forms patterns
    $gf_pattern = false;
    foreach (array_keys($form_data) as $key) {
        if (
            strpos($key, 'input_') === 0 ||
            strpos($key, 'gform_') !== false
        ) {
            $gf_pattern = true;
            break;
        }
    }

    if ($gf_pattern) {
        ga4_to_nutshell_log('Detected Gravity Forms pattern in form data', null);
        return 'gravity_forms';
    }

    // Check for WPForms patterns
    $wpforms_pattern = false;
    foreach (array_keys($form_data) as $key) {
        if (strpos($key, 'wpforms') !== false) {
            $wpforms_pattern = true;
            break;
        }
    }

    if ($wpforms_pattern) {
        ga4_to_nutshell_log('Detected WPForms pattern in form data', null);
        return 'wpforms';
    }

    // Check for Formidable patterns
    $formidable_pattern = false;
    foreach (array_keys($form_data) as $key) {
        if (
            strpos($key, 'item_meta') !== false ||
            strpos($key, 'frm_') !== false
        ) {
            $formidable_pattern = true;
            break;
        }
    }

    if ($formidable_pattern) {
        ga4_to_nutshell_log('Detected Formidable Forms pattern in form data', null);
        return 'formidable';
    }

    // If we can't detect a specific form type, return empty string
    ga4_to_nutshell_log('Could not detect form type from form data', null, 'warning');
    return '';
}
/**
 * Enhanced function to send data to Nutshell CRM
 * This version properly creates and links contacts, accounts, and leads
 *
 * @param array $settings The plugin settings
 * @param array $form_data The form data
 * @param string $form_name The form name/title
 * @param string $assigned_user_id The Nutshell user ID to assign the lead to
 * @param string $traffic_source The traffic source
 * @param string $referrer_url The referrer URL
 * @param string $current_url The current page URL
 * @param string $form_id The form ID (optional)
 * @param string $form_type The form type (optional)
 * @return int|WP_Error The lead ID or WP_Error on failure
 */
function ga4_to_nutshell_send_to_nutshell($settings, $form_data, $form_name, $assigned_user_id, $traffic_source, $traffic_medium, $referrer_url, $current_url, $form_id = '', $form_type = '')
{
    ga4_to_nutshell_log('Starting send to Nutshell process', [
        'form_id' => $form_id,
        'form_type' => $form_type,
        'form_name' => $form_name,
        'assigned_user_id' => $assigned_user_id,
        'traffic_source' => $traffic_source,
        'traffic_medium' => $traffic_medium
    ]);

    // Validate API credentials
    if (empty($settings['nutshell_username']) || empty($settings['nutshell_api_key'])) {
        ga4_to_nutshell_log('Missing Nutshell API credentials', null, 'error');
        return new WP_Error('missing_credentials', 'Nutshell API credentials are missing');
    }

    // Log full form data for debugging
    ga4_to_nutshell_log('Full form data received', $form_data, 'debug');

    // If form ID is not provided or empty, try to detect it
    if (empty($form_id)) {
        $form_id = ga4_to_nutshell_detect_form_id($form_data, $form_name, $current_url);
    }

    // If form type is not provided, try to detect it
    if (empty($form_type) && !empty($form_id)) {
        $form_type = ga4_to_nutshell_detect_form_type($form_id, $form_data);
    }

    // If we found a form ID but no assigned user, try to find it from mappings
    if (!empty($form_id) && empty($assigned_user_id)) {
        if (isset($settings['form_user_mappings']) && is_array($settings['form_user_mappings'])) {
            foreach ($settings['form_user_mappings'] as $mapping) {
                if ($mapping['form_id'] == $form_id) {
                    $assigned_user_id = $mapping['user_id'];
                    ga4_to_nutshell_log('Found user mapping for detected form', [
                        'form_id' => $form_id,
                        'user_id' => $assigned_user_id
                    ]);
                    break;
                }
            }
        }
    }

    // 1. Extract contact info from form data
    $contact = ga4_to_nutshell_extract_contact_from_form_data($form_data, $form_id, $form_type);
    if (!$contact || empty($contact['email'])) {
        ga4_to_nutshell_log('No valid contact data found', $form_data, 'error');
        return new WP_Error('invalid_contact', 'No valid contact data found in form submission');
    }

    ga4_to_nutshell_log('Successfully extracted contact information', $contact);

    // 2. Extract company info (if available)
    $company_data = ga4_to_nutshell_extract_company_from_form_data($form_data, $form_id, $form_type);

    // If we have a company name from the contact but not from company extraction
    if (empty($company_data) && !empty($contact['company'])) {
        $company_data = [
            'name' => $contact['company'],
            'country' => $contact['country'] ?? ''
        ];
        ga4_to_nutshell_log('Using company name from contact data', $company_data);
    }

    // Prepare the API request for Nutshell
    $api_url = 'https://app.nutshell.com/api/v1/json';
    $username = $settings['nutshell_username'];
    $api_key = $settings['nutshell_api_key'];

    ga4_to_nutshell_log('Using Nutshell API credentials', [
        'username' => $username,
        'api_url' => $api_url
    ]);

    // 3. Find or create contact
    $contact_id = ga4_to_nutshell_find_or_create_contact($api_url, $username, $api_key, $contact);
    if (is_wp_error($contact_id)) {
        ga4_to_nutshell_log('Error with contact creation/lookup', $contact_id->get_error_message(), 'error');
        return $contact_id;
    }

    ga4_to_nutshell_log('Successfully got contact ID', ['contact_id' => $contact_id]);

    // 4. Find or create account/company if we have company data
    $account_id = null;
    if (!empty($company_data) && !empty($company_data['name'])) {
        $account_id = ga4_to_nutshell_find_or_create_account($api_url, $username, $api_key, $company_data['name'], $company_data);
        if (is_wp_error($account_id)) {
            ga4_to_nutshell_log('Error with account creation/lookup', $account_id->get_error_message(), 'warning');
            // Continue without account rather than failing
            $account_id = null;
        } else {
            ga4_to_nutshell_log('Successfully got account ID', ['account_id' => $account_id]);
        }
    }

    // 5. Create lead with proper entity relationships
    // Using the correct JSON-RPC structure for leads
    $lead_data = [
        'description' => 'Website form lead: ' . esc_html($form_name),
        'confidence' => 50, // Medium confidence
        'note' => [
            "Lead created from " . esc_html($form_name) . " form submission.",
            "Traffic source: " . esc_html($traffic_source),
            "Referrer URL: " . esc_html($referrer_url),
            "Form URL: " . esc_html($current_url)
        ]
    ];

    // Add contacts array with the contact ID
    $lead_data['contacts'] = [
        [
            'id' => $contact_id
        ]
    ];

    // Add accounts array with the account ID if available
    if ($account_id) {
        $lead_data['accounts'] = [
            [
                'id' => $account_id
            ]
        ];

        // Set as primary account as well
        $lead_data['primaryAccount'] = [
            'id' => $account_id
        ];
    }

    // NEW SECTION: Enhanced traffic source and medium processing
    $valid_sources = [];
    $traffic_details = '';

    // Add Website as a default source
    $valid_sources[] = 'Website';

    // Process traffic medium - THIS IS THE KEY ADDITION
    if (!empty($traffic_medium)) {
        $medium = strtolower(trim($traffic_medium));
        
        // Add source based on medium
        if ($medium === 'cpc' || $medium === 'ppc' || $medium === 'paidsearch') {
            $valid_sources[] = 'Paid Search';
            $traffic_details .= "Medium: Paid Search\n";
        } else if ($medium === 'organic' || $medium === 'organicsearch') {
            $valid_sources[] = 'Organic Search';
            $traffic_details .= "Medium: Organic Search\n";
        } else if ($medium === 'social' || $medium === 'socialmedia') {
            $valid_sources[] = 'Social Media';
            $traffic_details .= "Medium: Social Media\n";
        } else if ($medium === 'email') {
            $valid_sources[] = 'Email';
            $traffic_details .= "Medium: Email Campaign\n";
        } else if ($medium === 'affiliate') {
            $valid_sources[] = 'Affiliate';
            $traffic_details .= "Medium: Affiliate\n";
        } else if ($medium === 'referral') {
            $valid_sources[] = 'Referral';
            $traffic_details .= "Medium: Referral\n";
        } else if ($medium === 'direct') {
            $valid_sources[] = 'Direct';
            $traffic_details .= "Medium: Direct\n";
        } else {
            // For custom or unknown mediums, capture in the details
            $traffic_details .= "Medium: " . ucfirst($medium) . "\n";
        }
    }

    // Process traffic source 
    if (!empty($traffic_source)) {
        $source = trim($traffic_source);
        
        if (!empty($source)) {
            // Add specific source details to notes
            $traffic_details .= "Source: " . esc_html($source) . "\n";
            
            // Check if it's a domain
            if (strpos($source, '.') !== false) {
                // For domains, categorize by common sources
                if (strpos($source, 'google') !== false) {
                    // Only add if we don't already have a medium-based source
                    if (empty($traffic_medium) && !in_array('Organic Search', $valid_sources) && !in_array('Paid Search', $valid_sources)) {
                        $valid_sources[] = 'Google';
                    }
                } else if (strpos($source, 'facebook') !== false || strpos($source, 'fb.com') !== false) {
                    if (empty($traffic_medium) && !in_array('Social Media', $valid_sources)) {
                        $valid_sources[] = 'Facebook';
                    }
                } else if (strpos($source, 'linkedin') !== false) {
                    if (empty($traffic_medium) && !in_array('Social Media', $valid_sources)) {
                        $valid_sources[] = 'LinkedIn';
                    }
                } else if (strpos($source, 'twitter') !== false || strpos($source, 'x.com') !== false) {
                    if (empty($traffic_medium) && !in_array('Social Media', $valid_sources)) {
                        $valid_sources[] = 'Twitter';
                    }
                } else if (strpos($source, 'bing') !== false) {
                    if (empty($traffic_medium) && !in_array('Organic Search', $valid_sources) && !in_array('Paid Search', $valid_sources)) {
                        $valid_sources[] = 'Bing';
                    }
                } else if (strpos($source, 'yahoo') !== false) {
                    if (empty($traffic_medium) && !in_array('Organic Search', $valid_sources) && !in_array('Paid Search', $valid_sources)) {
                        $valid_sources[] = 'Yahoo';
                    }
                } else if (empty($traffic_medium) && !in_array('Referral', $valid_sources)) {
                    // Only add Referral if not already determined by medium
                    $valid_sources[] = 'Referral';
                }
            } else if (empty($traffic_medium)) {
                // For non-domain sources and no medium specified
                // Note: We prioritize medium-based classifications over source-based ones
                $valid_sources[] = ucfirst(strtolower($source));
            }
        }
    }

    // Add all traffic details to the lead note
    if (!empty($traffic_details)) {
        // If note is already an array (from earlier code), append to it
        if (is_array($lead_data['note'])) {
            $lead_data['note'][] = "\n-- Traffic Details --";
            $lead_data['note'][] = $traffic_details;
        } else {
            // Otherwise append to the string
            $lead_data['note'] .= "\n\n-- Traffic Details --\n" . $traffic_details;
        }
    }

    // Set the sources with the correct format for Nutshell API
    $lead_data['sources'] = array_unique($valid_sources);

    ga4_to_nutshell_log('Final sources for lead', $lead_data['sources']);
    // END NEW SECTION

    // Add form fields to custom fields for additional context
    $lead_data['customFields'] = [];
    
    // Add traffic data to custom fields for analytics
    if (!empty($traffic_source)) {
        $lead_data['customFields']['Traffic Source'] = substr($traffic_source, 0, 255);
    }
    
    if (!empty($traffic_medium)) {
        $lead_data['customFields']['Traffic Medium'] = substr($traffic_medium, 0, 255);
    }
    
    // Add other form fields as custom fields
    foreach ($form_data as $key => $value) {
        if (is_string($value) && !empty($value)) {
            // Limit key length to 40 characters (Nutshell limit)
            $customFieldKey = substr("Form: " . $key, 0, 40);
            $lead_data['customFields'][$customFieldKey] = substr($value, 0, 255); // Limit value length
        }
    }

    // Assign to user if specified - FIXED FIELD NAME
    if (!empty($assigned_user_id)) {
        $lead_data['assignedTo'] = [
            'entityType' => 'Users',
            'id' => $assigned_user_id
        ];
        ga4_to_nutshell_log('Assigning lead to user ID', ['user_id' => $assigned_user_id]);
    } else {
        ga4_to_nutshell_log('No user assigned for this lead', null, 'warning');
    }

    // Remove any assignee field if it exists, as we're using assignedTo
    if (isset($lead_data['assignee'])) {
        unset($lead_data['assignee']);
    }

    // Create lead with the Nutshell API
    $lead_payload = [
        'jsonrpc' => '2.0',
        'method' => 'newLead',
        'params' => [
            'lead' => $lead_data
        ],
        'id' => wp_generate_uuid4()
    ];

    ga4_to_nutshell_log('Creating lead with payload', $lead_payload);

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
        ],
        'body' => json_encode($lead_payload),
        'timeout' => 30,
        'method' => 'POST'
    ];

    $response = wp_remote_request($api_url, $args);

    if (is_wp_error($response)) {
        ga4_to_nutshell_log('Error connecting to Nutshell API', $response->get_error_message(), 'error');
        return new WP_Error('api_error', 'Error connecting to Nutshell API: ' . $response->get_error_message());
    }

    ga4_to_nutshell_log('Lead creation API response', [
        'code' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response)
    ]);

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['error'])) {
        ga4_to_nutshell_log('Nutshell API error when creating lead', $response_body['error'], 'error');

        // If the error is with the lead structure, try to provide more detailed information
        $error_message = $response_body['error']['message'];
        $error_data = $response_body['error']['data'] ?? null;

        // Enhanced error message with recovery suggestions
        $detailed_error = 'Nutshell API error: ' . $error_message;

        if ($error_data) {
            $detailed_error .= " (Details: " . json_encode($error_data) . ")";

            // Add recovery suggestions based on common errors
            if (strpos($error_message, 'validation failed') !== false) {
                $detailed_error .= "\nPlease check that all required fields are provided and in the correct format.";
            } else if (strpos($error_message, 'not found') !== false) {
                $detailed_error .= "\nOne of the referenced entities (contact or account) could not be found.";
            } else if (strpos($error_message, 'permission') !== false) {
                $detailed_error .= "\nYour API credentials might not have permission to perform this action.";
            }
        }

        return new WP_Error('api_error', $detailed_error);
    }

    $lead_id = $response_body['result']['id'];
    ga4_to_nutshell_log('Successfully created lead in Nutshell', ['lead_id' => $lead_id]);
    return $lead_id;
}
/**
 * Attempt to detect the form ID from form data or URL
 */
function ga4_to_nutshell_detect_form_id($form_data, $form_name = '', $current_url = '')
{
    ga4_to_nutshell_log('Attempting to detect form ID', [
        'form_name' => $form_name,
        'current_url' => $current_url
    ]);

    // Check if form ID is in the form data
    foreach ($form_data as $key => $value) {
        if (($key === 'form_id' || $key === 'formId') && !empty($value)) {
            ga4_to_nutshell_log('Found form ID in form data', ['form_id' => $value]);
            return $value;
        }
    }

    // Get settings to check available form mappings
    $settings = get_option('ga4_to_nutshell_settings', []);
    $form_mappings = isset($settings['form_user_mappings']) ? $settings['form_user_mappings'] : [];

    // If we have a form name, try to match it to known forms
    if (!empty($form_name) && class_exists('Ninja_Forms')) {
        $forms = Ninja_Forms()->form()->get_forms();

        foreach ($forms as $form) {
            $title = $form->get_setting('title');
            $id = $form->get_id();

            if (
                strtolower($title) === strtolower($form_name) ||
                strpos(strtolower($title), strtolower($form_name)) !== false
            ) {
                ga4_to_nutshell_log('Matched form by name', [
                    'form_name' => $form_name,
                    'matched_form' => $title,
                    'form_id' => $id
                ]);
                return $id;
            }
        }
    }

    // Try to detect from URL path
    if (!empty($current_url)) {
        $path = parse_url($current_url, PHP_URL_PATH);

        // Map known URLs to form IDs
        $url_mappings = [
            '/see-how-it-works/' => '17', // Example mapping
            '/contact/' => '17',  // Example mapping
            '/request-demo/' => '17',  // Example mapping
        ];

        foreach ($url_mappings as $url_path => $form_id) {
            if (strpos($path, $url_path) !== false) {
                ga4_to_nutshell_log('Matched form by URL path', [
                    'url_path' => $url_path,
                    'form_id' => $form_id
                ]);
                return $form_id;
            }
        }
    }

    // If we have only one form mapping, assume it's that form
    if (count($form_mappings) === 1) {
        $form_id = $form_mappings[0]['form_id'];
        ga4_to_nutshell_log('Using the only available form mapping', ['form_id' => $form_id]);
        return $form_id;
    }

    ga4_to_nutshell_log('Could not detect form ID', null, 'warning');
    return '';
}
/**
 * Get available forms from all supported form plugins
 * Used by the admin UI to populate form selections
 */
function ga4_to_nutshell_get_all_forms()
{
    $forms = [];

    // Get Ninja Forms if available
    if (class_exists('Ninja_Forms')) {
        $ninja_forms = Ninja_Forms()->form()->get_forms();
        foreach ($ninja_forms as $form) {
            $forms[] = [
                'id' => $form->get_id(),
                'title' => $form->get_setting('title'),
                'type' => 'ninja_forms',
                'plugin' => 'Ninja Forms'
            ];
        }
    }

    // Get Contact Form 7 forms if available
    if (class_exists('WPCF7')) {
        $cf7_forms = WPCF7_ContactForm::find();
        foreach ($cf7_forms as $form) {
            $forms[] = [
                'id' => $form->id(),
                'title' => $form->title(),
                'type' => 'contact_form_7',
                'plugin' => 'Contact Form 7'
            ];
        }
    }

    // Get Gravity Forms if available
    if (class_exists('GFForms')) {
        $gravity_forms = GFAPI::get_forms();
        foreach ($gravity_forms as $form) {
            $forms[] = [
                'id' => $form['id'],
                'title' => $form['title'],
                'type' => 'gravity_forms',
                'plugin' => 'Gravity Forms'
            ];
        }
    }

    // Get WPForms if available
    if (class_exists('WPForms')) {
        $wpforms_forms = wpforms()->form->get();
        foreach ($wpforms_forms as $form) {
            $forms[] = [
                'id' => $form->ID,
                'title' => $form->post_title,
                'type' => 'wpforms',
                'plugin' => 'WPForms'
            ];
        }
    }

    // Get Formidable Forms if available
    if (class_exists('FrmForm')) {
        $formidable_forms = FrmForm::getAll(['is_template' => 0]);
        foreach ($formidable_forms as $form) {
            $forms[] = [
                'id' => $form->id,
                'title' => $form->name,
                'type' => 'formidable',
                'plugin' => 'Formidable Forms'
            ];
        }
    }

    return $forms;
}

/**
 * Get form fields for a specific form
 * Used by the admin UI to populate field mappings
 * 
 * @param string $form_type The form plugin type
 * @param int $form_id The ID of the form
 * @return array Array of form fields
 */
function ga4_to_nutshell_get_form_fields($form_type, $form_id)
{
    $fields = [];

    switch ($form_type) {
        case 'ninja_forms':
            if (class_exists('Ninja_Forms')) {
                $form_fields = Ninja_Forms()->form($form_id)->get_fields();

                foreach ($form_fields as $field) {
                    // Skip non-data fields
                    $type = $field->get_setting('type');
                    if (in_array($type, ['submit', 'html', 'hr', 'heading', 'divider'])) {
                        continue;
                    }

                    $fields[] = [
                        'id' => $field->get_id(),
                        'key' => $field->get_setting('key'),
                        'label' => $field->get_setting('label'),
                        'type' => $type
                    ];
                }
            }
            break;

        case 'contact_form_7':
            if (class_exists('WPCF7')) {
                $form = WPCF7_ContactForm::get_instance($form_id);

                if ($form) {
                    $form_tags = $form->scan_form_tags();

                    foreach ($form_tags as $tag) {
                        // Skip non-input tags
                        if (!in_array($tag->basetype, ['text', 'email', 'tel', 'url', 'textarea', 'select', 'radio', 'checkbox', 'number', 'date'])) {
                            continue;
                        }

                        $fields[] = [
                            'id' => $tag->name,
                            'key' => $tag->name,
                            'label' => $tag->name, // CF7 doesn't have built-in labels in the same way
                            'type' => $tag->basetype
                        ];
                    }
                }
            }
            break;

        case 'gravity_forms':
            if (class_exists('GFForms')) {
                $form = GFAPI::get_form($form_id);

                if ($form && isset($form['fields']) && is_array($form['fields'])) {
                    foreach ($form['fields'] as $field) {
                        // Skip certain field types
                        if (in_array($field->type, ['html', 'section', 'page', 'captcha'])) {
                            continue;
                        }

                        // Handle multi-input fields like name, address
                        if (is_array($field->inputs) && !empty($field->inputs)) {
                            foreach ($field->inputs as $input) {
                                $input_label = isset($input['label']) ? $input['label'] : $field->label . ' ' . $input['id'];

                                $fields[] = [
                                    'id' => $input['id'],
                                    'key' => 'input_' . str_replace('.', '_', $input['id']),
                                    'label' => $input_label,
                                    'type' => $field->type . ' (part)'
                                ];
                            }
                        } else {
                            $fields[] = [
                                'id' => $field->id,
                                'key' => 'input_' . $field->id,
                                'label' => $field->label,
                                'type' => $field->type
                            ];
                        }
                    }
                }
            }
            break;

        case 'wpforms':
            if (class_exists('WPForms')) {
                $form_data = wpforms()->form->get($form_id, ['content_only' => true]);

                if ($form_data && isset($form_data['fields']) && is_array($form_data['fields'])) {
                    foreach ($form_data['fields'] as $field) {
                        // Skip certain field types
                        if (in_array($field['type'], ['divider', 'html', 'pagebreak', 'captcha'])) {
                            continue;
                        }

                        $fields[] = [
                            'id' => $field['id'],
                            'key' => 'wpforms[fields][' . $field['id'] . ']',
                            'label' => isset($field['label']) ? $field['label'] : 'Field ' . $field['id'],
                            'type' => $field['type']
                        ];
                    }
                }
            }
            break;

        case 'formidable':
            if (class_exists('FrmField')) {
                $form_fields = FrmField::get_all_for_form($form_id);

                foreach ($form_fields as $field) {
                    // Skip certain field types
                    if (in_array($field->type, ['divider', 'html', 'captcha', 'break'])) {
                        continue;
                    }

                    $fields[] = [
                        'id' => $field->id,
                        'key' => 'item_meta[' . $field->id . ']',
                        'label' => $field->name,
                        'type' => $field->type
                    ];
                }
            }
            break;
    }

    return $fields;
}

// Add a new AJAX endpoint to get available forms from all plugins
add_action('wp_ajax_ga4_to_nutshell_get_all_forms', 'ga4_to_nutshell_ajax_get_all_forms');

function ga4_to_nutshell_ajax_get_all_forms()
{
    check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $forms = ga4_to_nutshell_get_all_forms();
    wp_send_json_success(['forms' => $forms]);
}

// Modify the existing AJAX endpoint to get form fields
function ga4_to_nutshell_ajax_get_form_fields()
{
    check_ajax_referer('ga4-to-nutshell-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
    $form_type = isset($_POST['form_type']) ? sanitize_text_field($_POST['form_type']) : 'ninja_forms';

    if (empty($form_id)) {
        wp_send_json_error(['message' => 'Missing form ID']);
        return;
    }

    $fields = ga4_to_nutshell_get_form_fields($form_type, $form_id);

    if (empty($fields)) {
        wp_send_json_error(['message' => 'No fields found for this form or form plugin not active']);
        return;
    }

    wp_send_json_success(['fields' => $fields]);
}

/**
 * Enhanced function to extract contact info from form data
 * This version supports multiple form types
 */
/**
 * Enhanced function to extract contact details from form data
 * This version adds more patterns and field matching logic
 *
 * @param array $form_data The form data
 * @param string $form_id The form ID (optional)
 * @param string $form_type The form type (optional)
 * @return array|null Contact data array or null if no valid contact data found
 */
function ga4_to_nutshell_extract_contact_from_form_data($form_data, $form_id = '', $form_type = '')
{
    ga4_to_nutshell_log('Extracting contact info from form data', [
        'form_data_keys' => array_keys($form_data),
        'form_id' => $form_id,
        'form_type' => $form_type
    ]);

    $settings = get_option('ga4_to_nutshell_settings', []);

    // Initialize contact with empty values
    $contact = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'company' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => ''
    ];

    // IMPORTANT: Direct mapping for standard field names
    // If the form data already contains keys that match our contact fields, use them directly
    foreach ($contact as $field => $value) {
        if (isset($form_data[$field]) && !empty($form_data[$field])) {
            $contact[$field] = sanitize_text_field($form_data[$field]);
            ga4_to_nutshell_log("Found direct match for {$field}", [
                'value' => $contact[$field]
            ]);
        }
    }

    // Try field mappings if email or name is still empty
    if (empty($contact['email']) || empty($contact['name'])) {
        // If we have field mappings for this form, use them
        if (!empty($form_id) && isset($settings['field_mappings'][$form_id])) {
            $mappings = $settings['field_mappings'][$form_id];
            ga4_to_nutshell_log('Using field mappings for form', $mappings);

            foreach ($mappings as $nutshell_field => $form_field_id) {
                if (empty($form_field_id)) {
                    continue; // Skip empty mappings
                }

                ga4_to_nutshell_log('Looking for mapped field', [
                    'nutshell_field' => $nutshell_field,
                    'form_field_id' => $form_field_id
                ]);

                // First try direct match by field ID
                if (isset($form_data[$form_field_id])) {
                    $contact[$nutshell_field] = sanitize_text_field($form_data[$form_field_id]);
                    ga4_to_nutshell_log("Mapped {$nutshell_field} to value using direct ID match", [
                        'field_id' => $form_field_id,
                        'value' => $contact[$nutshell_field]
                    ]);
                    continue;
                }

                // Form-type specific handling
                if (!empty($form_type)) {
                    // Apply form-specific transformations if needed
                    $possible_keys = ga4_to_nutshell_get_possible_field_keys($form_field_id, $form_type);

                    foreach ($possible_keys as $key) {
                        if (isset($form_data[$key])) {
                            $contact[$nutshell_field] = sanitize_text_field($form_data[$key]);
                            ga4_to_nutshell_log("Mapped {$nutshell_field} to value using transformed key", [
                                'original_id' => $form_field_id,
                                'transformed_key' => $key,
                                'value' => $contact[$nutshell_field]
                            ]);
                            break;
                        }
                    }
                }

                // If direct and form-type specific matching fails, try partial match
                if (empty($contact[$nutshell_field])) {
                    foreach ($form_data as $field_key => $field_value) {
                        // Skip empty values
                        if (empty($field_value)) {
                            continue;
                        }

                        // Check if the field key contains the ID or ends with the ID
                        if (
                            strpos($field_key, $form_field_id) !== false ||
                            substr($field_key, -strlen($form_field_id)) === $form_field_id
                        ) {
                            $contact[$nutshell_field] = sanitize_text_field($field_value);
                            ga4_to_nutshell_log("Mapped {$nutshell_field} to value using partial ID match", [
                                'field_key' => $field_key,
                                'field_id' => $form_field_id,
                                'value' => $contact[$nutshell_field]
                            ]);
                            break;
                        }
                    }
                }
            }
        } else {
            ga4_to_nutshell_log('No field mappings found for form ID: ' . $form_id, null, 'warning');
            ga4_to_nutshell_log('Using field pattern detection method', null, 'info');

            // Pattern detection method - expanded with more patterns
            $field_patterns = [
                'email' => ['email', 'e-mail', 'mail', 'email_address', 'your-email', 'user_email', 'contact_email'],
                'name' => ['name', 'full_name', 'fullname', 'customer_name', 'client_name', 'your-name', 'user_name', 'contact_name'],
                'phone' => ['phone', 'telephone', 'tel', 'mobile', 'phone_number', 'your-phone', 'user_phone', 'contact_phone', 'cell', 'work_phone'],
                'company' => ['company', 'business', 'organization', 'organisation', 'company_name', 'employer', 'your-company'],
                'address' => ['address', 'street', 'location', 'postal_address', 'street_address', 'address_line_1', 'your-address'],
                'city' => ['city', 'town', 'your-city', 'user_city'],
                'state' => ['state', 'province', 'region', 'your-state', 'user_state'],
                'postal_code' => ['postal_code', 'postcode', 'zip', 'zipcode', 'your-zip', 'user_zip'],
                'country' => ['country', 'nation', 'your-country', 'user_country']
            ];

            // Look for field keys that match our patterns
            foreach ($field_patterns as $contact_field => $patterns) {
                // Skip if we already have this field from direct mapping
                if (!empty($contact[$contact_field])) {
                    continue;
                }

                foreach ($form_data as $field_key => $field_value) {
                    // Skip empty values
                    if (empty($field_value)) {
                        continue;
                    }

                    // Check if any pattern is in the field key (case insensitive)
                    $field_key_lower = strtolower($field_key);
                    foreach ($patterns as $pattern) {
                        if (strpos($field_key_lower, $pattern) !== false) {
                            $contact[$contact_field] = sanitize_text_field($field_value);
                            ga4_to_nutshell_log("Detected {$contact_field} field using pattern match", [
                                'field_key' => $field_key,
                                'pattern' => $pattern,
                                'value' => $contact[$contact_field]
                            ]);
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }
    }

    // Handle special cases for names
    // If we have first+last name but no full name, combine them
    if (empty($contact['name'])) {
        $first_name = '';
        $last_name = '';

        // Look for first name
        foreach ($form_data as $key => $value) {
            $key_lower = strtolower($key);
            if (
                strpos($key_lower, 'first') !== false ||
                strpos($key_lower, 'fname') !== false ||
                $key_lower === 'first-name' ||
                $key_lower === 'firstname' ||
                $key_lower === 'your-first-name'
            ) {
                $first_name = $value;
                break;
            }
        }

        // Look for last name
        foreach ($form_data as $key => $value) {
            $key_lower = strtolower($key);
            if (
                strpos($key_lower, 'last') !== false ||
                strpos($key_lower, 'lname') !== false ||
                $key_lower === 'last-name' ||
                $key_lower === 'lastname' ||
                $key_lower === 'your-last-name'
            ) {
                $last_name = $value;
                break;
            }
        }

        if (!empty($first_name) || !empty($last_name)) {
            $contact['name'] = trim($first_name . ' ' . $last_name);
            ga4_to_nutshell_log('Combined first/last name', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'full_name' => $contact['name']
            ]);
        }
    }

    // Email validation - make sure it's a valid email
    if (!empty($contact['email']) && !is_email($contact['email'])) {
        ga4_to_nutshell_log('Found email field but value is not a valid email', [
            'value' => $contact['email']
        ], 'warning');
        $contact['email'] = '';
    }

    // If we couldn't find the email field, look for any field value that looks like an email
    if (empty($contact['email'])) {
        foreach ($form_data as $field_key => $field_value) {
            if (is_email($field_value)) {
                $contact['email'] = sanitize_email($field_value);
                ga4_to_nutshell_log('Found email value in field', [
                    'field_key' => $field_key,
                    'email' => $contact['email']
                ]);
                break;
            }
        }
    }

    // If no name found, use email as name
    if (empty($contact['name']) && !empty($contact['email'])) {
        $contact['name'] = $contact['email'];
        ga4_to_nutshell_log('No name found, using email as name', $contact['name']);
    }

    // Log and return the contact info
    ga4_to_nutshell_log('Final contact info extraction results', $contact);

    // If we have at least a name or email, return the contact info
    if (!empty($contact['name']) || !empty($contact['email'])) {
        return $contact;
    }

    ga4_to_nutshell_log('No valid contact data found in form data', $form_data, 'warning');
    return null;
}
/**
 * Get possible field keys based on form type and field ID
 * This helps map fields across different form plugins
 *
 * @param string $field_id The base field ID
 * @param string $form_type The form plugin type
 * @return array List of possible field keys to check
 */
function ga4_to_nutshell_get_possible_field_keys($field_id, $form_type)
{
    $possible_keys = [$field_id]; // Always include the original ID

    switch ($form_type) {
        case 'ninja_forms':
            // Ninja Forms - multiple possible key formats
            $possible_keys[] = 'field_' . $field_id;
            $possible_keys[] = 'ninja_forms_field_' . $field_id;
            break;

        case 'contact_form_7':
            // Contact Form 7 - common prefixes
            $possible_keys[] = 'your-' . $field_id;
            break;

        case 'gravity_forms':
            // Gravity Forms - input format variations
            $possible_keys[] = 'input_' . $field_id;
            $possible_keys[] = 'input_' . str_replace('.', '_', $field_id);
            break;

        case 'wpforms':
            // WPForms - field formats
            $possible_keys[] = 'wpforms[fields][' . $field_id . ']';
            break;

        case 'formidable':
            // Formidable Forms - field formats
            $possible_keys[] = 'item_meta[' . $field_id . ']';
            break;
    }
    // Add generic cases
    $possible_keys[] = $field_id . '_field';
    $possible_keys[] = 'field-' . $field_id;
    $possible_keys[] = 'fields[' . $field_id . ']';
    return $possible_keys;
}