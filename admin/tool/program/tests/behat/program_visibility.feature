@tool @tool_program @moodleworkplace @theme_workplace
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
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
      | Program3 | Tenant1 |
    Given the following users allocations to programs exist:
      | program  | user   |
      | Program1 | user1  |
      | Program2 | user1  |
      | Program3 | user1  |
    And user "manager1" has a department manager position over users "user1" with permissions "3"

  @javascript
  Scenario: Show or hide programs
    When I log in as "user1"
    Then I should see "Programs"
    And I should see "Program1"
    And I should see "Program2"
    And I should see "Program3"
    And I log out
    Then I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should see "Program1"
    And I should see "Program2"
    And I should see "Program3"
    Then I click on ".update_visibility" "css_element" in the "Program1" "table_row"
    And I log out
    Then I log in as "user1"
    Then I should see "Programs"
    And I should not see "Program1"
    And I should see "Program2"
    And I should see "Program3"
    And I log out
    Then I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on ".update_visibility" "css_element" in the "Program1" "table_row"
    Then I click on ".update_visibility" "css_element" in the "Program2" "table_row"
    Then I click on "Archive" "link" in the "Program3" "table_row"
    Then I press "Archive"
    And I log out
    Then I log in as "user1"
    Then I should see "Programs"
    And I should see "Program1"
    And I should not see "Program2"
    And I should not see "Program3"
    And I log out