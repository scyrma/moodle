@tool @tool_program @moodleworkplace @theme_workplace
Feature: Delete a program
  In order to delete a program
  As a manager
  I need to delete a program from the archived programs tab of the Programs manager view

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
  Scenario: Delete existing archived programs
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 1        | Tenant1 |
      | Program2 | 1        | Tenant1 |
      | Program3 | 1        | Tenant2 |
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    Then I click on "Archived" "link"
    And I should see "Program1"
    And I should see "Program2"
    And I should not see "Program3"
    Then I click on ".delete_program" "css_element" in the "Program2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I click on "Archived" "link"
    Then I should not see "Program2"
    And I should see "Program1"
    Then I click on ".delete_program" "css_element" in the "Program1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I click on "Archived" "link"
    Then I should not see "Program1"
    And I should see "Nothing to display"
    And I log out
    Then I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    Then I click on "Archived" "link"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should see "Program3"
    Then I click on ".delete_program" "css_element" in the "Program3" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Program3"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out