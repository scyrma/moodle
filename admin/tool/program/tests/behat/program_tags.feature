@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Use tags in a program
  In order to introduce tags into a program
  As a manager
  I need to add a tag in the program details page

  Background:
    Given "2" tenants exist with "4" users and "1" courses in each
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
    Given the following "tool_program > programs" exist:
      | fullname | tenant  | program_tags  |
      | Program1 | Tenant1 | blue, white   |
      | Program2 | Tenant1 | blue, yellow  |
      | Program3 | Tenant2 | white, yellow |
      | Program4 | Tenant2 | green, blue   |

  Scenario: Add tags to existing programs
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Active" "link"
    And I should see "Program2"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Details" in current page administration
    And I should see "Program name"
    And I should see "Program tags"
    And I set the field "Program tags" to "Tag example 1"
    Then I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Tag example 1"
    Then I click on "Program2" "link" in the "Program2" "table_row"
    And I navigate to "Details" in current page administration
    And I should see "Program name"
    And I should see "Program tags"
    And I set the field "Program tags" to "Tag example 2"
    Then I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Tag example 1"
    And I should see "Tag example 2"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Program2" "link" in the "Program2" "table_row"
    And I navigate to "Details" in current page administration
    And I should see "Tag example 2"
    And I should not see "Tag example 1"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Details" in current page administration
    And I should see "Tag example 1"
    And I should not see "Tag example 2"
    And I click on "[data-value='Tag example 1']" "css_element"
    Then I press "Save changes"
    And I navigate to "Details" in current page administration
    And I should not see "Tag example 2"
    And I should not see "Tag example 1"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Active" "link"
    And I should not see "Nothing to display"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should see "Program3"
    And I should see "Program4"
    And I log out

  Scenario: Search by tags on existing programs
    Given user "user13" has a manager position over users "user11,user12" with permissions "7"
    When I log in as "user13"
    And I change window size to "large"
    And I switch editing mode on
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "blue"
    And I should see "Program1"
    And I should see "Program2"
    And I should not see "Program3"
    And I should not see "Program4"
    And I click on "Program1" "link"
    And I should see "Program1"
    And I should see "Content"
    And I should see "Schedule"
    And I should see "Users"
    And I should see "Dynamic rules"
    Then I follow "Dashboard"
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "green"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should not see "Program3"
    And I should not see "Program4"
    And I log out
    Then I log in as "user11"
    And I switch editing mode on
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "blue"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should not see "Program3"
    And I should not see "Program4"
    And I log out
