@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Complete a program and check if user and manager can view the correct status
  In order to test that user and manager view the correct status
  As a manager
  I need to create a program and complete it

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    And the following "tool_tenant > users" exist:
      | username      | firstname | lastname | email                | tenant  |
      | manager1      | Manager   | 1        | manager1@example.com | Tenant1 |
    And the following "permission overrides" exist:
      | capability                | permission | role                 | contextlevel | reference |
      | tool/program:coursereset  | Allow      | tool_program_manager | System       |           |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_program_manager     | System       |           |
    And the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |
    And user "user13" has a department lead position over users "user11,user12" with permissions "3"

  Scenario: User completes program, status is correct and admin resets completion for this user
    Given the following users allocations to tenants exist:
    | user  | tenant  |
    | admin | Tenant1 |
    And the following "tool_program > program_courses" exist:
      | program      | course  | set    | sortorder |
      | Program1     | C11     |        |      1    |
    # Complete Program1 with user11 first.
    And I log in as "user11"
    And I am on "Course11" course homepage
    And I toggle the manual completion state of "URL1"
    And I toggle the manual completion state of "URL2"
    # Check status in program users report.
    When I log in as "user13"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program1" report row
    Then I should see "Completed" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I navigate to "Programs" in workplace launcher
    # Check status in program progress report.
    And I press "Progress report" action in the "Program1" report row
    And I should see "User 11"
    And I should see "Completed" in the "User 11" "table_row"
    And I should see "100%" in the "User 11" "table_row"
    And I should see "User 12"
    And I should see "Open" in the "User 12" "table_row"
    And I should see "0%" in the "User 12" "table_row"
    And I log in as "admin"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I press "Program reset" action in the "User 11" report row
    And I click on "Yes" "button" in the ".modal-dialog" "css_element"
    And I navigate to "Users" in current page administration
    And "Program reset" "link" should not exist in the "User 11" "table_row"
    And I log out
    And I trigger cron
    And I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program1" report row
    And "Program reset" "link" should exist in the "User 11" "table_row"
    And I should not see "Completed" in the "User 11" "table_row"
    And I should see "Open" in the "User 11" "table_row"

  Scenario: User dynamically allocated to program, can't reset program.
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Program completed.
    And I follow "Users not allocated to program"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I navigate to "Actions" in current page administration
    # Action Allocate users to programs.
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    And I press "Enable"
    And I click on "Enable" "button" in the ".modal-dialog" "css_element"
    # Program reset shouldn't be visible.
    And I log out
    And I trigger cron
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And "Program reset" "link" should not exist in the "User 13" "table_row"

  Scenario: User certification allocated to program, can't reset program.
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Certifications" in workplace launcher
    And I follow "New certification"
    And I set the following fields to these values:
      | Certification full name | Certification example 1 |
      | Certification ID number | 1                       |
    And I set the field "Select program" to "Program1"
    And I press "Save"
    And I navigate to "Users" in current page administration
    And I follow "Allocate users"
    And I set the field "Select users" to "User 13"
    And I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And "Program reset" "link" should not exist in the "User 13" "table_row"
