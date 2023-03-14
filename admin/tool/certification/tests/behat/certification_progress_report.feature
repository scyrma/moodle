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
      | fullname         | archived | tenant  | program       | expirydateabsolute |
      | Certification_1A | 0        | Tenant1 | Program_name  | +1 day             |
      | Certification_2B | 0        | Tenant1 | Program_name  | +3 day             |
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
    And user "manager1" has a department lead position over users "user1,user2,user3,user4" with permissions "3"

  Scenario: Manager can create a user allocation and a certifications report
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Progress report" "link" in the "Certification_1A" "table_row"
    And I should see "Start date"
    And I should see "Due date"
    And I should see "Allocation date"
    And I should see "Program status"
    And I should see "Certified date"
    And I should not see "User c"
    And the following should exist in the "report-table" table:
      | Certification name  | Program name | Certification status | Allocation source | Expiry date             | Program progress |
      | User a              | Program_name | Open                 | Manual            |                         |          0%      |
      | User b              |              | Certified            | Manual            |  ##tomorrow##%d/%m/%y## |                  |
      | User e              | Program_name | Open                 | Manual            |                         |          0%      |
    And I click on "//button[contains(.,'User b')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Details"
    And I click on "1 certified certifications" "link"
    And I should see "Certifications : User b"
    And the following should exist in the "report-table" table:
      | Certification name  | Certification status | Expiry date            |
      | Certification_1A    | Certified            | ##tomorrow##%d/%m/%y## |
    And I log out
