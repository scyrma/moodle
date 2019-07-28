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
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1 | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2  | Position_t1_f1 |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user   | department      | position       |
      | user13 | Deparment_t1_d1 | Position_t1_f1 |
      | user12 | Deparment_t1_d1 | Position_t1_f2 |
      | user11 | Deparment_t1_d1 | Position_t1_f2 |

  Scenario: Organisation manager should not be able to edit certification
    When I log in as "manager1"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    And I should see "Certification1"
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    # We want to test that org manager sees active rules.
    Then I click on "#certification_dynamic_rules_tab-tab" "css_element"
    When I follow "Edit actions of rule 'Certification rules'"
    And I click on "Notification" "link" in the "#outcomes-menu" "css_element"
    And I set the following fields to these values:
      | Subject | Rule1 notification      |
      | Body    | Rule1 notification body |
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the ".confirmation-dialogue" "css_element"
    And I log out
    When I log in as "user13"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    And I should see "Certification1"
    And "Edit content" "link" should not exist
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And "Edit details" "button" should not exist
    Then I click on "Schedule" "link"
    And "Save changes" "button" should not exist
    Then I click on "Dynamic rules" "link"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to certification Certification1"
    And I should see "Send notification"
    Then I click on "Users" "link"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User 12" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I should see "User 11"
    And I should see "User 12"
    And I log out