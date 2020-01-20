@mod @mod_appointment @moodleworkplace @javascript
Feature: Manage appointments

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | wplist |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber     |
      | appointment | Test appointment | Appointment desc | C1     | appointment1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

  Scenario: Add single appointment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I press "Add session"
    And I set the following visible fields to these values:
      | startdate[0][day]    | ##tomorrow##j## |
      | startdate[0][month]  | ##tomorrow##n## |
      | startdate[0][year]   | ##tomorrow##Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##tomorrow##j## |
      | startdate[1][month]  | ##tomorrow##n## |
      | startdate[1][year]   | ##tomorrow##Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
    And I press "Save" in the modal form dialogue
    Then I should see "##tomorrow##l, j F Y##"
    And I should see "1:00"
    And I should see "2:00"
    And I should see "##tomorrow##l, j F Y##"
    And I should see "3:00"
    And I should see "4:00"
    Then I click on "Actions menu" "link" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I choose "Settings" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
      | startdate[0][day]    | ##tomorrow##j## |
      | startdate[0][month]  | ##tomorrow##n## |
      | startdate[0][year]   | ##tomorrow##Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##tomorrow##j## |
      | startdate[1][month]  | ##tomorrow##n## |
      | startdate[1][year]   | ##tomorrow##Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
    And I press "Cancel" in the modal form dialogue
    And I log out

  Scenario: Add multiple appointments
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I follow "Multiple appointments"
    And I press "Add timeframe"
    And I set the following visible fields to these values:
      | startdate[0][day]    | ##tomorrow##j## |
      | startdate[0][month]  | ##tomorrow##n## |
      | startdate[0][year]   | ##tomorrow##Y## |
      | starttime[0][hour]   | 01              |
      | starttime[0][minute] | 00              |
      | endtime[0][hour]     | 02              |
      | endtime[0][minute]   | 00              |
      | split[0][timeunit]   | minutes         |
      | break[0][timeunit]   | minutes         |
      | startdate[1][day]    | ##tomorrow##j## |
      | startdate[1][month]  | ##tomorrow##n## |
      | startdate[1][year]   | ##tomorrow##Y## |
      | starttime[1][hour]   | 03              |
      | starttime[1][minute] | 00              |
      | endtime[1][hour]     | 04              |
      | endtime[1][minute]   | 00              |
      | split[1][number]     | 10              |
      | split[1][timeunit]   | minutes         |
      | split[1][enabled]    | 1               |
      | break[1][number]     | 10              |
      | break[1][timeunit]   | minutes         |
      | break[1][enabled]    | 1               |
    And I press "Save" in the modal form dialogue
    Then I should see "##tomorrow##l, j F Y##"
    And I should see "1:00"
    And I should see "1:15"
    And I should see "1:20"
    And I should see "1:35"
    And I should see "1:40"
    And I should see "1:55"
    And I should see "##tomorrow##l, j F Y##"
    And I should see "3:00"
    And I should see "3:10"
    And I should see "3:20"
    And I should see "3:30"
    And I should see "3:40"
    And I should see "3:50"
    And I should not see "4:00"
    And I should not see "4:10"
    And I log out

  Scenario: Delete appointment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I set the following visible fields to these values:
      | startdate[0][day]    | ##tomorrow##j## |
      | startdate[0][month]  | ##tomorrow##n## |
      | startdate[0][year]   | ##tomorrow##Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
    And I press "Save" in the modal form dialogue
    Then I should see "##tomorrow##l, j F Y##"
    And I should see "1:00"
    And I should see "2:00"
    Then I click on "Actions menu" "link" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I choose "Delete" in the open action menu
    And I should see "Are you completely sure you want to delete this appointment" in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "##tomorrow##l, j F Y##"
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
    And I set the following visible fields to these values:
      | Name | Example field |
      | Short name | example |
    And I press "Save changes"
    And I log out
    # Now create the appointment sessions.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I expand all fieldsets
    And I press "Add session"
    And I set the following visible fields to these values:
      | startdate[0][day]    | ##today##j## |
      | startdate[0][month]  | ##today##n## |
      | startdate[0][year]   | ##today##Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##today##j## |
      | startdate[1][month]  | ##today##n## |
      | startdate[1][year]   | ##today##Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
      | Example field        | Some example text |
    And I press "Save" in the modal form dialogue
    Then I should see "##today##l, j F Y##" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "1:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "2:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "##today##l, j F Y##" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "3:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "4:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    Then I click on "Actions menu" "link" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I choose "Duplicate" in the open action menu
    And I set the following visible fields to these values:
      | startdate[day]    | ##tomorrow##j## |
      | startdate[month]  | ##tomorrow##n## |
      | startdate[year]   | ##tomorrow##Y## |
      | starttime[hour]   | 06 |
      | starttime[minute] | 00 |
    And I press "Duplicate" in the modal form dialogue
    # Check existing
    Then I should see "##today##l, j F Y##" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "1:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "2:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "##today##l, j F Y##" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "3:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "4:00" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I click on "Details" "button" in the "table.sessions-list tbody tr:first-child" "css_element"
    And I should see "Some example text"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    # Check duplicated
    Then I should see "##tomorrow##l, j F Y##" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I should see "6:00" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I should see "7:00" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I should see "##tomorrow##l, j F Y##" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I should see "8:00" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I should see "9:00" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I click on "Details" "button" in the "table.sessions-list tbody tr:nth-child(2)" "css_element"
    And I should see "Some example text"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    And I log out

  Scenario: Edit appointment messages
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I follow "Test appointment"
    And I click on "Actions menu" "link"
    And I choose "Customised notifications" in the open action menu
    And I expand all fieldsets
    And I set the following fields to these values:
      | confirmationsubject | Confirmation subject |
      | confirmationmessage | Confirmation message |
      | remindersubject     | Reminder subject     |
      | remindermessage     | Reminder message     |
      | waitlistedsubject   | Waitlist subject     |
      | waitlistedmessage   | Waitlist message     |
      | cancellationsubject | Cancellation subject |
      | cancellationmessage | Cancellation message |
    And I press "Save changes"
    And I click on "Actions menu" "link"
    And I choose "Customised notifications" in the open action menu
    And I expand all fieldsets
    Then the following fields match these values:
      | confirmationsubject | Confirmation subject |
      | confirmationmessage | Confirmation message |
      | remindersubject     | Reminder subject     |
      | remindermessage     | Reminder message     |
      | waitlistedsubject   | Waitlist subject     |
      | waitlistedmessage   | Waitlist message     |
      | cancellationsubject | Cancellation subject |
      | cancellationmessage | Cancellation message |
