@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Check user report capabilities on profile
  In order to check reports on profile
  As a manager
  I need have permission to see the user reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "tool_program > programs" exist:
      | fullname | tenant  | archived | startdatetype | startdaterelative | duedatetype           | duedaterelative | enddatetype    | enddaterelative |
      | Program1 | Tenant1 | 0        | none          | 0 weeks           | after_user_allocation | 6 days          | after_due      | 1 weeks         |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | a        | user1@example.com    |
      | user2    | User      | b        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | user     | program  | allocationtype |
      | user1    | Program1 | 0              |

  Scenario: In programs manager has permission only over its users
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
    Given user "manager1" has a department lead position over users "user1" with permissions "3"
    When I log in as "manager1"
    Then I select "My teams" from primary navigation
    And I should not see "User b"
    And I click on "[data-toggle=collapse]" "css_element" in the "User a" "table_row"
    And I click on "See profile" "link" in the "User a" "table_row"
    Then I click on "1 active programs" "link"
    And I should see "Program name"
    And I should see "Allocation source"
    And I should see "Certification name"
    And I should see "Start date"
    And I should see "Due date"
    And I should see "Program status"
    And I should see "Program progress"
    And I should see "Completion date"
    And I should see "Program1"
    And I should see "Open"
    And I press "Progress overview" action in the "Program1" report row
    And I should see "Program1"
    And I should see "Progress overview"
    And I should see "Complete all in order"
    And I click on "Close" "button" in the "Progress overview" "dialogue"
    And I log out
    When I log in as "manager2"
    Then "My teams" "link" should not exist

  # TODO: WP-3456 re-implement tests for viewing "Full report > Overdue programs"
