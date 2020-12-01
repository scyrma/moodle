@tool @tool_tenant @auth_oauth2 @moodleworkplace @javascript
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
