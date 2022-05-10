@mod @mod_appointment @moodleworkplace @javascript
Feature: Course reset with appointment module
  In order to reuse Appointments
  As a teacher
  I need to remove all previous data.

  Scenario: Course reset defaults for appointment module
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber    |
      | appointment | Test appointment | Appointment desc | C1     | appointment |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Reset" in current page administration
    And I press "Select default"
    And I expand all fieldsets
    And the field "Remove all session sign-ups" matches value "1"

  Scenario: Course reset of appointment module
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
      | student1 | Student   | First    | student1@example.com |
      | student2 | Student   | Second   | student2@example.com |
      | student3 | Student   | Third    | student3@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber    | completion | completionview | completionbooked |
      | appointment | Test appointment | Appointment desc | C1     | appointment | 2          | 0              | 1                |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details | capacity | allowwaitlist | timestart1          | timefinish1          |
      | Test appointment | SES1    | 2        | 1             | ##2033-01-01 9:40## | ##2033-01-01 10:00## |
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
      | SES1    | student2 | booked     |
      | SES1    | student3 | waitlisted |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Student First"
    And I should see "Student Second"
    And I should see "Student Third"
    When I am on "Course 1" course homepage
    And I navigate to "Reports > Activity completion" in current page administration
    And "Completed" "icon" should exist in the "Student First" "table_row"
    And "Completed" "icon" should exist in the "Student Second" "table_row"
    And "Completed" "icon" should not exist in the "Student Third" "table_row"
    And I am on "Course 1" course homepage
    And I navigate to "Reset" in current page administration
    And I set the following fields to these values:
      | Remove all session sign-ups | 1 |
    And I press "Reset course"
    And I press "Continue"
    When I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "No users have signed-up for this session"
    And I am on "Course 1" course homepage
    And I navigate to "Reports > Activity completion" in current page administration
    And "Completed" "icon" should not exist in the "Student First" "table_row"
    And "Completed" "icon" should not exist in the "Student Second" "table_row"
    And "Completed" "icon" should not exist in the "Student Third" "table_row"
