@mod @mod_appointment @moodleworkplace @javascript
Feature: Viewing and modifying appointment attendees
  In order to work with appointments
  As a teacher
  I need to be able to take attendance and modify attendees

  Background:
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
      | appointment      | details | capacity | allowwaitlist | timestart1           | timefinish1          |
      | Test appointment | SES1    | 2        | 1             | ##2000-01-01 9:40##  | ##2000-01-01 10:00## |
      | Test appointment | SES2    | 2        | 1             | ##2033-01-01 10:40## | ##2033-01-01 11:00## |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details | capacity | allowwaitlist |
      | Test appointment | SES3    | 2        | 1             |

  Scenario: Teacher can manually add appointment attendees above capacity
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Booked" in the "Student First" "table_row"
    And I follow "Add/remove attendees"
    And the "removeselect" select box should contain "Student First"
    And I set the field "addselect" to "Student Second"
    And I press "Add"
    And I set the field "addselect" to "Student Third"
    And I press "Add"
    And I follow "Go back"
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Second" "table_row"
    And I should see "Booked" in the "Student Third" "table_row"

  Scenario: Teacher can manually add appointment attendees respecting capacity
    Given the following "permission overrides" exist:
      | capability               | permission | role           | contextlevel | reference |
      | mod/appointment:overbook | Prevent    | editingteacher | Course       | C1        |
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "9:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Booked" in the "Student First" "table_row"
    And I follow "Add/remove attendees"
    And the "removeselect" select box should contain "Student First"
    And I set the field "addselect" to "Student Second"
    And I press "Add"
    And I set the field "addselect" to "Student Third"
    And I press "Add"
    And I should see "Date is fully occupied"
    And I follow "Go back"
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Second" "table_row"
    And I should not see "Student Third"

  Scenario: Teacher can add attendees to a session without dates
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "Not set" "table_row"
    And I choose "Attendees" in the open action menu
    Then I should see "Not set" in the "Full description of the current session." "table"
    And I follow "Add/remove attendees"
    And I set the field "addselect" to "Student Second"
    And I press "Add"
    And I follow "Go back"
    And I should see "Wait-listed" in the "Student Second" "table_row"

  Scenario: Teacher can remove appointment attendees and move up waitlist
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES2    | student1 | booked     |
      | SES2    | student2 | booked     |
      | SES2    | student3 | waitlisted |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "10:40" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Second" "table_row"
    And I should see "Wait-listed" in the "Student Third" "table_row"
    And I follow "Add/remove attendees"
    And I set the field "removeselect" to "Student Second"
    And I press "Remove"
    And I follow "Go back"
    And I should not see "Student Second" in the "People planning on or having attended this session." "table"
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Third" "table_row"
    And I should see "Student Second" in the "List of people who have cancelled their session signups." "table"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: Teacher can take appointment attendance
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
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Second" "table_row"
    And I should see "Wait-listed" in the "Student Third" "table_row"
    And I follow "Take attendance"
    And I should see "Booked" in the "Student First" "table_row"
    And I should see "Booked" in the "Student Second" "table_row"
    And I should see "Wait-listed" in the "Student Third" "table_row"
    And I set the field "Take attendance" in the "Student First" "table_row" to "Partially attended"
    And I set the field "Take attendance" in the "Student Second" "table_row" to "No show"
    And I set the field "Take attendance" in the "Student Third" "table_row" to "Fully attended"
    And I press "Save attendance"
    And I should see "Partially attended" in the "Student First" "table_row"
    And I should see "No show" in the "Student Second" "table_row"
    And I should see "Fully attended" in the "Student Third" "table_row"
    And I press "Cancel"
    # Make sure we are back on "Attendees" page
    And "Take attendance" "link" should exist
    And "Add/remove attendees" "link" should exist
    And I should see "Partially attended" in the "Student First" "table_row"
    And I should see "No show" in the "Student Second" "table_row"
    And I should see "Fully attended" in the "Student Third" "table_row"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: User who was marked as no-show can rebook future sessions
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
    And the following "mod_appointment > attendance" exist:
      | session | user     | status     |
      | SES1    | student1 | no_show    |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "Book" "button" in the "Saturday, 1 January 2033" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I should see "Booked" in the "Saturday, 1 January 2033" "table_row"

  Scenario: Teacher can add user as attendee if they were previously marked as no-show
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
    And the following "mod_appointment > attendance" exist:
      | session | user     | status     |
      | SES1    | student1 | no_show    |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I wait "90" seconds
    Then I click on "Actions menu" "link" in the "Saturday, 1 January 2033" "table_row"
    And I choose "Attendees" in the open action menu
    And I follow "Add/remove attendees"
    And I set the field "addselect" to "Student First"
    And I press "Add"
    And I follow "Go back"
    And I should see "Booked" in the "Student First" "table_row"
