@format @format_wplist @moodleworkplace @javascript
Feature: Operations with activity modules in wplist format
  In order to rearrange my course contents
  As a teacher
  I need to manipulate activity modules

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | coursedisplay | numsections |
      | Course 1 | C1        | wplist | 0             | 5           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Moving modules in wplist format
    Given the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber    | section |
      | assign     | Test assignment name   | Test assignment description   | C1     | assign1     | 0       |
      | book       | Test book name         | Test book description         | C1     | book1       | 1       |
      | chat       | Test chat name         | Test chat description         | C1     | chat1       | 4       |
      | choice     | Test choice name       | Test choice description       | C1     | choice1     | 5       |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And "[data-region='module'].type-assign" "css_element" should appear before "[data-region='module'].type-book" "css_element"
    And I click on "Move resource" "button" in the "[data-region='module'].type-book" "css_element"
    And I follow "To the top of section \" General \""
    And "[data-region='module'].type-assign" "css_element" should appear after "[data-region='module'].type-book" "css_element"
    And I log out

  Scenario: Stealth activities in wplist format
    Given the following config values are set as admin:
      | allowstealth | 1 |
    Given the following "activities" exist:
      | activity | name                 | intro                       | course | idnumber | section |
      | assign   | Test assignment name | Test assignment description | C1     | assign1  | 2       |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Recent activity" block if not present
    When I open wplist activity "Test assignment name" actions menu
    Then wplist activity "Test assignment name" actions menu should not have "Show" item
    And wplist activity "Test assignment name" actions menu should have "Hide" item
    And wplist activity "Test assignment name" actions menu should not have "Make available" item
    And wplist activity "Test assignment name" actions menu should not have "Make unavailable" item
    And I click on "Hide" "link" in the "Test assignment name" wplist activity
    And "Test assignment name" wplist activity should be hidden
    And I open availability popup for wplist activity "Test assignment name"
    And I should see "Hidden from students"
    And I open wplist activity "Test assignment name" actions menu
    And wplist activity "Test assignment name" actions menu should have "Show" item
    And wplist activity "Test assignment name" actions menu should not have "Hide" item
    And wplist activity "Test assignment name" actions menu should not have "Make unavailable" item
    And I click on "Make available" "link" in the "Test assignment name" wplist activity
    And "Test assignment name" wplist activity should be available but hidden from course page
    And I open availability popup for wplist activity "Test assignment name"
    And I should see "Available but not shown on course page"
    # Make sure that "Availability" dropdown in the edit menu has three options.
    And I open wplist activity "Test assignment name" actions menu
    And I click on "Edit settings" "link" in the "Test assignment name" wplist activity
    And I expand all fieldsets
    And the "Availability" select box should contain "Show on course page"
    And the "Availability" select box should contain "Hide from students"
    And the field "Availability" matches value "Make available but not shown on course page"
    And I press "Save and return to course"
    And "Test assignment name" wplist activity should be available but hidden from course page
    And I turn editing mode off
    And I expand wplist section "2"
    And "Test assignment name" wplist activity should be available but hidden from course page
    And I open availability popup for wplist activity "Test assignment name"
    And I should see "Available but not shown on course page"
    And I log out
    # Student will not see the module on the course page but can access it from other reports and blocks:
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I expand wplist section "2"
    And "Test assignment name" wplist activity should be hidden
    And I click on "Test assignment name" "link" in the "Recent activity" "block"
    And I should see "Test assignment name"
    And I should see "Submission status"
    And I log out

  Scenario: Restricted activities in wplist format
    Given the following config values are set as admin:
      | allowstealth | 1 |
    Given the following "activities" exist:
      | activity | name                 | intro                       | course | idnumber | section |
      | assign   | Test assignment name | Test assignment description | C1     | assign1  | 2       |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open wplist activity "Test assignment name" actions menu
    And I click on "Edit settings" "link" in the "Test assignment name" wplist activity
    And I expand all fieldsets
    Given I click on "Add restriction..." "button"
    And I click on "Date" "button" in the "Add restriction..." "dialogue"
    And I set the following fields to these values:
      | x[day]   | 31   |
      | x[month] | 12   |
      | x[year]  | 2037 |
    And I press "Save and return to course"
    And I turn editing mode off
    And I expand wplist section "2"
    And I open availability popup for wplist activity "Test assignment name"
    And I should see "Available from 31 December 2037"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I expand wplist section "2"
    And I open availability popup for wplist activity "Test assignment name"
    And I should see "Available from 31 December 2037"
    And I log out
