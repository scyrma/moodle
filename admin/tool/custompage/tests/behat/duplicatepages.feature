@tool @tool_custompage @moodleworkplace @javascript
Feature: Duplicate custom pages
  In order to duplicate custom pages
  As a user
  I need to be able to duplicate existing pages

  Scenario: Duplicate global custom page
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name      | weight | global |
      | Site page | -1     | 1      |
    When I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I press "Duplicate" action in the "Site page" report row
    And I click on "Duplicate" "button" in the "Duplicate page" "dialogue"
    Then I should see "Site page (copy)"
    And I should see "Global page" in the ".page-context-header" "css_element"

  Scenario: Duplicate global custom page in single tenant site
    When I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I press "Duplicate to tenant page" action in the "My teams" report row
    And I click on "Duplicate" "button" in the "Duplicate to tenant page" "dialogue"
    Then I should see "My teams (copy)"
    And I should not see "Global page" in the ".page-context-header" "css_element"

  Scenario: Duplicate global to tenant custom page
    Given the following "tool_custompage > Pages" exist:
      | name      | weight | global |
      | Site page | -1     | 1      |
    When I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I press "Duplicate to tenant page" action in the "Site page" report row
    And I click on "Duplicate" "button" in the "Duplicate to tenant page" "dialogue"
    Then I should see "Site page (copy)"
    And I should not see "Global page" in the ".page-context-header" "css_element"

  Scenario: Duplicate global to tenant custom page as tenant admin
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name      | weight | global |
      | Site page | -1     | 1      |
    And the following "tool_custompage > Audiences" exist:
      | page      | configdata |
      | Site page |            |
    When I log in as "tenantadmin1"
    And I navigate to "Custom pages" in workplace launcher
    # Should not be able to perform exact duplicate, only to their tenant from action menu.
    And I open the action menu in "Site page" "table_row"
    And "//a[normalize-space(.)='Duplicate']" "xpath_element" should not exist in the ".moodle-actionmenu" "css_element"
    And I choose "Duplicate to tenant page" in the open action menu
    And I click on "Duplicate" "button" in the "Duplicate to tenant page" "dialogue"
    Then I should see "Site page (copy)"
    And I should not see "Global page" in the ".page-context-header" "css_element"

  Scenario: Duplicate tenant custom page
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name        | weight | tenant  |
      | Tenant page | 0      | Tenant1 |
    When I log in as "tenantadmin1"
    And I navigate to "Custom pages" in workplace launcher
    And I press "Duplicate" action in the "Tenant page" report row
    And I click on "Duplicate" "button" in the "Duplicate page" "dialogue"
    Then I should see "Tenant page (copy)"
    And I should not see "Global page" in the ".page-context-header" "css_element"

  Scenario: Duplicate tenant to global custom page
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name        | weight | tenant  |
      | Tenant page | -1     | Tenant1 |
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Custom pages" in workplace launcher
    And I press "Duplicate to global page" action in the "Tenant page" report row
    And I click on "Duplicate" "button" in the "Duplicate to global page" "dialogue"
    Then I should see "Tenant page (copy)"
    And I should see "Global page" in the ".page-context-header" "css_element"

  Scenario: Duplicate page in single tenant site
    Given I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I should see "Global page" in the "My teams" "table_row"
    And I open the action menu in "My teams" "table_row"
    And I press "Duplicate to tenant page" action in the "My teams" report row
    And I click on "Duplicate" "button" in the "Duplicate to tenant page" "dialogue"
    Then I should see "My teams (copy)"
    And I navigate to "Custom pages" in workplace launcher
    And I open the action menu in "My teams (copy)" "table_row"
    And I should not see "Duplicate to global page" in the "[data-rel='menu-content']" "css_element"
    And I press "Duplicate" action in the "My teams (copy)" report row
    And I click on "Duplicate" "button" in the "Duplicate page" "dialogue"
    And I should see "My teams (copy) (copy)"
