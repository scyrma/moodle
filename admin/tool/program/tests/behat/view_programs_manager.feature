@tool @tool_program @moodleworkplace @theme_workplace
Feature: View programs manager
  In order to see the program in the Programs manager
  As a manager
  I need to see all existing programs in the Programs manager view

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
      | manager2 | tool_program_manager | System       |           |

  @javascript
  Scenario: There are no existing programs
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should see "Nothing to display"
    And I log out

  @javascript
  Scenario: There are existing programs
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 1        | Tenant1 |
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should not see "Nothing to display"
    And I should see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    And I should not see "Program1"
    And I should see "Program2"
    And I log out
    Then I log in as "manager2"
    Then I navigate to "Programs" in workplace launcher
    Then I should see "Programs"
    And I should see "Nothing to display"
    And I log out
