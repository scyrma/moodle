@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import users
  As a tenant administrator
  I want to export and import users

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | One      | user1@example.com |
      | user2    | User      | Two      | user2@example.com |

  Scenario: Export single user
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Users" "radio"
    And I press "Next"
    And I click on "Select users manually..." "radio"
    And I press "Next"
    And I should see "Required" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I open the autocomplete suggestions list
    And "User One" "autocomplete_suggestions" should exist
    And I click on "User Two" item in the autocomplete list
    And I take focus off "Select users manually..." "field"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "User Two" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button"
    And I click on "View export" "link" in the "Users" "table_row"
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "User Two" in the "[data-region=\"exportimport-instances\"]" "css_element"
