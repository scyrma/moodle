@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Programs should appear or hide in user dashboard when changing visibility setting
  In order to show or hide a program in user dashboard
  As a manager
  I need to change visibility for a program from the Programs manager view and be able to restore it

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | user1    | User      | 1        | user1@example.com    |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | user1    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    Given the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
      | Program3 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program2 | user1  |
      | Program3 | user1  |
    And user "manager1" has a department lead position over users "user1" with permissions "3"

  Scenario: Show or hide programs
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I press "Hide" action in the "Program1" report row
    Then the "class" attribute of "Program1" "table_row" should contain "text-muted"
    And I click on "Program1" "link" in the "Program1" "table_row"
    And I should see "Hidden from learners" in the "page-header" "region"
    And I navigate to "Programs" in workplace launcher
    And I press "Show" action in the "Program1" report row
    And I click on "Program1" "link" in the "Program1" "table_row"
    And I should not see "Hidden from learners" in the "page-header" "region"

  Scenario: Show or hide programs from kebab action menu
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Actions" "icon" in the "page-header" "region"
    And I choose "Hide" in the open action menu
    Then I should see "Hidden from learners" in the "page-header" "region"
    And I navigate to "Programs" in workplace launcher
    And the "class" attribute of "Program1" "table_row" should contain "text-muted"
    And I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Actions" "icon" in the "page-header" "region"
    And I choose "Show" in the open action menu
    And I should not see "Hidden from learners" in the "page-header" "region"
