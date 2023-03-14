@tool @tool_program @_file_upload @javascript @moodleworkplace @tool_uploaduser
Feature: Allocate users on programs in upload users
  In order to allocate users on program
  As an admin
  I need to upload files containing the users data

  Scenario: Upload users allocating them on programs
    Given the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | Default tenant |
      | Program2 | prog2    | 0        | Default tenant |
      | Program3 | prog3    | 0        | Default tenant |
    When I log in as "admin"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/program/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I should see "program1"
    And I should see "program2"
    And I should see "prog1"
    And I should see "prog2"
    And I should see "prog3"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I should see "Tom Jones"
    And I should see "Maria Whatever"
    And I should see "Laia Whataday"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program2" report row
    And I should see "Trent Reznor"
    And I should see "Maria Whatever"
    And I should see "Judy Someother"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program3" report row
    And I should see "Judy Someother"

  Scenario: Upload users allocating them on programs as tenantadmin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | Tenant1 |
      | Program2 | prog2    | 0        | Tenant1 |
      | Program3 | prog3    | 0        | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/program/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I should see "Tom Jones"
    And I should see "Maria Whatever"
    And I should see "Laia Whataday"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program2" report row
    And I should see "Trent Reznor"
    And I should see "Maria Whatever"
    And I should see "Judy Someother"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program3" report row
    And I should see "Judy Someother"

  Scenario: Upload users updating program allocations
    Given "1" tenants exist with "6" users and "0" courses in each
    And the following "roles" exist:
      | shortname | name              | archetype |
      | allocator | Program allocator | |
    And the following "role assigns" exist:
      | user   | role      | contextlevel | reference |
      | user11 | allocator | System       |           |
    Given the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | Tenant1 |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program  |
      | Certification1 | 0        | Tenant1 | Program1 |
    And the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user12 |
    Given the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user13 |
    And the following "permission overrides" exist:
      | capability                | permission | role      | contextlevel | reference |
      | moodle/site:configview    | Allow      | allocator | System       |           |
      | moodle/site:uploadusers   | Allow      | allocator | System       |           |
      | tool/program:allocateuser | Allow      | allocator | System       |           |
    When I log in as "user11"
    And I change window size to "large"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/program/tests/fixtures/upload_users_update.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program1" report row
    And I wait until the page is ready
    Then the following should exist in the "reportbuilder-table" table:
      | First name/ Surname | Allocation source |
      | User 12   | Manual            |
      | User 13   | Manual            |
      | User 13   | Certification     |
      | User 14   | Manual            |
      | User 15   | Manual            |
    And I press "Edit" action in the "User 14" report row
    And the following fields match these values:
      | startdatetype    | Select date       |
      | startdate[day]   | 12                |
      | startdate[month] | May               |
      | startdate[year]  | 2019              |
      | enddatetype      | Not set (default) |
      | duedatetype      | Not set (default) |
    And I click on "Cancel" "button" in the "Allocation for 'User 14'" "dialogue"
    And I press "Edit" action in the "User 15" report row
    And the following fields match these values:
      | startdatetype        | Select date       |
      | startdate[day]         | 12                |
      | startdate[month]       | May               |
      | startdate[year]        | 2019              |
      | enddatetype            | Select date       |
      | enddate[day]   | 9                 |
      | enddate[month] | January           |
      | enddate[year]  | 2032              |
      | duedatetype            | Select date       |
      | duedate[day]   | 9                 |
      | duedate[month] | January           |
      | duedate[year]  | 2032              |
    And I click on "Cancel" "button" in the "Allocation for 'User 15'" "dialogue"
    And I press "Edit" action in the "User 12" report row
    And the following fields match these values:
      | startdatetype        | Select date       |
      | startdate[day]         | 12                |
      | startdate[month]       | May               |
      | startdate[year]        | 2019              |
      | enddatetype            | Select date       |
      | enddate[day]   | 9                 |
      | enddate[month] | January           |
      | enddate[year]  | 2032              |
      | duedatetype      | Not set (default) |
    And I click on "Cancel" "button" in the "Allocation for 'User 12'" "dialogue"
    # User 13 has two allocations (manual & via certification), but only one can be edited.
    # TODO Find a better way to select action from the manual allocation instead of having to filter by it first.
    And I click on "Filters" "button"
    And I set the following fields in the "Allocation source" "core_reportbuilder > Filter" to these values:
      | Allocation source operator | Is equal to |
      | Allocation source value    | Manual      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I click on "Filters" "button"
    And I press "Edit" action in the "User 13" report row
    And the following fields match these values:
      | startdatetype        | Select date       |
      | startdate[day]         | 12                |
      | startdate[month]       | May               |
      | startdate[year]        | 2019              |
      | enddatetype            | Not set (default) |
      | duedatetype            | Select date       |
      | duedate[day]   | 9                 |
      | duedate[month] | January           |
      | duedate[year]  | 2032              |
    And I click on "Cancel" "button" in the "Allocation for 'User 13'" "dialogue"

  Scenario: Upload users to shared programs as tenant admin
    Given "2" tenants exist with "6" users and "0" courses in each
    And shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | -       |
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/program/tests/fixtures/upload_users_shared.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I should see "User 12"
    And I should see "User 13"
    And I should see "User 14"
    And I should see "User 15"
    And I should not see "User 2"
    And I log out
    And I log in as "tenantadmin2"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I should not see "User 2"
    And I should not see "User 1"
    And I should see "Nothing to display"

  Scenario: Upload users to shared programs as admin
    Given "2" tenants exist with "6" users and "0" courses in each
    And shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | -       |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/program/tests/fixtures/upload_users_shared.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I switch to tenant "Tenant1"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I should see "User 12"
    And I should see "User 13"
    And I should see "User 14"
    And I should see "User 15"
    And I should not see "User 2"
    And I switch to tenant "Tenant2"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program1" report row
    And I should see "User 22"
    And I should see "User 23"
    And I should not see "User 1"
