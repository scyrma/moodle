@mod @mod_appointment @moodleworkplace @javascript
Feature: Testing mod_appointment generator
  In order to write tests for mod_appointment
  As a developer
  I need to be able to use generator

  Scenario: Appointment sessions generator
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber    |
      | appointment | Test appointment | Appointment desc | C1     | appointment |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details     | capacity | allowwaitlist | allowcancellations | timestart1          | timefinish1          | timestart2           | timefinish2          |
      | Test appointment | Hello there | 5        | 1             | 0                  | ##2033-01-01 9:40## | ##2033-01-01 10:00## | ##2033-01-02 10:40## | ##2033-01-02 11:00## |
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "Saturday, 1 January 2033"
    And I should see "9:40 AM - 10:00 AM"
    And I should see "0 / 5"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
      | startdate[0][day]    | 1           |
      | startdate[0][month]  | January     |
      | startdate[0][year]   | 2033        |
      | starttime[0][hour]   | 09          |
      | starttime[0][minute] | 40          |
      | endtime[0][hour]     | 10          |
      | endtime[0][minute]   | 00          |
      | startdate[1][day]    | 2           |
      | startdate[1][month]  | January     |
      | startdate[1][year]   | 2033        |
      | starttime[1][hour]   | 10          |
      | starttime[1][minute] | 40          |
      | endtime[1][hour]     | 11          |
      | endtime[1][minute]   | 00          |
      | capacity             | 5           |
      | allowwaitlist        | 1           |
      | allowcancellations   | 0           |
      | Description          | Hello there |
    And I click on "Cancel" "button" in the "Editing appointment" "dialogue"

  Scenario: Appointment signups generator
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
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
      | activity    | name             | intro            | course | idnumber    |
      | appointment | Test appointment | Appointment desc | C1     | appointment |
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
    And I should see "3 / 2" in the "9:40" "table_row"
    And I should see "Full" in the "9:40" "table_row"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Second" "table_row"
    And I should see "Wait-listed" in the "Student Third" "table_row"

  Scenario: Appointment attendance generator
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
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
      | activity    | name             | intro            | course | idnumber    |
      | appointment | Test appointment | Appointment desc | C1     | appointment |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details | capacity | allowwaitlist | timestart1          | timefinish1          |
      | Test appointment | SES1    | 2        | 1             | ##2021-01-01 9:40## | ##2033-01-01 10:00## |
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
      | SES1    | student2 | booked     |
      | SES1    | student3 | waitlisted |
    And the following "mod_appointment > attendance" exist:
      | session | user     | status             |
      | SES1    | student1 | fully_attended     |
      | SES1    | student2 | partially_attended |
      | SES1    | student3 | no_show            |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "Full" in the "9:40" "table_row"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Fully attended" in the "Student First" "table_row"
    And I should see "Partially attended" in the "Student Second" "table_row"
    And I should see "No show" in the "Student Third" "table_row"
    And I am on "Course 1" course homepage
    And I navigate to "View > Grader report" in the course gradebook
    And the following should exist in the "user-grades" table:
      | -1-            | -4- | -5- |
      | Student First  | 100 | 100 |
      | Student Second | 50  | 50  |
      | Student Third  | 0   | 0   |
