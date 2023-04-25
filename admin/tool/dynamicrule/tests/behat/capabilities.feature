@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Check dynamic rules capabilities
  As a tenant administrator
  I want to check that dynamic rules are awesome and work correctly with capabilities

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each

  Scenario: Check if tenant admin can edit rules
    #  1. Create a tenant with category and a course in it.
    #  2. Create another course (shared) in the Miscellaneous category.
    And the following "courses" exist:
      | fullname     | shortname  | category | enablecompletion |
      | CourseShared | CS         | 0        | 1                |
    #  3. As global admin login and switch to this tenant.
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    #  4. Create two rules, with conditions "User completed the course" and outcome "Notification".
    #  First rule points to the tenant course and second one to the shared course.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    # Create second rule.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "CourseShared"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule2 notification     |
      | Body    | Rule2 notification body|
    And I press "Save changes"
    And I log out
    #  5. Login as tenant admin.
    And I log in as "tenantadmin1"
    #  6. Make sure you can edit, archive, enable and disable the first rule.
    #  When you edit the rule you can edit all conditions and actions.
    And I navigate to "Dynamic rules" in workplace launcher
    And "Enable rule" "link" in the "Rule1" "table_row" should be visible
    And "Edit rule 'Rule1'" "link" should be visible
    And "Archive rule 'Rule1'" "link" should be visible
    And "Duplicate rule 'Rule1'" "link" should be visible
    Then I follow "Edit rule 'Rule1'"
    And the "Enable" "button" should be enabled
    And I follow "Edit condition"
    And I press "Save changes"
    And I follow "Actions"
    And I follow "Edit action"
    And I press "Save changes"
    And I press "Enable"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I navigate to "Dynamic rules" in workplace launcher
    When I click on "Archive rule 'Rule1'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    When I click on "Archived" "tool_wp > Tab"
    And I should see "Rule1"
    When I click on "Unarchive rule 'Rule1'" "link"
    And I should not see "Rule1"
    When I click on "Active" "tool_wp > Tab"
    And I should see "Rule1"
    #  7. Make sure you can NOT edit details, edit rule, duplicate, archive, enable or disable the second rule.
    #  You can not add/edit/delete outcomes either.
    And "Edit rule 'Rule2'" "link" should not exist
    And "Edit details of rule 'Rule2'" "link" should not exist
    And "Enable rule 'Rule2'" "link" should not exist
    And "Disable rule 'Rule2'" "link" should not exist
    And "Archive rule 'Rule2'" "link" should not exist
    And "Duplicate rule 'Rule2'" "link" should not exist
    #  8. Make sure you can view report on matching users on the first rule and CAN NOT view it on the second rule.
    And "View report for 'Rule1'" "link" should be visible
    And "View report for 'Rule2'" "link" should not exist

  Scenario: Check if tenant admin can restore or delete previously archived rules
    #  1. Create a tenant with category and a course in it.
    #  2. Create another course (shared) in the Miscellaneous category.
    And the following "courses" exist:
      | fullname     | shortname  | category | enablecompletion |
      | CourseShared | CS         | 0        | 1                |
    #  3. As global admin login and switch to this tenant.
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    #  4. Create two rules, with conditions "User completed the course" and outcome "Notification".
    #  First rule points to the tenant course and second one to the shared course.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    # Create second rule.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "CourseShared"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule2 notification     |
      | Body    | Rule2 notification body|
    And I press "Save changes"
    And I navigate to "Dynamic rules" in workplace launcher
    #5. Archive both rules
    Then I click on "Archive rule 'Rule1'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule1"
    Then I click on "Archive rule 'Rule2'" "link"
    And I should see "Are you sure you want to archive" in the "Confirm" "dialogue"
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I should not see "Rule2"
    When I click on "Archived" "tool_wp > Tab"
    And I should see "Rule1"
    And I should see "Rule2"
    And I log out
    #6. Login as tenant admin
    #7. Make sure you can restore/delete the first rule but can not restore/delete the second one.
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I click on "Archived" "tool_wp > Tab"
    Then I click on "Unarchive rule 'Rule1'" "link"
    And I should not see "Rule1"
    And "Unarchive rule 'Rule2'" "link" should not exist
    And "Delete rule 'Rule2'" "link" should not exist

  Scenario: Check capabilities for entity if entity was deleted
    # Create a tenant with category and a course in it.
    # Create another course (shared) in the Miscellaneous category.
    And the following "courses" exist:
      | fullname     | shortname  | category | enablecompletion |
      | CourseShared | CS         | 0        | 1                |
    # As global admin login and switch to this tenant.
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    # Create two rules, with conditions "User completed the course" and outcome "Notification".
    # First rule points to the tenant course and second one to the shared course.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    # Create second rule.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "CourseShared"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule2 notification     |
      | Body    | Rule2 notification body|
    And I press "Save changes"
    And I log out
    # Login as tenant admin and make sure you can edit the first rule but not the second.
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And "Edit rule 'Rule1'" "link" should be visible
    And "Edit rule 'Rule2'" "link" should not be visible
    And I log out
    Then I log in as "admin"
    And I delete the following courses:
      | fullname      | shortname  |
      | Course11      | C11        |
      | CourseShared  | CS         |
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And "Edit rule 'Rule1'" "link" should exist
    And "Edit details of rule 'Rule1'" "link" should exist
    And "Archive rule 'Rule1'" "link" should exist
    And "Duplicate rule 'Rule1'" "link" should exist
    And "Edit rule 'Rule2'" "link" should exist
    And "Edit details of rule 'Rule2'" "link" should exist
    And "Archive rule 'Rule2'" "link" should exist
    And "Duplicate rule 'Rule2'" "link" should exist
    And I click on "Edit rule 'Rule2'" "link"
    And I follow "Delete condition"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I follow "Actions"
    And I follow "Delete action"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I log out

  Scenario: Check capabilities for entity if entity was deleted and action is enrol into a course
    # Create a tenant with category and a course in it.
    # Create another course (shared) in the Miscellaneous category.
    And the following "courses" exist:
      | fullname     | shortname  | category | enablecompletion |
      | CourseShared | CS         | 0        | 1                |
      | CourseAction | CA         | 0        | 1                |
    # As global admin login and switch to this tenant.
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    # Create two rules, with conditions "User completed the course" and outcome "Notification".
    # First rule points to the tenant course and second one to the shared course.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    # Create second rule.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "CourseShared"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule2 notification     |
      | Body    | Rule2 notification body|
    And I press "Save changes"
    And I log out
    # Login as tenant admin and make sure you can edit the first rule but not the second.
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And "Edit rule 'Rule1'" "link" should be visible
    And "Edit rule 'Rule2'" "link" should not be visible
    And I log out
    Then I log in as "admin"
    And I delete the following courses:
      | fullname      | shortname  |
      | Course11      | C11        |
      | CourseShared  | CS         |
      | CourseAction  | CA         |
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And "Edit rule 'Rule1'" "link" should exist
    And "Edit details of rule 'Rule1'" "link" should exist
    And "Archive rule 'Rule1'" "link" should exist
    And "Duplicate rule 'Rule1'" "link" should exist
    And "Edit rule 'Rule2'" "link" should exist
    And "Edit details of rule 'Rule2'" "link" should exist
    And "Archive rule 'Rule2'" "link" should exist
    And "Duplicate rule 'Rule2'" "link" should exist
    And I click on "Edit rule 'Rule2'" "link"
    And I follow "Delete condition"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I follow "Actions"
    And I follow "Delete action"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I log out

  Scenario: Check capabilities for non existing condition class
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    #  Create a rule with two conditions and one action, enable it.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "User enrolled"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Modify the DB record on one condition to use non-existing class
    Then I modify first condition class to be invalid:
      | rule    |
      | Rule1   |
    And I run all adhoc tasks
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    # Make sure this rule is marked as "error".
    And I should see "Error" in the "Rule1" "table_row"
    And I should see "Missing condition" in the "Rule1" "table_row"
    # Make sure you can edit details, edit, archive, view matching users
    And "Edit rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Edit details of rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Archive rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And I click on "Edit rule 'Rule1'" "link"
    And I follow "View matching users"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification2     |
      | Body    | Rule1 notification2 body|
    And I press "Save changes"
    And I follow "Conditions"
    And I should not see "Edit condition" in the "#conditions-container [data-instanceclass=\"tool_dynamicrule:dummy\"]" "css_element"
    And I click on "Delete condition" "link" in the "#conditions-container [data-instanceclass=\"tool_dynamicrule:dummy\"]" "css_element"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I navigate to "Dynamic rules" in workplace launcher
    And I log out

  Scenario: Check capabilities for non existing action class
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    #  Create a rule with one condition and two actions, enable it.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course12"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Modify the DB record on one condition to use non-existing class
    Then I modify first action class to be invalid:
      | rule    |
      | Rule1   |
    And I run all adhoc tasks
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    # Make sure this rule is marked as "error".
    And I should see "Error" in the "Rule1" "table_row"
    # Make sure you can edit details, edit, archive, view matching users
    And "Edit rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Edit details of rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Archive rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And I click on "Edit rule 'Rule1'" "link"
    And I follow "View matching users"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification2     |
      | Body    | Rule1 notification2 body|
    And I press "Save changes"
    And I follow "Actions"
    And I should not see "Edit action" in the "#outcomes-container [data-instanceclass=\"tool_dynamicrule:dummy\"]" "css_element"
    And I click on "Delete action" "link" in the "#outcomes-container [data-instanceclass=\"tool_dynamicrule:dummy\"]" "css_element"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I navigate to "Dynamic rules" in workplace launcher
    And I log out

  Scenario: Check capabilities with user profile fields
    # As admin create two user profile fields, mark one of them as "not visible".
    # Create a custom role "Manage user rules" with the capabilities: tool/dynamicrule:manage and tool/tenant:browseusers
    # Assign this role to a non-admin user in a tenant
    Given the following "roles" exist:
      | shortname          | name              | archetype |
      | manageuserrules    | Manage user rules |           |
    And the following "role assigns" exist:
      | user     | role              | contextlevel | reference |
      | user11   | manageuserrules   | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role            | contextlevel | reference |
      | tool/dynamicrule:manage | Allow      | manageuserrules | System       |           |
      | tool/tenant:browseusers | Allow      | manageuserrules | System       |           |
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    And the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible |
      | text     | text_field1       | Text field 1      |  2       |
      | text     | text_field_hidden | Text field hidden |  0       |
    And I log in as "user11"
    # Create a new dynamic rule. Add "User profile field" condition, make sure the dropdown does not show hidden fields.
    # Select another user profile field and some condition for it.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    And the "userprofilefield" select box should contain "Text field 1"
    And the "userprofilefield" select box should not contain "Text field hidden"
    And I set the following fields to these values:
      | Field                                  | custom_profile_field_text_field1 |
      | custom_profile_field_text_field1_op    | 2                                |
      | custom_profile_field_text_field1_value | Workplace                        |
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    And I log out
    #  Login as admin and create another rule where condition checks the hidden user profile field.
    Then I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save"
    And I follow "User profile field"
    And the "userprofilefield" select box should contain "Text field 1"
    And the "userprofilefield" select box should contain "Text field hidden"
    And I set the following fields to these values:
      | Field                                        | custom_profile_field_text_field_hidden |
      | custom_profile_field_text_field_hidden_op    | 2                               |
      | custom_profile_field_text_field_hidden_value | Workplace2                      |
    And I press "Save changes"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule2 notification     |
      | Body    | Rule2 notification body|
    And I press "Save changes"
    And I log out
    # Login as the tenant user again, observe no permissions for Rule 2
    Then I log in as "user11"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule1"
    And "Edit rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Edit details of rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Archive rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "View report for 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And I should see "Rule2"
    And "Edit rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "Edit details of rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "Archive rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "View report for 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And I log out
    # Login as admin and delete both custom profile fields.
    Then I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I click on "Delete" "link" in the "Text field 1" "table_row"
    And I click on "Delete" "link" in the "Text field hidden" "table_row"
    And I log out
    Then I log in as "user11"
    And I navigate to "Dynamic rules" in workplace launcher
    # When field gets deleted, condition will become broken.
    # But user11 will not be able to edit it because user_can_add returns false for him too.
    And "Edit rule 'Rule1'" "link" should not exist in the "Rule1" "table_row"
    And "Edit details of rule 'Rule1'" "link" should not exist in the "Rule1" "table_row"
    And "Archive rule 'Rule1'" "link" should not exist in the "Rule1" "table_row"
    And "View report for 'Rule1'" "link" should not exist in the "Rule1" "table_row"
    And "Edit rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "Edit details of rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "Archive rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "View report for 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And I log out

  Scenario: Check capabilities with outcome
    # As admin create course enrolment outcome.
    # Create a custom role "Manage user rules" with the capabilities: tool/dynamicrule:manage and tool/tenant:browseusers
    # Assign this role to a non-admin user in a tenant
    Given the following "roles" exist:
      | shortname          | name              | archetype |
      | manageuserrules    | Manage user rules |           |
    And the following "role assigns" exist:
      | user     | role              | contextlevel | reference |
      | user11   | manageuserrules   | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role            | contextlevel | reference |
      | tool/dynamicrule:manage | Allow      | manageuserrules | System       |           |
      | tool/tenant:browseusers | Allow      | manageuserrules | System       |           |
    And the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    Given I log in as "admin"
    # Switch tenant.
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    # Edit Rule1.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule1'"
    # Add condition user profile field.
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    # Add action "Enrol users into a course11 (current tenant course)
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    # Edit Rule2.
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "Edit rule 'Rule2'"
    # Add condition user profile field.
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | User |
    And I press "Save changes"
    # Add action "Enrol users into a course21 (different tenant course)
    And I click on "Actions" "tool_wp > Tab"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course21"
    And I press "Save changes"
    And I log out
    # Login as the tenant admin, observe permissions for Rule 1 and no permissions for rule 2
    Then I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    Then I should see "Rule1"
    And "Edit rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Edit details of rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "Archive rule 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And "View report for 'Rule1'" "link" should exist in the "Rule1" "table_row"
    And I should see "Rule2"
    And "Edit rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "Edit details of rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "Archive rule 'Rule2'" "link" should not exist in the "Rule2" "table_row"
    And "View report for 'Rule2'" "link" should exist in the "Rule2" "table_row"
    And I log out
