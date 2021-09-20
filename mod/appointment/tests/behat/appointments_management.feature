@mod @mod_appointment @moodleworkplace @javascript
Feature: Manage appointments

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | wplist |
    And the following "activities" exist:
      | activity    | name                           | intro            | course | idnumber     | showoncalendar |
      | appointment | Test appointment               | Appointment desc | C1     | appointment1 | 1              |
      | appointment | Test appointment no calendar   | Appointment desc | C1     | appointment2 | 0              |
      | appointment | Test appointment site calendar | Appointment desc | C1     | appointment3 | 2              |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Calendar" block
    And I log out

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
    And I click on "Cancel" "button" in the "Editing appointment" "dialogue"
    And I log out

  Scenario: Add single appointment using invalid time and observe error.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Date                 | ##tomorrow## |
      | Start time           | ##04:00##    |
      | End time             | ##03:00##    |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "Session start time is later than session end time."
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Start time           | ##03:00##    |
      | End time             | ##04:00##    |
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

  Scenario: Test pagination
    Given I change window size to "large"
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And "nav.pagination" "css_element" should not exist in the ".tool_reportbuilder_report" "css_element"
    And I follow "Add"
    And I follow "Multiple appointments"
    And I set the following fields in the "Adding appointments" "dialogue" to these values:
      | Date                 | ##tomorrow##    |
      | Start time           | ##00:00##       |
      | End time             | ##10:00##       |
      | split[0][number]     | 10              |
      | split[0][timeunit]   | minutes         |
      | break[0][number]     | 5               |
      | break[0][timeunit]   | minutes         |
    And I click on "Save" "button" in the "Adding appointments" "dialogue"
    And "nav.pagination" "css_element" should exist in the ".tool_reportbuilder_report" "css_element"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "3" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "4" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "5" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    Then I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Same page after reload.
    And I click on "Actions menu" "link" in the "4:45" "table_row"
    And I choose "Settings" in the open action menu
    And I click on "Save" "button" in the "Editing appointment" "dialogue"
    And I should not see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Add one more session.
    Then I click on "4" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "4" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Date                 | ##tomorrow## |
      | Start time           | ##20:00##    |
      | End time             | ##21:00##    |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    # Test we move to previous page when single item on the page is deleted.
    And I should see "5" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    Then I click on "5" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "5" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I click on "Actions menu" "link" in the "8:00" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "5" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "4" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"

  Scenario: Delete appointment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | Date                 | ##tomorrow## |
      | Start time           | ##01:00##    |
      | End time             | ##02:00##    |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "1:00"
    And I should see "2:00"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Delete" in the open action menu
    And I should see "Are you completely sure you want to delete this appointment" in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "##tomorrow##%A, %d %B %Y##"
    And I should not see "1:00"
    And I should not see "2:00"
    And "table.sessions-list tbody tr:first-child" "css_element" should not exist
    And I log out

  Scenario: Duplicate appointment
    # First, add a custom field.
    When I log in as "admin"
    And I navigate to "Plugins > Appointment custom fields" in site administration
    And I press "Add a new category"
    And I follow "Add a new custom field"
    And I choose "Short text" in the open action menu
    And I set the following fields to these values:
      | Name | Example field |
      | Short name | example |
    And I press "Save changes"
    And I log out
    # Now create the appointment sessions.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I expand all fieldsets
    And I press "Add session"
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | startdate[0][day]    | ##today##%d## |
      | startdate[0][month]  | ##today##%B## |
      | startdate[0][year]   | ##today##%Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##today##%d## |
      | startdate[1][month]  | ##today##%B## |
      | startdate[1][year]   | ##today##%Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
      | Example field        | Some example text |
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "##today##%A, %d %B %Y##" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "1:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "2:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "3:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "4:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    Then I click on "Actions menu" "link" in the "1:00" "table_row"
    And I choose "Duplicate" in the open action menu
    And I set the following fields in the "Duplicate appointment" "dialogue" to these values:
      | Date                 | ##tomorrow## |
      | Start time           | ##06:00##    |
    And I click on "Duplicate" "button" in the "Duplicate appointment" "dialogue"
    # Check existing
    Then I should see "##today##%A, %d %B %Y##" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "1:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "2:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "3:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "4:00" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I click on "Details" "button" in the "table.sessions-list tbody tr:first-of-type" "css_element"
    And I should see "Some example text"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    # Check duplicated
    Then I should see "##tomorrow##%A, %d %B %Y##" in the "table.sessions-list tbody tr:nth-of-type(2)" "css_element"
    And I should see "6:00" in the "table.sessions-list tbody tr:nth-of-type(2)" "css_element"
    And I should see "7:00" in the "table.sessions-list tbody tr:nth-of-type(2)" "css_element"
    And I should see "8:00" in the "table.sessions-list tbody tr:nth-of-type(2)" "css_element"
    And I should see "9:00" in the "table.sessions-list tbody tr:nth-of-type(2)" "css_element"
    Then I click on "Details" "button" in the "6:00" "table_row"
    And I should see "Some example text"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    And I log out

  Scenario: Edit appointment messages
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I click on "Actions menu" "link"
    And I choose "Customised notifications" in the open action menu
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
    And I click on "Actions menu" "link"
    And I choose "Customised notifications" in the open action menu
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

  Scenario: Add attendees manually shows only users enroled to the course
    Given the following "users" exist:
      | username    | firstname  | lastname  | email               |
      | student1    | Rudolph    | Ritchie   | rudolph@example.com |
      | student2    | Lionel     | Reindeer  | lionel@example.com  |
      | student3    | Din        | Djarin    | mandal@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role      |
      | student1 | C1     | student   |
      | student3 | C1     | student   |
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
    And I am on "Course 1" course homepage
    And I click on "This month" "link" in the "Calendar" "block"
    # Navigate to tomorrow's month (e.g. "November 2019"), then click on tomorrow's date (20) in the calendar.
    And I click on "##tomorrow##%B %Y##" "link" in the "Monthly view" "block"
    And I click on "##tomorrow##%d##" "link" in the "calendarmonth" "table"
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
    And I am on "Course 1" course homepage
    And I click on "This month" "link" in the "Calendar" "block"
    # Navigate to tomorrow's month (e.g. "November 2019"), then click on tomorrow's date (20) in the calendar.
    And I click on "##tomorrow##%B %Y##" "link" in the "Monthly view" "block"
    And I click on "##tomorrow##%d##" "link" in the "calendarmonth" "table"
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
    And I am on "Course 1" course homepage
    And I click on "This month" "link" in the "Calendar" "block"
    # Navigate to tomorrow's month (e.g. "November 2019"), then click on tomorrow's date (20) in the calendar.
    And I click on "##tomorrow##%B %Y##" "link" in the "Monthly view" "block"
    And "##tomorrow##%d##" "link" should not exist in the "calendarmonth" "table"
    And I log out
