@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Program conditions and actions are marked as broken if program gets deleted or archived
  In order to test that conditions and actions are marked as broken
  As a manager
  I need to be able to create programs and create rules with them

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    And the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |

  Scenario: Program completed condition and Allocate users action is marked as broken if program gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Program completed
    And I follow "Program completed"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive programs in the rule's condition and action
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archive" "link" in the "Program1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    And I press "Archive"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Program2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Program completed"
    # Program completed condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_program:program_completed']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to programs"
    And I should see "Error" in the "[data-instanceclass='tool_program:allocation']" "css_element"
    And I log out

  Scenario: Program not completed condition and Deallocate users action is marked as broken if program gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Program not completed
    And I follow "Program not completed"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I follow "Actions"
    # Action Deallocate users from programs
    And I click on "Deallocate users from programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive programs in the rule's condition and action
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archive" "link" in the "Program1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    And I press "Archive"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Program2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Program not completed"
    # Program not completed condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_program:program_not_completed']" "css_element"
    And I follow "Actions"
    # Deallocate users action should be marked as broken
    And I should see "Deallocate users from programs"
    And I should see "Error" in the "[data-instanceclass='tool_program:deallocation']" "css_element"
    And I log out

  Scenario: Program overdue condition is marked as broken if program gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Program overdue
    And I follow "Program overdue"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive programs in the rule's condition and action
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archive" "link" in the "Program1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    And I press "Archive"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Program1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Program overdue"
    # Program overdue condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_program:program_overdue']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to programs"
    And I should see "Error" in the "[data-instanceclass='tool_program:allocation']" "css_element"
    And I log out

  Scenario: Program suspended condition is marked as broken if program gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Program suspended
    And I follow "Program suspended"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive programs in the rule's condition and action
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archive" "link" in the "Program1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    And I press "Archive"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Program1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Program suspended"
    # Program suspended condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_program:program_suspended']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to programs"
    And I should see "Error" in the "[data-instanceclass='tool_program:allocation']" "css_element"
    And I log out

  Scenario: Users allocated to program condition is marked as broken if program gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Users allocated to program
    And I follow "Users allocated to program"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive programs in the rule's condition and action
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archive" "link" in the "Program1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    And I press "Archive"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Program1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Users allocated to program"
    # Users allocated to program condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_program:user_allocated']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to programs"
    And I should see "Error" in the "[data-instanceclass='tool_program:allocation']" "css_element"
    And I log out

  Scenario: Users not allocated to program condition is marked as broken if program gets archived/deleted
    Given shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname      | idnumber | archived | tenant  |
      | SharedProgram | prog0    | 0        | -       |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition User not allocated to program
    And I follow "Users not allocated to program"
    # Check that shared programs are shown on the dropdown.
    And I open the autocomplete suggestions list
    And "SharedProgram" "autocomplete_suggestions" should exist
    And I click on "Program1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive programs in the rule's condition and action
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archive" "link" in the "Program1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    And I press "Archive"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Program1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Users not allocated to program"
    # Users not allocated to program condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_program:user_not_allocated']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to programs"
    And I should see "Error" in the "[data-instanceclass='tool_program:allocation']" "css_element"
    And I log out
