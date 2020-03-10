@mod @mod_appointment @moodleworkplace @javascript
Feature: Create a report using the appointments datasource

  Background:
    Given the following "categories" exist:
      | name        | category | idnumber |
      | Category1   | 0        | CAT1     |
    And the following tenants exist:
      | name    | category  |
      | Tenant1 | Category1 |
    And the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | CAT1     | wplist |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber     |
      | appointment | Test appointment | Appointment desc | C1     | appointment1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | teacher1      | Tenant1 |
      | student1      | Tenant1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | teacher1 | manager        | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Manager" role:
      | capability              | permission |
      | tool/reportbuilder:edit | Allow      |
    And I log out

  Scenario: Create a new report
    Given the following custom reports exist:
      | name    | tenant  | source                                                             |
      | Report1 | Tenant1 | mod_appointment\tool_reportbuilder\datasources\report_appointments |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I press "Add session"
    And I set the following visible fields to these values:
      | startdate[0][day]    | ##tomorrow##j## |
      | startdate[0][month]  | ##tomorrow##n## |
      | startdate[0][year]   | ##tomorrow##Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##tomorrow##j## |
      | startdate[1][month]  | ##tomorrow##n## |
      | startdate[1][year]   | ##tomorrow##Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    Then I should see "Report1"
    And I should see "Course 1"
    And I should see "##tomorrow##l, j F Y##" in the "Course 1" "table_row"
    And I should see "1:00" in the "Course 1" "table_row"
    And I should see "2:00" in the "Course 1" "table_row"
    And I should see "0 / 10" in the "Course 1" "table_row"
    And I should see "Open" in the "Course 1" "table_row"
    And I log out