@format @format_wplist @moodleworkplace @javascript
Feature: Sections can be expanded and collapsed in wplist format
  In order to show or hide my course contents
  As a teacher
  I need to expand and collapse sections

  Scenario: Expand and collapse sections title must change with status
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | wplist | 1           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Expand section General" "button"
    And I click on "Collapse section General" "button"
    Then "Expand section General" "button" should exist
    And I click on "Expand section Topic 1" "button"
    And I click on "Collapse section Topic 1" "button"
    Then "Expand section Topic 1" "button" should exist
    And I am on "Course 1" course homepage with editing mode on
    And I click on "Collapse section General" "button"
    And I click on "Expand section General" "button"
    Then "Collapse section General" "button" should exist
    And I click on "Collapse section Topic 1" "button"
    And I click on "Expand section Topic 1" "button"
    Then "Collapse section Topic 1" "button" should exist
