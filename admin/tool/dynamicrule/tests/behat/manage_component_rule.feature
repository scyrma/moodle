@tool @tool_dynamicrule @javascript @moodleworkplace @tool_program
Feature: Manage component rules
  In order to manage component rules
  As a tenant admin
  I need to be able to edit rules

  Background:
    Given "1" tenants exist with "3" users and "0" courses in each
    And the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |

  Scenario: Check that enable confirmation works correctly from inside action
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Dynamic rules" in current page administration
    #  1. no action added - no possiblity to enable
    And I press "Edit actions" action in the "Users allocated to program" report row
    And the "Enable" "button" should be disabled
    #  2. action is there, enabing new rule
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I press "Save changes"
    And I click on "Enable" "button"
    Then I should see "Are you sure you want to enable this rule? Enabling it will affect 2 users" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And "Disable rule" "field" in the "Users allocated to program 'Program1'" "table_row" should be visible
    #  3. some users matched, disabling and enabling again (no confirmation)
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Dynamic rules" in current page administration
    Then I click on "Disable rule" "field" in the "Users allocated to program 'Program1'" "table_row"
    And I press "Edit actions" action in the "Users allocated to program" report row
    And I click on "Enable" "button"
    And "Disable rule" "field" in the "Users allocated to program 'Program1'" "table_row" should be visible

  Scenario: Check that enable confirmation works correctly from actions list
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Dynamic rules" in current page administration
    #  1. no action added - no possiblity to enable
    Then "Enable rule 'Users allocated to program'" "field" should not exist
    And I press "Edit actions" action in the "Users allocated to program" report row
    And the "Enable" "button" should be disabled
    #  2. action is there, enabing new rule from actions list
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I press "Save changes"
    And I click on "Close" "button" in the ".modal-dialog" "css_element"
    Then I click on "Enable rule" "field" in the "Users allocated to program 'Program1'" "table_row"
    Then I should see "Are you sure you want to enable this rule? Enabling it will affect 2 users" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    #  3. some users matched, disabling and enabling again (no confirmation)
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Dynamic rules" in current page administration
    Then I click on "Disable rule" "field" in the "Users allocated to program 'Program1'" "table_row"
    Then I click on "Enable rule" "field" in the "Users allocated to program 'Program1'" "table_row"
    And "Enable rule" "field" in the "Users allocated to program 'Program1'" "table_row" should not be visible
    And "Disable rule" "field" in the "Users allocated to program 'Program1'" "table_row" should be visible

  Scenario: Check that component rules have scheduled rule indicating badges
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Dynamic rules" in current page administration
    And I should not see "Scheduled task" in the "Users allocated to program 'Program1'" "table_row"
    And I should not see "Scheduled task" in the "Users not allocated to program 'Program1'" "table_row"
    And I should not see "Scheduled task" in the "Users who have status 'Completed' in program 'Program1'" "table_row"
    And I should see "Scheduled task" in the "Users who do not have status 'Completed' in program 'Program1'" "table_row"
    And I should see "Scheduled task" in the "Users who have status 'Overdue' in program 'Program1'" "table_row"
    And I should see "Scheduled task" in the "Users who have status 'Suspended' in program 'Program1'" "table_row"
