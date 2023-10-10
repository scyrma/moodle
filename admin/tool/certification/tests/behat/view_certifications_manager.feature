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
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 1        | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "roles" exist:
      | shortname            | name                  | archetype |
      | certificationmanager | certification manager |           |
    And the following "role assigns" exist:
      | user     | role                  | contextlevel | reference |
      | manager1 | certificationmanager  | System       |           |
      | manager2 | certificationmanager  | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role                 | contextlevel | reference |
      | tool/certification:edit | Allow      | certificationmanager | System       |           |
      | moodle/site:configview  | Allow      | certificationmanager | System       |           |

  Scenario: There are no existing certifications
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I should see "Nothing to display"

  Scenario: We create one certification
    Given the following "tool_certification > certifications" exist:
    | fullname                | archived | tenant  | program   |
    | Certification example 1 | 0        | Tenant1 | Program1  |
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I press "Archive" action in the "Certification example 1" report row
    Then I click on "Archive" "button" in the "Confirm" "dialogue"
    Then I navigate to "Archived" in current page administration
    And the following should exist in the "reportbuilder-table" table:
      | Certification name      | Archived on      |
      | Certification example 1 | ##today##%d/%m/%y## |
    Then I log out
    And I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Nothing to display"
    Then I should not see "Certification example 1"
    Then I navigate to "Archived" in current page administration
    And I should see "Nothing to display"
    Then I should not see "Certification example 1"
    Then I log out
