@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Check user report capabilities for certifications on profile
  In order to check reports on profile
  As a manager
  I need have permission to see the user reports

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
    Given the following tool certification data "certifications" exist:
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
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user11 |

  Scenario: In certifications manager has permission only over its users
    Given user "manager1" has a department manager position over users "user11" with permissions "7"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User 11"
    And I should not see "User 12"
    And I click on "//button[contains(.,'User 11')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Job assignments"
    And I should see "Ongoing certifications: 1"
    Then I click on "Ongoing certifications: 1" "link"
    And I should see "Certification name"
    And I should see "Program name"
    And I should see "Due date"
    And I should see "Status"
    And I should see "Certification1"
    And I should see "Program1"
    And I should see "Open"
    And I log out
    When I log in as "manager2"
    Then I follow "Dashboard"
    And I should not see "Teams"
    And I should not see "User 11"
    And I should not see "User 12"
    And I should not see "Ongoing certifications: 1"
    And I log out

  Scenario: In certifications organisation manager has permission only over its users
    Given user "user13" has a department manager position over users "user11,user12" with permissions "7"
    When I log in as "user13"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User 11"
    And I should see "User 12"
    And I click on "//button[contains(.,'User 11')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Job assignments"
    And I should see "Ongoing certifications: 1"
    Then I click on "Ongoing certifications: 1" "link"
    And I should see "Certification name"
    And I should see "Program name"
    And I should see "Due date"
    And I should see "Status"
    And I should see "Certification1"
    And I should see "Program1"
    And I should see "Open"
    And I log out