@theme @theme_workplace @moodleworkplace @javascript
Feature: Theme workplace overview test
  To navigate in workplace theme I need to make sure standard steps work

  Scenario: Testing basic steps in workplace theme
    And I log in as "admin"
    And I set the following administration settings values:
      | contactdataprotectionofficer | 1 |
    And I navigate to "Appearance > Themes > Theme settings" in site administration
    And I log out

  Scenario: Testing workplace launcher in list format
    And I log in as "admin"
    And I set the following administration settings values:
      | wpmenumodal | 0 |
    And I navigate to "Courses" in workplace launcher
    And I should see "Manage courses and categories"
    And I log out

  Scenario: Testing workplace launcher in modal format
    And I log in as "admin"
    And I set the following administration settings values:
      | wpmenumodal | 1 |
    And I navigate to "Courses" in workplace launcher
    And I should see "Manage courses and categories"
    And I log out

  Scenario: Testing tenant site name/shortname is shown if it is defined
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename    | siteshortname |
      | Tenant1 | Tenant 1    | T1            |
      | Tenant2 |             |               |
    When I am on homepage for tenant "Tenant1"
    And I follow "Log in"
    Then I should see "Tenant 1" in the ".login-heading" "css_element"
    And I log in as "admin"
    And I switch to tenant "Tenant1"
    And I should see "T1" in the ".navbar" "css_element"
    And I log out
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    And I should see "Acceptance test site" in the ".login-heading" "css_element"
    And I log in as "admin"
    And I switch to tenant "Tenant2"
    And I should see "Acceptance test site" in the ".navbar" "css_element"
