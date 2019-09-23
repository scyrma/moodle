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
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following custom reports exist:
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

  @javascript
  Scenario: Change page
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Report aaaa" in the "table.report-table" "css_element"
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
    # And I should see course listing "Course 1" before "Course 2"
    And ".tool_reportbuilder_report .pagination" "css_element" should exist
    # And I should see "Showing courses 1 to 5 of 12 courses"
    And I should see "Next" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    # And I should not see "Prev" in the "#course-listing .pagination" "css_element"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Report aaaa" in the "table.report-table" "css_element"
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
    And I should see "Prev" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Prev" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "Report aaaa" in the "table.report-table" "css_element"
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
    And I click on "Next" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Report aaaa" in the "table.report-table" "css_element"
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
    And I log out

  @javascript
  Scenario: Filter with pagination
    Given the following custom reports exist:
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
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Report zzzz" "link"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Conditions"
    And I set the field "Select a condition" to "First name"
    And I set the field "First name field limiter" to "is equal to"
    And I set the field "First name value" to "User100"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should not see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should not see "User200 Lastname200" in the "table.report-table" "css_element"
    And ".tool_reportbuilder_report ul.pagination" "css_element" should not exist
    #Reset all conditions
    And I click on "Reset all conditions" "link"
    And I click on "Reset all" "button" in the "Confirm" "dialogue"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should see "User200 Lastname200" in the "table.report-table" "css_element"
    And ".tool_reportbuilder_report ul.pagination" "css_element" should exist
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    #Reset a condition
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I set the field "First name field limiter" to "is equal to"
    And I set the field "First name value" to "User100"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should not see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should not see "User200 Lastname200" in the "table.report-table" "css_element"
    And I click on "Reset 'First name' field" "link"
    And I click on "Reset condition" "button" in the "Confirm" "dialogue"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should see "User200 Lastname200" in the "table.report-table" "css_element"
    And ".tool_reportbuilder_report ul.pagination" "css_element" should exist
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    #Delete condition
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I set the field "First name field limiter" to "is equal to"
    And I set the field "First name value" to "User100"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should not see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should not see "User200 Lastname200" in the "table.report-table" "css_element"
    And I click on "Delete condition 'First name'" "link"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should see "User200 Lastname200" in the "table.report-table" "css_element"
    And ".tool_reportbuilder_report ul.pagination" "css_element" should exist
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    #Reset all filters
    And I wait "3" seconds
    And I follow "Filters"
    And I set the field "Select a filter" to "First name"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "First name field limiter" to "is equal to"
    And I set the field "First name value" to "User100"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should not see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should not see "User200 Lastname200" in the "table.report-table" "css_element"
    And ".tool_reportbuilder_report ul.pagination" "css_element" should not exist
    And I click on "Reset 'First name' field" "button"
    And I should see "User100 Lastname100" in the "table.report-table" "css_element"
    And I should see "User1000 Lastname1000" in the "table.report-table" "css_element"
    And I should see "User200 Lastname200" in the "table.report-table" "css_element"
    And ".tool_reportbuilder_report ul.pagination" "css_element" should exist
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    #Reset filter
    And I log out