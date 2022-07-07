@tool @tool_custompage @moodleworkplace @javascript
Feature: View custom page
  In order to view custom page
  As a user
  I need to be able to access the page from primary navigation

  Scenario: View custom pages in primary navigation
    Given "1" tenants exist with "2" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name        | weight | tenant  |
      | Second page | 2      | Tenant1 |
      | First page  | -4     | Tenant1 |
      | Third page  | 3      | Tenant1 |
    And the following "tool_custompage > Audiences" exist:
      | page        | configdata |
      | First page  |            |
      | Second page |            |
    When I log in as "user11"
    And I am on site homepage
    Then I should see "First page" in the ".primary-navigation" "css_element"
    And I should see "Second page" in the ".primary-navigation" "css_element"
    And I should not see "Third page" in the ".primary-navigation" "css_element"
    And "Second page" "text" should appear after "First page" "text"

  Scenario: View custom pages in page header
    Given the following "tool_custompage > Pages" exist:
      | name     | title        | weight |
      | Page one | My title one | 0      |
      | Page two |              | 1      |
    And the following "tool_custompage > Audiences" exist:
      | page     | configdata |
      | Page one |            |
      | Page two |            |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | user1    | John      | Smith    | john@example.com |
    When I log in as "user1"
    And I am on site homepage
    And I follow "My title one"
    Then I should see "Page one" in the "page-header" "region"
    And I follow "Page two"
    And I should see "Page two" in the "page-header" "region"
