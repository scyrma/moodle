@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Dynamicrule outcome cohort allocate work as expected
  In order to test the dynamicrule cohort outcome
  As a privileged user
  I need to create a rule with cohort outcome and validate it works as expected

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each
    And the following "categories" exist:
      | name    | category  | idnumber |
      | Subcat1 | CAT1      | SCAT1    |
      | Subcat2 | CAT2      | SCAT2    |
    And the following dynamic rules exist:
      | name  | tenant  |
      | Rule0 | Default tenant |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant2 |

  Scenario: Check availability of cohort outcome as admin.
    When I log in as "admin"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule0'"
    And I click on "Actions" "tool_wp > Tab"
    Then "No available cohorts" "icon" should exist
    # Add system cohort
    And the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule0'"
    And I click on "Actions" "tool_wp > Tab"
    And "No available cohorts" "icon" should not exist
    And I should see "Add to cohort"

  Scenario: Check availability of cohort member conditions as tenant.
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Actions" "tool_wp > Tab"
    Then "No available cohorts" "icon" should exist
    # Add system cohort, this should have no effect.
    And the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Actions" "tool_wp > Tab"
    And "No available cohorts" "icon" should exist
    # Add another tenant cohort, this should have no effect.
    And the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | Cohort in Cat 2      | CHCAT2   | Category     | CAT2      |
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Actions" "tool_wp > Tab"
    And "No available cohorts" "icon" should exist
    # Finally add this tenant cohort.
    And the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | Cohort in Cat 1      | CHCAT1   | Category     | CAT1      |
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Actions" "tool_wp > Tab"
    And "No available cohorts" "icon" should not exist
    And I should see "Add to cohort"

  Scenario: Add a rule with cohort member condition/action as admin.
    Given the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
      | Cohort in Cat 1      | CHCAT1   | Category     | CAT1      |
      | Cohort in Subcat 1   | CHSCAT1  | Category     | SCAT1     |
      | Cohort in Cat 2      | CHCAT2   | Category     | CAT2      |
      | Cohort in Subcat 2   | CHSCAT2  | Category     | SCAT2     |
    When I log in as "admin"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule0'"
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Add to cohort"
    And I expand the "Cohort" autocomplete
    And "System cohort 1" "autocomplete_suggestions" should exist
    And "Cohort in Cat 1" "autocomplete_suggestions" should exist
    And "Cohort in Subcat 1" "autocomplete_suggestions" should exist
    And "Cohort in Cat 2" "autocomplete_suggestions" should exist
    And "Cohort in Subcat 2" "autocomplete_suggestions" should exist
    And I click on "System cohort 1" item in the autocomplete list
    And I press "Save changes"
    Then I should see "Add users to cohort 'System cohort 1'"
    And I follow "Edit action"
    And I should see "System cohort 1" in the ".form-autocomplete-selection" "css_element"

  Scenario: Add a rule with cohort member condition/action as tenant.
    Given the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
      | Cohort in Cat 1      | CHCAT1   | Category     | CAT1      |
      | Cohort in Subcat 1   | CHSCAT1  | Category     | SCAT1     |
      | Cohort in Cat 2      | CHCAT2   | Category     | CAT2      |
      | Cohort in Subcat 2   | CHSCAT2  | Category     | SCAT2     |
    # Tenant 1
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Add to cohort"
    And I expand the "Cohort" autocomplete
    And "System cohort 1" "autocomplete_suggestions" should not exist
    And "Cohort in Cat 1" "autocomplete_suggestions" should exist
    And "Cohort in Subcat 1" "autocomplete_suggestions" should exist
    And "Cohort in Cat 2" "autocomplete_suggestions" should not exist
    And "Cohort in Subcat 2" "autocomplete_suggestions" should not exist
    And I click on "Cohort in Subcat 1" item in the autocomplete list
    And I press "Save changes"
    Then I should see "Add users to cohort 'Cohort in Subcat 1'"
    And I log out
    # Tenant 2
    When I log in as "tenantadmin2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule2'"
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Add to cohort"
    And I expand the "Cohort" autocomplete
    And "System cohort 1" "autocomplete_suggestions" should not exist
    And "Cohort in Cat 1" "autocomplete_suggestions" should not exist
    And "Cohort in Subcat 1" "autocomplete_suggestions" should not exist
    And "Cohort in Cat 2" "autocomplete_suggestions" should exist
    And "Cohort in Subcat 2" "autocomplete_suggestions" should exist
    And I click on "Cohort in Subcat 2" item in the autocomplete list
    And I press "Save changes"
    Then I should see "Add users to cohort 'Cohort in Subcat 2'"

  Scenario: Add a rule with cohort member condition/action as tenant with system context permission.
    Given the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
      | Cohort in Cat 1      | CHCAT1   | Category     | CAT1      |
      | Cohort in Subcat 1   | CHSCAT1  | Category     | SCAT1     |
    And the following "system role assigns" exist:
      | user         | role    | contextlevel |
      | tenantadmin1 | manager | System       |
    # Tenant 1
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Add to cohort"
    And I expand the "Cohort" autocomplete
    And "System cohort 1" "autocomplete_suggestions" should exist
    And "Cohort in Cat 1" "autocomplete_suggestions" should exist
    And "Cohort in Subcat 1" "autocomplete_suggestions" should exist
    And I click on "System cohort 1" item in the autocomplete list
    And I press "Save changes"
    Then I should see "Add users to cohort 'System cohort 1'"
