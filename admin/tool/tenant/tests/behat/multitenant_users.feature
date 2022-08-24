@tool @tool_tenant @moodleworkplace @javascript
Feature: Manage tenant that shows tenant column and other user fields with filters
  As a global admin or tenant admin
  I want to be able to manage users from a given tenant and any subtenant(s)
  along with the appropriate filters

  Scenario: Additional user fields visibility
    Given the following config values are set as admin:
      | showuseridentity | username,email,city |
    And "2" tenants exist with "4" users and "0" courses in each
    And I change window size to "large"
    When I log in as "admin"
    And I navigate to "Users" in workplace launcher
    Then I should see "Username" in the "reportbuilder-table" "table"
    Then I should see "Email address" in the "reportbuilder-table" "table"
    Then I should see "City/town" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    Then I should see "Username" in the "[data-region='report-filters']" "css_element"
    Then I should see "Email address" in the "[data-region='report-filters']" "css_element"
    Then I should see "City/town" in the "[data-region='report-filters']" "css_element"
    And I log out
    Given the following "roles" exist:
      | shortname | name | archetype |
      | tenantusermanager | Tenants user manager |  |
    And the following "role assigns" exist:
      | user  | role              | contextlevel | reference |
      | user11 | tenantusermanager  | System       |           |
    And the following "permission overrides" exist:
      | capability             | permission | role              | contextlevel | reference |
      | moodle/user:delete     | Allow      | tenantusermanager | System       |           |
      | moodle/user:update     | Allow      | tenantusermanager | System       |           |
      | moodle/user:create     | Allow      | tenantusermanager | System       |           |
      | tool/tenant:manage     | Allow      | tenantusermanager | System       |           |
      | moodle/site:viewuseridentity | Allow      | tenantusermanager | System       |           |
    And I log in as "user11"
    And I navigate to "Users" in workplace launcher
    Then I should see "Username" in the "reportbuilder-table" "table"
    Then I should see "Email address" in the "reportbuilder-table" "table"
    Then I should see "City/town" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    Then I should see "Username" in the "[data-region='report-filters']" "css_element"
    Then I should see "Email address" in the "[data-region='report-filters']" "css_element"
    Then I should see "City/town" in the "[data-region='report-filters']" "css_element"
    Then I should see "Username" in the "reportbuilder-table" "table"
    Then I should see "Email address" in the "reportbuilder-table" "table"
    Then I should see "City/town" in the "reportbuilder-table" "table"
    And I log out

  Scenario: Site administrators can not see Tenant column and filter on the sites that are not multi-tenant
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    # Cannot see tenant column
    And I should not see "Tenant" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I should not see "Tenant" in the "[data-region='report-filters']" "css_element"
    And I log out

  Scenario: Site administrators can not see Tenant column and filter in the Users list but can see it in All users
    Given "2" tenants exist with "4" users and "0" courses in each
    And I log in as "admin"
    And I navigate to "Users" in workplace launcher
    And I should not see "Tenant" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I should not see "Tenant" in the "[data-region='report-filters']" "css_element"
    And I navigate to "All users" in workplace launcher
    And I should see "Tenant" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I should see "Tenant" in the "[data-region='report-filters']" "css_element"
    And I log out

  Scenario: Tenant administrators can not see Tenant column and filter in the Users list
    Given "2" tenants exist with "4" users and "0" courses in each
    Given I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I should not see "Tenant" in the ".reportbuilder-table thead" "css_element"
    And I click on "Filters" "button"
    And I should not see "Tenant" in the "[data-region='report-filters']" "css_element"
    And I log out
