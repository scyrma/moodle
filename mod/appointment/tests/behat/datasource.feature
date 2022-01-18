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
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                                                             |
      | Report1 | Tenant1 | mod_appointment\tool_reportbuilder\datasources\report_appointments |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Date                 | ##tomorrow## |
      | starttime[0][hour]   | 01              |
      | starttime[0][minute] | 00              |
      | endtime[0][hour]     | 02              |
      | endtime[0][minute]   | 00              |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Preview" "link" in the "Report1" "table_row"
    Then I should see "Report1"
    And the following should exist in the "report-table" table:
      | Course full name | Session start date         | Session start time | Session finish time | Booked / Capacity | Status |
      | Course 1         | ##tomorrow##%A, %d %B %Y## | 1:00               | 2:00                | 0 / 10            | Open   |
