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
    When I am on the "My courses" page logged in as "user1"
    # Reload the page first, because "Welcome back ..." message is shown instead of heading.
    And I reload the page
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
    Then I press "Hide" action in the "Program1" report row
    And I log out
    Then I am on the "My courses" page logged in as "user1"
    And I should not see "Program1"
    And I should see "Program2"
    And I should see "Program3"
    And I log out
    Then I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Show" action in the "Program1" report row
    Then I press "Hide" action in the "Program2" report row
    Then I press "Archive" action in the "Program3" report row
    Then I click on "Archive" "button" in the "Confirm" "dialogue"
    And I log out
    Then I am on the "My courses" page logged in as "user1"
    And I should see "Program1"
    And I should not see "Program2"
    And I should not see "Program3"
    And I log out

  Scenario: Show or hide programs from kebab action menu
    When I am on the "My courses" page logged in as "user1"
    And I should see "Program1" in the "region-main" "region"
    And I should see "Program2" in the "region-main" "region"
    And I log out
    Then I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Program2" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I choose "Hide" in the open action menu
    And I should see "Hidden from learners" in the "#page-header" "css_element"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I should see "Show" in the "[data-rel='menu-content']" "css_element"
    And I log out
    Then I am on the "My courses" page logged in as "user1"
    And I should not see "Program1" in the "region-main" "region"
    And I should see "Program2" in the "region-main" "region"
    And I log out
    Then I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I choose "Show" in the open action menu
    And I should not see "Hidden from learners" in the "#page-header" "css_element"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I should see "Hide" in the "[data-rel='menu-content']" "css_element"
    And I log out
    Then I am on the "My courses" page logged in as "user1"
    And I should see "Program1" in the "region-main" "region"
    And I should see "Program2" in the "region-main" "region"
    And I log out
