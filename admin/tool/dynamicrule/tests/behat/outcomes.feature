@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing outcomes
  In order to manage rule outcomes
  As a tenant admin
  I need to be able to add and edit different outcomes

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each

  Scenario: Add a rule with award badge outcome
    Given I create the following badges:
      | name    |
      | Badge 1 |
      | Badge 2 |
    And I change window size to "large"
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I click on "Actions" "link" in the ".secondary-navigation" "css_element"
    And I follow "Award badge"
    And I set the field "Select badge" to "Badge 1"
    And I press "Save changes"
    Then I should see "Award badge 'Badge 1' to users"
    And I follow "Edit action"
    And I set the field "Select badge" to "Badge 2"
    And I press "Save changes"
    Then I should see "Award badge 'Badge 2' to users"

  Scenario: Add a rule with notification outcome
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I click on "Actions" "link" in the ".secondary-navigation" "css_element"
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
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I click on "Actions" "link" in the ".secondary-navigation" "css_element"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification     |
      | Body    | Rule1 notification body|
    And I should not see "{{userfirstname}}"
    And I click on "Available placeholders" "link"
    And I should see "{{userfirstname}}"
    And I should not see "Course completed"
    And I press "Save changes"
    Then I should see "Send notification 'Rule1 notification' to users"
    And I follow "Conditions"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    Then I should see "Users who have completed course 'Course11'"
    And I click on "Actions" "link" in the ".secondary-navigation" "css_element"
    Then I should see "Send notification 'Rule1 notification' to users"
    And I follow "Edit action"
    And I should not see "{{userfirstname}}"
    And I click on "Available placeholders" "link"
    And I should see "{{userfirstname}}"
    And I should see "Course completed"

  Scenario: Add a rule with award competency outcome
    Given the following lp "frameworks" exist:
      | shortname | idnumber |
      | Test-Framework | ID-FW1 |
    And the following lp "competencies" exist:
      | shortname | framework |
      | Competency ABC | ID-FW1 |
      | Competency XYZ | ID-FW1 |
    # TODO WP-1902: Remove role assignment when competencygrade is whitelisted for tenant admin.
    And the following "roles" exist:
      | shortname | name   | archetype |
      | role1     | Role 1 |           |
    And the following "system role assigns" exist:
      | user         | role  | contextlevel | reference |
      | tenantadmin1 | role1 | System       |           |
    And the following "permission overrides" exist:
      | capability                        | permission | role  | contextlevel | reference |
      | moodle/competency:competencygrade | Allow      | role1 | System       |           |
    And I change window size to "large"
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I click on "Actions" "link" in the ".secondary-navigation" "css_element"
    And I follow "Award competency"
    And I set the field "Select competency" to "Competency ABC"
    And I press "Save changes"
    Then I should see "Award competency 'Competency ABC' to users"
    And I follow "Edit action"
    And I set the field "Select competency" to "Competency XYZ"
    And I press "Save changes"
    Then I should see "Award competency 'Competency XYZ' to users"
