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