@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import users
  As a tenant administrator
  I want to export and import users

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each

  Scenario: Export single user
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Users" "radio"
    And I press "Next"
    And I click on "Select users manually..." "radio"
    And I press "Next"
    And I should see "Required" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I open the autocomplete suggestions list
    And "User 11" "autocomplete_suggestions" should exist
    And "User 21" "autocomplete_suggestions" should not exist
    And "User 22" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    And I take focus off "Select users manually..." "field"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "User 12" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View export" action in the "Users" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "User 12" in the "[data-region=\"exportimport-instances\"]" "css_element"

  Scenario: Export all site users as admin
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Tenant1"
    And I perform a new export with these options:
      | Step 1 | Users              | 1    |
      | Step 1 | Create export from | Site |
      | Step 2 | Select all users   | 1    |
    And I press "View export" action in the "Users" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (7)"
    And I press "Show 4 more..."
    And I should see "Admin User" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "Tenantadmin 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "Tenantadmin 2" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "User 11" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "User 12" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "User 21" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "User 22" in the "[data-region=\"exportimport-instances\"]" "css_element"

  Scenario: Export all tenant users as admin
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Tenant1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Users" "radio"
    And I set the field "Create export from" in the "//div[contains(@data-groupname,'users')]" "xpath_element" to "Current tenant"
    And I press "Next"
    And I set the field "Select all users from 'Tenant1'" to "1"
    And I press "Next"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View export" action in the "Users" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (3)"
    And I should see "Tenantadmin 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "User 11" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "User 12" in the "[data-region=\"exportimport-instances\"]" "css_element"
