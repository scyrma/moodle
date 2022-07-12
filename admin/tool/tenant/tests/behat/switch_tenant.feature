@tool @tool_tenant @javascript @moodleworkplace
Feature: Switch between tenants
  In order to switch between tenants
  As an tenant admin
  I should be able to see the different tenant switch interfaces

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
    And the following "roles" exist:
      | shortname | name | archetype |
      | tenantaddmanager | Tenants add manager |  |
    And the following "role assigns" exist:
      | user  | role              | contextlevel | reference |
      | user1 | tenantaddmanager  | System       |           |
    And the following "permission overrides" exist:
      | capability             | permission | role              | contextlevel | reference |
      | tool/tenant:manage     | Allow      | tenantaddmanager  | System       |           |
      | moodle/site:configview | Allow      | tenantaddmanager  | System       |           |
    And the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant1  |
      | Tenant2  |
      | Tenant3  |

  Scenario: Switch to a different tenant using dropdown
    When I log in as "user1"
    Then ".tenantswitch .dropdown-toggle" "css_element" should exist
    And ".tenantswitch .nav-item.nav-link" "css_element" should not exist
    And I switch to tenant "Tenant3"
    Then I should see "Tenant3" in the ".navbar" "css_element"
    And I should see "You have switched to 'Tenant3'"
    And I log out

  Scenario: Switch to a different tenant using autocomplete
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant4  |
      | Tenant5  |
      | Tenant6  |
      | Tenant7  |
      | Tenant8  |
      | Tenant9  |
    When I log in as "user1"
    Then ".tenantswitch .dropdown-toggle" "css_element" should not exist
    And ".tenantswitch .nav-item.nav-link" "css_element" should exist
    And I switch to tenant "Tenant3"
    Then I should see "Tenant3" in the ".navbar" "css_element"
    And I should see "You have switched to 'Tenant3'"
    And I log out

  Scenario: Change to a different tenant in login page
    Given the following "tool_tenant > tenants" exist:
      | name      | showinloginselector | sitename |
      | Tenant4   | 1                   | Site 4   |
      | Tenant5   | 0                   | Site 5   |
    And the following config values are set as admin:
      | showtenantselector    | 1 | tool_tenant |
    When I am on homepage
    And I should see "Acceptance test site"
    And I follow "Log in"
    And I press "Change site"
    And "Site 4" "tool_tenant > Tenant selector card" should be visible
    And "Site 5" "tool_tenant > Tenant selector card" should not be visible
    And the "Change site" "tool_tenant > Tenant selector button" should be disabled
    And I click on "Site 4" "tool_tenant > Tenant selector card"
    And I click on "Change site" "tool_tenant > Tenant selector button"
    And I follow "Log in"
    Then I should not see "Acceptance test site"
    And I should see "Site 4"

  Scenario: Change to a different tenant in signup page
    Given the following "tool_tenant > tenants" exist:
      | name      | sitename |
      | Tenant4   | Site 4   |
    And the following config values are set as admin:
      | registerauth          | email |             |
      | showtenantselector    | 1     | tool_tenant |
    When I am on homepage
    And I should see "Acceptance test site"
    And I follow "Log in"
    And I follow "Create new account"
    And I press "Change site"
    And I click on "Site 4" "tool_tenant > Tenant selector card"
    And I click on "Change site" "tool_tenant > Tenant selector button"
    And I follow "Log in"
    Then I should not see "Acceptance test site"
    And I should see "Site 4"
