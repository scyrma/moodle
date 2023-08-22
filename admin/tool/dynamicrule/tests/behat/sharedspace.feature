@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Shared space in tool_dynamicrule
  As a global admin
  I can use Dynamic rules in the Shared space

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each
    And shared space is enabled

  Scenario: When in shared space the Dynamic rules is available
    Given I log in as "admin"
    When I click on "#workplace-menulink" "css_element"
    Then I should see "All users" in the ".workplace-menu" "css_element"
    And I should see "Dynamic rules" in the ".workplace-menu" "css_element"
    And I switch to tenant "Shared space"
    And I click on "#workplace-menulink" "css_element"
    And I should see "All users" in the ".workplace-menu" "css_element"
    And I should see "Dynamic rules" in the ".workplace-menu" "css_element"
    And I log out

  Scenario: Add a rule with conditions and actions in shared space
    Given I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'First name' is equal to User"
    And I should see "6 total matches"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I should see "Action is not saved"
    And I press "Save changes"
    Then I should see "Send notification 'Test notification' to users"
    And I should not see "Add actions to this rule"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule1"
    And I should see "Send notification 'Test notification' to users"
    Then I follow "Edit rule 'Rule1'"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I should see "Since some users matched this rule in the past, you will be only able to edit rule actions. You might consider duplicating it to modify its conditions"
    And I press "Edit anyway"
    And I click on "Conditions" "tool_wp > Tab"
    And "Edit" "button" should not exist in the "Conditions" "tool_wp > Tab content"
    And "Delete condition" "button" should not exist
    And I should not see "total matches"
    And I should not see "Add conditions to this rule"
    And "#conditions-menu" "css_element" should not exist
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Delete action"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Send notification 'Second test' to users"
    And I should see "Add actions to this rule"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "No actions on this rule"
    And "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And I click on "Duplicate rule 'Rule1'" "link"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    Then I follow "Edit rule 'Rule1 Copy 1'"
    And I follow "Delete condition"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Users whose value for profile field 'First name' is equal to User"

  Scenario: Check matching report for the shared rule
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | RuleS |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'First name' is equal to User"
    And I should see "6 total matches"
    And I follow "View matching users"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I should see "user13@invalid.com"
    And I should see "user21@invalid.com"
    And I should see "user22@invalid.com"
    And I should see "user23@invalid.com"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"

  Scenario: Validate shared rule actions at different tenants
    Given the following dynamic rules exist:
      | name  | tenant  | archived |
      | RuleS | -       | 0        |
      | Rule1 | Tenant1 | 0        |
      | Rule2 | Tenant2 | 0        |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    Then I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And I should not see "Rule1"
    And I should not see "Rule2"
    And "Cannot enable rule 'RuleS' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And I should not see "Shared space" in the "RuleS" "table_row"
    And "Edit rule 'RuleS'" "link" should exist
    And "Edit details of rule 'RuleS'" "link" should exist
    And "Archive rule 'RuleS'" "link" should exist
    And "Duplicate rule 'RuleS'" "link" should exist
    And "View report for 'RuleS'" "link" should exist
    And I switch to tenant "Tenant1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And I should see "Rule1"
    And I should not see "Rule2"
    And I should see "Shared space" in the "RuleS" "table_row"
    And "Shared rule 'RuleS' can only be enabled from the shared space." "link" should be visible
    And "Edit rule 'RuleS'" "link" should not exist
    And "Edit details of rule 'RuleS'" "link" should not exist
    And "Archive rule 'RuleS'" "link" should not exist
    And "Duplicate rule 'RuleS'" "link" should exist
    And "View report for 'RuleS'" "link" should exist
    And I switch to tenant "Tenant2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And I should not see "Rule1"
    And I should see "Rule2"
    And I should see "Shared space" in the "RuleS" "table_row"
    And "Shared rule 'RuleS' can only be enabled from the shared space." "link" should be visible
    And "Edit rule 'RuleS'" "link" should not exist
    And "Edit details of rule 'RuleS'" "link" should not exist
    And "Archive rule 'RuleS'" "link" should not exist
    And "Duplicate rule 'RuleS'" "link" should exist
    And "View report for 'RuleS'" "link" should exist

  Scenario: Check matched report for the shared rule
    Given I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | RuleS |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I should see "Action is not saved"
    And I press "Save changes"
    Then I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'RuleS'"
    Then I should see "Nothing to display"
    Then I navigate to "Dynamic rules" in workplace launcher
    And I click on "Enable rule" "link"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'RuleS'"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I should see "user13@invalid.com"
    And I should see "user21@invalid.com"
    And I should see "user22@invalid.com"
    And I should see "user23@invalid.com"
    And I switch to tenant "Tenant1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And "Shared rule 'RuleS' can only be disabled from the shared space." "link" should be visible
    And I follow "View report for 'RuleS'"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I should see "user13@invalid.com"
    And I should not see "user21@invalid.com"
    And I should not see "user22@invalid.com"
    And I should not see "user23@invalid.com"
    And I switch to tenant "Tenant2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And "Shared rule 'RuleS' can only be disabled from the shared space." "link" should be visible
    And I follow "View report for 'RuleS'"
    And I should not see "user11@invalid.com"
    And I should not see "user12@invalid.com"
    And I should not see "user13@invalid.com"
    And I should see "user21@invalid.com"
    And I should see "user22@invalid.com"
    And I should see "user23@invalid.com"

  Scenario: Check matched report for the shared rule as tenantadmins
    Given I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | RuleS |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I should see "Action is not saved"
    And I press "Save changes"
    Then I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'RuleS'"
    Then I should see "Nothing to display"
    Then I navigate to "Dynamic rules" in workplace launcher
    And I click on "Enable rule" "link"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'RuleS'"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I should see "user13@invalid.com"
    And I should see "user21@invalid.com"
    And I should see "user22@invalid.com"
    And I should see "user23@invalid.com"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And "Shared rule 'RuleS' can only be disabled from the shared space." "link" should be visible
    And I follow "View report for 'RuleS'"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I should see "user13@invalid.com"
    And I should not see "user21@invalid.com"
    And I should not see "user22@invalid.com"
    And I should not see "user23@invalid.com"
    And I log out
    And I log in as "tenantadmin2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And "Shared rule 'RuleS' can only be disabled from the shared space." "link" should be visible
    And I follow "View report for 'RuleS'"
    And I should not see "user11@invalid.com"
    And I should not see "user12@invalid.com"
    And I should not see "user13@invalid.com"
    And I should see "user21@invalid.com"
    And I should see "user22@invalid.com"
    And I should see "user23@invalid.com"

  Scenario: Duplicate shared rule
    Given I log in as "admin"
    When I switch to tenant "Shared space"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | RuleS |
    And I press "Save"
    And I should see "Add conditions to this rule"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I press "Save changes"
    And I switch to tenant "Tenant1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "RuleS"
    And "Shared rule 'RuleS' can only be enabled from the shared space." "link" should be visible
    And I should see "Shared space" in the "RuleS" "table_row"
    And I follow "Duplicate rule 'RuleS'"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I should see "RuleS Copy 1"
    And I should not see "Shared space" in the "RuleS Copy 1" "table_row"
    And I should see "RuleS"
    And I should see "Shared space" in the "RuleS" "table_row"
    And I switch to tenant "Tenant2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Duplicate rule 'RuleS'"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I should see "RuleS Copy 1"
    And I should not see "RuleS Copy 2"
    And I follow "Duplicate rule 'RuleS'"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I should see "RuleS Copy 2"
