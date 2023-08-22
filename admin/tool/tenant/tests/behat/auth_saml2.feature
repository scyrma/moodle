@tool @tool_tenant @auth_saml2 @moodleworkplace @javascript @tool_tenant_auth
Feature: Testing functionality of auth_saml2
  As a user
  I want to be able to sign on using OAuth 2 provider

  Scenario: Tenant administrator can change settings in SAML2 auth plugin
    Given the plugin "auth_saml2" is installed
    And the following "tool_tenant > tenants" exist:
      | name    |
      | Tenant1 |
    And the following "tool_tenant > users" exist:
      | username     | firstname | lastname | email                  | tenant  | tenantadmin | auth   | city  |
      | tenantadmin1 | Test      | T        | test@address.invalid   | Tenant1 | 1           | manual |       |
      | user11       | User      | 11       | user11@address.invalid | Tenant1 | 0           | saml2  |       |
      | user12       | User      | 12       | user12@address.invalid | Tenant1 | 0           | manual |       |
      | user13       | User      | 13       | user13@address.invalid | Tenant1 | 0           | saml2  | Perth |
    When I log in as "admin"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Edit status" "link" in the "SAML2" "table_row"
    And I set the field "New status for SAML2" to "Disabled, available"
    And I click on "Settings" "link" in the "SAML2" "table_row"
    And I set the field "Lock value (Last name)" to "Unlocked if empty"
    And I set the field "Lock value (Email address)" to "Locked"
    And I click on "Force for all tenants" "text" in the "Lock value (Email address)" "tool_wp > Row"
    And I press "Save changes"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I click on "Settings" "link" in the "SAML2" "tool_wp > Row"
    And the following fields in the "SAML2. Settings" "dialogue" match these values:
      | field_lock_firstname_custom | Site default (Unlocked) |
      | field_lock_lastname_custom | Site default (Unlocked if empty) |
    And I should see "Locked" in the "Lock value (Email address)" "tool_wp > Row"
    And "select" "css_element" should not exist in the "Lock value (Email address)" "tool_wp > Row"
    And I set the following fields to these values:
      | field_lock_firstname_custom | Custom            |
      | Lock value (First name)     | Unlocked if empty |
      | field_lock_city_custom      | Custom            |
      | Lock value (City/town)      | Unlocked if empty |
    And I press "Save changes"
    And I navigate to "Users" in current page administration
    And I change window size to "large"
    And I press "Edit user account" action in the "User 11" report row
    # Can not use the step 'the "..." "field" should not be disabled' because it targets the report filter instead
    # Tenant admin can ALWAYS edit locked fields.
    And "input[name=firstname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=lastname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=email][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And I set the field "City/town" in the "Edit user 'User 11'" "dialogue" to "Somewhere"
    And I press "Save"
    And I press "Edit user account" action in the "User 11" report row
    And "input[name=firstname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=lastname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=email][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=city][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And the following fields in the "Edit user 'User 11'" "dialogue" match these values:
      | First name    | User                   |
      | Last name       | 11                     |
      | Email address | user11@address.invalid |
      | City/town     | Somewhere              |
    And I press "Save"
