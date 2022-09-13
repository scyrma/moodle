@mod @mod_appointment @moodleworkplace @javascript
Feature: Check appointment sessions on calendar

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "activities" exist:
      | activity    | name                | intro                    | course | idnumber     | showoncalendar |
      | appointment | Course appointment  | Course appointment desc  | C1     | appointment1 | 1              |
      | appointment | Site appointment    | Site appointment desc    | C1     | appointment2 | 2              |
      | appointment | Useless appointment | Useless appointment desc | C2     | appointment0 | 1              |
    And the following "mod_appointment > sessions" exist:
      | appointment         | timestart1                                  | timefinish1                                 |
      | Course appointment  | ##first day of this month 9:40##            | ##first day of this month 10:00##           |
      | Site appointment    | ##first day of this month 9:40 +24 hours##  | ##first day of this month 10:00 +24 hours## |
      # Useless appointment is needed to create sessions to be able to hover days for the real appointments.
      | Useless appointment | ##first day of this month 10:40##           | ##first day of this month 11:00##           |
      | Useless appointment | ##first day of this month 10:40 +24 hours## | ##first day of this month 11:00 +24 hours## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
      | student1 | Student   | First    | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | teacher1 | C2     | editingteacher |
      | student1 | C2     | student        |

  Scenario: Hide and show appointment with course calendar and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Course appointment" actions menu
    And I click on "Hide" "link" in the "Course appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should not see "Course appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Course appointment" actions menu
    And I click on "Show" "link" in the "Course appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I log out

  Scenario: Hide and show appointment with site calendar and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Site appointment" actions menu
    And I click on "Hide" "link" in the "Site appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should not see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Site appointment" actions menu
    And I click on "Show" "link" in the "Site appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out

  Scenario: Hide and show course and check appointments with course and site calendar events are present in calendar
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should not see "Course appointment"
    And I hover over day "2" of this month in the full calendar page
    And I should not see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Show"
    And I click on "Save and display" "button"
    And I log out
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out

  Scenario: Hide appointment with site calendar, hide course, show appointment with site calendar and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Site appointment" actions menu
    And I click on "Hide" "link" in the "Site appointment" activity
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I open "Site appointment" actions menu
    And I click on "Show" "link" in the "Site appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should not see "Site appointment"
    And I log out

  Scenario: Hide course, hide appointment with site calendar, show course and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I open "Site appointment" actions menu
    And I click on "Hide" "link" in the "Site appointment" activity
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Show"
    And I click on "Save and display" "button"
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should not see "Site appointment"
    And I log out

  Scenario: Hide course, hide appointment with site calendar, show appointment with site calendar and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should see "Site appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I open "Site appointment" actions menu
    And I click on "Hide" "link" in the "Site appointment" activity
    And I open "Site appointment" actions menu
    And I click on "Show" "link" in the "Site appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "2" of this month in the full calendar page
    And I should not see "Site appointment"
    And I log out

  Scenario: Hide appointment with course calendar, hide course, show appointment with course calendar and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Course appointment" actions menu
    And I click on "Hide" "link" in the "Course appointment" activity
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I open "Course appointment" actions menu
    And I click on "Show" "link" in the "Course appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should not see "Course appointment"
    And I log out

  Scenario: Hide course, hide appointment with course calendar, show course and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I open "Course appointment" actions menu
    And I click on "Hide" "link" in the "Course appointment" activity
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Show"
    And I click on "Save and display" "button"
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should not see "Course appointment"
    And I log out

  Scenario: Hide course, hide appointment with course calendar, show appointment with course calendar and check calendar events presence
    When I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should see "Course appointment"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Settings" in current page administration
    And I set the field "Course visibility" to "Hide"
    And I click on "Save and display" "button"
    And I open "Course appointment" actions menu
    And I click on "Hide" "link" in the "Course appointment" activity
    And I open "Course appointment" actions menu
    And I click on "Show" "link" in the "Course appointment" activity
    And I log out
    And I log in as "student1"
    And I am viewing site calendar
    And I hover over day "1" of this month in the full calendar page
    And I should not see "Course appointment"
    And I log out
