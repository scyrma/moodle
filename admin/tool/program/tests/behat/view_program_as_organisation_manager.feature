@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: View program as organisation manager
  In order to test that organisation manager cannot edit program
  As a manager
  I need to create a program and log in with user to test if user can modify program

  Background:
    Given "1" tenants exist with "4" users and "2" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_program_manager     | System       |           |
      | manager1 | tool_dynamicrule_manager | System       |           |
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    Given the following users allocations to programs exist:
      | program  | user    |
      | Program1 | user11  |
    And user "user13" has a department manager position over users "user12,user11" with permissions "3"

  Scenario: Organisation manager should not be able to edit program
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course11" item in the autocomplete list
    And I click on "Course12" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    # We want to test that org manager sees active rules.
    Then I click on "#program_dynamic_rules_tab-tab" "css_element"
    When I click on "Edit actions of rule 'Users allocated to program'" "link"
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the ".confirmation-dialogue" "css_element"
    And I log out
    When I log in as "user13"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And "Edit content" "link" should not exist
    Then I click on "Allocate users" "link" in the "Program1" "table_row"
    And "Edit details" "button" should not exist
    Then I click on "Content" "link"
    And I should see "Course12"
    And I should see "Course11"
    And I should see "All in order"
    And "Add a set or a course" "button" should not exist
    And "Move Course11" "button" should not exist
    Then I click on "Schedule" "link"
    And I should see "Availability"
    And I should see "Allocation window"
    And "Save changes" "button" should not exist
    Then I click on "Dynamic rules" "link"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to program 'Program1'"
    And I should see "Send notification"
    Then I click on "Users" "link"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    And I should see "User 11"
    And I should see "User 12"
    And I log out