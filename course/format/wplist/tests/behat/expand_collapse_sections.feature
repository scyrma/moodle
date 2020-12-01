@format @format_wplist @moodleworkplace @javascript
Feature: Sections can be expanded and collapsed in wplist format
  In order to show or hide my course contents
  As a teacher
  I need to expand and collapse sections

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections | newsitem | sectionstate |
      | Course 1 | C1        | wplist | 3           | 1        |  0            |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber    | section |
      | forum      | Notice board           | Notice board description      | C1     | noticeboard | 0       |
      | book       | Test book name         | Test book description         | C1     | book1       | 1       |
      | chat       | Test chat name         | Test chat description         | C1     | chat1       | 2       |

  Scenario: Expand and collapse sections title must change with status
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "Expand section General" "button" should not exist
    And "Collapse section General" "button" should not exist
    And I should see "Notice board" in the "General" "format_wplist > Section"
    And I click on "Expand section Topic 1" "button"
    And I should see "Test book name" in the "Topic 1" "format_wplist > Section"
    Then "Collapse section Topic 1" "button" should exist
    And I click on "Collapse section Topic 1" "button"
    Then "Expand section Topic 1" "button" should exist
    And I should not see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I turn editing mode on
    Then "Expand section General" "button" should not exist
    And "Collapse section General" "button" should not exist
    And I should see "Notice board" in the "General" "format_wplist > Section"
    And I click on "Collapse section Topic 1" "button"
    Then "Expand section Topic 1" "button" should exist
    And I should not see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I click on "Expand section Topic 1" "button"
    Then "Collapse section Topic 1" "button" should exist
    And I should see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I click on "Collapse section Topic 2" "button"
    Then "Expand section Topic 2" "button" should exist
    And I should not see "Test chat name" in the "Topic 2" "format_wplist > Section"
    And I click on "Expand section Topic 2" "button"
    Then "Collapse section Topic 2" "button" should exist
    And I should see "Test chat name" in the "Topic 2" "format_wplist > Section"
    And I log out

  Scenario: Expand and collapse all section except general section
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "Expand section General" "button" should not exist
    And "Collapse section General" "button" should not exist
    And I should see "Notice board" in the "General" "format_wplist > Section"
    And I click on "Expand all" "button"
    Then I should see "Notice board" in the "General" "format_wplist > Section"
    And I should see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I should see "Test chat name" in the "Topic 2" "format_wplist > Section"
    And I click on "Collapse all" "button"
    Then I should see "Notice board" in the "General" "format_wplist > Section"
    And I should not see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I should not see "Test chat name" in the "Topic 2" "format_wplist > Section"
    And I turn editing mode on
    And I should see "Notice board" in the "General" "format_wplist > Section"
    Then I click on "Collapse all" "button"
    And I should see "Notice board" in the "General" "format_wplist > Section"
    And I should not see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I should not see "Test chat name" in the "Topic 2" "format_wplist > Section"
    And I should see "Notice board" in the "General" "format_wplist > Section"
    And I click on "Expand all" "button"
    Then I should see "Notice board" in the "General" "format_wplist > Section"
    And I should see "Test book name" in the "Topic 1" "format_wplist > Section"
    And I should see "Test chat name" in the "Topic 2" "format_wplist > Section"
    And I log out

  Scenario: General section title should not be visible if it is empty
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then I should not see "General" in the "General" "format_wplist > Section"
    And I should see "Notice board" in the "General" "format_wplist > Section"
    And I am on "Course 1" course homepage with editing mode on
    And I edit the wplist section "0" and I fill the form with:
      | Custom | 1                      |
      | New value for Section name      | Course announcement |
      | Summary                         | General summary    |
    Then I should see "Course announcement"
    And I should see "General summary" in the "Course announcement" "format_wplist > Section"
    And I turn editing mode off
    Then I should see "Course announcement"
    And I should see "General summary" in the "Course announcement" "format_wplist > Section"
    And I log out
