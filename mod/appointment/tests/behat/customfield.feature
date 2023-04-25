@mod @mod_appointment @moodleworkplace @javascript
Feature: Use custom fields with appointments

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber     |
      | appointment | Test appointment | Appointment desc | C1     | appointment1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "admin"
    And I navigate to "Plugins > Appointment custom fields" in site administration
    And I press "Add a new category"
    And I follow "Add a new custom field"
    And I choose "Short text" in the open action menu
    And I set the following fields to these values:
      | Name | Example field |
      | Short name | example |
    And I press "Save changes"
    And I log out

  Scenario: Custom fields should appear in form for single appointment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    Then I should not see "Manage custom fields"
    And I should see "Other fields"
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | startdate[0][day]    | 19                |
      | startdate[0][month]  | February          |
      | startdate[0][year]   | 2020              |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 00                |
      | endtime[0][hour]     | 02                |
      | endtime[0][minute]   | 00                |
      | Example field        | Some example text |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    And I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
      | startdate[0][day]    | 19                |
      | startdate[0][month]  | February          |
      | startdate[0][year]   | 2020              |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 00                |
      | endtime[0][hour]     | 02                |
      | endtime[0][minute]   | 00                |
      | Example field        | Some example text |

  Scenario: Custom fields should appear in form for multiple appointments
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Multiple appointments" in the open action menu
    Then I should not see "Manage custom fields"
    And I should see "Other fields"
    And I set the following fields in the "Adding appointments" "dialogue" to these values:
      | startdate[0][day]    | 19                |
      | startdate[0][month]  | February          |
      | startdate[0][year]   | 2020              |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 00                |
      | endtime[0][hour]     | 02                |
      | endtime[0][minute]   | 00                |
      | split[0][timeunit]   | minutes           |
      | break[0][timeunit]   | minutes           |
      | Example field        | Some example text |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    And I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
      | startdate[0][day]    | 19                |
      | startdate[0][month]  | February          |
      | startdate[0][year]   | 2020              |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 00                |
      | endtime[0][hour]     | 01                |
      | endtime[0][minute]   | 15                |
      | Example field        | Some example text |
    And I click on "Cancel" "button" in the "Editing appointment" "dialogue"
    And I click on "Actions menu" "link" in the "1:20" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
      | startdate[0][day]    | 19                |
      | startdate[0][month]  | February          |
      | startdate[0][year]   | 2020              |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 20                |
      | endtime[0][hour]     | 01                |
      | endtime[0][minute]   | 35                |
      | Example field        | Some example text |

  Scenario: Manage custom fields link should be visible with required capability
    Given the following "role assigns" exist:
      | user     | role            | contextlevel | reference |
      | teacher1 | editingteacher  | System       |           |
    And the following "permission overrides" exist:
      | capability                         | permission | role               | contextlevel | reference |
      | mod/appointment:managecustomfields | Allow      | editingteacher     | System       |           |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    Then "Manage custom fields" "link" should exist
    And I click on "Cancel" "button" in the "Adding appointment" "dialogue"
    And I follow "Add"
    And I choose "Multiple appointments" in the open action menu
    And "Manage custom fields" "link" should exist

  Scenario: Custom fields should appear in calendar event
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    Then I should see "Other fields"
    And I expand all fieldsets
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Date                 | ##tomorrow##      |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 00                |
      | endtime[0][hour]     | 02                |
      | endtime[0][minute]   | 00                |
      | Example field        | Some example text |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    And I am viewing site calendar
    # Switch to Upcoming events view because 'tomorrow' may be in this or the next month.
    And I press "Month"
    And I follow "Upcoming events"
    Then I should see "Test appointment"
    And I should see "Some example text"
    And I should see "Tomorrow"
    And I should see "1:00"
    And I should see "2:00"
