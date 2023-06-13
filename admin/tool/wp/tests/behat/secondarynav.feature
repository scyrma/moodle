@tool @tool_wp @moodleworkplace @javascript
Feature: Test secondary navigation in components
  As a global admin
  I want to be able to use secondary navigation in components.

  Scenario: Warning on unsaved form when secondary navigation tab is changing
    When I log in as "admin"
    And I navigate to "Appearance" in workplace launcher
    And I set the following fields to these values:
      | Footer text | <a href="../my">Footer's Link1</p> |
    And I navigate to "Details" in current page administration
    Then I should see "You have made changes. Are you sure you want to navigate away and lose your changes?" in the "Changes made" "dialogue"
    And I click on "Cancel" "button" in the "Changes made" "dialogue"
    # We are back on branding tab
    And ".active" "css_element" should exist in the "Branding" "tool_wp > Secondary tab"
    # Trying again, now confirm
    And I navigate to "Details" in current page administration
    And I should see "You have made changes. Are you sure you want to navigate away and lose your changes?" in the "Changes made" "dialogue"
    And I click on "Confirm" "button" in the "Changes made" "dialogue"
    # We moved to the details tab
    And ".active" "css_element" should exist in the "Details" "tool_wp > Secondary tab"
    And I navigate to "Branding" in current page administration
    And "Changes made" "dialogue" should not exist
    # We are back on branding tab
    And ".active" "css_element" should exist in the "Branding" "tool_wp > Secondary tab"
