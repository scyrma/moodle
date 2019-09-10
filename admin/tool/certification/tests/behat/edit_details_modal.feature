@tool @tool_certification @moodleworkplace @theme_workplace
Feature: Edit certification details using edit details modal
  In order to edit a certification
  As a manager
  I need to open the edit details modal from certification content, create new certification and certification list

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
      | Program2 | 0        | Tenant1  |
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  |
      | Certification1 | 0        | Tenant1 |
      | Certification2 | 0        | Tenant1 |
      | Certification3 | 0        | Tenant2 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager2 | tool_certification_manager | System       |           |

  @javascript
  Scenario: Delete existing archived programs
    When I log in as "manager1"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    And I should see "Active certifications"
    And I should see "Certification1"
    And I should see "Certification2"
    And I should not see "Certification3"
    Then I click on ".edit_details" "css_element" in the "Certification1" "table_row"
    And I should see "Edit certification"
    And I should see "Certification full name"
    Then I set the field "fullname" to "Certification1new"
    Then I press "Save" in the modal form dialogue
    And I should see "Certification1new"
    Then I click on ".edit_certification" "css_element" in the "Certification1new" "table_row"
    And I should not see "Details"
    And I should see "Schedule"
    And I should see "Users"
    Then I click on "Edit details" "button"
    And I should see "Edit certification" in the ".modal-dialog .modal-title" "css_element"
    Then I set the field "fullname" to "newCertification1"
    Then I press "Save" in the modal form dialogue
    And I should see "Schedule"
    Then I navigate to "Courses > Certifications" in site administration
    Then I click on "Add new certification" "link"
    Then I should see "New certification"
    And I set the field "Certification full name" to "Certification example 4"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program1" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I press "Save" in the modal form dialogue
    And I should see "Schedule"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "newCertification1"
    And I should see "Certification2"
    And I should see "Certification example 4"
    And I should not see "Certification3"
    And I log out
    When I log in as "manager2"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    And I should see "Active certifications"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should see "Certification3"
    And I should not see "Certification example 4"
    And I log out