@tool @tool_organisation @moodleworkplace @javascript
Feature: Manage custom page audiences using jobs
  In order to manage custom page audiences using jobs
  As a user
  I need to be able to create, update and delete custom page audiences and jobs

  Scenario: Adding/removing a user to/from an audience job should apply changes immediately
    Given the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
      | global  | 1       |
    And the following departments exist in organisation structure:
      | name         | parent     |
      | Framework    |            |
      | DepartmentA  | Framework  |
    And the following positions exist in organisation structure:
      | name      | parent     | globalmanager | globalpermissions |
      | Framework |            |      0        |       0           |
      | Manager   | Framework  |      1        |       7           |
    When I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    And I select "Audience" from secondary navigation
    And I click on "Add audience 'Managers'" "link"
    And I set the field "Permissions" to "Manager"
    And I press "Save changes"
    And I navigate to "Organisation structure" in workplace launcher
    Then I should not see "My page" in the ".primary-navigation" "css_element"
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users  | Admin User   |
      | Position      | Manager      |
      | Department    | DepartmentA  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I reload the page
    And I should see "My page" in the ".primary-navigation" "css_element"
    And I press "Delete job" action in the "Admin User" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I reload the page
    And I should not see "My page" in the ".primary-navigation" "css_element"
