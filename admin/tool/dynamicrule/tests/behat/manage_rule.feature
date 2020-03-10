@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing rules
  In order to manage rules
  As a manager
  I need to be able to add, edit, archive and unarchive rules

  Background:
    Given "2" tenants exist with "3" users and "2" courses in each

  Scenario: Add a rule with conditions and actions
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I should see "1 total matches"
    And I should not see "Add conditions to this rule"
    And I follow "View matching users"
    Then I should see "tenantadmin1@invalid.com" in the ".modal-body" "css_element"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    And I follow "Edit condition"
    And I should not see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "2 total matches"
    And I follow "View matching users"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I should see "Action is not saved"
    And I press "Save changes"
    Then I should see "Send notification 'Test notification' to users"
    And I should not see "Add actions to this rule"
    And I follow "Edit action"
    And I should not see "Action is not saved"
    And I set the field "Subject" to "Second test"
    And I press "Save changes"
    Then I should see "Send notification 'Second test' to users"
    Then I should not see "Send notification 'Test notification' to users"
    And I navigate to "Dynamic rules" in site administration
    Then I should see "Rule1"
    And I should see "Send notification 'Second test' to users"
    Then I follow "Edit rule 'Rule1'"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the ".confirmation-dialogue" "css_element"
    And I run all adhoc tasks
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I click on "Edit" "button" in the ".confirmation-dialogue" "css_element"
    Then I should see "0 total matches"
    And I follow "View matching users"
    Then I should see "Nothing to display"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    And I follow "Delete condition"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Users who have never logged in"
    And I should see "Add conditions to this rule"
    And I follow "Actions"
    And I follow "Delete action"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Send notification 'Second test' to users"
    And I should see "Add actions to this rule"
    And I navigate to "Dynamic rules" in site administration
    And I should see "No conditions on this rule"
    And I should see "No actions on this rule"
    And "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible

  Scenario: Check editing rule conditions and actions
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And the "class" attribute of "Edit condition" "link" should contain "disabled"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And the "class" attribute of "Edit condition" "link" should not contain "disabled"
    Then I should see "Users who have logged in at least once"
    And I follow "Edit condition"
    And the "class" attribute of "Edit condition" "link" should contain "disabled"
    And I should not see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "Users who have never logged in"
    And the "class" attribute of "Edit condition" "link" should not contain "disabled"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And the "class" attribute of "Edit action" "link" should contain "disabled"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I should see "Action is not saved"
    And I press "Save changes"
    Then I should see "Send notification 'Test notification' to users"
    And the "class" attribute of "Edit action" "link" should not contain "disabled"
    And I follow "Edit action"
    And I should not see "Action is not saved"
    And the "class" attribute of "Edit action" "link" should contain "disabled"
    And I set the field "Subject" to "Second test"
    And I press "Save changes"
    Then I should see "Send notification 'Second test' to users"
    Then I should not see "Send notification 'Test notification' to users"
    And the "class" attribute of "Edit action" "link" should not contain "disabled"

  Scenario: Editing basic dynamic rule details
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
      | Rule3 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Edit details of rule 'Rule1'" "link"
    And I set the following fields to these values:
      | Name | Newrule |
    And I press "Save" in the modal form dialogue
    And I navigate to "Dynamic rules" in site administration
    And I should see "Newrule"
    And I should not see "Rule1"
    And I should see "Rule2"
    And I should see "Rule3"

  Scenario: Edit rule name inline
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Edit name of rule 'Rule1'" "link"
    And I set the field "New name for rule 'Rule1'" to "New rule1"
    And I press key "13" in the field "New name for rule 'Rule1'"
    And I should see "New rule1"
    And I should not see "Rule1"

  Scenario: Editing advanced dynamic rule details
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Edit details of rule 'Rule1'" "link"
    And the field "Name" matches value "Rule1"
    And I should not see "Can't be triggered more than"
    # Check default form look when limiting is enabled.
    Then I click on "limitingenabled" "checkbox"
    And I should see "Can't be triggered more than"
    And the following fields match these values:
      | matchlimit     | 0    |
      | duringselector | ever |
    # Set matchlimit and check form look when editing rule again.
    And I set the field "matchlimit" to "5"
    And I press "Save" in the modal form dialogue
    And I navigate to "Dynamic rules" in site administration
    And I click on "Edit details of rule 'Rule1'" "link"
    And I should see "Can't be triggered more than"
    And the following fields match these values:
      | matchlimit     | 5    |
      | duringselector | ever |
    # Set matchinterval and check form look when editing rule again.
    And I set the field "duringselector" to "during"
    And the following fields match these values:
      | matchinterval[number]   |  0   |
      | matchinterval[timeunit] | days |
    And I set the field "matchinterval[number]" to "2"
    And I press "Save" in the modal form dialogue
    And I navigate to "Dynamic rules" in site administration
    And I click on "Edit details of rule 'Rule1'" "link"
    And I should see "Can't be triggered more than"
    And the following fields match these values:
      | matchlimit              |  5     |
      | duringselector          | during |
      | matchinterval[number]   |  2     |
      | matchinterval[timeunit] | days   |
    # Disable limiting and check that form look has become default when editing rule again.
    Then I click on "limitingenabled" "checkbox"
    And I should not see "Can't be triggered more than"
    And I press "Save" in the modal form dialogue
    And I navigate to "Dynamic rules" in site administration
    And I click on "Edit details of rule 'Rule1'" "link"
    And I should not see "Can't be triggered more than"
    Then I click on "limitingenabled" "checkbox"
    And I should see "Can't be triggered more than"
    And the following fields match these values:
      | matchlimit     | 0    |
      | duringselector | ever |

  Scenario: Create new dynamic rules for different tenants
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I log out
    When I log in as "tenantadmin2"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Site administration > Dynamic rules" in site administration
    And I should see "Rule2"
    And I should not see "Rule1"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should not see "Rule2"
    And I log out

  Scenario: Validate actions presence depending on tabs
    Given the following dynamic rules exist:
      | name  | tenant  | archived |
      | Rule1 | Tenant1 | 0        |
      | Rule2 | Tenant1 | 1        |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should not see "Rule2"
    And "Edit rule 'Rule1'" "link" should exist
    And "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Archive rule 'Rule1'" "link" should exist
    And "Unarchive rule 'Rule1'" "link" should not exist
    And "Delete rule 'Rule1'" "link" should not exist
    Then I click on "Archived" "link"
    And I should not see "Rule1"
    And I should see "Rule2"
    And "Edit rule 'Rule2'" "link" should not exist
    And "Enable rule 'Rule2'" "link" should not exist
    And "Disable rule 'Rule2'" "link" should not exist
    And "Archive rule 'Rule2'" "link" should not exist
    And "Unarchive rule 'Rule2'" "link" should exist
    And "Delete rule 'Rule2'" "link" should exist

  Scenario: Enabling and disabling rules
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule 'Rule1'" "link" should not be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should not be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    When I follow "Edit rule 'Rule1'"
    And I follow "User last login"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule 'Rule1'" "link" should be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should not be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    And I click on "Enable rule 'Rule1'" "link"
    Then I should see "Enabling it will affect" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule 'Rule1'" "link" should not be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    # Test that after refreshing the state is the same
    When I navigate to "Dynamic rules" in site administration
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule 'Rule1'" "link" should not be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    When I click on "Disable rule 'Rule1'" "link"
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule 'Rule1'" "link" should be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should not be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"

  Scenario: Archiving and unarchiving rules
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should see "Rule2"
    When I click on "Archive rule 'Rule1'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    And I should see "Rule2"
    When I click on "Archived" "link"
    And I should see "Rule1"
    And I should not see "Rule2"
    When I click on "Unarchive rule 'Rule1'" "link"
    And I should not see "Rule1"
    When I click on "Active" "link"
    And I should see "Rule1"
    And I should see "Rule2"

  Scenario: Archiving and deleting rules
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should see "Rule2"
    When I click on "Archive rule 'Rule1'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    And I should see "Rule2"
    When I click on "Archived" "link"
    And I should see "Rule1"
    And I should not see "Rule2"
    When I click on "Delete rule 'Rule1'" "link"
    And I should see "Are you sure you want to delete" in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    # Test that after refreshing the state is the same
    And I navigate to "Dynamic rules" in site administration
    And I click on "Archived" "link"
    And I should not see "Rule1"
    When I click on "Active" "link"
    And I should not see "Rule1"
    And I should see "Rule2"

  Scenario: View report for dynamic rule
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "View report for 'Rule1'"
    Then I should see "Nothing to display"

  Scenario: Duplicate a dynamic rule
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User last login"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I press "Save changes"
    And I navigate to "Dynamic rules" in site administration
    And I follow "Duplicate rule 'Rule1'"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    Then I should see "Rule1 Copy 1"

  Scenario: Rule reflects broken conditions and actions
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Add condition "User not enrolled to course11"
    And I follow "User not enrolled"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    # Add action "Enrol users into a course11"
    And I follow "Actions"
    And I follow "Enrol users into a course"
    And I open the autocomplete suggestions list
    And I click on "Course12" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    # Check it looks as expected.
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should see "Users who are not enrolled in course 'Course11'" in the "Rule1" "table_row"
    And I should not see "Error" in the "[data-source='rule:conditions']" "css_element"
    And I should see "Enrol in course 'Course12'" in the "Rule1" "table_row"
    And I should not see "Error" in the "[data-source='rule:actions']" "css_element"
    # Delete Course11
    And I navigate to "Courses" in workplace launcher
    And I should see the "Course and category management" management page
    And I click on category "Category1" in the management interface
    And I should see "Course11" in the "#course-listing" "css_element"
    And I click on "delete" action for "Course11" in management course listing
    And I should see "Delete C11"
    And I should see "Course11 (C11)"
    And I press "Delete"
    And I should see "Deleting C11"
    And I should see "C11 has been completely deleted"
    And I press "Continue"
    # Check condition is marked as broken.
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should see "User not enrolled" in the "Rule1" "table_row"
    And I should see "Error" in the "[data-source='rule:conditions'] span" "css_element"
    And I should see "Enrol in course 'Course12'" in the "Rule1" "table_row"
    And "[data-source='rule:actions'] span" "css_element" should not exist
    # Delete Course12
    And I navigate to "Courses" in workplace launcher
    And I should see the "Course and category management" management page
    And I click on category "Category1" in the management interface
    And I should see "Course12" in the "#course-listing" "css_element"
    And I click on "delete" action for "Course12" in management course listing
    And I should see "Delete C12"
    And I should see "Course12 (C12)"
    And I press "Delete"
    And I should see "Deleting C12"
    And I should see "C12 has been completely deleted"
    And I press "Continue"
    # Check action is marked as broken.
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should see "User not enrolled" in the "Rule1" "table_row"
    And I should see "Error" in the "[data-source='rule:conditions'] span" "css_element"
    And I should see "Enrol users into a course" in the "Rule1" "table_row"
    And I should see "Error" in the "[data-source='rule:actions'] span" "css_element"
    # Check error state in rule editing.
    Then I follow "Edit rule 'Rule1'"
    And I should see "This condition contains an error."
    And I should see "Error" in the "[data-instanceclass='tool_dynamicrule:user_not_enrolled']" "css_element"
    And I follow "Actions"
    And I should see "This action contains an error."
    And I should see "Error" in the "[data-instanceclass='enrol_dynamicrule:course_enrol']" "css_element"

  Scenario: Can't create new dynamic rule when site limit reached.
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule10 | Tenant1 |
      | Rule21 | Tenant2 |
    And the following config values are set as admin:
      | tool_dynamicrule_sitelimit     | 3 |
      | tool_dynamicrule_limitsenabled | 1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule11 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I should see "You have reached the limit for the number of dynamic rules on this site." in the "Dynamic rules limit reached" "dialogue"
    And I click on "OK" "button" in the "Dynamic rules limit reached" "dialogue"

  Scenario: Can't create new dynamic rule when tenant limit reached.
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule10 | Tenant1 |
      | Rule21 | Tenant2 |
      | Rule22 | Tenant2 |
    And the following config values are set as admin:
      | tool_dynamicrule_tenantlimit   | 2 |
      | tool_dynamicrule_limitsenabled | 1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule11 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I should see "You can only create 2 dynamic rule(s) on this site" in the "Dynamic rules limit reached" "dialogue"
    And I click on "OK" "button" in the "Dynamic rules limit reached" "dialogue"

  Scenario: Can't duplicate dynamic rule when site limit reached.
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule10 | Tenant1 |
      | Rule21 | Tenant2 |
    And the following config values are set as admin:
      | tool_dynamicrule_sitelimit     | 3 |
      | tool_dynamicrule_limitsenabled | 1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Duplicate rule 'Rule10'" "link"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Duplicate rule 'Rule10'" "link"
    And I should see "You have reached the limit for the number of dynamic rules on this site." in the "Dynamic rules limit reached" "dialogue"
    And I click on "OK" "button" in the "Dynamic rules limit reached" "dialogue"

  Scenario: Can't duplicate dynamic rule when tenant limit reached.
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule10 | Tenant1 |
      | Rule21 | Tenant2 |
      | Rule22 | Tenant2 |
    And the following config values are set as admin:
      | tool_dynamicrule_tenantlimit   | 2 |
      | tool_dynamicrule_limitsenabled | 1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Duplicate rule 'Rule10'" "link"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I navigate to "Dynamic rules" in site administration
    And I click on "Duplicate rule 'Rule10'" "link"
    And I should see "You can only create 2 dynamic rule(s) on this site" in the "Dynamic rules limit reached" "dialogue"
    And I click on "OK" "button" in the "Dynamic rules limit reached" "dialogue"
