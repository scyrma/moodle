@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Certification users belong to certification tenant and have not been deleted
  In order to test that users belong to same certification tenant and are active
  As a manager
  I need to create a certification and allocate users

  Background:
    Given "2" tenants exist with "1" users and "0" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | user3    | User      | 3        | user3@example.com    |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | user3    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  |
      | Certification1 | 0        | Tenant1 |
    Given the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification1 | user3  |

  Scenario: Create a certification with basic information, allocate users and move one user to a different tenant
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I should see "Users"
    And I should see "Full name"
    And I should see "User 1"
    And I should see "User 3"
    And I should not see "User 2"
    Then I log out
    Then I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Manage tenant 'Tenant1'" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I set the field "Select user 'User 3'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    Then I log out
    Then I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I should see "Users"
    And I should see "Full name"
    And I should see "User 1"
    And I should not see "User 3"
    And I should not see "User 2"
    Then I log out

  Scenario: Create a certification with basic information, allocate users and delete a user
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I should see "Users"
    And I should see "Full name"
    And I should see "User 1"
    And I should see "User 3"
    And I should not see "User 2"
    Then I log out
    Then I log in as "admin"
    And I navigate to "Users > Accounts > Bulk user actions" in site administration
    And the "Available" select box should contain "User 3"
    And I set the field "Available" to "User 3"
    And I press "Add to selection"
    And I set the field "id_action" to "Delete"
    And I press "Go"
    And I press "Yes"
    And I should see "Changes saved"
    And I press "Continue"
    Then I log out
    Then I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I should see "Users"
    And I should see "Full name"
    And I should see "User 1"
    And I should not see "User 3"
    And I should not see "User 2"
    Then I log out
