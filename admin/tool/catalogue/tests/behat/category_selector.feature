@tool @tool_catalogue @moodleworkplace @javascript
Feature: Category selector functionality and accessing them in correct structure
  Background:
    When the following "categories" exist:
      | name    | category    | idnumber  |
      | CatA    | 0           | cata      |
      | CatA1   | cata        | cata1     |
      | CatA2   | cata        | cata2     |
      | CatA3   | cata        | cata3     |
    And the following config values are set as admin:
      | enabled           | 1 | tool_catalogue |
      | frontpage         | 7 |                |
      | frontpageloggedin | 7 |                |

  Scenario: User can see category selector in the Home page on desktop screen
    When I log in as "admin"
    And I am on site homepage
    And I should see "Categories"
    And I press "Categories"
    And I hover over the "CatA" link
    And I should see "CatA1"
    And I should see "CatA2"
    And I should see "CatA3"

  Scenario: User can see category selector in the Home page on mobile screen
    When I change window size to "mobile"
    And I log in as "admin"
    And I am on site homepage
    And I should see "Categories"
    And I press "Categories"
    And I should see "CatA"
    And I click on "CatA" "link"
    And I should see "All CatA"
    And I should see "CatA1"
    And I should see "CatA2"
    And I should see "CatA3"
    # Test back button.
    And I click on "Back" "link"
    And I should see "CatA"
    # Test close button.
    And I click on ".tool_catalogue-dropdown-close" "css_element"
    And I should not see "CatA"
    And I should see "Categories"
