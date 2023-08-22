@mod @mod_appointment @moodleworkplace @javascript
Feature: Viewing appointments list in a course
  In order to overview appointemnt activity modules in a course
  As a manager
  I can add activities block in a course or on the frontpage

  Scenario: View appointments in activities block on the frontpage
    Given the following "activities" exist:
      | activity    | name             | intro            | course               | idnumber     |
      | appointment | Test appointment | Appointment desc | Acceptance test site | appointment1 |

    When I log in as "admin"
    And I am on site homepage
    And I turn editing mode on
    And I add the "Activities" block
    And I click on "Appointments" "link" in the "Activities" "block"
    Then I should see "Test appointment"

  Scenario: View appointments in activities block in a course
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
      | activity    | name                 | intro            | course | idnumber     | section |
      | appointment | Appt with signups    | Appointment desc | C1     | appointment1 | 0       |
      | appointment | Appt without signups | Appointment desc | C1     | appointment2 | 1       |
    And the following "mod_appointment > sessions" exist:
      | appointment       | details | capacity | allowwaitlist | timestart1          | timefinish1          |
      | Appt with signups | SES1    | 2        | 1             | ##2033-01-01 9:40## | ##2033-01-01 10:00## |
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
      | SES1    | student2 | booked     |
      | SES1    | student3 | waitlisted |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Activities" block
    And I click on "Appointments" "link" in the "Activities" "block"
    And I should see "2" in the "Appt with signups" "table_row"
    And I should see "0" in the "Appt without signups" "table_row"
    Then I click on "Appt with signups" "link" in the "region-main" "region"
    And I should see "3 / 2" in the "9:40" "table_row"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: Teacher can create, edit and delete appointment activity
    Given the following config values are set as admin:
      | coursebinenable | 0 | tool_recyclebin |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | First    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I press "Add an activity or resource"
    And I click on "Add a new Appointment booking" "link" in the "Add an activity or resource" "dialogue"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Name                                                       | Test Appointment name                             |
      | Completion tracking                                        | Show activity as complete when conditions are met |
      | completionview                                             | 0                                                 |
      | Learner must book an appointment to complete this activity | 1                                                 |
    And I press "Save and display"
    And I click on "Add" "link" in the "region-main" "region"
    And I choose "Appointment" in the open action menu
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | startdate[0][year] | 2021 |
      | endtime[0][hour]   | 23   |
      | endtime[0][minute] | 59   |
    And I set the field "Description" to "SES1"
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
    And the following "mod_appointment > attendance" exist:
      | session | user     |
      | SES1    | student1 |
    And I navigate to "Settings" in current page administration
    And the field "Name" matches value "Test Appointment name"
    And I set the field "Name" to "New Appointment name"
    And I press "Save and return to course"
    And I open "New Appointment name" actions menu
    And I choose "Delete" in the open action menu
    And I should see "Are you sure that you want to delete the Appointment \"New Appointment name\"?"
    And I press "Yes"
    And I should not see "New Appointment name"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
