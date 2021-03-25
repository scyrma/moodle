@tool @tool_program @moodleworkplace @javascript
Feature: Export and import programs
  As a tenant administrator
  I want to export and import programs

  Background:
    Given "2" tenants exist with "1" users and "2" courses in each

  Scenario: Export one program
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant  | idnumber  |
      | Program1 | 0        | Tenant1 | idnumber1 |
      | Program2 | 0        | Tenant1 | idnumber2 |
      | Program3 | 0        | Tenant2 |           |
    When I log in as "tenantadmin1"
    And I perform a new export with these options:
      | Step 1 | Programs           | 1        |
      | Step 2 | Select manually... | 1        |
      | Step 2 | Programs           | Program1 |
    And I click on "View export" "link" in the "Programs" "table_row"
    Then I should see "Success"
    And I should see "Instances (1)"
    And I should see "Program1"
    And I should not see "Program2"
    And I should not see "Program3"
    And I press "New import from this file"
    And I should see "Programs (1)" in the "Content" "table_row"
    And I log out

  Scenario: Validate "Include course content" checkbox availability
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Programs" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be enabled
    And I press "Cancel"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Programs" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be disabled
    And I press "Cancel"
    And I log out
    When the following config values are set as admin:
      | coursecontentbackup | 1 | tool_wp |
    And I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Programs" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be enabled
    And I press "Cancel"

  @_file_upload
  Scenario: Import a program into current tenant
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I perform a new import with these options:
      | Step 1 | Upload a file                        | admin/tool/program/tests/fixtures/program-export.zip |
      | Step 3 | Select manually...                   | 1                                                    |
      | Step 3 | Programs                             | Program 1A                                           |
      | Step 3 | Course backups excluding user data   | 1                                                    |
    And I click on "View import" "link" in the "Programs" "table_row"
    Then I should see "Instances (1)"
    And I should see "Program 1A"
    And I click on "Log" "link" in the "[data-region=newimport]" "css_element"
    And I should see "Created new program 'Program 1A' with 2 sets, 3 courses"
    And I should see "Created new course 'Course 1a"
    And I should see "Created new course 'Course 2a"
    And I should see "Created new course 'Course 3a"
    And I navigate to "Programs" in workplace launcher
    And I should see "Program 1A"
    And I navigate to "Courses" in workplace launcher
    And I should see "Course 1a"
    And I should see "Course 2a"
    And I should see "Course 3a"

  @_file_upload
  Scenario: Import a program into a different tenant
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file       | admin/tool/program/tests/fixtures/program-export.zip |
      | Step 2 | Select tenant       | 1                                                    |
      | Step 2 | Choose tenant       | Tenant2                                              |
      | Step 3 | Select manually...  | 1                                                    |
      | Step 3 | Programs            | Program 1A                                           |
      | Step 4 | Create empty course | 1                                                    |
    And I navigate to "Programs" in workplace launcher
    And I should not see "Program 1A"
    And I switch to tenant "Tenant2"
    And I should see "Program 1A"
    And I log out

  Scenario: Export a program and check form validation
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant  | idnumber  |
      | Program1 | 0        | Tenant1 | idnumber1 |
      | Program3 | 0        | Tenant2 |           |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Programs" "radio"
    And I press "Next"
    And I should see "Select all active programs"
    And I should see "Select all active and archived programs"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one program"
    And I open the autocomplete suggestions list
    And "Program3" "autocomplete_suggestions" should not exist
    And I click on "Program1" item in the autocomplete list
    And I press "Next"
    And I should see "Program1"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button"
    Then the following should exist in the "report-table" table:
      | Exporter       | Status    |
      | Programs       | Scheduled |

  @_file_upload
  Scenario: Import a program into current tenant and check form validation and conflict resolution
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/program/tests/fixtures/program-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Programs (1)"
    And I press "Next"
    And I click on "Course backups excluding user data" "checkbox"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one program"
    And I set the following fields to these values:
      | Programs | Program 1A |
    And I press "Next"
    Then I should see "Some courses do not exist"
    And I click on "Create empty course" "radio"
    And I press "Next"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button"
    And I run all adhoc tasks
    And I navigate to "Programs" in workplace launcher
    And I should see "Program 1A"

  Scenario: Check some export options are disabled if user has no capabilities
    Given the following "permission overrides" exist:
      | capability                 | permission  | role               | contextlevel  | reference |
      | tool/program:allocateuser  | Prevent     | tool_tenant_admin  | System        |           |
      | tool/dynamicrule:manage    | Prevent     | tool_tenant_admin  | System        |           |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Programs" "radio"
    And I press "Next"
    And the "Program user allocations" "checkbox" should be disabled
    And the "Dynamic rules" "checkbox" should be disabled
    And I log out

  @_file_upload
  Scenario: Check some import options are disabled if user has no capabilities
    Given the following "permission overrides" exist:
      | capability                 | permission  | role               | contextlevel  | reference |
      | tool/program:allocateuser  | Prevent     | tool_tenant_admin  | System        |           |
      | tool/dynamicrule:manage    | Prevent     | tool_tenant_admin  | System        |           |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/program/tests/fixtures/program-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Programs (1)"
    And I press "Next"
    And the "Program user allocations" "checkbox" should be disabled
    And the "Dynamic rules" "checkbox" should be disabled
    And I log out
