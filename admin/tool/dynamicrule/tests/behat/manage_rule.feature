@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing
  In order to manage rules
  As a manager
  I need to be able to add, edit, archive and unarchive rules

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_dynamicrule_manager | System       |           |
      | manager2 | tool_dynamicrule_manager | System       |           |

  Scenario: Add a rule with conditions and actions
    When I log in as "manager1"
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
    Then I should see "manager1@example.com" in the ".modal-body" "css_element"
    And I click on "Cancel" "button" in the ".modal-footer" "css_element"
    And I follow "Edit condition"
    And I should not see "Condition is not saved"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "2 total matches"
    And I follow "View matching users"
    And I should see "tenantadmin1@invalid.com"
    And I should see "user11@invalid.com"
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

  Scenario: Editing basic dynamic rule details
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
      | Rule3 | Tenant1 |
    When I log in as "manager1"
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
    When I log in as "manager1"
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
    When I log in as "manager1"
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
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I log out
    When I log in as "manager2"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Site administration > Dynamic rules" in site administration
    And I should see "Rule2"
    And I should not see "Rule1"
    And I log out
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I should see "Rule1"
    And I should not see "Rule2"
    And I log out

  Scenario: Validate actions presence depending on tabs
    Given the following dynamic rules exist:
      | name  | tenant  | archived |
      | Rule1 | Tenant1 | 0        |
      | Rule2 | Tenant1 | 1        |
    When I log in as "manager1"
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
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    Then "Cannot enable rule 'Rule1' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    And "Disable rule 'Rule1'" "link" should not be visible
    And "Disable rule 'Rule2'" "link" should not be visible
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
    And I click on "Enable rule 'Rule1'" "link"
    Then I should see "Enabling it will affect" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And "Enable rule 'Rule1'" "link" should not be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    # Test that after refreshing the state is the same
    When I navigate to "Dynamic rules" in site administration
    And "Enable rule 'Rule1'" "link" should not be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible
    When I click on "Disable rule 'Rule1'" "link"
    Then "Enable rule 'Rule1'" "link" should be visible
    And "Enable rule 'Rule2'" "link" should not be visible
    And "Disable rule 'Rule1'" "link" should not be visible
    And "Disable rule 'Rule2'" "link" should not be visible
    And "Cannot enable rule 'Rule2' unless it has conditions, actions and doesn't contain any errors" "link" should be visible

  Scenario: Archiving and unarchiving rules
    Given the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant1 |
    When I log in as "manager1"
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
    When I log in as "manager1"
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
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "View report for 'Rule1'"
    Then I should see "Nothing to display"

  Scenario: Add a rule with user last login condition
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User last login"
    And I set the field "lastlogintype" to "Over"
    And I press "Save changes"
    Then I should see "Users who last logged in over 1 week ago"
    And I follow "Edit condition"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    Then I should see "Users who have logged in at least once"
    And I follow "Edit condition"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "Users who have never logged in"

  Scenario: Add a rule with course completed condition
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "Course completed"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who have completed course 'Course11'"
    And I follow "Edit condition"
    And I open the autocomplete suggestions list
    And I click on "Course12" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who have completed course 'Course12'"
    And I should not see "Users who have completed course 'Course11'"

  Scenario: Add a rule with course not completed condition
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "Course not completed"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who have not completed course 'Course11'"
    And I follow "Edit condition"
    And I open the autocomplete suggestions list
    And I click on "Course12" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who have not completed course 'Course12'"
    And I should not see "Users who have not completed course 'Course11'"

  Scenario: Add a rule with user enrolled condition
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User enrolled"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who are enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I open the autocomplete suggestions list
    And I click on "Course12" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who are enrolled in course 'Course12'"
    And I should not see "Users who are enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Enrolment method" to "Self enrolment"
    And I press "Save changes"
    Then I should see "Users who are enrolled in course 'Course12' with enrolment method 'Self enrolment'"

  Scenario: Add a rule with user not enrolled condition
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User not enrolled"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who are not enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I open the autocomplete suggestions list
    And I click on "Course12" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who are not enrolled in course 'Course12'"
    And I should not see "Users who are not enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Enrolment method" to "Self enrolment"
    And I press "Save changes"
    Then I should see "Users who are not enrolled in course 'Course12' with enrolment method 'Self enrolment'"

  Scenario: Add a rule with user profile field condition
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Text input"
    And I set the following fields to these values:
      | Short name                    | example_field   |
      | Name                          | Example field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to user |
    And I click on "Save changes" "button"
    And I log out
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | Value | moodler |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'First name' is 'moodler'"
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | Value | Some value |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' is 'Some value'"

  Scenario: Add a rule with award badge outcome
    Given the following badges exists:
      | name    |
      | Badge 1 |
      | Badge 2 |
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "Actions"
    And I click on "Award badge" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list
    And I click on "Badge 1" item in the autocomplete list
    And I press key "27" in the field "Select badge"
    And I press "Save changes"
    Then I should see "Award badge 'Badge 1' to users"
    And I follow "Edit action"
    And I open the autocomplete suggestions list
    And I click on "Badge 2" item in the autocomplete list
    And I press key "27" in the field "Select badge"
    And I press "Save changes"
    Then I should see "Award badge 'Badge 2' to users"

  Scenario: Add a rule with notification outcome
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I press "Save changes"
    Then I should see "Send notification 'Rule1 notification' to users"
    And I follow "Edit action"
    And the following fields match these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I set the following fields to these values:
      | Subject | Rule1 notification test |
    And I press "Save changes"
    Then I should see "Send notification 'Rule1 notification test' to users"

  Scenario: Test placeholders in a rule with notification outcome
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I should not see "User placeholders"
    And I click on "Available placeholders" "link"
    And I should see "User placeholders"
    And I should not see "Course completed"
    And I press "Save changes"
    Then I should see "Send notification 'Rule1 notification' to users"
    And I follow "Conditions"
    And I follow "Course completed"
    And I open the autocomplete suggestions list
    And I click on "Course11" item in the autocomplete list
    And I press key "27" in the field "Course"
    And I press "Save changes"
    Then I should see "Users who have completed course 'Course11'"
    And I follow "Actions"
    Then I should see "Send notification 'Rule1 notification' to users"
    And I follow "Edit action"
    And I should not see "User placeholders"
    And I click on "Available placeholders" "link"
    And I should see "User placeholders"
    And I should see "Course completed"

  Scenario: Add a rule with award competency outcome
    Given the following lp "frameworks" exist:
      | shortname | idnumber |
      | Test-Framework | ID-FW1 |
    And the following lp "competencies" exist:
      | shortname | framework |
      | Competency ABC | ID-FW1 |
      | Competency XYZ | ID-FW1 |
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "Actions"
    And I click on "Award competency" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list
    And I click on "Competency ABC" item in the autocomplete list
    And I press key "27" in the field "Select competency"
    And I press "Save changes"
    Then I should see "Award competency 'Competency ABC' to users"
    And I follow "Edit action"
    And I open the autocomplete suggestions list
    And I click on "Competency XYZ" item in the autocomplete list
    And I press key "27" in the field "Select competency"
    And I press "Save changes"
    Then I should see "Award competency 'Competency XYZ' to users"

  Scenario: Duplicate a dynamic rule
    When I log in as "manager1"
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
    And I follow "Duplicate"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    Then I should see "Rule1 Copy 1"
