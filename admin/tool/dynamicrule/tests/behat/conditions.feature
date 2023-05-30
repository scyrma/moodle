@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing conditions
  In order to manage rules
  As a tenant admin
  I need to be able to add and edit different conditions

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each
    And the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
      | Course 2 | C2        | topics | 1                |

  Scenario: Add a rule with user last login condition
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User last login"
    And I set the field "lastlogintype" to "Prior to the last..."
    And I press "Save changes"
    Then I should see "Users who last logged in prior to the last 1 week"
    And I follow "Edit condition"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    Then I should see "Users who have logged in at least once"
    And I follow "Edit condition"
    And I set the field "lastlogintype" to "Never"
    And I press "Save changes"
    Then I should see "Users who have never logged in"

  Scenario: Add a rule with course completed condition
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    Then I should see "Users who have completed course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Course" to "Course12"
    And I press "Save changes"
    Then I should see "Users who have completed course 'Course12'"
    And I should not see "Users who have completed course 'Course11'"

  Scenario: Add a rule with course not completed condition
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course not completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    Then I should see "Users who have not completed course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Course" to "Course12"
    And I press "Save changes"
    Then I should see "Users who have not completed course 'Course12'"
    And I should not see "Users who have not completed course 'Course11'"

  Scenario: Add a rule with user enrolled condition
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User enrolled"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    Then I should see "Users who are enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Course" to "Course12"
    And I press "Save changes"
    Then I should see "Users who are enrolled in course 'Course12'"
    And I should not see "Users who are enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Enrolment method" to "Manual enrolments"
    And I press "Save changes"
    Then I should see "Users who are enrolled in course 'Course12'"
    And I should see "Enrolment method: 'Manual enrolments'"

  Scenario: Add a rule with user not enrolled condition
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User not enrolled"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    Then I should see "Users who are not enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Course" to "Course12"
    And I press "Save changes"
    Then I should see "Users who are not enrolled in course 'Course12'"
    And I should not see "Users who are not enrolled in course 'Course11'"
    And I follow "Edit condition"
    And I set the field "Enrolment method" to "Manual enrolments"
    And I press "Save changes"
    Then I should see "Users who are not enrolled in course 'Course12'"
    And I should see "Enrolment method: 'Manual enrolments'"

  Scenario: Add a rule with user profile field condition
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | moodler |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'First name' is equal to moodler"

  Scenario: Add a rule with competency condition
    Given the following lp "frameworks" exist:
      | shortname      | idnumber |
      | Test-Framework | ID-FW1   |
    And the following lp "competencies" exist:
      | shortname      | framework |
      | Competency ABC | ID-FW1    |
      | Competency XYZ | ID-FW1    |
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
    And I follow "User has achieved competency"
    And I set the field "Select competency" to "Competency ABC"
    And I press "Save changes"
    Then I should see "Users who have achieved competency 'Competency ABC'"
    And I follow "Edit condition"
    And I should see "Competency ABC" in the ".form-autocomplete-selection" "css_element"
    And I set the field "Select competency" to "Competency XYZ"
    And I press "Save changes"
    Then I should see "Users who have achieved competency 'Competency XYZ'"
