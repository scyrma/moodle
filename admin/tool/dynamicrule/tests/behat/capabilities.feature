@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Check dynamic rules capabilities
  As a tenant administrator
  I want to check that dynamic rules are awesome and work correctly with capabilities

  Background:
    Given "2" tenants exist with "1" users and "1" courses in each

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
    And I press "Save" in the modal form dialogue
    And I follow "Course completed"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
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
    And I press "Save" in the modal form dialogue
    And I follow "Course completed"
    And I open the autocomplete suggestions list
    And I click on "CourseShared" item in the autocomplete list
    And I press key "27" in the field "Course"
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
    And "Enable rule 'Rule1'" "link" should be visible
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
    And I press "Save" in the modal form dialogue
    And I follow "Course completed"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
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
    And I press "Save" in the modal form dialogue
    And I follow "Course completed"
    And I open the autocomplete suggestions list
    And I click on "CourseShared" item in the autocomplete list
    And I press key "27" in the field "Course"
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
