@tool @tool_tenant @moodleworkplace @javascript
Feature: Tenants generator
  As a developer
  I want to be able to use generator for the tenants

  Scenario: Usage of tenants generator
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
    And the following tenants exist:
      | name   |
      | Nike   |
      | Adidas |
      | Empty  |
    And the following users allocations to tenants exist:
      | user     | tenant |
      | user1    | Nike   |
      | user2    | Adidas |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "2" "text" should exist in the "Default tenant" table tree node
    And "1" "text" should exist in the "Nike" table tree node
    And "1" "text" should exist in the "Adidas" table tree node
    And "0" "text" should exist in the "Empty" table tree node
    And I click on "Nike" "link" in the "Nike" table tree node
    And I should see "User 1"
    And I should not see "User 2"
    And I should not see "User 3"
    And I should not see "Admin User" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Adidas" "link" in the "Adidas" table tree node
    And I should see "User 2"
    And I should not see "User 1"
    And I should not see "User 3"
    And I should not see "Admin User" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Default tenant" "link" in the "Default tenant" table tree node
    And I should not see "User 2"
    And I should not see "User 1"
    And I should see "User 3"
    And I should see "Admin User" in the "region-main" "region"
    And I log out

  Scenario: Tenants generator to bulk create tenants and users
    Given "3" tenants exist with "5" users and "2" courses in each
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "1" "text" should exist in the "Default tenant" table tree node
    And "5" "text" should exist in the "Tenant1" table tree node
    And "Category1" "text" should exist in the "Tenant1" table tree node
    And I should see "Tenant2"
    And I should see "Tenant3"
    And I should not see "Tenant4"
    And I click on "Tenant2" "link" in the "Tenant2" table tree node
    And I should see "Tenantadmin 2"
    And I should see "User 21"
    And I should see "User 22"
    And I should see "User 23"
    And I should see "User 24"
    And I should not see "User 25"
    And I should not see "User 1"
    And I should not see "User 3"
    And I am on course index
    And I follow "Category1"
    And I should see "Course11"
    And I should see "Course12"
    And I should not see "Course13"
    And I should not see "Course2"
    And I follow "Course11"
    And I click on "Expand all" "button"
    And I should see "URL1"
    And I log out
