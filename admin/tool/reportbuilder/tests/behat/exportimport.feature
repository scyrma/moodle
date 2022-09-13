@tool @tool_reportbuilder @moodleworkplace @javascript
Feature: Export and import custom reports
  As a tenant administrator
  I want to export and import custom reports

  Background:
    Given "2" tenants exist with "1" users and "0" courses in each
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant2 | tool_reportbuilder\test\mock_report |

  Scenario: Export a custom report
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Custom reports" "radio"
    And I press "Next"
    And I click on "Export specific custom reports..." "radio"
    And I press "Next"
    And I should see "Required" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I open the autocomplete suggestions list
    And "Report2" "autocomplete_suggestions" should not exist
    And I click on "Report1" item in the autocomplete list
    And I press the escape key
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Report1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View export" action in the "Custom reports" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Report1" in the "[data-region=\"exportimport-instances\"]" "css_element"

  @_file_upload
  Scenario: Import a custom report into current tenant
    # Create entities to match those referred to in the test fixture.
    Given the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email            |
      | Tenant1 | paul     | Paul      | Test     | paul@example.com |
    And the following departments exist in organisation structure:
      | tenant  | name       | parent    |
      | Tenant1 | Framework  |           |
      | Tenant1 | Sub-Garden | Framework |
    And the following positions exist in organisation structure:
      | tenant  | name              | parent    |
      | Tenant1 | Framework         |           |
      | Tenant1 | Office manager    | Framework |
      | Tenant1 | Franchise manager | Framework |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I navigate to "Import" in current page administration
    And I press "Import"
    And I upload "admin/tool/reportbuilder/tests/fixtures/custom-reports-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Custom reports (2)" in the "Content" "table_row"
    And I should see "Audiences (2)" in the "Content" "table_row"
    And I should see "Schedules (2)" in the "Content" "table_row"
    And I press "Next"
    And the "Audiences" "checkbox" should be enabled
    And the field "Audiences" matches value "1"
    And the "Schedules" "checkbox" should be enabled
    And the field "Schedules" matches value "1"
    And I click on "Import specific custom reports..." "radio"
    And I press "Next"
    And I should see "Required" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I open the autocomplete suggestions list
    And "UK Users" "autocomplete_suggestions" should exist
    And I click on "Course completion" item in the autocomplete list
    And I press the escape key
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Course completion" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View import" action in the "Custom reports" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Course completion" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I click on "[data-action=\"show-log\"]" "css_element"
    And I should see "Created new report 'Course completion' with 4 columns, 0 conditions and 2 filters"
    And I should see "Audience record imported"
    And I should see "Schedule record imported"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And the following should exist in the "report-table" table:
      | Report name       | Plugin         | Created             | Last modified       | Modified by   |
      | Course completion | Report builder | ##today##%d/%m/%y## | ##today##%d/%m/%y## | Tenantadmin 1 |

  @_file_upload
  Scenario: Import a custom report into different tenant
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file                     | admin/tool/reportbuilder/tests/fixtures/custom-reports-export.zip |
      | Step 2 | Select tenant                     | 1                                                                 |
      | Step 2 | Choose tenant                     | Tenant2                                                           |
      | Step 3 | Import specific custom reports... | 1                                                                 |
      | Step 3 | Custom reports                    | Course completion                                                 |
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    Then the following should not exist in the "report-table" table:
      | Report name       | Plugin         |
      | Course completion | Report builder |
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant2" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    And the following should exist in the "report-table" table:
      | Report name       | Plugin         | Created             | Last modified       | Modified by |
      | Course completion | Report builder | ##today##%d/%m/%y## | ##today##%d/%m/%y## | Admin User  |

  @_file_upload
  Scenario: Import a custom report with mapped entities
    Given the following "users" exist:
      | username | firstname | lastname |
      | paul     | Paul      | James    |
    And the following users allocations to tenants exist:
      | user | tenant  |
      | paul | Tenant1 |
    And the following departments exist in organisation structure:
      | tenant  | name       | parent    |
      | Tenant1 | Framework  |           |
      | Tenant1 | Sub-Garden | Framework |
    And the following positions exist in organisation structure:
      | tenant  | name              | parent    | globalmanager | departmentmanager |
      | Tenant1 | Framework         |           | 0             | 0                 |
      | Tenant1 | Franchise manager | Framework | 1             | 1                 |
      | Tenant1 | Office manager    | Framework | 0             | 1                 |
    When I log in as "tenantadmin1"
    And I perform a new import with these options:
      | Step 1 | Upload a file                     | admin/tool/reportbuilder/tests/fixtures/custom-reports-export.zip |
      | Step 3 | Import specific custom reports... | 1                                                                 |
      | Step 3 | Custom reports                    | Course completion                                                 |
    And I press "View import" action in the "Custom reports" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Course completion" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I click on "[data-action=\"show-log\"]" "css_element"
    And I should see "Created new report 'Course completion' with 4 columns, 0 conditions and 2 filters"
    And I should see "Audience record imported"
    And I should see "Schedule record imported"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And the following should exist in the "report-table" table:
      | Report name       | Plugin         | Created             | Last modified       | Modified by   |
      | Course completion | Report builder | ##today##%d/%m/%y## | ##today##%d/%m/%y## | Tenantadmin 1 |
