@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Check user report capabilities on profile
  In order to check reports on profile
  As a manager
  I need have permission to see the user reports

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    And the following "tool_program > programs" exist:
      | fullname | tenant  | archived | startdatetype | startdaterelative | duedatetype           | duedaterelative | enddatetype    | enddaterelative |
      | Program1 | Tenant1 | 0        | none          | 0 weeks           | after_user_allocation | 6 days          | after_due      | 1 weeks         |
    And the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program1  | C11     |
    And the following "tool_program > program_users" exist:
      | user      | program  | allocationtype |
      | user11    | Program1 | 0              |
      | user12    | Program1 | 0              |
    And the following "tool_program > program_completions" exist:
      | program   | user    |
      | Program1  | user12  |

  Scenario: Manager can see certifications in subordinates profile
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
    Given user "user13" has a manager position over users "user11,user12" with permissions "3"
    When I am on the "user11" "user > profile" page logged in as "user13"
    Then I click on "1 active programs" "link"
    And the following should exist in the "reportbuilder-table" table:
      | Program name  | Allocation source   | Certification name  | Start date | Due date               | Program status  | Program progress  | Completion date |
      | Program1      | Manual              |                     | Not set    | ##+6 days##%d/%m/%y##  | Open            | 0%                |                 |
    And I am on the "user12" "user > profile" page
    And I click on "1 completed programs" "link"
    And the following should exist in the "reportbuilder-table" table:
      | Program name  | Allocation source   | Certification name  | Start date | Due date               | Program status  | Program progress  | Completion date         |
      | Program1      | Manual              |                     | Not set    | ##+6 days##%d/%m/%y##  | Completed       | 100%              | ##today##%d/%m/%y##     |
