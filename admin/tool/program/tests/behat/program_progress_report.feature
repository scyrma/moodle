@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Ensure program progress report works as expected
  In order to check that progress report works as expected
  As a manager
  I need be able to view reports from users enrolled to programs

  Background:
    Given "1" tenants exist with "5" users and "1" courses in each
    And the following "tool_program > programs" exist:
      | fullname     | archived | tenant  |
      | Program1     | 0        | Tenant1 |
    And the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program1  | C11     |
    And the following "tool_tenant > users" exist:
      | username      | firstname | lastname | email                | tenant  |
      | manager1      | Manager   | 1        | manager1@example.com | Tenant1 |
    And user "manager1" has a manager position over users "user11,user12,user13,user14" with permissions "3"
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |
      | Program1 | user14  |
    And the following "tool_program > program_completions" exist:
      | program  | user    |
      | Program1 | user12  |

  Scenario: Manager can view a program progress report
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I press "Progress report" action in the "Program1" report row
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Allocation source | Program status | Program progress |
      | User 11    | Manual            | Open           | 0%               |
      | User 12    | Manual            | Completed      | 100%             |
      | User 14    | Manual            | Open           | 0%               |
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I click on "Filters" "button" in the "[data-region='core_reportbuilder/report']" "css_element"
    And I set the following fields in the "Program status" "core_reportbuilder > Filter" to these values:
      | Program status operator | Is equal to  |
      | Program status value    | Completed    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name | Allocation source | Program status | Program progress |
      | User 12    | Manual            | Completed      | 100%             |
    And I should not see "User 11" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
