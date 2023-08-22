@tool @tool_wp @moodleworkplace @javascript
Feature: Production state settings
  As a global admin
  I want to be able to change the production state settings.

  Background:
    Given "1" tenants exist with "2" users and "0" courses in each

  Scenario: Production state is set to non-production
    Given I log in as "admin"
    And I navigate to "Development > Production state" in site administration
    And I set the field "Production state" to "Non-production site"
    When I press "Save changes"
    Then I should see "This is a non-production site" in the "#wp-site-status-disclaimer" "css_element"
    And "Change" "link" should exist in the "#wp-site-status-disclaimer" "css_element"
    And I log out
    Given I log in as "tenantadmin1"
    When I am on site homepage
    Then I should see "This is a non-production site" in the "#wp-site-status-disclaimer" "css_element"
    And "Change" "link" should not exist in the "#wp-site-status-disclaimer" "css_element"
    And I log out

  Scenario: Production state is set to production and site not registered
    Given I log in as "admin"
    And I navigate to "Development > Production state" in site administration
    And I set the field "Production state" to "Production site"
    When I press "Save changes"
    Then I should see "Your site is not yet registered" in the "#wp-site-status-disclaimer" "css_element"
    And "Register your site" "link" should exist in the "#wp-site-status-disclaimer" "css_element"
    And I log out
    Given I log in as "tenantadmin1"
    When I am on site homepage
    Then "#wp-site-status-disclaimer" "css_element" should not exist
    And I log out

  Scenario: Production state is set to production and site is registered
    Given the site is registered
    And I log in as "admin"
    And I navigate to "Development > Production state" in site administration
    And I set the field "Production state" to "Production site"
    When I press "Save changes"
    And I am on site homepage
    Then "#wp-site-status-disclaimer" "css_element" should not exist
    And I log out
    Given I log in as "tenantadmin1"
    When I am on site homepage
    Then "#wp-site-status-disclaimer" "css_element" should not exist
    And I log out

  Scenario: Retrieving the production state for the site
    Given the following config values are set as admin:
      | forcelogin      | 1 |
      | autologinguests | 0 |
    And I am on fixture page "/admin/tool/wp/tests/behat/fixtures/productionstate.php"
    And following "Download me" should download between "150" and "500" bytes
