/**
 * Admin JavaScript for GA4 to Nutshell Plugin
 * Handles admin UI interactions, form mappings, and field mappings
 */
jQuery(document).ready(function ($) {
    // Variables to store data
    var nutshellUsers = [];
    var allForms = [];
    var formMappings = [];

    // Initialize settings
    var settings = ga4ToNutshell.settings || {};

    // Initialize form mappings
    if (settings.form_user_mappings && Array.isArray(settings.form_user_mappings)) {
        formMappings = settings.form_user_mappings;
    }

    /**
     * Load Nutshell users
     */
    function loadNutshellUsers() {
        var username = $('#nutshell_username').val();
        var apiKey = $('#nutshell_api_key').val();

        if (!username || !apiKey) {
            return;
        }

        $('#ga4-to-nutshell-mapping-container').html('<p>Loading Nutshell users...</p>');

        $.ajax({
            url: ga4ToNutshell.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ga4_to_nutshell_get_users',
                nonce: ga4ToNutshell.nonce,
                username: username,
                api_key: apiKey
            },
            success: function (response) {
                if (response.success && response.data.users) {
                    nutshellUsers = response.data.users;
                    loadAllForms();
                } else {
                    $('#ga4-to-nutshell-mapping-container').html('<p class="error">Error loading Nutshell users: ' + (response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function () {
                $('#ga4-to-nutshell-mapping-container').html('<p class="error">Error loading Nutshell users. Please check your API credentials.</p>');
            }
        });
    }

    /**
     * Load all forms from all supported plugins
     */
    function loadAllForms() {
        $('#ga4-to-nutshell-mapping-container').html('<p>Loading forms...</p>');

        $.ajax({
            url: ga4ToNutshell.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ga4_to_nutshell_get_all_forms',
                nonce: ga4ToNutshell.nonce
            },
            success: function (response) {
                if (response.success && response.data.forms) {
                    allForms = response.data.forms;
                    renderFormMappings();
                } else {
                    $('#ga4-to-nutshell-mapping-container').html('<p class="error">Error loading forms: ' + (response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function () {
                $('#ga4-to-nutshell-mapping-container').html('<p class="error">Error loading forms.</p>');
            }
        });
    }

    /**
     * Render form to user mappings
     */
    function renderFormMappings() {
        var template = $('#form-mapping-template').html();

        if (!template) {
            $('#ga4-to-nutshell-mapping-container').html('<p class="error">Template not found!</p>');
            return;
        }

        // Prepare data for template
        var mappingData = {
            mappings: [],
            forms: allForms,
            users: nutshellUsers
        };

        // Add existing mappings
        for (var i = 0; i < formMappings.length; i++) {
            var mapping = formMappings[i];
            var formType = '';
            var formTypeLabel = '';
            var formExists = false;

            for (var j = 0; j < allForms.length; j++) {
                if (allForms[j].id == mapping.form_id) {
                    formType = allForms[j].type;
                    formTypeLabel = allForms[j].plugin;
                    formExists = true;
                    break;
                }
            }

            // If form no longer exists, show placeholder
            var formTitle = formExists
                ? allForms.find(f => f.id == mapping.form_id).title
                : 'Form ID ' + mapping.form_id + ' (missing)';

            mappingData.mappings.push({
                index: i,
                form_id: mapping.form_id,
                user_id: mapping.user_id,
                form_type: formType,
                form_type_label: formTypeLabel,
                forms: allForms.map(function (form) {
                    return {
                        id: form.id,
                        title: form.title,
                        plugin: form.plugin,
                        type: form.type,
                        selected: form.id == mapping.form_id
                    };
                }).concat(!formExists ? [{
                    id: mapping.form_id,
                    title: formTitle,
                    plugin: 'unknown',
                    type: '',
                    selected: true
                }] : []),
                users: nutshellUsers.map(function (user) {
                    return {
                        id: user.id,
                        name: user.name,
                        selected: user.id == mapping.user_id
                    };
                })
            });
        }


        // Render template
        var html = renderTemplate(template, mappingData);
        $('#ga4-to-nutshell-mapping-container').html(html);

        // Set up event handlers for the rendered content
        setUpMappingEventHandlers();
    }

    /**
     * Set up event handlers for form mappings
     */
    function setUpMappingEventHandlers() {
        // Form selection change handler - update form type hidden field
        $('.form-select').on('change', function () {
            var selectedOption = $(this).find('option:selected');
            var formType = selectedOption.data('form-type') || '';
            $(this).closest('tr').find('.form-type-input').val(formType);

            // Also update the displayed form type
            var formTypeLabel = selectedOption.text().match(/\((.*?)\)$/);
            formTypeLabel = formTypeLabel ? formTypeLabel[1] : '';
            $(this).closest('tr').find('td:eq(1)').text(formTypeLabel);
        });

        // Remove mapping click handler
        $('.ga4-to-nutshell-remove-mapping').on('click', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();

            // Re-index remaining rows
            $('.form-mapping-table tbody tr').each(function (index) {
                $(this).find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                    }
                });
            });
        });
    }

    /**
     * Add mapping button click handler
     */
    $('#ga4-to-nutshell-add-mapping').on('click', function () {
        var template = $('#form-mapping-template').html();

        if (!template) {
            alert('Template not found!');
            return;
        }

        // Prepare data for template
        var newIndex = $('.form-mapping-table tbody tr').length;
        var mappingData = {
            mappings: [{
                index: newIndex,
                form_id: '',
                user_id: '',
                form_type: '',
                form_type_label: '',
                forms: allForms.map(function (form) {
                    return {
                        id: form.id,
                        title: form.title,
                        plugin: form.plugin,
                        type: form.type,
                        selected: false
                    };
                }),
                users: nutshellUsers.map(function (user) {
                    return {
                        id: user.id,
                        name: user.name,
                        selected: false
                    };
                })
            }]
        };

        // Render template
        var html = renderTemplate(template, mappingData);

        // If table exists, add new row to it
        if ($('.form-mapping-table').length) {
            $('.form-mapping-table tbody').append($(html).find('tbody tr'));
        } else {
            // Otherwise create new table
            $('#ga4-to-nutshell-mapping-container').html(html);
        }

        // Set up event handlers for the new row
        setUpMappingEventHandlers();
    });

    /**
     * Test connection button click handler
     */
    $('#ga4-to-nutshell-test-connection').on('click', function () {
        var username = $('#nutshell_username').val();
        var apiKey = $('#nutshell_api_key').val();

        if (!username || !apiKey) {
            $('#ga4-to-nutshell-test-result').html('<p class="error">Please enter your Nutshell API credentials.</p>');
            return;
        }

        $('#ga4-to-nutshell-test-result').html('<p>Testing connection...</p>');

        $.ajax({
            url: ga4ToNutshell.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ga4_to_nutshell_test_connection',
                nonce: ga4ToNutshell.nonce,
                username: username,
                api_key: apiKey
            },
            success: function (response) {
                if (response.success) {
                    $('#ga4-to-nutshell-test-result').html('<p class="success">' + response.data.message + '</p>');
                } else {
                    $('#ga4-to-nutshell-test-result').html('<p class="error">Error: ' + response.data.message + '</p>');
                }
            },
            error: function () {
                $('#ga4-to-nutshell-test-result').html('<p class="error">Connection test failed. Please check your network connection.</p>');
            }
        });
    });

    /**
     * Field mapping form type selector change handler
     */
    $('#form-type-selector').on('change', function () {
        var formType = $(this).val();

        if (!formType) {
            $('#form-selector').html('<option value="">-- Select a Form --</option>');
            $('#field-mapping-content').html('<p>Please select a form type and form to see available fields.</p>');
            return;
        }

        // Filter forms by type
        var typeFilteredForms = allForms.filter(function (form) {
            return form.type === formType;
        });

        // Update form selector
        var formOptions = '<option value="">-- Select a Form --</option>';
        typeFilteredForms.forEach(function (form) {
            formOptions += '<option value="' + form.id + '" data-form-type="' + form.type + '">' + form.title + '</option>';
        });

        $('#form-selector').html(formOptions);
        $('#field-mapping-content').html('<p>Please select a form to see available fields.</p>');
    });

    /**
     * Field mapping form selector change handler
     */
    $('#form-selector').on('change', function () {
        var formId = $(this).val();
        var formType = $(this).find('option:selected').data('form-type') || '';

        if (!formId) {
            $('#field-mapping-content').html('<p>Please select a form to see available fields.</p>');
            return;
        }

        $('#field-mapping-content').html('<p>Loading form fields...</p>');

        $.ajax({
            url: ga4ToNutshell.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ga4_to_nutshell_get_form_fields',
                nonce: ga4ToNutshell.nonce,
                form_id: formId,
                form_type: formType
            },
            success: function (response) {
                if (response.success && response.data.fields) {
                    renderFieldMappings(formId, response.data.fields);
                } else {
                    $('#field-mapping-content').html('<p class="error">Error loading form fields: ' + (response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function () {
                $('#field-mapping-content').html('<p class="error">Error loading form fields.</p>');
            }
        });
    });

    /**
     * Render field mappings
     */
    function renderFieldMappings(formId, fields) {
        var template = $('#field-mapping-template').html();

        if (!template) {
            $('#field-mapping-content').html('<p class="error">Template not found!</p>');
            return;
        }

        // Get existing field mappings for this form
        var existingMappings = {};
        if (settings.field_mappings && settings.field_mappings[formId]) {
            existingMappings = settings.field_mappings[formId];
        }

        // Prepare data for template
        var mappingData = {
            formId: formId,
            formFields: fields.map(function (field) {
                // Check if this field is selected for any Nutshell field
                var selectedFor = null;
                for (var nutshellField in existingMappings) {
                    if (existingMappings[nutshellField] == field.id) {
                        selectedFor = nutshellField;
                        break;
                    }
                }

                return {
                    id: field.id,
                    key: field.key,
                    label: field.label,
                    type: field.type,
                    selected: function () {
                        return function (text, render) {
                            // This is a Mustache.js section function
                            // It will return 'selected="selected"' if this field is selected for the current Nutshell field
                            var nutshellField = text.trim();
                            return existingMappings[nutshellField] == field.id ? 'selected="selected"' : '';
                        };
                    }
                };
            })
        };

        // Render template
        var html = renderTemplate(template, mappingData);
        $('#field-mapping-content').html(html);
    }

    /**
     * Debug field mappings button click handler
     */
    $('#debug-field-mappings').on('click', function () {
        var formMappingsData = {};

        $('.field-mapping-table select').each(function () {
            var name = $(this).attr('name');
            var value = $(this).val();

            if (name && value) {
                formMappingsData[name] = value;
            }
        });

        $('#debug-output').html('<pre>' + JSON.stringify(formMappingsData, null, 2) + '</pre>');
    });

    /**
     * Simple template rendering function (basic Mustache-like syntax)
     */
    function renderTemplate(template, data) {
        function processSection(template, data, key) {
            var sectionRegex = new RegExp('{{#' + key + '}}([\\s\\S]*?){{/' + key + '}}', 'g');

            return template.replace(sectionRegex, function (match, sectionContent) {
                var itemValue = data[key];

                if (typeof itemValue === 'function') {
                    return itemValue.call(data);
                } else if (Array.isArray(itemValue)) {
                    var result = '';
                    for (var i = 0; i < itemValue.length; i++) {
                        result += renderTemplate(sectionContent, itemValue[i]);
                    }
                    return result;
                } else if (itemValue) {
                    return renderTemplate(sectionContent, data);
                } else {
                    return '';
                }
            });
        }

        // Process sections first
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                template = processSection(template, data, key);
            }
        }

        // Then process simple variables
        template = template.replace(/{{([^#^/][\w\.]*)}}/g, function (match, key) {
            var keys = key.trim().split('.');
            var value = data;

            for (var i = 0; i < keys.length; i++) {
                if (value && value.hasOwnProperty(keys[i])) {
                    value = value[keys[i]];
                } else {
                    value = '';
                    break;
                }
            }

            if (typeof value === 'function') {
                return value.call(data);
            } else {
                return value || '';
            }
        });

        return template;
    }

    // Initialize form mappings if we have API credentials
    if ($('#nutshell_username').val() && $('#nutshell_api_key').val()) {
        loadNutshellUsers();
    } else {
        $('#ga4-to-nutshell-mapping-container').html('<p>Please enter your Nutshell API credentials and save to configure form mappings.</p>');
    }
});

jQuery(document).ready(function ($) {
    const form = $('#ga4-nutshell-settings-form');
    if (!form.length) return;

    form.on('submit', function () {
        // Serialize all current field mappings
        const fieldMappings = {};
        $('.field-mapping-row').each(function () {
            const formId = $(this).find('.form-id').val();
            const fieldKey = $(this).find('.field-key').val();
            const mappedTo = $(this).find('.mapped-to').val();
            if (formId && fieldKey && mappedTo) {
                if (!fieldMappings[formId]) fieldMappings[formId] = {};
                fieldMappings[formId][fieldKey] = mappedTo;
            }
        });

        const userMappings = {};
        $('.user-mapping-row').each(function () {
            const formId = $(this).find('.user-form-id').val();
            const userId = $(this).find('.user-id').val();
            if (formId && userId) {
                userMappings[formId] = userId;
            }
        });

        // Remove any previous injected fields
        form.find('input[name="ga4_nutshell_field_mappings"]').remove();
        form.find('input[name="ga4_nutshell_user_mapping"]').remove();

        // Inject hidden fields
        const fieldInput = $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'ga4_nutshell_field_mappings')
            .val(JSON.stringify(fieldMappings));
        const userInput = $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'ga4_nutshell_user_mapping')
            .val(JSON.stringify(userMappings));
        form.append(fieldInput, userInput);
    });
});