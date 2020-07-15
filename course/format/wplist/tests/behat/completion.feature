@format @format_wplist @moodleworkplace @javascript
Feature: Allow students to manually mark an activity as complete in wplist format
  In order to let students decide when an activity is completed in wplist format
  As a teacher
  I need to allow students to mark activities as completed

  Scenario: Mark an activity as completed in wplist format
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion| format |
      | Course 1 | C1        | 0        | 1               | wplist |
    And the following "activities" exist:
      | activity | name      | intro      | course | idnumber | completion |
      | page     | Test page | PageDesc1  | C1     | PAGE1    | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Expand all" "button"
    And I click on "Not completed: Test page. Select to mark as complete." "icon"
    And I click on "Completed: Test page. Select to mark as not complete." "icon"
    Then "Not completed: Test page. Select to mark as complete." "icon" should exist
