@tool @tool_tenant @moodleworkplace @javascript
Feature: Shared space in multitenancy
  As a global admin
  I want to be able to switch to shared space

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each

  Scenario: There is shared space in the tenant selector
    Given I log in as "admin"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I should see "Shared space"

  Scenario: As an admin I can switch to shared space
    Given shared space is enabled
    Given I log in as "admin"
    And I switch to tenant "Shared space"
    And I should see "Shared space" in the ".navbar" "css_element"
    And I navigate to "Tenants" in workplace launcher
    And I should see "Shared space" in the ".navbar" "css_element"
    And I should not see "Shared space" in the "#activetenantstable" "css_element"
    And I should not see "Enable Shared space"
    And I log out

  Scenario: Enable shared space using dropdown
    Given I log in as "admin"
    And I switch to tenant "Shared space"
    And I press "Enable Shared space" in the modal form dialogue
    And I should see "You have switched to 'Shared space'"
    And I log out

  Scenario: Enable shared space using switch autocomplete
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant1  |
      | Tenant2  |
      | Tenant3  |
      | Tenant4  |
      | Tenant5  |
      | Tenant6  |
      | Tenant7  |
      | Tenant8  |
      | Tenant9  |
      | Tenant10  |
    And I log in as "admin"
    And ".tenantswitch .nav-link" "css_element" should exist
    And I click on ".tenantswitch .nav-link" "css_element"
    And I press "Go to Shared space" in the modal form dialogue
    And I should see "You have switched to 'Shared space'"
    And I log out

  Scenario: Enable shared space using the button
    Given I log in as "admin"
    And I navigate to "Tenants" in workplace launcher
    When I click on "Active tenants" "tool_wp > Tab"
    And I should see "Enable Shared space"
    And I click on "Enable Shared space" "button"
    And I press "Enable Shared space" in the modal form dialogue
    And I switch to tenant "Shared space"
    And I should see "You have switched to 'Shared space'"
    And I log out

  Scenario: Disable shared space reminder
    Given I log in as "admin"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I should see "Shared space"
    And I click on "Shared space" "link"
    And I press "Not now" in the modal form dialogue
    And I should not see "Shared space" in the ".navbar" "css_element"
    And I navigate to "Tenants" in workplace launcher
    And I click on "Active tenants" "tool_wp > Tab"
    And I should see "Enable Shared space"
    And I log out
