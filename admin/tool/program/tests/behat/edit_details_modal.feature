@tool @tool_program @moodleworkplace @theme_workplace
Feature: Edit program details using edit details modal
  In order to edit a program
  As a manager
  I need to open the edit details modal from program content, create new program and program list

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
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant1 |
      | Program3 | 0        | Tenant2 |
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Active programs"
    And I should see "Program1"
    And I should see "Program2"
    And I should not see "Program3"
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And I should see "Edit program"
    And I should see "Program name"
    Then I set the field "fullname" to "Program1new"
    Then I press "Save" in the modal form dialogue
    And I should see "Program1new"
    Then I click on ".edit_program" "css_element" in the "Program1new" "table_row"
    And I should not see "Details"
    And I should see "Content"
    And I should see "Schedule"
    And I should see "Users"
    Then I click on "Edit details" "button"
    And I should see "Edit program" in the ".modal-dialog .modal-title" "css_element"
    Then I set the field "fullname" to "newProgram1"
    Then I press "Save" in the modal form dialogue
    And I should see "Content"
    And I should see "Schedule"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Add new program" "link"
    Then I should see "New program"
    And I set the field "Program name" to "Program example 4"
    Then I press "Save" in the modal form dialogue
    And I should see "Content"
    And I should see "Schedule"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "newProgram1"
    And I should see "Program2"
    And I should see "Program example 4"
    And I should not see "Program3"
    And I log out
    When I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Active programs"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should see "Program3"
    And I should not see "Program example 4"
    And I log out