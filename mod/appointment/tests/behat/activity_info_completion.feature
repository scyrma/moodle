@mod @mod_appointment @moodleworkplace @javascript
Feature: Completion in the appointment activity
  To avoid navigating from the course certificate to the course homepage to see the course certificate activity information
  As a student
  I need to be able to see the course certificate activity information in the course certificate activity itself

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | student1 | Student   | 1        | student1@example.com  |
    And the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
      | category          | 0         |
      | enablecompletion  | 1         |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity    | name                | intro            | course | idnumber     | completion | completionview | completionbooked |
      | appointment | Test appointment 1  | Appointment desc | C1     | appointment1 | 1          | 0              | 0                |
      | appointment | Test appointment 2  | Appointment desc | C1     | appointment2 | 2          | 1              | 1                |

  Scenario: Toggle manual completion as a student
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment 1"
    And the manual completion button of "Test appointment 1" is displayed as "Mark as done"
    When I toggle the manual completion state of "Test appointment 1"
    Then the manual completion button of "Test appointment 1" is displayed as "Done"
    But "Mark as done" "button" should not exist
    # Just make sure that the change persisted.
    And I reload the page
    And I wait until the page is ready
    And I should not see "Mark as done"
    And the manual completion button of "Test appointment 1" is displayed as "Done"
    And I toggle the manual completion state of "Test appointment 1"
    And the manual completion button of "Test appointment 1" is displayed as "Mark as done"
    But "Done" "button" should not exist
    # Just make sure that the change persisted.
    And I reload the page
    And the manual completion button of "Test appointment 1" is displayed as "Mark as done"
    But "Done" "button" should not exist

  Scenario: Viewing an appointment activity with manual completion as a teacher
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Test appointment 1"
    Then the manual completion button for "Test appointment 1" should be disabled

  Scenario: Viewing an appointment activity with automatic completion as a student
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment 2"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I press "Add session"
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | startdate[0][day]    | ##tomorrow##%d## |
      | startdate[0][month]  | ##tomorrow##%B## |
      | startdate[0][year]   | ##tomorrow##%Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##tomorrow##%d## |
      | startdate[1][month]  | ##tomorrow##%B## |
      | startdate[1][year]   | ##tomorrow##%Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Test appointment 2"
    Then the "View" completion condition of "Test appointment 2" is displayed as "done"
    And the "Book an appointment" completion condition of "Test appointment 2" is displayed as "todo"
    And I press "Book"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I reload the page
    And the "View" completion condition of "Test appointment 2" is displayed as "done"
    And the "Book an appointment" completion condition of "Test appointment 2" is displayed as "done"

  Scenario: Viewing an appointment activity with automatic completion as a teacher
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Test appointment 2"
    Then "Test appointment 2" should have the "View" completion condition
    Then "Test appointment 2" should have the "Book an appointment" completion condition
