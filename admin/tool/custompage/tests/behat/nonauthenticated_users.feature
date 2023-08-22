@tool @tool_custompage @moodleworkplace @javascript
Feature: View custom pages as a non-authenticated user
  In order to view custom pages
  As a non-authenticated user or a guest user
  I need to be able to access the page from primary navigation

  Background:
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | title       | name        | weight | global | tenant  |
      | Tenant page | Tenant page | 0      | 0      | Tenant1 |
      | Global page | Global page | 0      | 1      | Tenant1 |
    And the following "tool_custompage > Audiences" exist:
      | page        | classname                                                      | configdata |
      | Tenant page | tool_custompage\tool_custompage\audience\nonauthenticatedusers |            |
      | Global page | tool_custompage\tool_custompage\audience\nonauthenticatedusers |            |
    And the following config values are set as admin:
      | forceloginforprofiles | 0 |

  Scenario Outline: View global custom page as non-authenticated user
    When I am on homepage for tenant "<tenant>"
    And I select "Global page" from primary navigation
    And I should see "Global page" in the "page-header" "region"
    Then I follow "Log in"
    And I press "Access as a guest"
    And I select "Global page" from primary navigation
    And I should see "Global page" in the "page-header" "region"
    Examples:
      | tenant  |
      | Tenant1 |
      | Tenant2 |

  Scenario: View tenant custom page as non-authenticated user
    When I am on homepage for tenant "Tenant1"
    And I select "Tenant page" from primary navigation
    And I should see "Tenant page" in the "page-header" "region"
    Then I follow "Log in"
    And I press "Access as a guest"
    And I select "Tenant page" from primary navigation
    And I should see "Tenant page" in the "page-header" "region"
    And I log out
    Then I am on homepage for tenant "Tenant2"
    And I should not see "Tenant page" in the ".primary-navigation" "css_element"
    Then I follow "Log in"
    And I press "Access as a guest"
    And I should not see "Tenant page" in the ".primary-navigation" "css_element"
