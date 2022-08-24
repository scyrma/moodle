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
    Given the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user11  |
    And user "user13" has a department lead position over users "user12,user11" with permissions "3"

  Scenario: Organisation manager should not be able to edit program
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    And I set the field "Select courses" to "Course11,Course12"
    Then I press "Save changes"
    # We want to test that org manager sees active rules.
    Then I navigate to "Dynamic rules" in current page administration
    Then I press "Edit actions" action in the "Users allocated to program" report row
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    When I log in as "user13"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I press "Users" action in the "Program1" report row
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
    And I set the field "Select users" to "User 12"
    Then I press "Save changes"
    And I should see "User 11"
    And I should see "User 12"
    Then I click on "Details" "link"
    And I should see "Program visibility"
    And I should see "Show"
    And I log out
