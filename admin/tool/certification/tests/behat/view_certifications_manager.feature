@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: View certification manager
  In order to see the certification in the Certifications manager
  As a manager
  I need to see all existing certifications in the Certification manager view

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 1        | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "roles" exist:
      | shortname     | name            | archetype |
      | certificationmanager | certification manager |           |
    And the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | manager1 | certificationmanager  | System       |           |
      | manager2 | certificationmanager  | System       |           |
      | user1    | user   | System       |           |
      | user2    | user   | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "certification manager" role:
      | capability              | permission |
      | tool/certification:edit | Allow      |
      | moodle/site:configview  | Allow      |
    And I log out

  Scenario: There are no existing certifications
    When I log in as "manager1"
    Then I navigate to "Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Nothing to display"

  Scenario: We create one certification
    When I log in as "manager1"
    Then I navigate to "Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Nothing to display"
    Then I click on "Add new certification" "link"
    Then I should see "New certification"
    And I set the field "Certification full name" to "Certification example 1"
    And I set the field "Certification ID number" to "1"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program1" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I press "Save" in the modal form dialogue
    And I navigate to "Certifications" in site administration
    Then I click on "Active" "link"
    Then I should see "Certification example 1"
    And I should see "Certification name" in the "table.report-table thead" "css_element"
    And I should see "Tags" in the "table.report-table thead" "css_element"
    And I should see "Program" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And "Certification name" "text" should appear before "table.report-table thead th.c1" "css_element"
    And "Tags" "text" should appear before "table.report-table thead th.c2" "css_element"
    And "Program" "text" should appear before "table.report-table thead th.c3" "css_element"
    Then I click on ".archive_certification" "css_element" in the "Certification example 1" "table_row"
    Then I press "Archive"
    Then I click on "Archived" "link"
    And I should see "Certification name" in the "table.report-table thead" "css_element"
    And I should see "Archived on" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And "Certification name" "text" should appear before "table.report-table thead th.c1" "css_element"
    And "Archived on" "text" should appear before "table.report-table thead th.c2" "css_element"
    Then I log out
    And I log in as "manager2"
    Then I navigate to "Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Nothing to display"
    Then I should not see "Certification example 1"
    Then I click on "Archived" "link"
    And I should see "Nothing to display"
    Then I should not see "Certification example 1"
    Then I log out