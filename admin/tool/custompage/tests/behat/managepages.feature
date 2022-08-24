@tool @tool_custompage @moodleworkplace @javascript
Feature: Manage custom pages
  In order to manage custom pages
  A a user
  I need to be able to create, update and delete pages

  Scenario: Create new global custom page
    Given I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    When I open the action menu in "page-header" "region"
    And I choose "New global page" in the open action menu
    And I set the following fields in the "New global page" "dialogue" to these values:
      | Title  | This is my site page |
      | Weight | -4                   |
    And I click on "Save" "button" in the "New global page" "dialogue"
    And I should see "You must supply a value here."
    And I set the following fields in the "New global page" "dialogue" to these values:
      | Name   | My site page |
    And I click on "Save" "button" in the "New global page" "dialogue"
    Then I should see "My site page"
    And I should see "Global page" in the ".page-context-header" "css_element"
    And I should see "Nothing to display"

  Scenario: Create new custom page for current tenant
    Given I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    When I open the action menu in "page-header" "region"
    And I choose "New tenant page" in the open action menu
    And I set the following fields in the "New page" "dialogue" to these values:
      | Title  | This is my tenant page |
      | Weight | -4                     |
    And I click on "Save" "button" in the "New page" "dialogue"
    And I should see "You must supply a value here."
    And I set the following fields in the "New page" "dialogue" to these values:
      | Name   | My tenant page |
    And I click on "Save" "button" in the "New page" "dialogue"
    Then I should see "My tenant page"
    And I should not see "Global page"
    And I should see "Nothing to display"

  Scenario: View list of custom pages for tenant as admin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name          | weight | global | tenant  |
      | Tenant 1 page | 0      | 0      | Tenant1 |
      | Tenant 2 page | 0      | 0      | Tenant2 |
      | Site page     | -1     | 1      | Tenant2 |
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Custom pages" in workplace launcher
    Then the following should exist in the "reportbuilder-table" table:
      | Weight | Name          |
      | -1     | Site page     |
      | 0      | Tenant 1 page |
    And I should see "Global page" in the "Site page" "table_row"
    And I should not see "Global page" in the "Tenant 1 page" "table_row"
    And I should not see "Tenant 2 page" in the "reportbuilder-table" "table"

  Scenario Outline: View list of custom pages for tenant as tenant admin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name          | weight | global | tenant  |
      | Tenant 1 page | 0      | 0      | Tenant1 |
      | Tenant 2 page | 0      | 0      | Tenant2 |
      | Site page 1   | -1     | 1      | Tenant2 |
      | Site page 2   | 1      | 1      | Tenant2 |
    And the following "tool_custompage > Audiences" exist:
      | page        | configdata |
      | Site page 1 |            |
    When I log in as "<user>"
    And I navigate to "Custom pages" in workplace launcher
    Then I should see "<tenantpage>" in the "reportbuilder-table" "table"
    And I should not see "<nontenantpage>" in the "reportbuilder-table" "table"
    And I should see "Site page 1" in the "reportbuilder-table" "table"
    And I should not see "Site page 2" in the "reportbuilder-table" "table"
    Examples:
      | user         | tenantpage    | nontenantpage |
      | tenantadmin1 | Tenant 1 page | Tenant 2 page |
      | tenantadmin2 | Tenant 2 page | Tenant 1 page |

  Scenario: Edit details of custom page from page listings
    Given the following "tool_custompage > Page" exists:
      | name   | My page |
      | weight | 0       |
    When I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I set the following fields in the "My page" "table_row" to these values:
    # TODO: Behat flakes out on the select inplace editable.
    #  | Edit weight | 5               |
      | Edit name   | My updated page |
    And I reload the page
    Then the following should exist in the "reportbuilder-table" table:
      | Weight | Name            |
      | 0      | My updated page |
    And I should not see "My page" in the "reportbuilder-table" "table"

  Scenario: Delete custom page from page listings
    Given the following "tool_custompage > Pages" exist:
      | name     | weight |
      | Page one | 0      |
      | Page two | 1      |
    When I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I press "Delete" action in the "Page one" report row
    And I click on "Delete" "button" in the "Delete page" "dialogue"
    Then I should see "Deleted page"
    And I should not see "Page one" in the "reportbuilder-table" "table"

  Scenario: Filter page by type
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name           | weight | global |
      | My global page | -1     | 1      |
      | My tenant page | 0      | 0      |
    When I log in as "admin"
    And I navigate to "Custom pages" in workplace launcher
    And I click on "Filters" "button"
    And I set the following fields in the "Page type" "core_reportbuilder > Filter" to these values:
      | Page type operator | Is any value |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Weight | Name           |
      | -1     | My global page |
      | 0      | My tenant page |
    And I set the following fields in the "Page type" "core_reportbuilder > Filter" to these values:
      | Page type operator | Is equal to |
      | Page type value    | Global page |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Weight | Name           |
      | -1     | My global page |
    And I should not see "My tenant page" in the "reportbuilder-table" "table"
    And I set the following fields in the "Page type" "core_reportbuilder > Filter" to these values:
      | Page type operator | Is equal to |
      | Page type value    | Tenant page |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Weight | Name           |
      | 0      | My tenant page |
    And I should not see "My global page" in the "reportbuilder-table" "table"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Custom pages" in workplace launcher
    And I click on "Filters" "button"
    Then I should not see "Page type" in the "[data-region='report-filters']" "css_element"
