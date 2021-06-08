@tool @tool_tenant @moodleworkplace @javascript
Feature: Export and import tenants
  As a site administration
  I want to export and import tenants

  Scenario: Export single tenant
    Given the following "tool_tenant > tenants" exist:
      | name       |
      | Tenant one |
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I click on "Exports" "link" in the "[role=tablist]" "css_element"
    And I press "Export"
    And I click on "Tenants" "radio"
    And I press "Next"
    And I click on "Select tenants manually..." "radio"
    And I press "Next"
    And I should see "Required" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I open the autocomplete suggestions list in the "Instances" "fieldset"
    And "Default tenant" "autocomplete_suggestions" should exist
    And I press the escape key
    And I set the field "Tenants" to "Tenant one"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Tenant one" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button"
    And I click on "View export" "link" in the "Tenants" "table_row"
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Tenant one" in the "[data-region=\"exportimport-instances\"]" "css_element"

  Scenario: Export current tenant
    Given the following "tool_tenant > tenants" exist:
      | name       |
      | Tenant one |
    When I log in as "admin"
    And I switch to tenant "Tenant one"
    And I navigate to "Migration" in workplace launcher
    And I click on "Exports" "link" in the "[role=tablist]" "css_element"
    And I press "Export"
    And I click on "Tenants" "radio"
    And I set the field "Create export from" in the "//div[contains(@data-groupname,'tenants')]" "xpath_element" to "Current tenant"
    And I press "Next"
    And "Select tenants manually..." "field" should not be visible
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Tenant one" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I should see "Tenant one" in the "Tenant" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button"
    And I click on "View export" "link" in the "Tenants" "table_row"
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Tenant one" in the "[data-region=\"exportimport-instances\"]" "css_element"

  @_file_upload
  Scenario: Import single tenant
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/tenant/tests/fixtures/tenants-export-users.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Tenants (2)" in the "Content" "table_row"
    And I should see "Users (4)" in the "Content" "table_row"
    And I press "Next"
    And the "Users" "checkbox" should be enabled
    And the field "Users" matches value "1"
    # If merging into existing tenant, ensure we are only importing a single tenant.
    And I click on "Merge into existing tenant..." "radio"
    And I click on "Select all tenants" "radio"
    And I press "Next"
    And I should see "Only a single instance can be selected in order to merge into an existing tenant" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I click on "Select tenants manually..." "radio"
    And I set the field "Tenants" to "Tenant one, Tenant two"
    And I press "Next"
    And I should see "Only a single instance can be selected in order to merge into an existing tenant" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    # Remove "Tenant one" and proceed.
    And I click on "Tenant one" "autocomplete_selection"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Tenant two" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button"
    And I click on "View import" "link" in the "Tenants" "table_row"
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Tenant two" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I click on "[data-action=\"show-log\"]" "css_element"
    And I should see "Imported tenant 'Tenant two'"
    And I should see "Created user 'User Three'"
    And I should see "Created user 'User Four'"
