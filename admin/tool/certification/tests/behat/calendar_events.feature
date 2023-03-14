@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Ensure calendar events are created when user is allocated into a certification
  In order to check calendar events from a certification
  As a user
  I need to be able to see the events in my calendar when I am allocated to a certification

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |

  Scenario: Check that certification due date and expiry dates appear in user calendar
    Given the following "tool_certification > certifications" exist:
      | fullname                | archived | tenant  | program   | startdatetype        | startdaterelative | duedatetype      | duedaterelative | expirydatetype | expirydaterelative |
      | Certification example 1 | 0        | Tenant1 | Program1  | user_allocation_date | 0 week            | after_start_date | 1 week          | after_due_date | 1 week             |
    Given the following "tool_certification > certification_users" exist:
      | certification           | user   |
      | Certification example 1 | user1  |
    Then I log in as "user1"
    And I am viewing site calendar
    And I view the calendar for "1" more weeks
    And I should see "Due date for certification Certification example 1"
    And I am viewing site calendar
    And I view the calendar for "2" more weeks
    And I should not see "Expiry date for certification Certification example 1"
    And I log out
    Then I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I press "Users" action in the "Certification example 1" report row
    Then I press "Certify user" action in the "User 1" report row
    Then I click on "Certify" "button" in the "Certify" "dialogue"
    And I log out
    Then I log in as "user1"
    And I am viewing site calendar
    And I view the calendar for "1" more weeks
    And I should not see "Due date for certification Certification example 1"
    And I am viewing site calendar
    And I view the calendar for "2" more weeks
    And I should see "Expiry date for certification Certification example 1"
    And I log out
