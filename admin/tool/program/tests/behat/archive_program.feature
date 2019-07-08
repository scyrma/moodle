@tool @tool_program @moodleworkplace @theme_workplace
Feature: Archive and restore programs
  In order to archive and restore a program
  As a manager
  I need to archive a program from the Programs manager view and be able to restore it

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
      | Program3 | Tenant1 |
    And the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
      | Certification2 | 0        | Tenant1 | Program1  |
      | Certification3 | 1        | Tenant1 | Program1  |
      | Certification4 | 1        | Tenant1 | Program3  |

  @javascript
  Scenario: Archive and restore existing programs
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    Then I click on "Archived" "link"
    And I should see "Nothing to display"
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    And I should see "Certification1" in the "Program1" "table_row"
    And I should see "Certification2" in the "Program1" "table_row"
    And I should see "Certification3" in the "Program1" "table_row"
    And I should see "Program3"
    And I should see "Certification4" in the "Program3" "table_row"
    Then I click on ".archive_program" "css_element" in the "Program2" "table_row"
    Then I press "Archive"
    Then I should not see "Program2"
    And I should see "Program1"
    And I should see "Program3"
    # Program1 contains non archived certifications therefore cannot be archived
    And ".archive_program" "css_element" should not exist in the "Program1" "table_row"
    # Program3 contains one archived certifications therefore can be archived
    And ".archive_program" "css_element" should exist in the "Program3" "table_row"
    Then I click on "Archived" "link"
    Then I should not see "Program1"
    And I should see "Program2"
    # Restore Program1
    Then I click on ".restore_program" "css_element" in the "Program2" "table_row"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out