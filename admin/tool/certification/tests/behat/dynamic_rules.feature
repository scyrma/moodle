@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Certification conditions and actions are marked as broken if certification gets deleted or archived
  In order to test that conditions and actions are marked as broken
  As a manager
  I need to be able to create certifications and create rules with them

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    Given the following tool certification data "certifications" exist:
      | fullname       | tenant  |
      | Certification1 | Tenant1 |
      | Certification2 | Tenant1 |
    Given the following users allocations to certifications exist:
      | certification  | user    |
      | Certification1 | user11  |
      | Certification1 | user12  |

  Scenario: Certification certified condition and Allocate users action is marked as broken if certification gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Certification certified
    And I follow "Certification certified"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to certifications
    And I click on "Allocate users to certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification2" item in the autocomplete list
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive certifications in the rule's condition and action
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Certification2" "table_row"
    And I press "Archive"
    And I should not see "Certification1"
    And I should not see "Certification2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Certification2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Certification certified"
    # Certification certified condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_certification:certification_certified']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to certifications"
    And I should see "Error" in the "[data-instanceclass='tool_certification:allocation']" "css_element"
    And I log out

  Scenario: Certification not certified condition and Deallocate users action is marked as broken if certification gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Certification not certified
    And I follow "Certification not certified"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Deallocate users from certifications
    And I click on "Deallocate users from certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification2" item in the autocomplete list
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive certifications in the rule's condition and action
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Certification2" "table_row"
    And I press "Archive"
    And I should not see "Certification1"
    And I should not see "Certification2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Certification2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Certification not certified"
    # Certification not certified condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_certification:certification_not_certified']" "css_element"
    And I follow "Actions"
    # Deallocate users action should be marked as broken
    And I should see "Deallocate users from certifications"
    And I should see "Error" in the "[data-instanceclass='tool_certification:deallocation']" "css_element"
    And I log out

  Scenario: Certification overdue condition is marked as broken if certification gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Certification overdue
    And I follow "Certification overdue"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to certifications
    And I click on "Allocate users to certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification2" item in the autocomplete list
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive certifications in the rule's condition and action
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Certification2" "table_row"
    And I press "Archive"
    And I should not see "Certification1"
    And I should not see "Certification2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Certification1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Certification overdue"
    # Certification overdue condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_certification:certification_overdue']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to certifications"
    And I should see "Error" in the "[data-instanceclass='tool_certification:allocation']" "css_element"
    And I log out

  Scenario: Certification suspended condition is marked as broken if certification gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Certification suspended
    And I follow "Certification suspended"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to certifications
    And I click on "Allocate users to certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification2" item in the autocomplete list
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive certifications in the rule's condition and action
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Certification2" "table_row"
    And I press "Archive"
    And I should not see "Certification1"
    And I should not see "Certification2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Certification1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Certification suspended"
    # Certification suspended condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_certification:certification_suspended']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to certifications"
    And I should see "Error" in the "[data-instanceclass='tool_certification:allocation']" "css_element"
    And I log out

  Scenario: Users allocated to certification condition is marked as broken if certification gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Users allocated to certification
    And I follow "Users allocated to certification"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to certifications
    And I click on "Allocate users to certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification2" item in the autocomplete list
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive certifications in the rule's condition and action
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Certification2" "table_row"
    And I press "Archive"
    And I should not see "Certification1"
    And I should not see "Certification2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Certification1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Users allocated to certification"
    # Users allocated to certification condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_certification:user_allocated']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to certifications"
    And I should see "Error" in the "[data-instanceclass='tool_certification:allocation']" "css_element"
    And I log out

  Scenario: Users not allocated to certification condition is marked as broken if certification gets archived/deleted
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition User not allocated to certification
    And I follow "Users not allocated to certification"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to certifications
    And I click on "Allocate users to certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification2" item in the autocomplete list
    And I press "Save changes"
    And I should not see "Add actions to this rule"
    # Delete and archive certifications in the rule's condition and action
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    And I press "Archive"
    Then I click on "Archive" "link" in the "Certification2" "table_row"
    And I press "Archive"
    And I should not see "Certification1"
    And I should not see "Certification2"
    Then I click on "Archived" "link"
    Then I click on "Delete" "link" in the "Certification1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Dynamic rules" in workplace launcher
    Then I follow "Edit rule 'Rule1'"
    And I should see "Users not allocated to certification"
    # Users not allocated to certification condition should be marked as broken
    And I should see "Error" in the "[data-instanceclass='tool_certification:user_not_allocated']" "css_element"
    And I follow "Actions"
    # Allocate users action should be marked as broken
    And I should see "Allocate users to certifications"
    And I should see "Error" in the "[data-instanceclass='tool_certification:allocation']" "css_element"
    And I log out