@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: View certification as organisation manager
  In order to test that organisation manager cannot edit certification
  As a manager
  I need to create a certification and log in with user to test if user can modify certification

  Background:
    Given "1" tenants exist with "4" users and "2" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
    Given the following "tool_certification > certifications" exist:
      | fullname       | tenant  |
      | Certification1 | Tenant1 |
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification1 | user11  |
    And user "user13" has a department lead position over users "user12,user11" with permissions "3"

  Scenario: Organisation manager should not be able to edit certification
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Certification1" "link"
    # We want to test that org manager sees active rules.
    Then I navigate to "Dynamic rules" in current page administration
    When I press "Edit actions" action in the "Users allocated to certification" report row
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification      |
      | Body    | Rule1 notification body |
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    When I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    Then I press "Users" action in the "Certification1" report row
    Then I navigate to "Certification" in current page administration
    And "Save changes" "button" should not exist
    Then I navigate to "Dynamic rules" in current page administration
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to certification 'Certification1'"
    And I should see "Send notification"
    Then I click on "Users" "link"
    Then I click on "Allocate users" "link"
    And I set the field "Select users" to "User 12"
    Then I press "Save changes"
    And I should see "User 11"
    And I should see "User 12"
    Then I click on "Details" "link"
    And I should see "Certification ID number"
    And I log out
