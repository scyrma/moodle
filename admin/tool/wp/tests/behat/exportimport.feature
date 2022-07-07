@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import workflows
  As a tenant admin
  I should be able to navigate through export and import workflows

  Scenario: Checking previous and next buttons in export wizard
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    Then I should see "Exports" in the "h2" "css_element"
    And I press "Export"
    And I should see "Step 1"
    And "Previous" "button" should not exist
    And I set the field "Organisation structure frameworks" to "1"
    And I press "Next"
    And I should see "Step 2"
    And the field "Select all department and position frameworks" matches value "1"
    And I set the field "Select all department frameworks" to "1"
    And I press "Next"
    And I should see "Step 3"
    And "Next" "button" should not exist
    And I press "Previous"
    And I should see "Step 2"
    And the field "Select all department frameworks" matches value "1"
    And I press "Previous"
    And I should see "Step 1"
    And the field "Organisation structure frameworks" matches value "1"
    And I press "Next"
    And I should see "Step 2"
    And I press "Next"
    And I should see "Step 3"
    And I press "Export"
    And I press "Proceed"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    Then I should see "Exports" in the "h2" "css_element"
    And I press "View export" action in the "Organisation structure frameworks" report row
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I reload the page
    And I should see "Success" in the "Status" "table_row"
    And I log out

  Scenario: Checking cancel button in export wizard
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    Then I should see "Exports" in the "h2" "css_element"
    And I press "Export"
    And I should see "Step 1"
    And I press "Cancel"
    And I should see "Exports" in the "h2" "css_element"
    And I press "Export"
    And I set the field "Organisation structure frameworks" to "1"
    And I press "Next"
    And I should see "Step 2"
    And I press "Cancel"
    And I should see "Exports" in the "h2" "css_element"
    And I press "Export"
    And I set the field "Organisation structure jobs" to "1"
    And I press "Next"
    And I press "Next"
    And I should see "Step 3"
    And I press "Cancel"
    And I should see "Exports" in the "h2" "css_element"
    And I log out

  @_file_upload
  Scenario: Checking previous and next buttons in import wizard without conflicts
    Given "2" tenants exist with "4" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I follow "Imports"
    Then I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I should see "Step 1"
    And I upload "admin/tool/organisation/tests/fixtures/orgstructure.zip" file to "Upload a file" filemanager
    And "Previous" "button" should not exist
    And I press "Next"
    And I should see "Step 2"
    And I press "Next"
    And I should see "Step 3"
    And the field "Select all department and position frameworks" matches value "1"
    And I set the field "Select all position frameworks" to "1"
    And I press "Next"
    And I should see "Step 5"
    And "Next" "button" should not exist
    And I press "Previous"
    And I should see "Step 3"
    And the field "Select all position frameworks" matches value "1"
    And I press "Previous"
    And I should see "Step 2"
    And I press "Next"
    And I should see "Step 3"
    And the field "Select all position frameworks" matches value "1"
    And I press "Next"
    And I should see "Step 5"
    And I press "Import"
    And I press "Proceed"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    Then I should see "Imports" in the "h2" "css_element"
    And I press "View import" action in the "Organisation structure frameworks" report row
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I reload the page
    And I should see "Success" in the "Status" "table_row"
    And I log out

  @_file_upload
  Scenario: Checking previous and next buttons in import wizard with conflicts
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      | idnumber |
      | Tenant1 | Framework   |             |          |
      | Tenant1 | Department1 | Framework   | spain    |
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I follow "Imports"
    Then I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I should see "Step 1"
    And I upload "admin/tool/organisation/tests/fixtures/orgstructure.zip" file to "Upload a file" filemanager
    And "Previous" "button" should not exist
    And I press "Next"
    And I should see "Step 2"
    And I press "Next"
    And I should see "Step 3"
    And the field "Select all department and position frameworks" matches value "1"
    And I set the field "Select all department frameworks" to "1"
    And I press "Next"
    And I should see "Step 4"
    And I press "Next"
    And I should see "Step 5"
    And "Next" "button" should not exist
    And I press "Previous"
    And I should see "Step 4"
    And I press "Previous"
    And I should see "Step 3"
    And the field "Select all department frameworks" matches value "1"
    And I press "Previous"
    And I should see "Step 2"
    And I press "Next"
    And I should see "Step 3"
    And the field "Select all department frameworks" matches value "1"
    And I press "Next"
    And I should see "Step 4"
    And I press "Next"
    And I should see "Step 5"
    And I press "Import"
    And I press "Proceed"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    Then I should see "Imports" in the "h2" "css_element"
    And I press "View import" action in the "Organisation structure frameworks" report row
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I reload the page
    And I should see "Success" in the "Status" "table_row"
    And I log out

  @_file_upload
  Scenario: Checking cancel button in import wizard with conflicts
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      | idnumber |
      | Tenant1 | Framework   |             |          |
      | Tenant1 | Department1 | Framework   | dept     |
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I follow "Imports"
    Then I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I should see "Step 1"
    And I press "Cancel"
    And I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/fullexport.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Step 2"
    And I press "Cancel"
    And I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/fullexport.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I press "Next"
    And I should see "Step 3"
    And I press "Cancel"
    And I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/fullexport.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And I should see "Step 4"
    And I press "Cancel"
    And I should see "Imports" in the "h2" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/fullexport.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And I should see "Step 5"
    And I press "Cancel"
    And I should see "Imports" in the "h2" "css_element"
    And I log out

  Scenario: Checking export button is disabled in export wizard with no instances to export
    Given "2" tenants exist with "4" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I set the field "Organisation structure frameworks" to "1"
    And I press "Next"
    And I set the field "Select all department frameworks" to "1"
    And I press "Next"
    And the "Export" "button" should be disabled

  Scenario: A user with no available exporters cannot create an export
    Given "1" tenants exist with "2" users and "0" courses in each
    And the following "permission overrides" exist:
      | capability              | permission | role | contextlevel | reference |
      | tool/wp:useexportimport | Allow      | user | System       |           |
    When I log in as "user11"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    Then I should see "There are no available exporters for you to use"

  @_file_upload
  Scenario: Checking import button is disabled in import wizard with no instances to import
    Given "2" tenants exist with "4" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I follow "Imports"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/fullexport.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And the "Import" "button" should be disabled

  Scenario: Deleting workplace export
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    When I log in as "tenantadmin1"
    And I perform a new export with these options:
      | Step 1 | Organisation structure frameworks | 1 |
      | Step 2 | Select all department frameworks  | 1 |
    Then I should see "Success" in the "Organisation structure frameworks" "table_row"
    And I press "Delete" action in the "Organisation structure frameworks" report row
    Then I should see "Are you sure"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    And I should see "Nothing to display"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @_file_upload
  Scenario: Deleting workplace import
    Given "2" tenants exist with "4" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I perform a new import with these options:
      | Step 1 | Upload a file                    | admin/tool/organisation/tests/fixtures/orgstructure.zip |
      | Step 3 | Select all department frameworks | 1                                                       |
    Then I should see "Success" in the "Tenantadmin 1" "table_row"
    And I press "Delete" action in the "Tenantadmin 1" report row
    Then I should see "Are you sure"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    And I should see "Nothing to display"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @_file_upload
  Scenario: Import wizard when importing into a different tenant
    Given "2" tenants exist with "4" users and "0" courses in each
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I follow "Imports"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/orgstructure.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Step 2"
    And the field "Import into the current tenant" matches value "1"
    And I set the field "Select tenant" to "1"
    And I set the field "Choose tenant" to "Tenant2"
    And I press "Next"
    And I should see "Step 3"
    And I press "Previous"
    And the field "Import into the current tenant" matches value "0"
    And the field "Select tenant" matches value "1"
    And the field "Choose tenant" matches value "Tenant2"
    And I press "Next"
    And I press "Next"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I should see "Tenant2" in the "Organisation structure frameworks" "table_row"
    And I log out

  Scenario: Creating an import from exportfile
    Given "1" tenants exist with "2" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    When I log in as "tenantadmin1"
    And I perform a new export with these options:
      | Step 1 | Organisation structure frameworks | 1 |
      | Step 2 | Select all department frameworks | 1 |
    Then I press "New import from this file" action in the "Tenantadmin 1" report row
    And I press "Next"
    And I press "Next"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I should see "Scheduled" in the "Organisation structure frameworks" "table_row"

  Scenario: Showing export report
    Given "1" tenants exist with "2" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    When I log in as "tenantadmin1"
    And I perform a new export with these options:
      | Step 1 | Organisation structure frameworks | 1 |
      | Step 2 | Select all department frameworks  | 1 |
    Then I press "View export" action in the "Tenantadmin 1" report row
    And I should see "Success" in the "Status" "table_row"
    And I should see "Date"
    And I should see "Tenantadmin 1" in the "Created by" "table_row"
    And I should see "Organisation structure frameworks" in the "Exporter" "table_row"
    And I should see "Exported"
    And I should see "Version"
    # We don't need to test the actual size, just that something is printed.
    And I should see "KB" in the "Size" "table_row"
    And I should see "New import from this file"
    And following "Download file" should download between "1536" and "1740" bytes

  Scenario: Show export report with manage export import capability
    Given "1" tenants exist with "3" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    And the following "roles" exist:
      | shortname        | name               | archetype |
      | managemigrations | Migrations Manager |           |
      | usemigrations    | Migrations User    |           |
    And the following "role assigns" exist:
      | user   | role             | contextlevel | reference |
      | user11 | managemigrations | System       |           |
      | user12 | usemigrations    | System       |           |
    And the following "permission overrides" exist:
      | capability                          | permission | role             | contextlevel | reference |
      | tool/wp:manageexportimport          | Allow      | managemigrations | System       |           |
      | tool/wp:useexportimport             | Allow      | managemigrations | System       |           |
      | tool/wp:useexportimport             | Allow      | usemigrations    | System       |           |
      | tool/organisation:managepositions   | Allow      | usemigrations    | System       |           |
      | tool/organisation:managedepartments | Allow      | usemigrations    | System       |           |
    When I log in as "user12"
    And I perform a new export with these options:
      | Step 1 | Organisation structure frameworks | 1 |
      | Step 2 | Select all department frameworks  | 1 |
    Then I press "View export" action in the "User 12" report row
    And I should see "Success" in the "Status" "table_row"
    And I log out
    And I log in as "user11"
    And I navigate to "Migration" in workplace launcher
    Then "Success" "text" should exist in the "User 12" "table_row"
    And I log out

  @_file_upload
  Scenario: Showing import report
    Given "1" tenants exist with "2" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I perform a new import with these options:
      | Step 1 | Upload a file                    | admin/tool/organisation/tests/fixtures/orgstructure.zip |
      | Step 3 | Select all department frameworks | 1                                                       |
    Then I press "View import" action in the "Tenantadmin 1" report row
    And I should see "Success" in the "Status" "table_row"
    And I should see "Date"
    And I should see "Tenantadmin 1" in the "Created by" "table_row"
    And I should see "Organisation structure frameworks" in the "Importer" "table_row"
    And I should see "Imported"
    And I should see "Version"
    And I should see "5.0" in the "Size" "table_row"
    And I click on "[data-action=show-log]" "css_element"
    And I should see "Created new department"
