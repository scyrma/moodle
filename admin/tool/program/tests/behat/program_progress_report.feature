@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Ensure program progress report works as expected
  In order to check that progress report works as expected
  As a manager
  I need be able to view reports from users enrolled to programs

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "tool_program > programs" exist:
      | fullname     | archived | tenant  | generatecourses |
      | Program_name | 0        | Tenant1 | 1               |
    And the following "tool_tenant > users" exist:
      | username      | firstname | lastname | email                | tenant  |
      | user1         | User      | a        | user1@example.com    | Tenant1 |
      | user2         | User      | b        | user2@example.com    | Tenant1 |
      | user3         | User      | c        | user3@example.com    | Tenant1 |
      | user4         | User      | e        | user3@example.com    | Tenant1 |
      | manager1      | Manager   | 1        | manager1@example.com | Tenant1 |
    And user "manager1" has a manager position over users "user1,user2,user3,user4" with permissions "3"
    And the following "tool_program > program_users" exist:
      | program      | user   |
      | Program_name | user1  |
      | Program_name | user2  |
      | Program_name | user4  |
    And the following "tool_program > program_completions" exist:
      | program      | user   |
      | Program_name | user2  |

  Scenario: Manager can view a program progress report
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Programs" in workplace launcher
    And I press "Progress report" action in the "Program_name" report row
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Allocation source | Program status | Program progress |
      | User a     | Manual            | Open           | 0%               |
      | User b     | Manual            | Completed      | 100%             |
      | User e     | Manual            | Open           | 0%               |
    And I should not see "User c" in the "reportbuilder-table" "table"
    And I follow "User b"
    And I should see "1 completed programs"
    And I log out
