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
    And I navigate to "All tenants" in workplace launcher
    And I should see "Shared space" in the ".navbar" "css_element"
    And I should not see "Shared space" in the "#activetenantstable" "css_element"
    And I should not see "Enable Shared space"
    And I log out

  Scenario: Enable shared space using dropdown
    Given I log in as "admin"
    And I switch to tenant "Shared space"
    And I press "Enable Shared space"
    And I should see "You have switched to 'Shared space'"
    And I log out

  Scenario: Ensure shared space unavailable using dropdown when tenant limit reached
    Given the following config values are set as admin:
      | tool_tenant_tenantlimitenabled | 1 |
      | tool_tenant_tenantlimit        | 2 |
    When I log in as "admin"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    Then "Shared space" "link" should not exist in the ".tenantswitch .dropdown-menu.show" "css_element"

  Scenario: Enable shared space using switch autocomplete
    # Create 8 more tenants. These plus 2 we already created + default tenant push us above the threshold for showing dialog.
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant3  |
      | Tenant4  |
      | Tenant5  |
      | Tenant6  |
      | Tenant7  |
      | Tenant8  |
      | Tenant9  |
      | Tenant10 |
    When I log in as "admin"
    And I click on ".tenantswitch .nav-link" "css_element"
    And I press "Go to Shared space"
    Then I should see "You have switched to 'Shared space'"
    And I click on ".tenantswitch .nav-link" "css_element"
    And "Go to shared space" "button" should not exist
    And I click on "Cancel" "button" in the "Switch tenant" "dialogue"
    And I log out

  Scenario: Ensure shared space button unavailable using switch autocomplete when tenant limit reached
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant3  |
      | Tenant4  |
      | Tenant5  |
      | Tenant6  |
      | Tenant7  |
      | Tenant8  |
      | Tenant9  |
      | Tenant10 |
    And the following config values are set as admin:
      | tool_tenant_tenantlimitenabled | 1  |
      | tool_tenant_tenantlimit        | 10 |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on ".tenantswitch .nav-link" "css_element"
    Then "Go to Shared space" "button" should not exist in the ".modal.show .modal-footer" "css_element"

  Scenario: Enable shared space using the button
    Given I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    When I click on "Active tenants" "tool_wp > Tab"
    And I should see "Enable Shared space"
    And I click on "Enable Shared space" "button"
    And I click on "Enable Shared space" "button" in the "Confirm" "dialogue"
    And I switch to tenant "Shared space"
    And I should see "You have switched to 'Shared space'"
    And I log out

  Scenario: Ensure shared space button unavailable when tenant limit reached
    Given the following config values are set as admin:
      | tool_tenant_tenantlimitenabled | 1 |
      | tool_tenant_tenantlimit        | 2 |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    Then "Enable Shared space" "button" should not exist

  Scenario: Disable shared space reminder
    Given I log in as "admin"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I should see "Shared space"
    And I click on "Shared space" "link"
    And I press "Not now"
    And I should not see "Shared space" in the ".navbar" "css_element"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Active tenants" "tool_wp > Tab"
    And I should see "Enable Shared space"
    And I log out
