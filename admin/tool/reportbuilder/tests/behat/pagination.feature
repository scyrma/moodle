@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Pagination
  In order to manage reports
  As a manager
  I need to be view the reports with a pagination

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "roles" exist:
      | shortname                  | name                   | archetype |
      | tool_reportbuilder_manager | Report builder manager |           |
    And the following "permission overrides" exist:
      | capability              | permission | role                       | contextlevel | reference |
      | tool/reportbuilder:edit | Allow      | tool_reportbuilder_manager | System       |           |
      | moodle/site:configview  | Allow      | tool_reportbuilder_manager | System       |           |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report aaaa | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report bbbb | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report cccc | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report dddd | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report eeee | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report ffff | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report gggg | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report hhhh | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report iiii | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report jjjj | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report kkkk | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report llll | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |

  Scenario: Change page
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    Then I should see "Report aaaa" in the "table.report-table" "css_element"
    And I should see "Report bbbb" in the "table.report-table" "css_element"
    And I should see "Report cccc" in the "table.report-table" "css_element"
    And I should see "Report dddd" in the "table.report-table" "css_element"
    And I should see "Report eeee" in the "table.report-table" "css_element"
    And I should see "Report ffff" in the "table.report-table" "css_element"
    And I should see "Report gggg" in the "table.report-table" "css_element"
    And I should see "Report hhhh" in the "table.report-table" "css_element"
    And I should see "Report iiii" in the "table.report-table" "css_element"
    And I should see "Report jjjj" in the "table.report-table" "css_element"
    And I should not see "Report kkkk" in the "table.report-table" "css_element"
    And I should not see "Report llll" in the "table.report-table" "css_element"
    And "nav.pagination" "css_element" should exist in the ".tool_reportbuilder_report" "css_element"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Change page.
    When I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    Then I should not see "Report aaaa" in the "table.report-table" "css_element"
    And I should not see "Report bbbb" in the "table.report-table" "css_element"
    And I should not see "Report cccc" in the "table.report-table" "css_element"
    And I should not see "Report dddd" in the "table.report-table" "css_element"
    And I should not see "Report eeee" in the "table.report-table" "css_element"
    And I should not see "Report ffff" in the "table.report-table" "css_element"
    And I should not see "Report gggg" in the "table.report-table" "css_element"
    And I should not see "Report hhhh" in the "table.report-table" "css_element"
    And I should not see "Report iiii" in the "table.report-table" "css_element"
    And I should not see "Report jjjj" in the "table.report-table" "css_element"
    And I should see "Report kkkk" in the "table.report-table" "css_element"
    And I should see "Report llll" in the "table.report-table" "css_element"
    # Check page 2 is active.
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Change page and check it become active.
    When I click on "1" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    Then I should see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"

  Scenario: Stay on same page
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    Then I should see "Report aaaa" in the "table.report-table" "css_element"
    And "nav.pagination" "css_element" should exist in the ".tool_reportbuilder_report" "css_element"
    # Check page 1 is active.
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Change page and check page 2 is active.
    When I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "Report kkkk" in the "table.report-table" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Edit report, we should remain on same page after editing.
    Then I click on "Edit details" "link" in the "Report kkkk" "table_row"
    And I click on "Save" "button" in the "Edit report 'Report kkkk'" "dialogue"
    And I should not see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    # Delete reports, so we get single page with no pagination.
    Then I click on "Delete report" "link" in the "Report kkkk" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "1" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination li.active" "css_element"
    Then I click on "Delete report" "link" in the "Report llll" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And "nav.pagination" "css_element" should not exist in the ".tool_reportbuilder_report" "css_element"

  Scenario: Filter with pagination
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report zzzz | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    And the following "users" exist:
      | username | firstname | lastname    | email               | phone1 | department   | institution   | city  | country | lastaccess |
      | user100  | User100   | Lastname100 | user100@example.com | 101    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user200  | User200   | Lastname200 | user200@example.com | 102    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user300  | User300   | Lastname300 | user300@example.com | 103    | Department 2 | Institution 2 | CITY2 | AU      | 1315958041 |
      | user400  | User400   | Lastname400 | user400@example.com | 104    | Department 2 | Institution 2 | CITY3 | AU      | 1425158941 |
      | user500  | User500   | Lastname500 | user500@example.com | 105    | Department 2 | Institution 2 | CITY4 | AU      | 1354958041 |
      | user600  | User600   | Lastname600 | user600@example.com | 106    | Department 2 | Institution 2 | CITY5 | FR      | 1423158941 |
      | user700  | User700   | Lastname700 | user700@example.com | 107    | Department 2 | Institution 2 | CITY6 | FR      | 1425118941 |
      | user800  | User800   | Lastname700 | user800@example.com | 107    | Department 3 | Institution 3 | CITY6 | ES      | 1425158941 |
      | user900  | User900   | Lastname700 | user900@example.com | 107    | Department 4 | Institution 3 | CITY6 | AU      | 1425158941 |
      | user1000  | User1000   | Lastname1000 | user1000@example.com | 108    | Department 4 | Institution 3 | CITY7 | AU      | 1425158941 |
      | user1100  | User1100   | Lastname1100 | user1100@example.com | 109    | Department 4 | Institution 3 | CITY7 | AU      | 1425158941 |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user100    | Tenant1 |
      | user200    | Tenant1 |
      | user300    | Tenant1 |
      | user400    | Tenant1 |
      | user500    | Tenant1 |
      | user600    | Tenant1 |
      | user700    | Tenant1 |
      | user800    | Tenant1 |
      | user900    | Tenant1 |
      | user1000    | Tenant1 |
      | user1100    | Tenant1 |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Edit content" "link" in the "Report zzzz" "table_row"
    Then I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Show/hide filters sidebar" "button"
    # Reset conditions.
    When I click on "Expand conditions" "button"
    And I set the following fields to these values:
      | Select a condition       | First name  |
      | First name value         | User100     |
      | First name field limiter | is equal to |
    Then "User100 Lastname100" "table_row" should exist
    And "User200 Lastname200" "table_row" should not exist
    And "User1000 Lastname1000" "table_row" should not exist
    And ".tool_reportbuilder_report ul.pagination" "css_element" should not exist
    And I click on "Reset all conditions" "link"
    And I click on "Reset all" "button" in the "Confirm" "dialogue"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    # Reset filters.
    When I click on "Expand filters" "button"
    And I set the field "Select a filter" to "First name"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the following fields to these values:
      | First name value         | User100     |
      | First name field limiter | is equal to |
    Then "User100 Lastname100" "table_row" should exist
    And "User200 Lastname200" "table_row" should not exist
    And "User1000 Lastname1000" "table_row" should not exist
    And ".tool_reportbuilder_report ul.pagination" "css_element" should not exist
    And I press "Reset table"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"

  Scenario: Show all report results
    Given the following "tool_reportbuilder > reports" exist:
      | name        | tenant  | source |
      | Report mmmm | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    Then I should not see "Report kkkk" in the "table.report-table" "css_element"
    And I should not see "Report llll" in the "table.report-table" "css_element"
    And I should not see "Report mmmm" in the "table.report-table" "css_element"
    And "nav.pagination" "css_element" should exist in the ".tool_reportbuilder_report" "css_element"
    And I press "Show all 13"
    And I should see "Report kkkk" in the "table.report-table" "css_element"
    And I should see "Report llll" in the "table.report-table" "css_element"
    And I should see "Report mmmm" in the "table.report-table" "css_element"
    And "nav.pagination" "css_element" should not exist in the ".tool_reportbuilder_report" "css_element"
    And I press "Show 10 per page"
    And I should not see "Report kkkk" in the "table.report-table" "css_element"
    And I should not see "Report llll" in the "table.report-table" "css_element"
    And I should not see "Report mmmm" in the "table.report-table" "css_element"
    And "nav.pagination" "css_element" should exist in the ".tool_reportbuilder_report" "css_element"
    # Test 'Show all' with filters and no pagination
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Report source field limiter" in the "//*[@data-region='active-filters']" "xpath_element" to "is equal to"
    And I set the field "Report source value" in the "//*[@data-region='active-filters']" "xpath_element" to "Users list"
    And "nav.pagination" "css_element" should not exist in the ".tool_reportbuilder_report" "css_element"
    And I should see "Report mmmm" in the "table.report-table" "css_element"
    And I should not see "Show all 13"
    And I should not see "Show 10 per page"
    # Test 'Show all' with filters and pagination
    And I set the field "Report source value" in the "//*[@data-region='active-filters']" "xpath_element" to "Course completion from datastore"
    And "nav.pagination" "css_element" should exist in the ".tool_reportbuilder_report" "css_element"
    And I should not see "Report kkkk" in the "table.report-table" "css_element"
    And I should not see "Report llll" in the "table.report-table" "css_element"
    And I press "Show all 12"
    And I should see "Report kkkk" in the "table.report-table" "css_element"
    And I should see "Report llll" in the "table.report-table" "css_element"
    And "nav.pagination" "css_element" should not exist in the ".tool_reportbuilder_report" "css_element"
    And I press "Show 10 per page"
    And I should not see "Report kkkk" in the "table.report-table" "css_element"
    And I should not see "Report llll" in the "table.report-table" "css_element"
    And I log out
