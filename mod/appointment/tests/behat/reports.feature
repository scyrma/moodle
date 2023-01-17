@mod @mod_appointment @moodleworkplace @javascript
Feature: Student reports in Appointment module
  In order to work with appointments
  As a student and teacher
  I need to view appointments on the course page and in the course reports

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | 0        | 1                | 1                        |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber     | showoncalendar | completion | completionview | completionbooked |
      | appointment | Test appointment | Appointment desc | C1     | appointment1 | 1              | 2          | 0              | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | Main     | teacher1@example.com |
      | student1 | Student   | First    | student1@example.com |
      | student2 | Student   | Second   | student2@example.com |
      | student3 | Student   | Third    | student3@example.com |
      | student4 | Student   | Fourth   | student4@example.com |
      | student5 | Student   | Fifth    | student5@example.com |
      | student6 | Student   | Sixth    | student6@example.com |
      | student7 | Student   | Seventh  | student7@example.com |
      | student8 | Student   | Eighth   | student7@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
      | student5 | C1     | student        |
      | student6 | C1     | student        |
      | student7 | C1     | student        |
      | student8 | C1     | student        |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details       | capacity | allowwaitlist | allowcancellations | timestart1           | timefinish1          |
      | Test appointment | PastSession   | 5        | 1             | 1                  | ##2000-01-01 9:40##  | ##2000-01-01 10:00## |
      | Test appointment | FutureSession | 5        | 1             | 1                  | ##2033-01-01 10:40## | ##2033-01-02 11:00## |
    And the following "mod_appointment > sessions" exist:
      | appointment      | details       | capacity | allowwaitlist | allowcancellations |
      | Test appointment | EmptySession  | 5        | 1             | 1                  |
    And the following "mod_appointment > signups" exist:
      | session       | user     | status     |
      | PastSession   | student1 | booked     |
      | PastSession   | student2 | booked     |
      | PastSession   | student3 | booked     |
      | PastSession   | student4 | waitlisted |
      | FutureSession | student5 | booked     |
      | EmptySession  | student6 | waitlisted |
      | FutureSession | student8 | booked     |
    And the following "mod_appointment > attendance" exist:
      | session     | user     | status             |
      | PastSession | student1 | fully_attended     |
      | PastSession | student2 | partially_attended |
    And I am on the "Course 1" course page logged in as student8
    And I follow "Test appointment"
    And I press "Cancel"
    And I press "Confirm cancellation"
    And I log out

  Scenario: Viewing appointment dates on the course page
    And I am on the "Course 1" course page logged in as student1
    And I should see "Saturday, 1 January 2000, 9:40 AM - 10:00 AM" in the "Test appointment" "activity"
    And I should see "Done: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student2
    And I should see "Saturday, 1 January 2000, 9:40 AM - 10:00 AM" in the "Test appointment" "activity"
    And I should see "Done: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student3
    And I should see "Saturday, 1 January 2000, 9:40 AM - 10:00 AM" in the "Test appointment" "activity"
    And I should see "Done: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student4
    And I should see "Saturday, 1 January 2000, 9:40 AM - 10:00 AM" in the "Test appointment" "activity"
    And I should see "To do: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student5
    And I should see "Saturday, 1 January 2033 - Sunday, 2 January 2033, 10:40 AM - 11:00 AM" in the "Test appointment" "activity"
    And I should see "Done: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student6
    And I should see "Wait-listed" in the "Test appointment" "activity"
    And I should see "To do: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student7
    And I should see "9 seats available" in the "Test appointment" "activity"
    And I should see "To do: Book an appointment" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student8
    And I should see "9 seats available" in the "Test appointment" "activity"
    And I should see "To do: Book an appointment" in the "Test appointment" "activity"
    # View dates on the course page with the timezone
    Given the following config values are set as admin:
      | appointment_displaysessiontimezones | 1 |
    And I am on the "Course 1" course page logged in as student4
    And I should see "Saturday, 1 January 2000, 9:40 AM - 10:00 AM (time zone: Australia/Perth)" in the "Test appointment" "activity"
    And I am on the "Course 1" course page logged in as student5
    And I should see "Saturday, 1 January 2033 - Sunday, 2 January 2033, 10:40 AM - 11:00 AM (time zone: Australia/Perth)" in the "Test appointment" "activity"

  Scenario: Appointments in the outline report
    And I am on the "Course 1" course page logged in as teacher1
    And I navigate to course participants
    And I follow "Student First"
    And I follow "Outline report"
    And I should see "Grade: 100.00" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Second"
    And I follow "Outline report"
    And I should see "Grade: 50.00" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Third"
    And I follow "Outline report"
    And I should see "Status: signed up" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Fourth"
    And I follow "Outline report"
    And I should see "Status: signed up" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Fifth"
    And I follow "Outline report"
    And I should see "Status: signed up" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Sixth"
    And I follow "Outline report"
    And I should see "Status: signed up" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Seventh"
    And I follow "Outline report"
    And I should see "Status: not signed up" in the "Test appointment" "table_row"
    And I navigate to course participants
    And I follow "Student Eighth"
    And I follow "Outline report"
    And I should see "Status: not signed up" in the "Test appointment" "table_row"

  Scenario: Appointments in the outline report
    And I am on the "Course 1" course page logged in as teacher1
    And I navigate to course participants
    And I follow "Student First"
    And I follow "Complete report"
    And I should see "Grade: 100.00"
    And I should see "Fully attended"
    And I navigate to course participants
    And I follow "Student Second"
    And I follow "Complete report"
    And I should see "Grade: 50.00"
    And I should see "Partially attended"
    And I navigate to course participants
    And I follow "Student Third"
    And I follow "Complete report"
    And I should see "Grade: -"
    And I navigate to course participants
    And I follow "Student Fourth"
    And I follow "Complete report"
    And I should see "Grade: -"
    And I should see "Wait-listed"
    And I navigate to course participants
    And I follow "Student Fifth"
    And I follow "Complete report"
    And I should see "Grade: -"
    And I should see "User signed up on"
    And I navigate to course participants
    And I follow "Student Sixth"
    And I follow "Complete report"
    And I should see "Grade: -"
    And I should see "Wait-listed"
    And I navigate to course participants
    And I follow "Student Seventh"
    And I follow "Complete report"
    And I should see "Status: not signed up"
    And I should not see "Grade: -"
    And I navigate to course participants
    And I follow "Student Eighth"
    And I follow "Complete report"
    And I should see "Grade: -"
    And I should see "User cancelled on"
