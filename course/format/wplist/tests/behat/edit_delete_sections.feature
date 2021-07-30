@format @format_wplist @moodleworkplace @javascript
Feature: Sections can be edited and deleted in wplist format
  In order to rearrange my course contents
  As a teacher
  I need to edit and Delete topics

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | coursedisplay | numsections | sectionstate  |
      | Course 1 | C1        | wplist | 0             | 5           |  0             |
    And the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber    | section |
      | assign     | Test assignment name   | Test assignment description   | C1     | assign1     | 0       |
      | book       | Test book name         | Test book description         | C1     | book1       | 1       |
      | chat       | Test chat name         | Test chat description         | C1     | chat1       | 4       |
      | choice     | Test choice name       | Test choice description       | C1     | choice1     | 5       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: View the default name of the general section in wplist format
    When I edit the wplist section "0"
    Then the field "Custom" matches value "0"
    And the field "New value for Section name" matches value "General"

  Scenario: Edit the default name of the general section in wplist format
    When I edit the wplist section "0" and I fill the form with:
      | Custom | 1                     |
      | New value for Section name      | This is the general section |
    Then I should see "This is the general section" in the "[data-region=section][data-sectionnumber=0]" "css_element"

  Scenario: View the default name of the second section in wplist format
    When I edit the wplist section "2"
    Then the field "Custom" matches value "0"
    And the field "New value for Section name" matches value "Topic 2"

  Scenario: Edit section summary in wplist format
    When I edit the wplist section "2" and I fill the form with:
      | Summary | Welcome to section 2 |
    Then I should see "Welcome to section 2" in the "[data-region=section][data-sectionnumber=2]" "css_element"

  Scenario: Edit section default name in wplist format
    When I edit the wplist section "2" and I fill the form with:
      | Custom | 1                      |
      | New value for Section name      | This is the second topic |
    Then I should see "This is the second topic" in the "[data-region=section][data-sectionnumber=2]" "css_element"
    And I should not see "Topic 2" in the "[data-region=section][data-sectionnumber=2]" "css_element"

  Scenario: Inline edit section name in wplist format
    When I set the field "Edit section name" in the "[data-region=section][data-sectionnumber=1]" "css_element" to "Midterm evaluation"
    Then I should not see "Topic 1" in the "region-main" "region"
    And "New name for section" "field" should not exist
    And I should see "Midterm evaluation" in the "[data-region=section][data-sectionnumber=1]" "css_element"
    And I am on "Course 1" course homepage
    And I should not see "Topic 1" in the "region-main" "region"
    And I should see "Midterm evaluation" in the "[data-region=section][data-sectionnumber=1]" "css_element"

  Scenario: Deleting the last section in wplist format
    When I delete wplist section "5"
    Then I should see "Are you absolutely sure you want to completely delete \"Topic 5\" and all the activities it contains?"
    And I press "Delete"
    And I should not see "Topic 5"
    And I should see "Topic 4"

  Scenario: Deleting the middle section in wplist format
    When I delete wplist section "4"
    And I press "Delete"
    Then I should not see "Topic 5"
    And I should not see "Test chat name"
    And I should see "Test choice name" in the "[data-region=section][data-sectionnumber=4]" "css_element"
    And I should see "Topic 4"

  Scenario: Adding sections in wplist format
    When I follow "Add sections"
    Then the field "Number of sections" matches value "1"
    And I press "Add sections"
    And I should see "Topic 6" in the "[data-region=section][data-sectionnumber=6]" "css_element"
    And "[data-region=section][data-sectionnumber=7]" "css_element" should not exist
    And I follow "Add sections"
    And I set the field "Number of sections" to "3"
    And I press "Add sections"
    And I should see "Topic 7" in the "[data-region=section][data-sectionnumber=7]" "css_element"
    And I should see "Topic 8" in the "[data-region=section][data-sectionnumber=8]" "css_element"
    And I should see "Topic 9" in the "[data-region=section][data-sectionnumber=9]" "css_element"
    And "[data-region=section][data-sectionnumber=10]" "css_element" should not exist

  Scenario: Rearranging sections in wplist format
    And "Test chat name" "link" should appear after "Test book name" "link"
    And I click on "Move section 4" "button"
    And I follow "After \" General \""
    And "Test chat name" "link" should appear before "Test book name" "link"
    And I log out

  Scenario: Hiding a section in wplist format
    And I hide wplist section "2"
    And I open availability popup for wplist section "2"
    And I should see "Hidden from students"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Topic 1"
    And I should see "Topic 2"
    And I open availability popup for wplist section "2"
    And I should see "Not available"
    And I should see "Topic 3"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And the field "Format" matches value "Workplace list format"
    And I set the field "Hidden sections" to "Hidden sections are completely invisible"
    And I press "Save and display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Topic 1"
    And I should not see "Topic 2"
    And I should see "Topic 3"
    And I log out
