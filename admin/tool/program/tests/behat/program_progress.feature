@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Complete a program and check if user and manager can view the correct status
  In order to test that user and manager view the correct status
  As a manager
  I need to create a program and complete it

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "permission overrides" exist:
      | capability                | permission | role                 | contextlevel | reference |
      | tool/program:coursereset  | Allow      | tool_program_manager | System       |           |
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
      | Program1 | user12  |
    Given user "user13" has a department lead position over users "user11,user12" with permissions "3"

  Scenario: User completes program, status is correct and admin resets completion for this user
    Given the following users allocations to tenants exist:
    | user  | tenant  |
    | admin | Tenant1 |
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    And I set the field "Select courses" to "Course 11"
    Then I press "Save changes"
    And I log out
    When I log in as "user11"
    Then I press "Enrol" for the "Course11" program course
    And I should see "Course11"
    And I should see "Announcements"
    And I should see "Topic 1"
    And I should see "Topic 2"
    And I toggle the manual completion state of "URL1"
    And I toggle the manual completion state of "URL2"
    And I am on homepage
    And I should see "100%"
    And I should see "Completed"
    And I log out
    When I log in as "user13"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Users" "link" in the "Program1" "table_row"
    Then I should see "Completed" in the "User 11" "table_row"
    Then I should see "Open" in the "User 12" "table_row"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Progress report" "link" in the "Program1" "table_row"
    And I should see "User 11"
    And I should see "Completed" in the "User 11" "table_row"
    And I should see "100%" in the "User 11" "table_row"
    And I should see "User 12"
    And I should see "Open" in the "User 12" "table_row"
    And I should see "0%" in the "User 12" "table_row"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I press "User 11"
    And I click on "1 completed programs" "link"
    And I should see "Programs : User 11"
    And I should see "Program1"
    And I should see "Completed" in the "Program1" "table_row"
    And I should see "100%" in the "Program1" "table_row"
    And I log out
    And I log in as "admin"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Users" "link" in the "Program1" "table_row"
    And I should see "Completed" in the "User 11" "table_row"
    And I click on "Program reset" "link" in the "User 11" "table_row"
    And I click on "Yes" "button" in the ".modal-dialog" "css_element"
    And I click on "Users" "link" in the ".wptabs" "css_element"
    And "Program reset" "link" should not exist in the "User 11" "table_row"
    And I log out
    And I trigger cron
    And I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Users" "link" in the "Program1" "table_row"
    And I click on "Users" "link" in the ".wptabs" "css_element"
    And "Program reset" "link" should exist in the "User 11" "table_row"
    And I should not see "Completed" in the "User 11" "table_row"
    And I should see "Open" in the "User 11" "table_row"
    And I log out
