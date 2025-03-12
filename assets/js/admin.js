/**
 * Admin JavaScript for GA4 to Nutshell Integration
 */
(function ($) {
  "use strict";

  // Store retrieved data
  let nutshellUsers = [];
  let ninjaForms = [];

  $(document).ready(function () {
    // Initialize the mapping UI
    initMappingUI();

    // Add new mapping row
    $("#ga4-to-nutshell-add-mapping").on("click", function (e) {
      e.preventDefault();
      addMappingRow();
    });

    // Test connection button
    $("#ga4-to-nutshell-test-connection").on("click", function (e) {
      e.preventDefault();
      testNutshellConnection();
    });

    // Remove mapping row
    $(document).on("click", ".ga4-to-nutshell-remove-mapping", function (e) {
      e.preventDefault();
      $(this).closest(".mapping-row").remove();
    });

    // Handle form selection change for field mapping
    $("#ninja-form-selector").on("change", function () {
      const formId = $(this).val();
      if (formId) {
        loadFormFields(formId);
      } else {
        $("#field-mapping-content").html(
          "<p>" + "Please select a form to configure field mappings." + "</p>"
        );
      }
    });
    // Add the event handler in admin.js
    $(document).on("click", "#debug-field-mappings", function (e) {
      e.preventDefault();
      debugFieldMappings();

      // Show debug info in the page
      $("#debug-output").text(JSON.stringify(ga4ToNutshell.settings, null, 2));
    });
  });

  /**
   * Initialize the form-to-user mapping UI
   */
  function initMappingUI() {
    const container = $("#ga4-to-nutshell-mapping-container");

    // Clear the container
    container.empty();

    // Show loading indicator
    container.html("<p>Loading data...</p>");

    // Load Nutshell users
    loadNutshellUsers()
      .then(function () {
        // Load Ninja Forms
        return loadNinjaForms();
      })
      .then(function () {
        // Initialize the mapping table
        initMappingTable();
      })
      .catch(function (error) {
        container.html('<p class="error">Error: ' + error.message + "</p>");
      });

    // Load forms for the selector
    loadNinjaForms()
      .then(function () {
        // Populate the form selector
        const formSelector = $("#ninja-form-selector");
        formSelector.empty();

        // Add default option
        $("<option>")
          .attr("value", "")
          .text("-- Select a Form --")
          .appendTo(formSelector);

        // Add each form
        ninjaForms.forEach(function (form) {
          $("<option>")
            .attr("value", form.id)
            .text(form.title)
            .appendTo(formSelector);
        });

        // Check if we have a form selected by default
        const formId = formSelector.val();
        if (formId) {
          loadFormFields(formId);
        } else {
          $("#field-mapping-content").html(
            "<p>" + "Please select a form to configure field mappings." + "</p>"
          );
        }
      })
      .catch(function (error) {
        $("#field-mapping-content").html(
          '<p class="error">' + "Error: " + error.message + "</p>"
        );
      });
  }
  /**
   * Update the loadFormFields function in admin.js to clear existing mappings with placeholder values
   */
  function loadFormFields(formId) {
    $("#field-mapping-content").html("<p>" + "Loading form fields..." + "</p>");

    // Check for existing mappings with placeholder values
    if (
      ga4ToNutshell.settings &&
      ga4ToNutshell.settings.field_mappings &&
      ga4ToNutshell.settings.field_mappings[formId]
    ) {
      const mappings = ga4ToNutshell.settings.field_mappings[formId];

      // Check if any mapping contains placeholder values like {{id}}
      let hasPlaceholders = false;
      for (const key in mappings) {
        if (mappings[key] && mappings[key].includes("{{")) {
          hasPlaceholders = true;
          console.log(
            "Found placeholder in field mapping:",
            key,
            mappings[key]
          );

          // Reset the mapping
          mappings[key] = "";
        }
      }

      if (hasPlaceholders) {
        console.log("Resetting placeholder mappings for form:", formId);
      }
    }

    // Make AJAX request to get form fields
    $.ajax({
      url: ga4ToNutshell.ajaxUrl,
      type: "POST",
      data: {
        action: "ga4_to_nutshell_get_form_fields",
        nonce: ga4ToNutshell.nonce,
        form_id: formId,
      },
      success: function (response) {
        console.log("Form fields response:", response);

        if (response.success) {
          renderFieldMapping(formId, response.data.fields);
        } else {
          $("#field-mapping-content").html(
            '<p class="error">' + "Error: " + response.data.message + "</p>"
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", xhr, status, error);
        $("#field-mapping-content").html(
          '<p class="error">' +
            "Failed to load form fields. Check the console for details." +
            "</p>"
        );
      },
    });
  }
  /**
   * Update the renderFieldMapping function in admin.js
   */
  function renderFieldMapping(formId, formFields) {
    // Get the container element
    const container = $("#field-mapping-content");

    // Clear the container
    container.empty();

    // Create table
    const table = $("<table>").addClass("widefat field-mapping-table");
    const thead = $("<thead>").appendTo(table);
    const headerRow = $("<tr>").appendTo(thead);

    $("<th>").text("Nutshell Field").appendTo(headerRow);
    $("<th>").text("Ninja Form Field").appendTo(headerRow);

    const tbody = $("<tbody>").appendTo(table);

    // Define the Nutshell fields we support mapping to
    const nutshellFields = {
      email: "Email",
      name: "Name",
      phone: "Phone",
      company: "Company",
      address: "Address",
      country: "Country",
    };

    // Add a row for each Nutshell field
    for (const [fieldKey, fieldLabel] of Object.entries(nutshellFields)) {
      const row = $("<tr>").appendTo(tbody);

      // Nutshell field label
      $("<td>").text(fieldLabel).appendTo(row);

      // Ninja Form field dropdown
      const cell = $("<td>").appendTo(row);
      const select = $("<select>")
        .attr(
          "name",
          `ga4_to_nutshell_settings[field_mappings][${formId}][${fieldKey}]`
        )
        .addClass("widefat")
        .appendTo(cell);

      // Add default option
      $("<option>").attr("value", "").text("-- Not Mapped --").appendTo(select);

      // Add each form field as an option
      formFields.forEach(function (field) {
        const option = $("<option>").attr("value", field.id).text(field.label);

        // Check if this field is already mapped
        if (
          ga4ToNutshell.settings.field_mappings &&
          ga4ToNutshell.settings.field_mappings[formId] &&
          ga4ToNutshell.settings.field_mappings[formId][fieldKey] === field.id
        ) {
          option.attr("selected", "selected");
        }

        option.appendTo(select);
      });
    }

    // Add the table to the container
    container.append(table);

    // Add a helper message
    $("<p>")
      .addClass("description")
      .text(
        "Map each Nutshell CRM field to the corresponding field in your Ninja Form."
      )
      .appendTo(container);
  }
  /**
   * Load Nutshell users
   */
  function loadNutshellUsers() {
    return new Promise(function (resolve, reject) {
      const username = $(
        'input[name="ga4_to_nutshell_settings[nutshell_username]"]'
      ).val();
      const apiKey = $(
        'input[name="ga4_to_nutshell_settings[nutshell_api_key]"]'
      ).val();

      if (!username || !apiKey) {
        reject(new Error("Please enter Nutshell API credentials first"));
        return;
      }

      $.ajax({
        url: ga4ToNutshell.ajaxUrl,
        type: "POST",
        data: {
          action: "ga4_to_nutshell_get_users",
          nonce: ga4ToNutshell.nonce,
          username: username,
          api_key: apiKey,
        },
        success: function (response) {
          if (response.success) {
            nutshellUsers = response.data.users;
            resolve();
          } else {
            reject(new Error(response.data.message));
          }
        },
        error: function () {
          reject(new Error("Failed to load Nutshell users"));
        },
      });
    });
  }

  /**
   * Load Ninja Forms
   */
  function loadNinjaForms() {
    return new Promise(function (resolve, reject) {
      $.ajax({
        url: ga4ToNutshell.ajaxUrl,
        type: "POST",
        data: {
          action: "ga4_to_nutshell_get_ninja_forms",
          nonce: ga4ToNutshell.nonce,
        },
        success: function (response) {
          if (response.success) {
            ninjaForms = response.data.forms;
            resolve();
          } else {
            reject(new Error(response.data.message));
          }
        },
        error: function () {
          reject(new Error("Failed to load Ninja Forms"));
        },
      });
    });
  }

  /**
   * Initialize the mapping table
   */
  function initMappingTable() {
    const container = $("#ga4-to-nutshell-mapping-container");

    // Clear the container
    container.empty();

    // Create table header
    const table = $('<table class="wp-list-table widefat fixed striped">');
    const thead = $("<thead>").appendTo(table);
    const headerRow = $("<tr>").appendTo(thead);

    $("<th>").text("Ninja Form").appendTo(headerRow);
    $("<th>").text("Nutshell User").appendTo(headerRow);
    $("<th>").text("Actions").appendTo(headerRow);

    // Create table body
    const tbody = $("<tbody>").appendTo(table);

    // Add the table to the container
    container.append(table);

    // Add existing mappings
    let hasMappings = false;

    // For debugging, console log the settings
    console.log("GA4 to Nutshell Settings:", ga4ToNutshell.settings);

    if (ga4ToNutshell.settings && ga4ToNutshell.settings.form_user_mappings) {
      const mappings = ga4ToNutshell.settings.form_user_mappings;
      console.log("Found mappings:", mappings);

      if (Array.isArray(mappings) && mappings.length > 0) {
        mappings.forEach(function (mapping) {
          if (mapping.form_id && mapping.user_id) {
            addMappingRow(mapping.form_id, mapping.user_id, tbody);
            hasMappings = true;
          }
        });
      }
    }

    // If no mappings exist, add an empty row
    if (!hasMappings) {
      addMappingRow(null, null, tbody);
    }
  }

  /**
   * Add a new mapping row
   */
  function addMappingRow(formId, userId, tbody) {
    // If tbody not provided, use the existing one
    if (!tbody) {
      tbody = $("#ga4-to-nutshell-mapping-container table tbody");
    }

    const row = $('<tr class="mapping-row">');

    // Form select
    const formCell = $("<td>").appendTo(row);
    const formSelect = $("<select>")
      .attr({
        name: "ga4_to_nutshell_settings[form_user_mappings][form_id][]", // Changed this line
        class: "form-select",
      })
      .appendTo(formCell);

    $("<option>")
      .attr("value", "")
      .text("-- Select Form --")
      .appendTo(formSelect);

    ninjaForms.forEach(function (form) {
      const option = $("<option>").attr("value", form.id).text(form.title);
      if (formId && formId == form.id) {
        option.attr("selected", "selected");
      }
      option.appendTo(formSelect);
    });

    // User select
    const userCell = $("<td>").appendTo(row);
    const userSelect = $("<select>")
      .attr({
        name: "ga4_to_nutshell_settings[form_user_mappings][user_id][]", // Changed this line
        class: "user-select",
      })
      .appendTo(userCell);

    $("<option>")
      .attr("value", "")
      .text("-- Select User --")
      .appendTo(userSelect);

    nutshellUsers.forEach(function (user) {
      const option = $("<option>").attr("value", user.id).text(user.name);
      if (userId && userId == user.id) {
        option.attr("selected", "selected");
      }
      option.appendTo(userSelect);
    });

    // Action buttons
    const actionCell = $("<td>").appendTo(row);
    $("<button>")
      .attr({
        type: "button",
        class: "button ga4-to-nutshell-remove-mapping",
      })
      .text("Remove")
      .appendTo(actionCell);

    // Add the row to the tbody
    tbody.append(row);
  }

  /**
   * Test Nutshell API connection
   */
  function testNutshellConnection() {
    const resultContainer = $("#ga4-to-nutshell-test-result");
    const username = $(
      'input[name="ga4_to_nutshell_settings[nutshell_username]"]'
    ).val();
    const apiKey = $(
      'input[name="ga4_to_nutshell_settings[nutshell_api_key]"]'
    ).val();

    if (!username || !apiKey) {
      resultContainer.html(
        '<p class="error">Please enter Nutshell API credentials first</p>'
      );
      return;
    }

    // Show loading message
    resultContainer.html("<p>Testing connection...</p>");

    $.ajax({
      url: ga4ToNutshell.ajaxUrl,
      type: "POST",
      data: {
        action: "ga4_to_nutshell_test_connection",
        nonce: ga4ToNutshell.nonce,
        username: username,
        api_key: apiKey,
      },
      success: function (response) {
        if (response.success) {
          resultContainer.html(
            '<p class="success">' + response.data.message + "</p>"
          );
        } else {
          resultContainer.html(
            '<p class="error">Error: ' + response.data.message + "</p>"
          );
        }
      },
      error: function () {
        resultContainer.html(
          '<p class="error">Connection failed. Please check your network connection.</p>'
        );
      },
    });
  }
  /**
   * Add this debug function to admin.js to help see what's happening
   */
  function debugFieldMappings() {
    console.log("Current settings:", ga4ToNutshell.settings);

    if (ga4ToNutshell.settings && ga4ToNutshell.settings.field_mappings) {
      console.log("Field mappings:", ga4ToNutshell.settings.field_mappings);
    } else {
      console.log("No field mappings found in settings");
    }

    // Show what's currently in the form
    const formData = {};
    $('select[name^="ga4_to_nutshell_settings[field_mappings]"]').each(
      function () {
        formData[$(this).attr("name")] = $(this).val();
      }
    );

    console.log("Current form field mapping values:", formData);
  }
})(jQuery);
