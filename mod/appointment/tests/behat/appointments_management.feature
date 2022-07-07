@mod @mod_appointment @moodleworkplace @javascript
Feature: Manage appointments

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "activities" exist:
      | activity    | name                           | intro            | course | idnumber     | showoncalendar |
      | appointment | Test appointment               | Appointment desc | C1     | appointment1 | 1              |
      | appointment | Test appointment no calendar   | Appointment desc | C1     | appointment2 | 0              |
      | appointment | Test appointment site calendar | Appointment desc | C1     | appointment3 | 2              |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
      | student1 | Rudolph   | Ritchie  | rudolph@example.com  |
      | student2 | Lionel    | Reindeer | lionel@example.com   |
      | student3 | Din       | Djarin   | mandal@example.com   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student3 | C1     | student        |

  Scenario: Add single appointment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
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
    Then I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "1:00"
    And I should see "2:00"
    And I should see "3:00"
    And I should see "4:00"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
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
    And I set the field "starttime[1][minute]" to "05"
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: Add single appointment using invalid time and observe error.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Date                 | ##tomorrow## |
      | starttime[0][hour]   | 04           |
      | starttime[0][minute] | 00           |
      | endtime[0][hour]     | 03           |
      | endtime[0][minute]   | 00           |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "Session start time is later than session end time."
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | starttime[0][hour]   | 03           |
      | starttime[0][minute] | 00           |
      | endtime[0][hour]     | 04           |
      | endtime[0][minute]   | 00           |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "3:00"
    And I should see "4:00"
    And I log out

  Scenario: Add multiple appointments
    When I log in as "teacher1"
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
      | split[0][timeunit]   | minutes         |
      | break[0][timeunit]   | minutes         |
      | startdate[1][day]    | ##tomorrow##%d## |
      | startdate[1][month]  | ##tomorrow##%B## |
      | startdate[1][year]   | ##tomorrow##%Y## |
      | starttime[1][hour]   | 03              |
      | starttime[1][minute] | 00              |
      | endtime[1][hour]     | 04              |
      | endtime[1][minute]   | 00              |
      | split[1][number]     | 10              |
      | split[1][timeunit]   | minutes         |
      | break[1][number]     | 10              |
      | break[1][timeunit]   | minutes         |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    Then I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "1:00"
    And I should see "1:15"
    And I should see "1:20"
    And I should see "1:35"
    And I should see "1:40"
    And I should see "1:55"
    And I should see "3:00"
    And I should see "3:10"
    And I should see "3:20"
    And I should see "3:30"
    And I should see "3:40"
    And I should see "3:50"
    And I should not see "4:00"
    And I should not see "4:10"
    And I log out

  Scenario: Add multiple appointments using invalid time and observe error.
    When I log in as "teacher1"
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
      | startdate[1][day]    | ##tomorrow##%d## |
      | startdate[1][month]  | ##tomorrow##%B## |
      | startdate[1][year]   | ##tomorrow##%Y## |
      | starttime[1][hour]   | 04              |
      | starttime[1][minute] | 00              |
      | endtime[1][hour]     | 03              |
      | endtime[1][minute]   | 00              |
      | split[1][number]     | 10              |
      | split[1][timeunit]   | minutes         |
      | break[1][number]     | 10              |
      | break[1][timeunit]   | minutes         |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    Then I should see "Session start time is later than session end time."
    And I set the following fields in the "Adding appointments" "dialogue" to these values:
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
      | split[1][number]     | 10 |
      | split[1][timeunit]   | hours |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    Then I should see "Session split time exceeds session duration."
    And I set the following fields in the "Adding appointments" "dialogue" to these values:
      | split[1][number]     | 10 |
      | split[1][timeunit]   | minutes |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    Then I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "1:00"
    And I should see "1:15"
    And I should see "1:20"
    And I should see "1:35"
    And I should see "1:40"
    And I should see "1:55"
    And I should see "3:00"
    And I should see "3:10"
    And I should see "3:20"
    And I should see "3:30"
    And I should see "3:40"
    And I should see "3:50"
    And I log out

  Scenario: Add appointment without dates
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I press "Delete session"
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "Not set" in the "0 / 10" "table_row"
    Then I click on "Actions menu" "link" in the "Not set" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    And I set the field "Capacity" to "5"
    And "startdate[0][day]" "field" should not exist
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    Then I should see "Not set" in the "0 / 5" "table_row"

  Scenario: Test appointment pagination
    Given I am on the "Course 1" "course" page logged in as "teacher1"
    And I follow "Test appointment"
    And I follow "Add"
    And I follow "Multiple appointments"
    # Create a bunch of appointments, such that they are spread over multiple pages of the report.
    And I set the following fields in the "Adding appointments" "dialogue" to these values:
      | Date                 | ##tomorrow##    |
      | starttime[0][hour]   | 00              |
      | starttime[0][minute] | 00              |
      | endtime[0][hour]     | 10              |
      | endtime[0][minute]   | 00              |
      | split[0][number]     | 10              |
      | split[0][timeunit]   | minutes         |
      | break[0][number]     | 5               |
      | break[0][timeunit]   | minutes         |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    # Switch to second page, assert we remain on same page after editing.
    When I click on "2" "link" in the "nav.pagination" "css_element"
    And I click on "Actions menu" "link" in the "4:45" "table_row"
    And I choose "Settings" in the open action menu
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    Then I should see "4:45" in the "reportbuilder-table" "table"
    # Now delete the same session, assert we remain on same page.
    And I click on "Actions menu" "link" in the "4:45" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "4:45" in the "reportbuilder-table" "table"
    But I should see "4:30" in the "reportbuilder-table" "table"

  Scenario: Delete appointment session
    Given the following "mod_appointment > session" exists:
      | appointment | Test appointment   |
      | timestart1  | ##tomorrow 01:00## |
      | timefinish1 | ##tomorrow 02:00## |
      | details     | SES1               |
    And the following "mod_appointment > signups" exist:
      | session | user     | status     |
      | SES1    | student1 | booked     |
      | SES1    | student3 | waitlisted |
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I follow "Test appointment"
    And I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Delete" in the open action menu
    And I should see "Are you completely sure you want to delete this appointment" in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should see "Nothing to display"
    And I log out
    # Check there are no debugging messages in logs
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"

  Scenario: Duplicate appointment
    Given the following "mod_appointment > session" exists:
      | appointment | Test appointment |
      | timestart1  | ##today 01:00##  |
      | timefinish1 | ##today 02:00##  |
      | timestart2  | ##today 03:00##  |
      | timefinish2 | ##today 04:00##  |
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
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I follow "Test appointment"
    # Edit session to populate custom field.
    And I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    And I set the field "Example field" in the "Editing appointment" "dialogue" to "Some example text"
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    # Now duplicate the appointment sessions.
    And I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Duplicate" in the open action menu
    And I set the following fields in the "Duplicate appointment" "dialogue" to these values:
      | Date              | ##tomorrow## |
      | starttime[hour]   | 06           |
      | starttime[minute] | 00           |
    And I click on "Duplicate" "button" in the "Duplicate appointment" "dialogue"
    # Check existing (first row).
    Then I should see "##today##%A, %d %B %Y##" in the ".reportbuilder-table tbody tr:first-of-type" "css_element"
    And I should see "1:00" in the "##today##%A, %d %B %Y##" "table_row"
    And I should see "2:00" in the "##today##%A, %d %B %Y##" "table_row"
    And I should see "3:00" in the "##today##%A, %d %B %Y##" "table_row"
    And I should see "4:00" in the "##today##%A, %d %B %Y##" "table_row"
    And I click on "Details" "button" in the "##today##%A, %d %B %Y##" "table_row"
    And I should see "Some example text" in the "Details" "dialogue"
    And I click on "Cancel" "button" in the "Details" "dialogue"
    # Check duplicated (second row).
    And I should see "##tomorrow##%A, %d %B %Y##" in the ".reportbuilder-table tbody tr:nth-of-type(2)" "css_element"
    And I should see "6:00" in the "##tomorrow##%A, %d %B %Y##" "table_row"
    And I should see "7:00" in the "##tomorrow##%A, %d %B %Y##" "table_row"
    And I should see "8:00" in the "##tomorrow##%A, %d %B %Y##" "table_row"
    And I should see "9:00" in the "##tomorrow##%A, %d %B %Y##" "table_row"
    And I click on "Details" "button" in the "##tomorrow##%A, %d %B %Y##" "table_row"
    And I should see "Some example text" in the "Details" "dialogue"
    And I click on "Cancel" "button" in the "Details" "dialogue"

  Scenario: Edit appointment messages
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I navigate to "Customised notifications" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | confirmationsubject               | Confirmation subject |
      | confirmationmessage_editor[text]  | Confirmation message |
      | remindersubject                   | Reminder subject     |
      | remindermessage_editor[text]      | Reminder message     |
      | waitlistedsubject                 | Waitlist subject     |
      | waitlistedmessage_editor[text]    | Waitlist message     |
      | cancellationsubject               | Cancellation subject |
      | cancellationmessage_editor[text]  | Cancellation message |
    And I press "Save changes"
    And I navigate to "Customised notifications" in current page administration
    And I expand all fieldsets
    Then the following fields match these values:
      | confirmationsubject               | Confirmation subject |
      | confirmationmessage_editor[text]  | Confirmation message |
      | remindersubject                   | Reminder subject     |
      | remindermessage_editor[text]      | Reminder message     |
      | waitlistedsubject                 | Waitlist subject     |
      | waitlistedmessage_editor[text]    | Waitlist message     |
      | cancellationsubject               | Cancellation subject |
      | cancellationmessage_editor[text]  | Cancellation message |

  Scenario: Filter appointment sessions
    Given the following "mod_appointment > sessions" exist:
      | appointment      | timestart1        | timefinish1       |
      | Test appointment | ##-2 days 13:00## | ##-2 days 14:00## |
      | Test appointment | ##+2 days 13:00## | ##+2 days 14:00## |
    # Now create a session with multiple dates (one in the past, one in the future).
    And the following "mod_appointment > session" exists:
      | appointment | Test appointment    |
      | timestart1  | ##yesterday 13:00## |
      | timefinish1 | ##yesterday 14:00## |
      | timestart2  | ##tomorrow 13:00##  |
      | timefinish2 | ##tomorrow 14:00##  |
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I change window size to "large"
    And I follow "Test appointment"
    And I should see "##-2 days##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    And I should see "##yesterday##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    And I should see "##tomorrow##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    And I should see "##+2 days##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I set the following fields in the "Session start date" "core_reportbuilder > Filter" to these values:
      | Session start date operator | Next   |
      | Session start date value    | 3      |
      | Session start date unit     | day(s) |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should not see "##-2 days##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    # We should see yesterdays date, because the session it belongs to also has a date tomorrow.
    And I should see "##yesterday##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    And I should see "##tomorrow##%A, %d %B %Y##" in the "reportbuilder-table" "table"
    And I should see "##+2 days##%A, %d %B %Y##" in the "reportbuilder-table" "table"

  Scenario: Add attendees manually shows only users enroled to the course
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
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
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Attendees" in the open action menu
    And I click on "Add/remove attendees" "link"
    Then I should see "Rudolph Ritchie"
    And I should not see "Lionel Reindeer"
    And I should see "Din Djarin"
    And I log out

  Scenario: Add single appointment with course calendar and observe it.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
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
    # Navigate to the calendar and view events for tomorrow, they may be in this or the following calendar month.
    And I am viewing site calendar
    And I press "Month"
    And I follow "Upcoming events"
    And I follow "Tomorrow"
    And I should see "Course event"
    And I should see "1:00"
    And I should see "2:00"
    And I should see "3:00"
    And I should see "4:00"
    And I should see "Course 1"
    And "Site event" "icon" should not exist
    And I log out

  Scenario: Add single appointment with site calendar and observe it.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment site calendar"
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
    # Navigate to the calendar and view events for tomorrow, they may be in this or the following calendar month.
    And I am viewing site calendar
    And I press "Month"
    And I follow "Upcoming events"
    And I follow "Tomorrow"
    And "Site event" "icon" should exist
    And I should see "1:00"
    And I should see "2:00"
    And I should see "3:00"
    And I should see "4:00"
    And I should not see "Course event"
    And I log out

  Scenario: Add single appointment with calendar disabled and check it is not listed.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment no calendar"
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
    # Navigate to the calendar and view events for tomorrow, they may be in this or the following calendar month.
    And I am viewing site calendar
    And I press "Month"
    And I follow "Upcoming events"
    And I should see "There are no upcoming events"
    And I log out
