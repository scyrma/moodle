@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Check user report capabilities for certifications on profile
  In order to check reports on profile
  As a manager
  I need have permission to see the user reports

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
    And the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program1  | C11     |
    And the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program       |
      | Certification1 | 0        | Tenant1 | Program1  |
    And the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user11 |
      | Certification1 | user12 |
    And the following "tool_program > program_completions" exist:
      | program   | user    |
      | Program1  | user12  |

  Scenario: Manager can see certifications in subordinates profile
    Given user "user13" has a manager position over users "user11,user12" with permissions "7"
    When I am on the "user11" "user > profile" page logged in as "user13"
    Then I click on "1 ongoing certifications" "link"
    And the following should exist in the "reportbuilder-table" table:
      | Certification name  | Allocation date     | Due date              | Expiry date | Certification status  | Certified date | Is recertification | Current program | Current program status | Current program progress |
      | Certification1      | ##today##%d/%m/%y## | ##+2 days##%d/%m/%y## |             | Open                  |                | No                 | Program1        | Open                   | 0%                       |
    And I am on the "user12" "user > profile" page
    And I click on "1 certified certifications" "link"
    And the following should exist in the "reportbuilder-table" table:
      | Certification name  | Allocation date     | Due date   | Expiry date             | Certification status  | Certified date      | Is recertification | Current program  | Current program status  | Current program progress |
      | Certification1      | ##today##%d/%m/%y## | Not set    | ##+7 month##%d/%m/%y##  | Certified             | ##today##%d/%m/%y## | No                 |                  | -                       | -                        |
