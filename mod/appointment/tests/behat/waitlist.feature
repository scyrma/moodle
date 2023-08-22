@mod @mod_appointment @moodleworkplace @javascript
Feature: Appointments wait-listing
  In order to work with appointments
  As a student
  I need to be able to cancel waitlisted past sessions

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber    |
      | appointment | Test appointment | Appointment desc | C1     | appointment |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details | capacity | allowwaitlist | allowcancellations | timestart1           | timefinish1          |
      | Test appointment | SES1    | 1        | 1             | 1                  | ##2000-01-01 9:40##  | ##2000-01-01 10:00## |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First  | teacher1@example.com |
      | student1 | Student | First  | student1@example.com |
      | student2 | Student | Second | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |

  Scenario: Cancel or re-signup as waitlisted user
    Given the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
      | SES1    | student2 | waitlisted |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "[data-toggle='collapse']" "css_element" in the "9:40" "table_row"
    And "Cancel" "button" should not exist in the "9:40" "table_row"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "[data-toggle='collapse']" "css_element" in the "9:40" "table_row"
    And I click on "Cancel" "button" in the "9:40" "table_row"
    And I click on "Confirm cancellation" "button" in the "Cancel booking" "dialogue"
    And I click on "[data-toggle='collapse']" "css_element" in the "9:40" "table_row"
    And "Cancel" "button" should not exist in the "9:40" "table_row"
