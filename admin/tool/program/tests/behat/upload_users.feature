@tool @tool_program @_file_upload @javascript @moodleworkplace @tool_uploaduser
Feature: Allocate users on programs in upload users
  In order to allocate users on program
  As an admin
  I need to upload files containing the users data

  Scenario: Upload users allocating them on programs
    Given the following tool program data "programs" exist:
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
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program1" "table_row"
    And I wait until the page is ready
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Tom Jones"
    And I should see "Maria Whatever"
    And I should see "Laia Whataday"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program2" "table_row"
    And I wait until the page is ready
    And I should see "Trent Reznor"
    And I should see "Maria Whatever"
    And I should see "Judy Someother"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program3" "table_row"
    And I wait until the page is ready
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Judy Someother"

  Scenario: Upload users allocating them on programs as tenantadmin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following tool program data "programs" exist:
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
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Tom Jones"
    And I should see "Maria Whatever"
    And I should see "Laia Whataday"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program2" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Trent Reznor"
    And I should see "Maria Whatever"
    And I should see "Judy Someother"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program3" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Judy Someother"

  Scenario: Upload users updating program allocations
    Given "1" tenants exist with "6" users and "0" courses in each
    And the following "roles" exist:
      | shortname | name              | archetype |
      | allocator | Program allocator | |
    And the following "role assigns" exist:
      | user   | role      | contextlevel | reference |
      | user11 | allocator | System       |           |
    Given the following tool program data "programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program1 | prog1    | 0        | Tenant1 |
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program  |
      | Certification1 | 0        | Tenant1 | Program1 |
    And the following users allocations to programs exist:
      | program  | user   | allocationtype |
      | Program1 | user12 | 2              |
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user13 |
    And I log in as "admin"
    And I set the following system permissions of "Program allocator" role:
      | capability                | permission |
      | moodle/site:configview    | Allow      |
      | moodle/site:uploadusers   | Allow      |
      | tool/program:allocateuser | Allow      |
    And I log out
    When I log in as "user11"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/program/tests/fixtures/upload_users_update.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program1" "table_row"
    And I wait until the page is ready
    And "Manual" "text" should exist in the "User 14" "table_row"
    And I click on "Edit" "link" in the "User 14" "table_row"
    And the following fields match these values:
      | startdatetype    | Select date       |
      | startdate[day]   | 12                |
      | startdate[month] | May               |
      | startdate[year]  | 2019              |
      | enddatetype      | Not set (default) |
      | duedatetype      | Not set (default) |
    And I press "Cancel" in the modal form dialogue
    And "Manual" "text" should exist in the "User 15" "table_row"
    And I click on "Edit" "link" in the "User 15" "table_row"
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
    And I press "Cancel" in the modal form dialogue
    And "Manual" "text" should exist in the "User 12" "table_row"
    And I click on "Edit" "link" in the "User 12" "table_row"
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
    And I press "Cancel" in the modal form dialogue
    # First row for the "User 13" user:
    And "//table//tbody//tr[2]//td[contains(@class,'c0') and (text() = 'User 13')]" "xpath_element" should exist in the "body" "css_element"
    And "//table//tbody//tr[2]//td[contains(@class,'c2') and (text() = 'Manual')]" "xpath_element" should exist in the "body" "css_element"
    # Second row for the "User 13" user:
    And "//table//tbody//tr[3]//td[contains(@class,'c0') and (text() = 'User 13')]" "xpath_element" should exist in the "body" "css_element"
    And "//table//tbody//tr[3]//td[contains(@class,'c2') and (text() = 'Certification')]" "xpath_element" should exist in the "body" "css_element"
    And I click on "Edit" "link" in the "//table//tbody//tr[2]//td[contains(@class,'c6')]" "xpath_element"
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
