@block @block_myteams @moodleworkplace @javascript
Feature: View the course progress for a user
  In order to view the course progress for a user
  As a manager
  I need be able to view reports from users in course progress

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
    # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role    | timestart      | timeend      |
      | user12   | C1     | student | ##3 days ago## | ##tomorrow## |
      | user13   | C1     | student | ##2 days ago## |              |
    And the following "activities" exist:
      | course | activity | name          | idnumber | completion |
      | C1     | assign   | Assignment 01 | assign01 | 1          |
      | C1     | forum    | Forum 01      | forum01  | 1          |
    And I am on the "Course 1" course page logged in as "user12"
    And I toggle the manual completion state of "Forum 01"
    And the manual completion button of "Forum 01" is displayed as "Done"
    And I log out

  Scenario: Manager can view a user specific course progress report
    When I log in as "user11"
    And I change window size to "large"
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 12" "table_row"
    And I click on "//div[contains(@class, 'pagesectioncontent') and contains(@class, 'show')]//span[text()='Courses']/following::span/a[text()='Full report']" "xpath_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Course   | Method            | Time started             | Time ended             | Course status | Activity completion |
      | Course 1 | Manual enrolments | ##3 days ago##%d/%m/%y## | ##tomorrow##%d/%m/%y## | Active        | 1/2                 |
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 13" "table_row"
    And I click on "//div[contains(@class, 'pagesectioncontent') and contains(@class, 'show')]//span[text()='Courses']/following::span/a[text()='Full report']" "xpath_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Course   | Method            | Time started             | Time ended | Course status | Activity completion |
      | Course 1 | Manual enrolments | ##2 days ago##%d/%m/%y## |            | Active        | 0/2                 |
