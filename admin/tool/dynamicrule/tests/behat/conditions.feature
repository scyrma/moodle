@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing conditions
  In order to manage rules
  As a manager
  I need to be able to add and edit different conditions

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