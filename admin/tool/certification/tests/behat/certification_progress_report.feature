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
    And the following "tool_tenant > users" exist:
      | username      | firstname | lastname | email                | tenant  |
      | user1         | User      | a        | user1@example.com    | Tenant1 |
      | user2         | User      | b        | user2@example.com    | Tenant1 |
      | user3         | User      | c        | user3@example.com    | Tenant1 |
      | user4         | User      | e        | user3@example.com    | Tenant1 |
      | manager1      | Manager   | 1        | manager1@example.com | Tenant1 |
    And user "manager1" has a manager position over users "user1,user2,user3,user4" with permissions "3"
    And the following "tool_certification > certification_users" exist:
      | certification    | user   |
      | Certification_1A | user1  |
      | Certification_1A | user2  |
      | Certification_2B | user1  |
      | Certification_1A | user4  |
    And the following "tool_program > program_completions" exist:
      | program      | user   |
      | Program_name | user2  |

  Scenario: Manager can view user allocation and a certifications report
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Certifications" in workplace launcher
    And I press "Progress report" action in the "Certification_1A" report row
    And I should see "Allocation date"
    And I should see "Due date"
    And I should see "Certified date"
    And I should not see "User c"
    And the following should exist in the "reportbuilder-table" table:
      | -0-    | Current program | Certification status | Current program progress | Current program status | Is recertification |
      | User a | Program_name    | Open                 | 0%                       | Open                   | No                 |
      | User b |                 | Certified            |                          |                        | No                 |
      | User e | Program_name    | Open                 | 0%                       | Open                   | No                 |
    And I follow "User b"
    And I click on "1 certified certifications" "link"
    And I should see "User b"
    And the following should exist in the "reportbuilder-table" table:
      | Certification name  | Certification status | Certified date      |
      | Certification_1A    | Certified            | ##today##%d/%m/%y## |
    And I log out
