@tool @tool_tenant @moodleworkplace @javascript
Feature: Tenants generator
  As a developer
  I want to be able to use generator for the tenants

  Scenario: Testing generator, allocate users to tenants using deprecated steps
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
    # The following method is not recommended, instead use: Given the following "tool_tenant > tenants" exist:
    And the following tenants exist:
      | name   |
      | Nike   |
      | Adidas |
      | Empty  |
    # The following method is deprecated, see examples below on how to create users in tenants directly.
    And the following users allocations to tenants exist:
      | user     | tenant |
      | user1    | Nike   |
      | user2    | Adidas |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "2" "text" should exist in the "Default tenant" "tool_wp > Table tree node"
    And "1" "text" should exist in the "Nike" "tool_wp > Table tree node"
    And "1" "text" should exist in the "Adidas" "tool_wp > Table tree node"
    And "0" "text" should exist in the "Empty" "tool_wp > Table tree node"
    And I click on "Nike" "link" in the "Nike" "tool_wp > Table tree node"
    And I should see "User 1"
    And I should not see "User 2"
    And I should not see "User 3"
    And I should not see "Admin User" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Adidas" "link" in the "Adidas" "tool_wp > Table tree node"
    And I should see "User 2"
    And I should not see "User 1"
    And I should not see "User 3"
    And I should not see "Admin User" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I should not see "User 2"
    And I should not see "User 1"
    And I should see "User 3"
    And I should see "Admin User" in the "region-main" "region"
    And I log out

  Scenario: Tenants generator to bulk create tenants and users
    Given "3" tenants exist with "5" users and "2" courses in each
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "1" "text" should exist in the "Default tenant" "tool_wp > Table tree node"
    And "5" "text" should exist in the "Tenant1" "tool_wp > Table tree node"
    And "Category1" "text" should exist in the "Tenant1" "tool_wp > Table tree node"
    And I should see "Tenant2"
    And I should see "Tenant3"
    And I should not see "Tenant4"
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
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
    And I should see "URL1"
    And I log out
    And I log in as "user11"
    And I am on course index
    And I should see "Course11"
    And I should see "Course12"
    And I should not see "Course2"
    And I log out

  Scenario: Testing generator, create users inside tenants
    Given the following "categories" exist:
      | name  | category | idnumber |
      | CAT1  |          | C001     |
    And the following "tool_tenant > tenants" exist:
      | name   | category |
      | Nike   |          |
      | Adidas |          |
      | Empty  | C001     |
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                 | tenant         |
      | user1    | User      | 1        | user1@address.invalid | Nike           |
      | user2    | User      | 2        | user2@address.invalid | Adidas         |
      | user3    | User      | 3        | user3@address.invalid | Default tenant |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "2" "text" should exist in the "Default tenant" "tool_wp > Table tree node"
    And "No category" "text" should exist in the "Default tenant" "tool_wp > Table tree node"
    And "1" "text" should exist in the "Nike" "tool_wp > Table tree node"
    And "No category" "text" should exist in the "Nike" "tool_wp > Table tree node"
    And "1" "text" should exist in the "Adidas" "tool_wp > Table tree node"
    And "No category" "text" should exist in the "Adidas" "tool_wp > Table tree node"
    And "0" "text" should exist in the "Empty" "tool_wp > Table tree node"
    And "CAT1" "text" should exist in the "Empty" "tool_wp > Table tree node"
    And I click on "Nike" "link" in the "Nike" "tool_wp > Table tree node"
    And I should see "User 1"
    And I should not see "User 2"
    And I should not see "User 3"
    And I should not see "Admin User" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Adidas" "link" in the "Adidas" "tool_wp > Table tree node"
    And I should see "User 2"
    And I should not see "User 1"
    And I should not see "User 3"
    And I should not see "Admin User" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I should not see "User 2"
    And I should not see "User 1"
    And I should see "User 3"
    And I should see "Admin User" in the "region-main" "region"
    And I log out

  Scenario: Testing generator, create tenant administrators inside tenants
    Given the following "tool_tenant > tenants" exist:
      | name   | category |
      | Nike   |          |
      | Adidas |          |
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                 | tenant         | tenantadmin |
      | user1    | User      | 1        | user1@address.invalid | Nike           | 1           |
      | user2    | User      | 2        | user2@address.invalid | Nike           | 0           |
      | user3    | User      | 3        | user3@address.invalid | Adidas         | 0           |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "2" "text" should exist in the "Nike" "tool_wp > Table tree node"
    And I click on "Nike" "link" in the "Nike" "tool_wp > Table tree node"
    And I should see "User 1"
    And I should see "User 2"
    And I should see "Tenant administrator" in the "User 1" "table_row"
    And I should not see "Tenant administrator" in the "User 2" "table_row"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Adidas" "link" in the "Adidas" "tool_wp > Table tree node"
    And I should not see "Tenant administrator" in the "User 3" "table_row"
    And I should see "User 3"

  Scenario: We can restrict user profile fields categories to be available only for specific tenants
    Given "3" tenants exist with "5" users and "3" courses in each
    And the following "custom profile field categories" exist:
      | name |
      | cat1 |
      | cat2 |
    And the following "custom profile fields" exist:
      | datatype | shortname | name   | param2 | category |
      | text     | f1        | Field1 | 100    | cat1     |
      | text     | f2        | Field2 | 100    | cat2     |
    And profile category "cat1" is available only for tenants "Tenant1"
    And profile category "cat2" is available only for tenants "Tenant2,Tenant3"
    When I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    Then I should see "Tenants: Tenant1" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And I should not see "Tenant2" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And I should see "Tenants: Tenant2, Tenant3" in the "//div[@data-category-id and .//h3[contains(.,'cat2')]]" "xpath_element"
    And I should not see "Tenant1" in the "//div[@data-category-id and .//h3[contains(.,'cat2')]]" "xpath_element"
