@tool @tool_certification @moodleworkplace @javascript
Feature: Export and import certifications
  As a tenant administrator
  I want to export and import certifications

  Background:
    Given "2" tenants exist with "1" users and "2" courses in each

  Scenario: Export one certification
    And the following "tool_certification > certifications" exist:
      | fullname        | archived | tenant  |
      | Certification1  | 0        | Tenant1 |
      | Certification2  | 0        | Tenant2 |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Certifications" "radio"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one certification"
    And I set the following fields to these values:
      | Certifications | Certification1 |
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Certification1"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    Then the following should exist in the "reportbuilder-table" table:
      | Exporter       | Status    |
      | Certifications | Scheduled |

  Scenario: Validate "Include course content" checkbox availability
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Certifications" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be enabled
    And I press "Cancel"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Certifications" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be disabled
    And I press "Cancel"
    And I log out
    When the following config values are set as admin:
      | coursecontentbackup | 1 | tool_wp |
    And I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Certifications" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be enabled
    And I press "Cancel"

  @_file_upload
  Scenario: Import a certification into current tenant
    Given the following "users" exist:
      | username  | firstname | lastname    | email              |
      | luana     | Luana     | Moon        | luanamoon@test.com |
    And the following users allocations to tenants exist:
      | user    | tenant  |
      | luana   | Tenant1 |
    And the following "tool_certification > certifications" exist:
      | fullname        | archived | tenant  | idnumber       |
      | Certification0  | 0        | Tenant1 | Cert1_idnumber |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/certification/tests/fixtures/certifications-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Certifications (1)"
    And I should see "Certification user allocations (2)"
    And I should see "Programs (1)"
    And I should see "Courses (1)"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one certification"
    And I set the following fields to these values:
      | Certifications | Certification1 |
    And I click on "Associated programs" "checkbox"
    And I click on "Certification user allocations" "checkbox"
    And I press "Next"
    And I should see "Some programs do not exist"
    And I press "Previous"
    And I click on "Associated programs" "checkbox"
    And I press "Next"
    And I should see "Custom field field instance not found"
    And I should see "Instances (6)"
    And I should not see "Some programs do not exist"
    And I should see "Certification with the same ID number already exists"
    And I click on "Add a numeric suffix to the ID number" "radio"
    And I should see "Some users do not exist"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Certification1"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I run all adhoc tasks
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "View import" action in the "Certifications" report row
    Then I should see "Instances (1)"
    And I should see "Certification1"
    And I click on "Log" "link" in the "[data-region=newimport]" "css_element"
    And I should see "Created new course"
    And I should see "Created new program 'Program1A'"
    And I should see "Created new certification 'Certification1"
    And I should see "Allocated user 'Luana Moon' into certification 'Certification1'"
    And I should see "Created new rule 'Users allocated to certification' with 1 conditions and 1 actions"
    And I navigate to "Programs" in workplace launcher
    And I should see "Program1A"
    And I navigate to "Courses" in workplace launcher
    And I should see "Course 1a"
    And I navigate to "Certifications" in workplace launcher
    Then I press "Users" action in the "Certification1" report row
    And I should see "Luana Moon"
    Then I navigate to "Dynamic rules" in current page administration
    And I should see "Send notification 'Hello!' to users"
    And I log out

  @_file_upload
  Scenario: Import a certification into a different tenant
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file                      | admin/tool/certification/tests/fixtures/certifications-export.zip |
      | Step 2 | Select tenant                      | 1                                                                 |
      | Step 2 | Choose tenant                      | Tenant2                                                           |
      | Step 3 | Associated programs                | 1                                                                 |
      | Step 3 | Course backups excluding user data | 1                                                                 |
      | Step 3 | Select manually...                 | 1                                                                 |
      | Step 3 | Certifications                     | Certification1                                                    |
    And I navigate to "Certifications" in workplace launcher
    And I should not see "Certification1"
    And I switch to tenant "Tenant2"
    And I should see "Certification1"
    And I log out

  @_file_upload
  Scenario: Check some import options are disabled if user has no capabilities
    Given the following "permission overrides" exist:
      | capability                       | permission  | role               | contextlevel  | reference |
      | tool/certification:allocateuser  | Prevent     | tool_tenant_admin  | System        |           |
      | tool/program:edit  | Prevent     | tool_tenant_admin  | System        |           |
      | tool/dynamicrule:manage          | Prevent     | tool_tenant_admin  | System        |           |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/certification/tests/fixtures/certifications-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Certifications (1)"
    And I press "Next"
    And the "Associated programs" "checkbox" should be disabled
    And the "Certification user allocations" "checkbox" should be disabled
    And the "Dynamic rules" "checkbox" should be disabled
    And I log out

  @_file_upload
  Scenario: Importing export created when certification certified DR conditions were single-value selects
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file | admin/tool/certification/tests/fixtures/dr_export_using_singleselects_cc.zip |
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                   | tenant   |
      | user11   | User      | 11       | user11@address.invalid  | TenantDR |
      | user12   | User      | 12       | user12@address.invalid  | TenantDR |
    And the following "tool_certification > certification_users" exist:
      | certification               | user    |
      | CertificationDR             | user11  |
      | CertificationDR             | user12  |
    And I navigate to "All tenants" in workplace launcher
    And I switch to tenant "TenantDR"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Users who are certified in certification 'CertificationDR'"
    And I follow "RULE CERTIFICATION"
    And I should see "0 total matches"
    And I navigate to "Programs" in workplace launcher
    And I click on "ProgramDR" "link" in the "ProgramDR" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    And I set the field "Select courses" to "Course 11"
    Then I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Enable rule" "field" in the "RULE CERTIFICATION" "table_row"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    When I am on the "My courses" page logged in as "user12"
    And I click on "ProgramDR" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And I click on "Course11" "tool_catalogue > Catalogue item"
    And I toggle the manual completion state of "URL1"
    And I toggle the manual completion state of "URL2"
    And I log out
    When I log in as "admin"
    And I switch to tenant "TenantDR"
    Then I navigate to "Dynamic rules" in workplace launcher
    And I press "View report" action in the "RULE CERTIFICATION" report row
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 11" in the "reportbuilder-table" "table"
