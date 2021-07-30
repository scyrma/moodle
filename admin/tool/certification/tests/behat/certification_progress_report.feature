@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Ensure certification progress report works as expected
  In order to check that progress report works as expected
  As a manager
  I need be able to view reports from users enrolled to certifications

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "tool_program > programs" exist:
      | fullname     | archived | tenant  | generatecourses |
      | Program_name | 0        | Tenant1 | 1               |
    Given the following "tool_certification > certifications" exist:
      | fullname         | archived | tenant  | program       |
      | Certification_1A | 0        | Tenant1 | Program_name  |
      | Certification_2B | 0        | Tenant1 | Program_name  |
    Given the following "users" exist:
      | username      | firstname | lastname | email                |
      | user1         | User      | a        | user1@example.com    |
      | user2         | User      | b        | user2@example.com    |
      | user3         | User      | c        | user3@example.com    |
      | user4         | User      | e        | user3@example.com    |
      | manager1      | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user1      | Tenant1 |
      | user2      | Tenant1 |
      | user3      | Tenant1 |
      | user4      | Tenant1 |
      | manager1   | Tenant1 |
    Given the following "tool_certification > certification_users" exist:
      | certification    | user   |
      | Certification_1A | user1  |
      | Certification_1A | user2  |
      | Certification_2B | user1  |
      | Certification_1A | user4  |
    And the following "tool_program > program_completions" exist:
      | program      | user   |
      | Program_name | user2  |
    And the following "role assigns" exist:
      | user       | role                       | contextlevel | reference |
      | manager1   | tool_certification_manager | System       |           |
      | manager1   | tool_reportbuilder_manager | System       |           |

  Scenario: Manager can create a user allocation and a certifications report
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I click on "Progress report" "link" in the "Certification_1A" "table_row"
    And I should see "Certification name"
    And I should see "Start date"
    And I should see "Due date"
    And I should see "Expiry date"
    And I should see "Allocation date"
    And I should see "Allocation source"
    And I should see "Program"
    And I should see "Certification status"
    And I should see "Program status"
    And I should see "Program progress"
    And I should see "Certified date"
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should see "User e"
    And I should see "Manual" in the "User a" "table_row"
    And I should see "Program_name" in the "User a" "table_row"
    And I should see "Open" in the "User a" "table_row"
    And I should not see "Completed" in the "User a" "table_row"
    And I should see "0%" in the "User a" "table_row"
    And I should see "Manual" in the "User b" "table_row"
    And I should not see "Program_name" in the "User b" "table_row"
    And I should see "Certified" in the "User b" "table_row"
    And I click on "//button[contains(.,'User b')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Details"
    And I log out
