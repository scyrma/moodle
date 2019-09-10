@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing outcomes
  In order to manage rule outcomes
  As a manager
  I need to be able to add and edit different outcomes

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