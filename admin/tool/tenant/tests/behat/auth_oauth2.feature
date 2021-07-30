@tool @tool_tenant @auth_oauth2 @moodleworkplace @javascript @tool_tenant_auth
Feature: Testing functionality of auth_oauth2
  As a user
  I want to be able to sign on using OAuth 2 provider

  Background:
    Given the following config values are set as admin:
      | auth | email,oauth2 |

  Scenario: OAuth2 issuer with email verification
    Given the test OAuth2 issuer with the following properties exists:
      | name | Moodle test OAuth2 |
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test1@example.com"
    And I press "Login"
    And I should see "An email should have been sent to your address at test1@example.com"
    And I press "Continue"
    # Confirm and login
    And I confirm OAuth2 email for "test1@example.com"
    And I follow "Dashboard"
    And I should see "Edit profile"
    And the following fields match these values:
      | Email address | test1@example.com |
    And I set the field "First name" to "Mary"
    And I set the field "Surname" to "Jones"
    And I press "Update profile"
    And I should see "Mary Jones"

  Scenario: OAuth2 issuer without email verification
    Given the test OAuth2 issuer with the following properties exists:
      | name                | Moodle test OAuth2 |
      | requireconfirmation | 0                  |
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test1@example.com"
    And I press "Login"
    And I should see "Edit profile"
    And the following fields match these values:
      | Email address | test1@example.com |
    And I set the field "First name" to "Mary"
    And I set the field "Surname" to "Jones"
    And I press "Update profile"
    And I should see "Mary Jones"
    And I should see "Preferences" in the "#page-navbar" "css_element"
    # User can log in again using oauth2.
    And I log out
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test1@example.com"
    And I press "Login"
    And I should see "Mary Jones"

  Scenario: OAuth2 login with a linked account without email confirmation
    Given the test OAuth2 issuer with the following properties exists:
      | name | Moodle test OAuth2 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | user1    | Student   | 1        | user@example.com |
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "user@example.com"
    And I press "Login"
    And I should see "An existing account was found with this email address but it is not linked yet"
    And I should see "An email should have been sent to your address at user@example.com"
    And I confirm OAuth2 linked email for "user1"
    And I should see "Thanks, Student 1"
    And I should see "Your registration has been confirmed"
    And I should see "You are logged in as Student 1"
    And I follow "Preferences" in the user menu
    And I follow "Linked logins"
    And I should see "user@example.com" in the "Moodle test OAuth2" "table_row"

  Scenario: OAuth2 login with a linked account with email confirmation
    Given the test OAuth2 issuer with the following properties exists:
      | name                | Moodle test OAuth2 |
      | requireconfirmation | 0                  |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | user1    | Student   | 1        | user@example.com |
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "user@example.com"
    And I press "Login"
    And I should see "You are logged in as Student 1"

  Scenario: Check that correct buttons are displayed on each tenant login page after using tenant availability form
    Given "3" tenants exist with "0" users and "0" courses in each
    And the test OAuth2 issuer with the following properties exists:
      | name | Moodle OAuth2 for Tenant1  |
    And the test OAuth2 issuer with the following properties exists:
      | name | Moodle OAuth2 for All tenants  |
    And the test OAuth2 issuer with the following properties exists:
      | name | Moodle OAuth2 for everybody except Tenant2  |
    When I log in as "admin"
    And I navigate to "Server > OAuth 2 services" in site administration
    # Set Tenant availability for the first service.
    Then I click on "Edit tenant availability" "link" in the "Moodle OAuth2 for Tenant1" "table_row"
    And I should see "Tenant availability for 'Moodle OAuth2 for Tenant1'"
    Then I click on "This service is available only to the following tenants..." "radio"
    And I set the field "Select tenants" in the "//div[contains(@id, 'fitem_id_manual_select_tenants')]" "xpath_element" to "Tenant1"
    And I press "Save"
    Then I should see "Service tenant availability updated correctly"
    # Set Tenant availability for the second service.
    Then I click on "Edit tenant availability" "link" in the "Moodle OAuth2 for All tenants" "table_row"
    And I should see "Tenant availability for 'Moodle OAuth2 for All tenants'"
    Then I click on "This service is available to all tenants (including future ones)" "radio"
    And I press "Save"
    # Set Tenant availability for the third service.
    Then I click on "Edit tenant availability" "link" in the "Moodle OAuth2 for everybody except Tenant2" "table_row"
    And I should see "Tenant availability for 'Moodle OAuth2 for everybody except Tenant2'"
    Then I click on "This service is available to all tenants except the following..." "radio"
    And I set the field "Select tenants" in the "//div[contains(@id, 'fitem_id_manual_exclude_tenants')]" "xpath_element" to "Tenant2"
    And I press "Save"
    # Check that options appear on the tenant settings page.
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    And I follow "Authentication"
    And I click on "Settings" "link" in the "OAuth 2" "tool_wp > Row"
    And "Moodle OAuth2 for Tenant1" "text" should not exist in the "OAuth 2. Settings" "dialogue"
    And "Moodle OAuth2 for All tenants" "text" should exist in the "OAuth 2. Settings" "dialogue"
    And "Moodle OAuth2 for everybody except Tenant2" "text" should not exist in the "OAuth 2. Settings" "dialogue"
    And I click on "Cancel" "button" in the "OAuth 2. Settings" "dialogue"
    And I log out
    # Check homepage for Tenant1.
    And I am on homepage for tenant "Tenant1"
    And I follow "Log in"
    Then I should see "Moodle OAuth2 for Tenant1"
    And I should see "Moodle OAuth2 for All tenants"
    And I should see "Moodle OAuth2 for everybody except Tenant2"
    # Check homepage for Tenant2.
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    Then I should not see "Moodle OAuth2 for Tenant1"
    And I should see "Moodle OAuth2 for All tenants"
    And I should not see "Moodle OAuth2 for everybody except Tenant2"
    # Check homepage for Tenant3.
    And I am on homepage for tenant "Tenant3"
    And I follow "Log in"
    Then I should not see "Moodle OAuth2 for Tenant1"
    And I should see "Moodle OAuth2 for All tenants"
    And I should see "Moodle OAuth2 for everybody except Tenant2"
