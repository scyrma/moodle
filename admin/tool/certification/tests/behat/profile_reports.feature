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
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program       |
      | Certification1 | 0        | Tenant1 | Program1  |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
    Given the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user11 |

  Scenario: In certifications manager has permission only over its users
    Given user "manager1" has a department lead position over users "user11" with permissions "7"
    When I log in as "manager1"
    And I select "My teams" from primary navigation
    And I should not see "User 12"
    And I click on "[data-toggle=collapse]" "css_element" in the "User 11" "table_row"
    And I click on "See profile" "link" in the "User 11" "table_row"
    Then I click on "1 ongoing certifications" "link"
    And the following should exist in the "reportbuilder-table" table:
    | Certification name  | Allocation date     | Due date              | Expiry date | Certification status  | Certified date | Is recertification | Current program | Current program status | Current program progress |
    | Certification1      | ##today##%d/%m/%y## | ##+2 days##%d/%m/%y## |             | Open                  |                | No                 | Program1        | Open                   | 0%                       |
    And I press "Progress overview" action in the "Certification1" report row
    And I should see "Program1" in the "Progress overview for User 11" "dialogue"
    And I should see "Complete all in order" in the "Progress overview for User 11" "dialogue"
    And I click on "Close" "button" in the "Progress overview for User 11" "dialogue"
    And I press "Certification activity log" action in the "Certification1" report row
    And I should see "Last allocation date"
    And I click on "Close" "button" in the "Certification1 activity log" "dialogue"
    And I log out
    When I log in as "manager2"
    Then "My teams" "link" should not exist

  Scenario: In certifications organisation manager has permission only over its users
    Given user "user13" has a department lead position over users "user11,user12" with permissions "7"
    When I log in as "user13"
    Then I select "My teams" from primary navigation
    And I should see "User 12"
    And I click on "[data-toggle=collapse]" "css_element" in the "User 11" "table_row"
    And I click on "See profile" "link" in the "User 11" "table_row"
    Then I click on "1 ongoing certifications" "link"
    And the following should exist in the "reportbuilder-table" table:
    | Certification name  | Allocation date     | Due date              | Expiry date | Certification status  | Certified date | Is recertification | Current program | Current program status | Current program progress |
    | Certification1      | ##today##%d/%m/%y## | ##+2 days##%d/%m/%y## |             | Open                  |                | No                 | Program1        | Open                   | 0%                       |
