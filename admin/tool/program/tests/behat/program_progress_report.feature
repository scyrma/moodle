@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Ensure program progress report works as expected
  In order to check that progress report works as expected
  As a manager
  I need be able to view reports from users enrolled to programs

  Background:
    Given "1" tenants exist with "6" users and "1" courses in each
    And the following "tool_program > programs" exist:
      | fullname     | archived | tenant  |
      | Program1     | 0        | Tenant1 |
      | Program2     | 0        | Tenant1 |
    And the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program1  | C11     |
    And the following "tool_tenant > users" exist:
      | username      | firstname | lastname | email                | tenant  |
      | manager1      | Manager   | 1        | manager1@example.com | Tenant1 |
      | manager2      | Manager   | 2        | manager2@example.com | Tenant1 |
    # User 'manager1'
    And user "manager1" has a manager position over users "user11,user12,user13,user14" with permissions "3"
    And user "manager2" has a manager position over users "user11,user12,user13,user14" with permissions "2"
    And the following "tool_program > program_users" exist:
      | program  | user   | duedate    | duedatelocked |
      | Program1 | user11 |            |               |
      | Program1 | user12 |            |               |
      | Program1 | user14 | 1684335053 | 1             |
      | Program1 | user15 |            |               |
      | Program2 | user11 |            |               |
    And the following "tool_program > program_completions" exist:
      | program  | user    |
      | Program1 | user12  |

  Scenario: Manager who can allocate can view a program progress report from programs list page
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I press "Progress report" action in the "Program1" report row
    Then I should see "Program1" in the "#page-navbar" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Allocation source | Program status | Program progress |
      | User 11    | Manual            | Open           | 0%               |
      | User 12    | Manual            | Completed      | 100%             |
      | User 14    | Manual            | Overdue        | 0%               |
    # User 13 is not allocated to the program, User 15 is not in the team.
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 15" in the "reportbuilder-table" "table"
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

  Scenario: Manager who can view reports can view a program progress report
    When I log in as "manager2"
    And I navigate to "Programs" in workplace launcher
    Then I should see "Reports"
    And I should not see "Program1"
    # Viewing programs report for all programs for users in my team
    And I follow "Program progress"
    And I should see "Programs" in the "#page-navbar" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | Program name | First name | Allocation source | Program status | Program progress |
      | Program1     | User 11    | Manual            | Open           | 0%               |
      | Program1     | User 12    | Manual            | Completed      | 100%             |
      | Program1     | User 14    | Manual            | Overdue        | 0%               |
      | Program2     | User 11    | Manual            | Open           | 0%               |
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 15" in the "reportbuilder-table" "table"
    And I press "Progress overview" action in the "User 12" report row
    And I should see "100% completed" in the "Progress overview for User 12" "dialogue"
    And I click on "Close" "button" in the "Progress overview for User 12" "dialogue"
    And I click on "Programs" "link" in the "#page-navbar" "css_element"
    And I follow "Overdue programs"
    And I should see "User 14"
    And I should not see "User 11"
    # Viewing program report for one program for users in my team
    And I follow "Program1"
    And I should see "Reports"
    And I follow "Program progress"
    And I should see "Program1" in the "#page-navbar" "css_element"
    And I should see "User 11"
    And I should not see "Program2"
    And I press "Progress overview" action in the "User 14" report row
    And I should see "0% completed" in the "Progress overview for User 14" "dialogue"
    And I click on "Close" "button" in the "Progress overview for User 14" "dialogue"
    # Viewing programs report for one user in my team
    And I follow "User 11"
    And I follow "2 active programs"
    And I should see "User 11" in the "#page-navbar" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | Program name | Allocation source | Program status | Program progress |
      | Program1     | Manual            | Open           | 0%               |
      | Program2     | Manual            | Open           | 0%               |
    And I press "Progress overview" action in the "Program1" report row
    And I should see "0% completed" in the "Progress overview for User 11" "dialogue"
