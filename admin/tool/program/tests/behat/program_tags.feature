@tool @tool_program @moodleworkplace @theme_workplace
Feature: Use tags in a program
  In order to introduce tags into a program
  As a manager
  I need to add a tag in the program details page

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
  Scenario: Add tags to existing programs
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    Then I should see "Edit program"
    And I should see "Program name"
    And I should see "Program tags"
    And I set the field "Program tags" to "Tag example 1"
    Then I press "Save" in the modal form dialogue
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Tag example 1"
    Then I click on ".edit_details" "css_element" in the "Program2" "table_row"
    Then I should see "Edit program"
    And I should see "Program name"
    And I should see "Program tags"
    And I set the field "Program tags" to "Tag example 2"
    Then I press "Save" in the modal form dialogue
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Tag example 1"
    And I should see "Tag example 2"
    Then I click on ".edit_details" "css_element" in the "Program2" "table_row"
    And I should see "Tag example 2" in the ".modal-dialog" "css_element"
    And I should not see "Tag example 1" in the ".modal-dialog" "css_element"
    Then I click on "Cancel" "button" in the ".modal-dialog .modal-footer" "css_element"
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And I should see "Tag example 1" in the ".modal-dialog" "css_element"
    And I should not see "Tag example 2" in the ".modal-dialog" "css_element"
    And I click on "[data-value='Tag example 1']" "css_element" in the ".modal-dialog" "css_element"
    Then I press "Save" in the modal form dialogue
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And I should not see "Tag example 2" in the ".modal-dialog" "css_element"
    And I should not see "Tag example 1" in the ".modal-dialog" "css_element"
    Then I click on "Cancel" "button" in the ".modal-dialog .modal-footer" "css_element"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Active" "link"
    And I should see "Nothing to display"
    And I log out
