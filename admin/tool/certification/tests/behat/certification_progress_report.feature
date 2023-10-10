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
      | user4         | User      | e        | user4@example.com    | Tenant1 |
      | user5         | User      | f        | user5@example.com    | Tenant1 |
      | manager1      | Manager   | 1        | manager1@example.com | Tenant1 |
      | manager2      | Manager   | 2        | manager2@example.com | Tenant1 |
    And user "manager1" has a manager position over users "user1,user2,user3,user4" with permissions "3"
    And user "manager2" has a manager position over users "user1,user2,user3,user4" with permissions "2"
    And the following "tool_certification > certification_users" exist:
      | certification    | user  | duedate    | duedatelocked |
      | Certification_1A | user1 |            |               |
      | Certification_1A | user2 |            |               |
      | Certification_2B | user1 |            |               |
      | Certification_1A | user4 | 1684335053 | 1             |
      | Certification_1A | user5 |            |               |
    And the following "tool_program > program_completions" exist:
      | program      | user   |
      | Program_name | user2  |

  Scenario: Manager can view user allocation and a certifications report
    When I log in as "manager1"
    And I change window size to "medium"
    Then I navigate to "Certifications" in workplace launcher
    And I press "Progress report" action in the "Certification_1A" report row
    And I should see "Allocation date"
    And I should see "Due date"
    And I should see "Certified date"
    And I should not see "User c"
    And I should not see "User f"
    And the following should exist in the "reportbuilder-table" table:
      | -0-    | Current program | Certification status | Current program progress | Current program status | Is recertification |
      | User a | Program_name    | Open                 | 0%                       | Open                   | No                 |
      | User b |                 | Certified            |                          |                        | No                 |
      | User e | Program_name    | Overdue              | 0%                       | Overdue                | No                 |
    And I follow "User b"
    And I click on "1 certified certifications" "link"
    And I should see "User b"
    And the following should exist in the "reportbuilder-table" table:
      | Certification name  | Certification status | Certified date      |
      | Certification_1A    | Certified            | ##today##%d/%m/%y## |
    And I log out

  Scenario: Manager who can view reports can view user allocation and a certifications report
    When I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    Then I should see "Reports"
    And I should not see "Certification_1A"
    # Viewing certification report for all certifications for users in my team
    And I follow "Certification progress"
    And I should not see "User c"
    And I should not see "User f"
    And the following should exist in the "reportbuilder-table" table:
      | -0-    | Certification name | Current program | Certification status | Current program progress | Current program status | Is recertification |
      | User a | Certification_1A   | Program_name    | Open                 | 0%                       | Open                   | No                 |
      | User b | Certification_1A   |                 | Certified            |                          |                        | No                 |
      | User e | Certification_1A   | Program_name    | Overdue              | 0%                       | Overdue                | No                 |
      | User a | Certification_2B   | Program_name    | Open                 | 0%                       | Open                   | No                 |

    And I press "Progress overview" action in the "User a" report row
    And I should see "0% completed" in the "Progress overview for User a" "dialogue"
    And I click on "Close" "button" in the "Progress overview for User a" "dialogue"
    And I press "Certification activity log" action in the "User a" report row
    And I should see "There are no event logs for this user certification." in the "User a activity log" "dialogue"
    And I click on "Close" "button" in the "User a activity log" "dialogue"

    And I click on "Certifications" "link" in the "#page-navbar" "css_element"
    And I follow "Overdue certifications"
    And I should see "User e"
    And I should not see "User a"

    # Viewing certification report for one certification for users in my team
    And I follow "Certification_1A"
    And I should see "Reports"
    And I follow "Certification progress"
    And I should see "Certification_1A" in the "#page-navbar" "css_element"
    And I should see "User a"
    And I should not see "Certification_2A"

    And I press "Progress overview" action in the "User a" report row
    And I should see "0% completed" in the "Progress overview for User a" "dialogue"
    And I click on "Close" "button" in the "Progress overview for User a" "dialogue"
    And I press "Certification activity log" action in the "User a" report row
    And I should see "There are no event logs for this user certification." in the "User a activity log" "dialogue"
    And I click on "Close" "button" in the "User a activity log" "dialogue"

    # Viewing certifications report for one user in my team
    And I follow "User a"
    And I follow "2 ongoing certifications"
    And I should see "User a" in the "#page-navbar" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Current program progress |
      | Certification_1A   | 0%                       |
      | Certification_2B   | 0%                       |

    And I press "Progress overview" action in the "Certification_2B" report row
    And I should see "0% completed" in the "Progress overview for User a" "dialogue"
    And I click on "Close" "button" in the "Progress overview for User a" "dialogue"
    And I press "Certification activity log" action in the "Certification_2B" report row
    And I should see "There are no event logs for this user certification." in the "Certification_2B activity log" "dialogue"
    And I click on "Close" "button" in the "Certification_2B activity log" "dialogue"
