@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Archive and restore a certification
  In order to archive a certification in the Certifications manager
  As a manager
  I need to see have an existing certification in the Certification manager view

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  |
      | Certification1 | 0        | Tenant1 |
      | Certification2 | 1        | Tenant2 |
      | Certification3 | 1        | Tenant1 |
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

  Scenario: We archive and restore one certification
    When I log in as "manager1"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    And I should see "Certification name" in the "table.report-table thead" "css_element"
    And I should see "Tags" in the "table.report-table thead" "css_element"
    And I should see "Program" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And "Name" "text" should appear before "table.report-table thead th.c1" "css_element"
    And "Tags" "text" should appear before "table.report-table thead th.c2" "css_element"
    And "Program" "text" should appear before "table.report-table thead th.c3" "css_element"
    # Archive certification
    Then I click on ".archive_certification" "css_element" in the "Certification1" "table_row"
    Then I click on "Archive" "button" in the ".confirmation-dialogue" "css_element"
    Then I click on "Active" "link"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    Then I click on "Archived" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    And I should see "Certification3"
    And I should see "Certification name" in the "table.report-table thead" "css_element"
    And I should see "Archived on" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And "Name" "text" should appear before "table.report-table thead th.c1" "css_element"
    And "Archived on" "text" should appear before "table.report-table thead th.c2" "css_element"
    # Restore certification
    Then I click on ".restore_certification" "css_element" in the "Certification1" "table_row"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should see "Certification3"
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    Then I log out
    # Manager from tenant 2
    And I log in as "manager2"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Nothing to display"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    Then I click on "Archived" "link"
    And I should not see "Nothing to display"
    Then I should see "Certification2"
    And I should not see "Certification1"
    And I should not see "Certification3"
    Then I log out