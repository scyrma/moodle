@tool @tool_tenant @auth_oauth2 @moodleworkplace @javascript @tool_tenant_auth_oauth2_mnet
Feature: Testing functionality of auth_oauth2 with MoodleNet
  In order to send activity to MoodleNet server
  As a user
  I want to be able to share using MoodleNet OAuth 2 provider

  Background:
    Given the following config values are set as admin:
      | enablesharingtomoodlenet | 1 |
    And I log in as "admin"
    And a MoodleNet mock server is configured
    And the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | teacher1 | Tenant1 |
      | teacher2 | Tenant2 |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C2     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name              | intro             |
      | assign   | C1     | assign1  | Test Assignment 1 | Test Assignment 1 |
      | assign   | C2     | assign2  | Test Assignment 2 | Test Assignment 2 |
    And I navigate to "Server > OAuth 2 services" in site administration
    And I press "MoodleNet"
    And I should see "Create new service: MoodleNet"
    And I change the MoodleNet field "Service base URL" to mock server
    And I press "Save changes"
    And I navigate to "MoodleNet > MoodleNet outbound settings" in site administration
    And I set the field "Auth 2 service" to "MoodleNet"
    And I press "Save changes"

  Scenario: Test share to MoodleNet option availability all tenants
    Given I log in as "admin"
    And I navigate to "Server > OAuth 2 services" in site administration
    And I click on "Edit tenant availability" "link" in the "MoodleNet" "table_row"
    And I should see "Tenant availability for 'MoodleNet'"
    And I click on "This service is available to all tenants (including future ones)" "radio"
    And I press "Save"
    And I should see "Service tenant availability updated correctly"
    And I log out
    When I am on the "Test Assignment 1" "assign activity" page logged in as teacher1
    Then "Share to MoodleNet" "link" should exist in current page administration
    When I am on the "Test Assignment 2" "assign activity" page logged in as teacher2
    Then "Share to MoodleNet" "link" should exist in current page administration

  Scenario: Test share to MoodleNet option availability only for specified tenants
    Given I log in as "admin"
    And I navigate to "Server > OAuth 2 services" in site administration
    And I click on "Edit tenant availability" "link" in the "MoodleNet" "table_row"
    And I click on "This service is available only to the following tenants..." "radio"
    And I set the field "Select tenants" in the "//div[contains(@id, 'fitem_id_manual_select_tenants')]" "xpath_element" to "Tenant2"
    And I press "Save"
    And I should see "Service tenant availability updated correctly"
    And I log out
    When I am on the "Test Assignment 1" "assign activity" page logged in as teacher1
    Then "Share to MoodleNet" "link" should not exist in current page administration
    When I am on the "Test Assignment 2" "assign activity" page logged in as teacher2
    Then "Share to MoodleNet" "link" should exist in current page administration

  Scenario: Test share to MoodleNet option availability except for specified tenants
    Given I log in as "admin"
    And I navigate to "Server > OAuth 2 services" in site administration
    And I click on "Edit tenant availability" "link" in the "MoodleNet" "table_row"
    Then I click on "This service is available to all tenants except the following..." "radio"
    And I set the field "Select tenants" in the "//div[contains(@id, 'fitem_id_manual_exclude_tenants')]" "xpath_element" to "Tenant2"
    And I press "Save"
    And I should see "Service tenant availability updated correctly"
    And I log out
    When I am on the "Test Assignment 1" "assign activity" page logged in as teacher1
    Then "Share to MoodleNet" "link" should exist in current page administration
    When I am on the "Test Assignment 2" "assign activity" page logged in as teacher2
    Then "Share to MoodleNet" "link" should not exist in current page administration
