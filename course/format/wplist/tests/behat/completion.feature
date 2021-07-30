@format @format_wplist @moodleworkplace @javascript
Feature: Allow students to manually mark an activity as complete in wplist format
  In order to let students decide when an activity is completed in wplist format
  As a teacher
  I need to allow students to mark activities as completed

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion| format | sectionstate  |
      | Course 1 | C1        | 0        | 1               | wplist | 0              |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: Mark an activity as completed in wplist format
    Given the following "activities" exist:
      | activity | name      | intro                  | course | idnumber | completion |
      | page     | Test page | Test page description  | C1     | page1    | 1          |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Expand all" "button"
    # Check that "Mark as done" button added in MDL-70821 is not shown.
    And "Mark as done" "button" should not exist
    And I click on "Not completed: Test page. Select to mark as complete." "icon"
    And I click on "Completed: Test page. Select to mark as not complete." "icon"
    And "Not completed: Test page. Select to mark as complete." "icon" should exist
    And I follow "Test page"
    And the manual completion button of "Test page" is displayed as "Mark as done"
    When I toggle the manual completion state of "Test page"
    And I am on "Course 1" course homepage
    And "Completed: Test page. Select to mark as not complete." "icon" should exist

  Scenario: See activity completion information in wplist format
    Given the following "activities" exist:
      | activity | name            | intro                       | course | idnumber | completion | completionview |
      | assign   | Test assignment | Test assignment description | C1     | assign1  | 2          | 1              |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then the "View" completion condition of "Test assignment" is displayed as "todo"
    And I follow "Test assignment"
    And I am on "Course 1" course homepage
    Then the "View" completion condition of "Test assignment" is displayed as "done"
