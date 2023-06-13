@block @block_myteams @moodleworkplace @javascript
Feature: View the course progress for a user
  In order to view the course progress for a user
  As a manager
  I need be able to view reports from users in course progress

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
    # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a manager position over users "user12,user13,user14" with permissions "7"
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion | startdate      | enddate        |
      | Course 1 | C1        | 0        | 1                |                |                |
      | Course 2 | C2        | 0        | 1                | ##5 days ago## | ##yesterday##  |
    And the following "course enrolments" exist:
      | user     | course | role    | timestart      | timeend       |
      | user12   | C1     | student | ##3 days ago## | ##tomorrow##  |
      | user13   | C1     | student | ##2 days ago## |               |
      | user14   | C2     | student | ##5 days ago## |               |
    And the following "activities" exist:
      | course | activity | name          | idnumber | completion |
      | C1     | assign   | Assignment 01 | assign01 | 1          |
      | C1     | forum    | Forum 01      | forum01  | 1          |
    # Set completion criteria for course 1.
    And I am on the "Course 1" course page logged in as "admin"
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Assignment 01 | 1 |
      | Forum 01      | 1 |
    And I press "Save changes"
    And I log out
    # Complete some activities as user12 and user13.
    And I am on the "Course 1" course page logged in as "user12"
    And I toggle the manual completion state of "Forum 01"
    And I log out
    And I am on the "Course 1" course page logged in as "user13"
    And I toggle the manual completion state of "Assignment 01"
    And I toggle the manual completion state of "Forum 01"
    And I log out

  Scenario: Manager can view a user specific course progress report
    When I log in as "user11"
    And I change window size to "large"
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 12" "table_row"
    And I click on "//span[text()='Courses']/following::span/a[text()='Full report']" "xpath_element" in the "User 12" "table_row"
    Then the following should exist in the "reportbuilder-table" table:
      | Course   | Method            | Time started             | Time ended             | Course status | Activity completion |
      | Course 1 | Manual enrolments | ##3 days ago##%d/%m/%y## | ##tomorrow##%d/%m/%y## | Active        | 1/2                 |
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 13" "table_row"
    And I click on "//span[text()='Courses']/following::span/a[text()='Full report']" "xpath_element" in the "User 13" "table_row"
    Then the following should exist in the "reportbuilder-table" table:
      | Course   | Method            | Time started             | Time ended | Course status | Activity completion |
      | Course 1 | Manual enrolments | ##2 days ago##%d/%m/%y## |            | Completed     | 2/2                 |
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 14" "table_row"
    And I click on "//span[text()='Courses']/following::span/a[text()='Full report']" "xpath_element" in the "User 14" "table_row"
    Then the following should exist in the "reportbuilder-table" table:
      | Course   | Method            | Time started             | Time ended | Course status | Activity completion |
      | Course 2 | Manual enrolments | ##5 days ago##%d/%m/%y## |            | Overdue       | 0/0                 |

  Scenario: Manager can view a global course progress report
    When I log in as "user11"
    And I select "My teams" from primary navigation
    And I click on "Reports..." "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Full course report" "link" in the "[data-region='myteams-global-reports']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Course   | First name     | Method             | Time started             | Time ended                | Course status | Activity completion |
      | Course 1 | User 12        | Manual enrolments  | ##3 days ago##%d/%m/%y## |  ##tomorrow##%d/%m/%y##   | Active        | 1/2                 |
      | Course 1 | User 13        | Manual enrolments  | ##2 days ago##%d/%m/%y## |                           | Completed     | 2/2                 |
      | Course 2 | User 14        | Manual enrolments  | ##5 days ago##%d/%m/%y## |                           | Overdue       | 0/0                 |
    # Check "Course status" filter.
    And I click on "Filters" "button" in the "[data-region='core_reportbuilder/report']" "css_element"
    And I set the following fields in the "Course status" "core_reportbuilder > Filter" to these values:
      | Course status operator | Is equal to  |
      | Course status value    | Overdue      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 14" in the "reportbuilder-table" "table"
    And I should not see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I set the following fields in the "Course status" "core_reportbuilder > Filter" to these values:
      | Course status operator | Is equal to  |
      | Course status value    | Active      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    And I set the following fields in the "Course status" "core_reportbuilder > Filter" to these values:
      | Course status operator | Is equal to  |
      | Course status value    | Completed    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"

  Scenario: Manager can view a global course overdue notification
    When I log in as "user11"
    And I select "My teams" from primary navigation
    Then I should see "Some team members have overdue courses." in the "[data-region='myteams-notifications']" "css_element"
    And I click on "See report" "link" in the "[data-region='myteams-notification-myteams']" "css_element"
    And I should see "User 14" in the "reportbuilder-table" "table"
    And I should not see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"
