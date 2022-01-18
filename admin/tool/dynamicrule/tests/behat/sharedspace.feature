@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Shared space in tool_dynamicrule
  As a global admin
  I can not use Dynamic rules in the Shared space

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each

  Scenario: When in shared space the Report builder is not available
    Given shared space is enabled
    Given I log in as "admin"
    And I click on "#workplace-menulink" "css_element"
    And I should see "All users" in the ".workplace-menu" "css_element"
    And I should see "Dynamic rules" in the ".workplace-menu" "css_element"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Shared space" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    And I should see "Shared space" in the ".navbar" "css_element"
    And I click on "#workplace-menulink" "css_element"
    And I should see "All users" in the ".workplace-menu" "css_element"
    And I should not see "Dynamic rules" in the ".workplace-menu" "css_element"
    And I log out
