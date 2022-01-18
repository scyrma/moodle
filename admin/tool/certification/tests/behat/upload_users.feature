@tool @tool_certification @_file_upload @javascript @moodleworkplace @tool_uploaduser
Feature: Allocate users on certifications in upload users
  In order to allocate users on certification
  As global admin and tenant admin
  I need to upload files containing the users data

  Scenario: Upload users allocating them on certifications as global admin
    Given the following users allocations to tenants exist:
      | user  | tenant  |
      | admin | Default tenant |
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program2 | prog2    | 0        | Default tenant |
    And the following "tool_certification > certifications" exist:
      | fullname       | idnumber | archived | program  | tenant         |
      | Certification1 | cert1    | 0        | Program2 | Default tenant |
      | Certification2 | cert2    | 0        | Program2 | Default tenant |
      | Certification3 | cert3    | 0        | Program2 | Default tenant |
    And I log in as "admin"
    When I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/certification/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I should see "certification1"
    And I should see "certification2"
    And I should see "cert1"
    And I should see "cert2"
    And I should see "cert3"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification1" "table_row"
    And I wait until the page is ready
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Tom Jones"
    And I should see "Maria Whatever"
    And I should see "Luana Whataday"
    And I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification2" "table_row"
    And I wait until the page is ready
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Trent Reznor"
    And I should see "Maria Whatever"
    And I should see "Judy Someother"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification3" "table_row"
    And I wait until the page is ready
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Judy Someother"

  Scenario: Upload users allocating them on certifications as tenantadmin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program2 | prog2    | 0        | Tenant1 |
    And the following "tool_certification > certifications" exist:
      | fullname       | idnumber | archived | program  | tenant  |
      | Certification1 | cert1    | 0        | Program2 | Tenant1 |
      | Certification2 | cert2    | 0        | Program2 | Tenant1 |
      | Certification3 | cert3    | 0        | Program2 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/certification/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification1" "table_row"
    And I wait until the page is ready
    And I should see "Tom Jones"
    And I should see "Maria Whatever"
    And I should see "Luana Whataday"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification2" "table_row"
    And I wait until the page is ready
    And I should see "Trent Reznor"
    And I should see "Maria Whatever"
    And I should see "Judy Someother"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification3" "table_row"
    And I wait until the page is ready
    And I should see "Judy Someother"

  Scenario: Upload users updating certification allocations
    Given "1" tenants exist with "6" users and "0" courses in each
    And the following "roles" exist:
      | shortname | name                    | archetype |
      | allocator | Certification allocator | |
    And the following "role assigns" exist:
      | user   | role      | contextlevel | reference |
      | user11 | allocator | System       |           |
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | Tenant1 |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program  | idnumber |
      | Certification1 | 0        | Tenant1 | Program1 | cert1    |
    And the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user12 |
    And the following "permission overrides" exist:
      | capability                      | permission | role      | contextlevel | reference |
      | moodle/site:configview          | Allow      | allocator | System       |           |
      | moodle/site:uploadusers         | Allow      | allocator | System       |           |
      | tool/certification:allocateuser | Allow      | allocator | System       |           |
    When I log in as "user11"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/certification/tests/fixtures/upload_users_update.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I wait until the page is ready
    And "Manual" "text" should exist in the "User 12" "table_row"
    And I click on "Edit" "link" in the "User 12" "table_row"
    And the following fields match these values:
      | startdatetype    | Select date |
      | startdate[day]   | 9           |
      | startdate[month] | January     |
      | startdate[year]  | 2032        |
      | duedatetype      | 1 week after start date (default) |
    And I click on "Cancel" "button" in the "Allocation for 'User 12'" "dialogue"
    And "Manual" "text" should exist in the "User 13" "table_row"
    And I click on "Edit" "link" in the "User 13" "table_row"
    # Due date type for users 13 and 15 should be locked to this date and should not be the default.
    And the following fields match these values:
      | startdatetype    | Select date |
      | startdate[day]   | 9           |
      | startdate[month] | January     |
      | startdate[year]  | 2032        |
      | duedatetype      | Select date |
    And I click on "Cancel" "button" in the "Allocation for 'User 13'" "dialogue"
    And "Manual" "text" should exist in the "User 14" "table_row"
    And I click on "Edit" "link" in the "User 14" "table_row"
    And the following fields match these values:
      | Status   | Default |
      | duedatetype      | 1 week after start date (default) |
    And I click on "Cancel" "button" in the "Allocation for 'User 14'" "dialogue"
    And "Manual" "text" should exist in the "User 15" "table_row"
    And I click on "Edit" "link" in the "User 15" "table_row"
    And the following fields match these values:
      | startdatetype    | Select date |
      | startdate[day]   | 9           |
      | startdate[month] | January     |
      | startdate[year]  | 2032        |
      | duedatetype      | Select date |
    And I click on "Cancel" "button" in the "Allocation for 'User 15'" "dialogue"

  Scenario: Upload users creating certification completion records
    Given "1" tenants exist with "6" users and "0" courses in each
    And the following "roles" exist:
      | shortname | name                    | archetype |
      | allocator | Certification allocator | |
    And the following "role assigns" exist:
      | user   | role      | contextlevel | reference |
      | user11 | allocator | System       |           |
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | Tenant1 |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program  | idnumber | expirydateabsolute |
      | Certification1 | 0        | Tenant1 | Program1 | cert1    | +5 day             |
    And the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user12 |
    And the following "permission overrides" exist:
      | capability                      | permission | role      | contextlevel | reference |
      | moodle/site:configview          | Allow      | allocator | System       |           |
      | moodle/site:uploadusers         | Allow      | allocator | System       |           |
      | tool/certification:allocateuser | Allow      | allocator | System       |           |
    When I log in as "user11"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/certification/tests/fixtures/upload_users_certify.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Certification1" "table_row"
    Then I should see "Not set" in the "User 12" "table_row"
    And I should see "Certified" in the "User 12" "table_row"
    And I should see "##+5 days##%d/%m/%y##" in the "User 12" "table_row"
    And I should see "-" in the "User 12" "table_row"
    And I should see "Not set" in the "User 13" "table_row"
    And I should see "Certified" in the "User 13" "table_row"
    And I should see "1/10/21" in the "User 13" "table_row"
    And I should see "-" in the "User 13" "table_row"
    And I should see "Not set" in the "User 14" "table_row"
    And I should see "Certified" in the "User 14" "table_row"
    And I should see "1/12/21" in the "User 14" "table_row"
    And I should see "-" in the "User 14" "table_row"
    And I should see "Not set" in the "User 15" "table_row"
    And I should see "Certified" in the "User 15" "table_row"
    And I should see "##+5 days##%d/%m/%y##" in the "User 15" "table_row"
    And I should see "-" in the "User 15" "table_row"

  Scenario: Upload users to shared certification as tenant admin
    Given "2" tenants exist with "6" users and "0" courses in each
    And I change window size to "large"
    And shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program0 | prog0    | 0        | -       |
    And the following "tool_certification > certifications" exist:
      | fullname        | idnumber | archived | tenant  | program   |
      | Certification0  | cert0    | 0        | -       | Program0  |
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/certification/tests/fixtures/upload_users_shared.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "User 12"
    And I should see "User 13"
    And I should see "User 14"
    And I should see "User 15"
    And I should not see "User 2"
    And I log out
    And I log in as "tenantadmin2"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should not see "User 1"
    And I should see "User 22"
    And I should see "User 23"
    And I log out

  Scenario: Upload users to shared certification as admin
    Given "2" tenants exist with "6" users and "0" courses in each
    And I change window size to "large"
    And shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program0 | prog0    | 0        | -       |
    And the following "tool_certification > certifications" exist:
      | fullname        | idnumber | archived | tenant  | program   |
      | Certification0  | cert0    | 0        | -       | Program0  |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/certification/tests/fixtures/upload_users_shared.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I switch to tenant "Tenant1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "User 12"
    And I should see "User 13"
    And I should see "User 14"
    And I should see "User 15"
    And I should not see "User 2"
    And I switch to tenant "Tenant2"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "User 22"
    And I should see "User 23"
    And I should not see "User 1"
