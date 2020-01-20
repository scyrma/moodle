@mod @mod_appointment @moodleworkplace @javascript
Feature: Use custom fields with appointments

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | wplist |
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
    And I set the following visible fields to these values:
      | Name | Example field |
      | Short name | example |
    And I press "Save changes"
    And I log out

  Scenario: Custom fields should appear in form for single appointment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    Then I should see "Other fields"
    And I expand all fieldsets
    And I set the following visible fields to these values:
      | startdate[0][day]    | 19                |
      | startdate[0][month]  | February          |
      | startdate[0][year]   | 2020              |
      | starttime[0][hour]   | 01                |
      | starttime[0][minute] | 00                |
      | endtime[0][hour]     | 02                |
      | endtime[0][minute]   | 00                |
      | Example field        | Some example text |
    And I press "Save" in the modal form dialogue
    And I click on "Actions menu" "link" in the "table.sessions-list tbody tr:first-child" "css_element"
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
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Multiple appointments" in the open action menu
    Then I should see "Other fields"
    And I expand all fieldsets
    And I set the following visible fields to these values:
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
    And I press "Save" in the modal form dialogue
    And I click on "Actions menu" "link" in the "table.sessions-list tbody tr:first-child" "css_element"
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
    And I press "Cancel" in the modal form dialogue
    And I click on "Actions menu" "link" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
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
