@mod @mod_appointment @moodleworkplace @javascript
Feature: Book appointments

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber     |
      | appointment | Test appointment | Appointment desc | C1     | appointment1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First  | teacher1@example.com |
      | student1 | Student | First  | student1@example.com |
      | student2 | Student | Second | student2@example.com |
      | student3 | Student | Third  | student3@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
    Then I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I follow "Multiple appointments"
    And I press "Add timeframe"
    And I set the following fields in the "Adding appointments" "dialogue" to these values:
      | startdate[0][day]    | ##tomorrow##%d## |
      | startdate[0][month]  | ##tomorrow##%B## |
      | startdate[0][year]   | ##tomorrow##%Y## |
      | starttime[0][hour]   | 01              |
      | starttime[0][minute] | 00              |
      | endtime[0][hour]     | 02              |
      | endtime[0][minute]   | 00              |
      | split[0][number]     | 0               |
      | split[0][timeunit]   | minutes         |
      | startdate[1][day]    | ##+2 days##%d##  |
      | startdate[1][month]  | ##+2 days##%B##  |
      | startdate[1][year]   | ##+2 days##%Y##  |
      | starttime[1][hour]   | 03              |
      | starttime[1][minute] | 00              |
      | endtime[1][hour]     | 04              |
      | endtime[1][minute]   | 00              |
      | split[1][number]     | 30              |
      | split[1][timeunit]   | minutes         |
      | break[1][number]     | 0               |
      | break[1][timeunit]   | minutes         |
      | capacity             | 2               |
      | allowwaitlist        | 0               |
      | allowcancellations   | 0               |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    And I log out

  Scenario: Test booking
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I should see "##tomorrow##%A, %d %B %Y##" in the "Details" "dialogue"
    And I should see "1:00" in the "Details" "dialogue"
    And I should see "Open" in the "Details" "dialogue"
    And I click on "Book" "button" in the "Details" "dialogue"
    And "Book" "button" should not exist in the "1:00" "table_row"
    And "Cancel" "button" should not exist in the "1:00" "table_row"
    And I should not see "Open" in the "1:00" "table_row"
    And I should see "Booked" in the "1:00" "table_row"
    And I click on "Details" "button" in the "1:00" "table_row"
    And I should see "Booked" in the "Details" "dialogue"
    And I should not see "Open" in the "Details" "dialogue"
    And I click on "Close" "button" in the "Details" "dialogue"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: Test booking cancellation
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I set the following fields in the "Editing appointment" "dialogue" to these values:
      | allowcancellations | 1 |
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And "Book" "button" should not exist in the "1:00" "table_row"
    And "Cancel" "button" should exist in the "1:00" "table_row"
    And I should not see "Open" in the "1:00" "table_row"
    And I should see "Booked" in the "1:00" "table_row"
    And I click on "Cancel" "button" in the "1:00" "table_row"
    And I click on "Confirm cancellation" "button" in the "Cancel booking" "dialogue"
    And I should see "Open" in the "1:00" "table_row"
    And "Book" "button" should exist in the "1:00" "table_row"
    And "Cancel" "button" should not exist in the "1:00" "table_row"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: Test booking cancellation for session in the past
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I set the following fields in the "Editing appointment" "dialogue" to these values:
      | allowcancellations | 1 |
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And "Book" "button" should not exist in the "1:00" "table_row"
    And "Cancel" "button" should exist in the "1:00" "table_row"
    And I should not see "Open" in the "1:00" "table_row"
    And I should see "Booked" in the "1:00" "table_row"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I set the following fields in the "Editing appointment" "dialogue" to these values:
      | Date                 | ##-3 days## |
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And "Book" "button" should not exist in the "1:00" "table_row"
    And "Cancel" "button" should not exist in the "1:00" "table_row"
    And I should see "Finished" in the "1:00" "table_row"
    And I log out

  Scenario: Test booking cancellation is not possible when cancellation is disabled
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And "Book" "button" should not exist in the "1:00" "table_row"
    And "Cancel" "button" should not exist in the "1:00" "table_row"
    And I log out

  Scenario: Test booking through calendar
    When I log in as "student1"
    # Navigate to the calendar and view events for tomorrow, they may be in this or the following calendar month.
    And I am viewing site calendar
    And I press "Month"
    And I follow "Upcoming events"
    And I follow "Tomorrow"
    And I should see "1:00"
    And I should see "2:00"
    And I follow "Sign-up for this Appointment session"
    And I should see "##tomorrow##%A, %d %B %Y##" in the "Details" "dialogue"
    And I should see "1:00" in the "Details" "dialogue"
    And I should see "Open" in the "Details" "dialogue"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I should see "Booked" in the "1:00" "table_row"
    And I click on "Details" "button" in the "1:00" "table_row"
    And I should see "Booked" in the "Details" "dialogue"
    And I should not see "Open" in the "Details" "dialogue"
    And I click on "Close" "button" in the "Details" "dialogue"
    And I log out

  Scenario: Test overbooking is not possible
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I log out
    Then I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I log out
    Then I log in as "student3"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Full" in the "1:00" "table_row"
    And "Book" "button" should not exist in the "1:00" "table_row"
    And I log out

  Scenario: Test overbooking is possible
    Then I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I set the following fields in the "Editing appointment" "dialogue" to these values:
      | allowwaitlist | 1 |
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I log out
    Then I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Open" in the "1:00" "table_row"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I log out
    Then I log in as "student3"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I should see "1:00"
    And I should see "Full" in the "1:00" "table_row"
    And I click on "Join waitlist" "button" in the "1:00" "table_row"
    And I should see "1:00" in the "Details" "dialogue"
    And I should see "Full" in the "Details" "dialogue"
    And I click on "Join waitlist" "button" in the "Details" "dialogue"
    And I should not see "Full" in the "1:00" "table_row"
    And I should see "Wait-listed" in the "1:00" "table_row"
    And I click on "Details" "button" in the "1:00" "table_row"
    And I should see "Wait-listed" in the "Details" "dialogue"
    And I click on "Close" "button" in the "Details" "dialogue"
    And I log out

  Scenario: Test attendees list
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I set the following fields in the "Editing appointment" "dialogue" to these values:
      | allowcancellations | 1 |
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "Book" "button" in the "1:00" "table_row"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I press "Cancel"
    And I set the field "Reason for cancellation" to "Short on time"
    And I click on "Confirm cancellation" "button" in the "Cancel booking" "dialogue"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Attendees" in the open action menu
    And I should see "Student First" in the "People planning on or having attended this session." "table"
    And I should see "Student Second" in the "List of people who have cancelled their session signups." "table"
    And I should see "Short on time" in the "Student Second" "table_row"
    And I log out
