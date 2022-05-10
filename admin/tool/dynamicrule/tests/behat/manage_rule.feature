@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing rules
  In order to manage rules
  As a tenant admin
  I need to be able to add, edit, archive and unarchive rules

  Background:
    Given "2" tenants exist with "3" users and "2" courses in each

  Scenario: Add a rule with conditions and actions
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I should see "1 total matches"
    And I follow "Edit condition"
    And I should not see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "2 total matches"
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
    And I follow "Edit action"
    And I should not see "Action is not saved"
    And I set the field "Subject" to "Second test"
    And I press "Save changes"
    Then I should see "Send notification 'Second test' to users"
    Then I should not see "Send notification 'Test notification' to users"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule1"
    And I should see "Send notification 'Second test' to users"
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
    Then I should not see "Users who have never logged in"
    And I should see "Add conditions to this rule"

  Scenario: Check matching report for the rule
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
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
    And I click on "Cancel" "button" in the "View matching users" "dialogue"
    And I follow "Edit condition"
    And I should not see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "2 total matches"
    And I follow "View matching users"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"

  Scenario: Check matching report for the rule with limiting matching
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name            | Rule1|
      | limitingenabled | 1    |
      | matchlimit      | 1    |
      | duringselector  | ever |
    And I press "Save"
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
    And I click on "Cancel" "button" in the "View matching users" "dialogue"
    And I follow "Edit condition"
    And I should not see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "2 total matches"
    And I follow "View matching users"
    And I should see "user11@invalid.com"
    And I should see "user12@invalid.com"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"

  Scenario: Check editing rule conditions and actions
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
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
    And I click on "Actions" "tool_wp > Tab"
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
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Edit details of rule 'Rule1'" "link"
    And I set the following fields to these values:
      | Name | Newrule |
    And I press "Save"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Newrule"
    And I should not see "Rule1"
    And I should see "Rule2"
    And I should see "Rule3"

  Scenario: Edit rule name inline
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I set the field "Edit name of rule 'Rule1'" to "New rule1"
    And I should see "New rule1"
    And I should not see "Rule1"

  Scenario: Editing advanced dynamic rule details
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Edit details of rule 'Rule1'" "link"
    And the field "Name" matches value "Rule1"
    And I should not see "Can't be applied more than"
    # Check default form look when limiting is enabled.
    Then I click on "limitingenabled" "checkbox"
    And I should see "Can't be applied more than"
    And the following fields match these values:
      | matchlimit     | 1    |
      | duringselector | ever |
    # Set matchlimit and check form look when editing rule again.
    And I set the field "matchlimit" to "5"
    And I press "Save"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Edit details of rule 'Rule1'" "link"
    And I should see "Can't be applied more than"
    And the following fields match these values:
      | matchlimit     | 5    |
      | duringselector | ever |
    # Set matchinterval and check form look when editing rule again.
    And I set the field "duringselector" to "per"
    And the following fields match these values:
      | matchinterval[number]   |  1   |
      | matchinterval[timeunit] | days |
    And I set the field "matchinterval[number]" to "2"
    And I press "Save"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Edit details of rule 'Rule1'" "link"
    And I should see "Can't be applied more than"
    And the following fields match these values:
      | matchlimit              |  5     |
      | duringselector          | per    |
      | matchinterval[number]   |  2     |
      | matchinterval[timeunit] | days   |
    # Disable limiting and check that form look has become default when editing rule again.
    Then I click on "limitingenabled" "checkbox"
    And I should not see "Can't be applied more than"
    And I press "Save"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Edit details of rule 'Rule1'" "link"
    And I should not see "Can't be applied more than"
    Then I click on "limitingenabled" "checkbox"
    And I should see "Can't be applied more than"
    And the following fields match these values:
      | matchlimit     | 1    |
      | duringselector | ever |

  Scenario: Create new dynamic rules for different tenants
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I log out
    When I log in as "tenantadmin2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I navigate to "Site administration > Dynamic rules" in site administration
    And I should see "Rule2"
    And I should not see "Rule1"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Rule1"
    And I should not see "Rule2"
    And I log out

  Scenario: Validate actions presence depending on tabs
    Given the following dynamic rules exist:
      | name  | tenant  | archived |
      | Rule1 | Tenant1 | 0        |
      | Rule2 | Tenant1 | 1        |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Rule1"
    And I should not see "Rule2"
    And "Edit rule 'Rule1'" "link" should exist
    And "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Archive rule 'Rule1'" "link" should exist
    And "Unarchive rule 'Rule1'" "link" should not exist
    And "Delete rule 'Rule1'" "link" should not exist
    Then I click on "Archived" "tool_wp > Tab"
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
    And I navigate to "Dynamic rules" in workplace launcher
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule" "link" in the "Rule1" "table_row" should not be visible
    And "Enable rule" "link" in the "Rule2" "table_row" should not be visible
    And "Disable rule" "link" in the "Rule1" "table_row" should not be visible
    And "Disable rule" "link" in the "Rule2" "table_row" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    When I follow "Edit rule 'Rule1'"
    And I follow "User last login"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule" "link" in the "Rule1" "table_row" should be visible
    And "Enable rule" "link" in the "Rule2" "table_row" should not be visible
    And "Disable rule" "link" in the "Rule1" "table_row" should not be visible
    And "Disable rule" "link" in the "Rule2" "table_row" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    And I click on "Enable rule" "link" in the "Rule1" "table_row"
    Then I should see "Conditions will be locked when at least one user is affected by this rule. Are you sure you want to enable this rule?" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule 'Rule1'" "link" should not be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule" "link" in the "Rule1" "table_row" should be visible
    And "Disable rule" "link" in the "Rule2" "table_row" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    # Test that after refreshing the state is the same
    When I navigate to "Dynamic rules" in site administration
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Enable rule" "link" in the "Rule1" "table_row" should not be visible
    And "Enable rule" "link" in the "Rule2" "table_row" should not be visible
    And "Disable rule" "link" in the "Rule1" "table_row" should be visible
    And "Disable rule" "link" in the "Rule2" "table_row" should not be visible
    And the "class" attribute of "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should contain "disabled"
    # Test that after some users matches, there is no confirmation on enabling.
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    When I navigate to "Dynamic rules" in site administration
    And I click on "Disable rule" "link" in the "Rule1" "table_row"
    And "Enable rule" "link" in the "Rule1" "table_row" should be visible
    And I click on "Enable rule" "link" in the "Rule1" "table_row"
    And "Enable rule" "link" in the "Rule1" "table_row" should not be visible
    And "Disable rule" "link" in the "Rule1" "table_row" should be visible

  Scenario: Enabling rule in the editing interface
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    When I follow "Edit rule 'Rule1'"
    And the "Enable" "button" should be disabled
    And I follow "User last login"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And the "Enable" "button" should be disabled
    And I click on "Actions" "tool_wp > Tab"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I press "Enable"
    Then I should see "Conditions will be locked when at least one user is affected by this rule. Are you sure you want to enable this rule?" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I should see "Rule1" in the "Active" "tool_wp > Tab content"
    And "Edit rule 'Rule1'" "link" should exist
    # Test that after some users matches, there is no confirmation on enabling.
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Disable rule" "link" in the "Rule1" "table_row"
    When I follow "Edit rule 'Rule1'"
    And I should see "Since some users matched this rule in the past, you will be only able to edit rule actions. You might consider duplicating it to modify its conditions"
    And I press "Edit anyway"
    And I press "Enable"
    And I should see "Rule1" in the "Active" "tool_wp > Tab content"
    And "Edit rule 'Rule1'" "link" should exist

  Scenario: Archiving and unarchiving rules
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Rule1"
    And I should see "Rule2"
    When I click on "Archive rule 'Rule1'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    And I should see "Rule2"
    When I click on "Archived" "tool_wp > Tab"
    And I should see "Rule1"
    And I should not see "Rule2"
    When I click on "Unarchive rule 'Rule1'" "link"
    And I should not see "Rule1"
    When I click on "Active" "tool_wp > Tab"
    And I should see "Rule1"
    And I should see "Rule2"

  Scenario: Archiving and deleting rules
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Rule1"
    And I should see "Rule2"
    When I click on "Archive rule 'Rule1'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    And I should see "Rule2"
    When I click on "Archived" "tool_wp > Tab"
    And I should see "Rule1"
    And I should not see "Rule2"
    When I click on "Delete rule 'Rule1'" "link"
    And I should see "Are you sure you want to delete" in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    # Test that after refreshing the state is the same
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Archived" "tool_wp > Tab"
    And I should not see "Rule1"
    When I click on "Active" "tool_wp > Tab"
    And I should not see "Rule1"
    And I should see "Rule2"

  Scenario: View empty report for dynamic rule
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'Rule1'"
    Then I should see "Nothing to display"

  Scenario: As a site admin I can see filters in report
    Given the following config values are set as admin:
      | showuseridentity | username,email |
    And the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    And I should see "2 total matches"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | You win     |
      | Body    | Your <a href="#">name is {{userfullname}}</a> |
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'Rule1'"
    Then I should see "Username" in the "report-table" "table"
    And I should see "Email address" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Username" in the "[data-region='report-filters']" "css_element"
    And I should see "Email address" in the "[data-region='report-filters']" "css_element"
    When the following config values are set as admin:
      | showuseridentity | username |
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'Rule1'"
    Then I should see "Username" in the "report-table" "table"
    And I should not see "Email address" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Username" in the "[data-region='report-filters']" "css_element"
    And I should not see "Email address" in the "[data-region='report-filters']" "css_element"

  Scenario: Duplicate a dynamic rule
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    And I follow "User last login"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Duplicate rule 'Rule1'"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    Then I should see "Rule1 Copy 1"

  @enrol_dynamicrule
  Scenario: Rule reflects broken conditions and actions
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    # Add condition "User not enrolled to course11"
    And I follow "User not enrolled"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    # Add action "Enrol users into a course11"
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course12"
    And I press "Save changes"
    # Check it looks as expected.
    And I navigate to "Dynamic rules" in workplace launcher
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
    And I navigate to "Dynamic rules" in workplace launcher
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
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Rule1"
    And I should see "User not enrolled" in the "Rule1" "table_row"
    And I should see "Error" in the "[data-source='rule:conditions'] span" "css_element"
    And I should see "Enrol users into a course" in the "Rule1" "table_row"
    And I should see "Error" in the "[data-source='rule:actions'] span" "css_element"
    # Check error state in rule editing.
    Then I follow "Edit rule 'Rule1'"
    And I should see "Invalid course"
    And I should see "Error" in the "[data-instanceclass='tool_dynamicrule:user_not_enrolled']" "css_element"
    And I click on "Actions" "tool_wp > Tab"
    And I should see "Invalid course"
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
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule11 |
    And I press "Save"
    And I navigate to "Dynamic rules" in workplace launcher
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
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule11 |
    And I press "Save"
    And I navigate to "Dynamic rules" in workplace launcher
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
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Duplicate rule 'Rule10'" "link"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I navigate to "Dynamic rules" in workplace launcher
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
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Duplicate rule 'Rule10'" "link"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    And I navigate to "Dynamic rules" in workplace launcher
    And I click on "Duplicate rule 'Rule10'" "link"
    And I should see "You can only create 2 dynamic rule(s) on this site" in the "Dynamic rules limit reached" "dialogue"
    And I click on "OK" "button" in the "Dynamic rules limit reached" "dialogue"

  Scenario: Rules list filters
    Given I create the following badges:
      | name    |
      | Badge 1 |
    And I change window size to "large"
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    # Create a first rule, enabled, with a condition and action.
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User last login"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Create second rule, not enabled, with different condition and action.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I follow "Time since user was created"
    And I press "Save changes"
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Award badge"
    And I set the field "Select badge" to "Badge 1"
    And I press "Save changes"
    # Test each filter.
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Rule1"
    And I should see "Rule2"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "rule:enabled_op" to "Yes"
    And I should see "Rule1"
    And I should not see "Rule2"
    And I press "Reset table"
    And I set the field "rule:rulename_op" to "contains"
    And I set the field "rule:rulename" to "Rule2"
    And I press the enter key
    And I should not see "Rule1"
    And I should see "Rule2"
    And I press "Reset table"
    And I set the field "rule:conditions_op" to "is equal to"
    And I set the field "rule:conditions" to "User last login"
    And I should see "Rule1"
    And I should not see "Rule2"
    And I press "Reset table"
    And I set the field "rule:actions_op" to "is equal to"
    And I set the field "rule:actions" to "Award badge"
    And I should not see "Rule1"
    And I should see "Rule2"

  Scenario: Show enabled/disabled dynamic rules to tenant admin, whom he has no right to enable/disable and edit.
    Given the following "courses" exist:
      | fullname | shortname  | category      |
      | Course1  | c1          | 0            |
      | Course2  | c2          | 0            |
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Dynamic rules" in workplace launcher
    # Rule to enroll users in Course1 and enable the rule.
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "3 total matches"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Enrol users into a course" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Course | Course1 |
      | Role   | Student |
    And I should see "Action is not saved"
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule1"
    And I should see "Enrol in course 'Course1'"
    And I should see "Role: Student"
    And I should see "Group: None"
    Then I follow "Edit rule 'Rule1'"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Rule to enroll users in Course2 but keep rule remain disabled.
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "3 total matches"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Enrol users into a course" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Course | Course2 |
      | Role   | Student |
    And I should see "Action is not saved"
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule1"
    And I should see "Enrol in course 'Course2'"
    And I should see "Role: Student"
    And I should see "Group: None"
    Then I follow "Edit rule 'Rule1'"
    # Create Rule to send notification to users and enable the rule.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule3 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "3 total matches"
    And I click on "Actions" "tool_wp > Tab"
    Then I should see "Add actions to this rule"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Test notification |
      | Body | Test body |
    And I should see "Action is not saved"
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule3"
    And I should see "Send notification 'Test notification' to users"
    And I follow "Edit rule 'Rule3'"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    # Tenant Admin login
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    # See active DR rule1 but not able to disable it
    Then I should see "Rule1"
    And I should see "Enrol in course 'Course1'"
    And I should see "Role: Student"
    And I should see "Group: None"
    And "You don't have sufficient rights to disable this rule 'Rule1'" "link" should be visible
    And "Edit details of rule 'Rule1'" "link" should not be visible
    And "Archive rule 'Rule1'" "link" should not be visible
    And "Duplicate rule 'Rule1'" "link" should not be visible
    And "Duplicate rule 'Rule1'" "link" should not be visible
    # See disabled DR rule2 but not able to enable it
    Then I should see "Rule2"
    And I should see "Enrol in course 'Course2'"
    And I should see "Role: Student"
    And I should see "Group: None"
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Edit details of rule 'Rule2'" "link" should not be visible
    And "Archive rule 'Rule2'" "link" should not be visible
    And "Duplicate rule 'Rule2'" "link" should not be visible
    And "Duplicate rule 'Rule2'" "link" should not be visible
    # See active DR rule3 and Can edit and disable the rule
    Then I should see "Rule3"
    And I should see "Send notification 'Test notification' to users"
    And "Disable rule" "link" in the "Rule3" "table_row" should be visible
    And "Edit details of rule 'Rule3'" "link" should be visible
    And "Archive rule 'Rule3'" "link" should be visible
    And "Duplicate rule 'Rule3'" "link" should be visible
    And "Duplicate rule 'Rule3'" "link" should be visible
    And I log out
