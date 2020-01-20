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
    Given the following tool certification data "certifications" exist:
      | fullname       | tenant  |
      | Certification1 | Tenant1 |
    Given the following users allocations to certifications exist:
      | certification  | user    |
      | Certification1 | user11  |
    And user "user13" has a department manager position over users "user12,user11" with permissions "3"

  Scenario: Organisation manager should not be able to edit certification
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    # We want to test that org manager sees active rules.
    Then I click on "#certification_dynamic_rules_tab-tab" "css_element"
    When I follow "Edit actions of rule 'Users allocated to certification'"
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification      |
      | Body    | Rule1 notification body |
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the ".confirmation-dialogue" "css_element"
    And I log out
    When I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And "Edit content" "link" should not exist
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And "Edit details" "button" should not exist
    Then I click on "Certification" "link" in the ".wptabs" "css_element"
    And "Save changes" "button" should not exist
    Then I click on "Dynamic rules" "link"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to certification Certification1"
    And I should see "Send notification"
    Then I click on "Users" "link"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    And I should see "User 11"
    And I should see "User 12"
    And I log out