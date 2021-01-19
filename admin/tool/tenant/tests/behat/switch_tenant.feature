@tool @tool_tenant @javascript @moodle_workplace
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
      | Tenant4  |
      | Tenant5  |
      | Tenant6  |
      | Tenant7  |
      | Tenant8  |

  Scenario: Switch to a different tenant using dropdown
    When I log in as "user1"
    Then ".tenantswitch .dropdown-toggle" "css_element" should exist
    And ".tenantswitch .nav-link" "css_element" should not exist
    And I switch to tenant "Tenant3"
    Then I should see "Tenant3" in the ".navbar" "css_element"
    And I should see "You have switched to 'Tenant3'"
    And I log out

  Scenario: Switch to a different tenant using autocomplete
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant9 |
    When I log in as "user1"
    Then ".tenantswitch .dropdown-toggle" "css_element" should not exist
    And ".tenantswitch .nav-link" "css_element" should exist
    And I switch to tenant "Tenant3"
    Then I should see "Tenant3" in the ".navbar" "css_element"
    And I should see "You have switched to 'Tenant3'"
    And I log out
