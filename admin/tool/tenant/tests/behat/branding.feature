@tool @tool_tenant @moodleworkplace @javascript
Feature: Test tenant branding settings
  As an tenant admin
  I want to customise the branding for each tenant

  Background:
    Given "1" tenants exist with "2" users and "0" courses in each

  Scenario: Customise tenant footer text
    When I log in as "tenantadmin1"
    #And I navigate to "Appearance" in workplace launcher
    And I navigate to "Appearance > Appearance" in site administration
    And I click on "Appearance" "link" in the "#adminsettings" "css_element"
    And I set the following fields to these values:
      | Footer text | <a href="../my">Footer's Link1</p> |
    And I press "Save changes"
    Then I should see "Theme settings were saved. It may take several minutes before changes are visible on the site."
    And I log out
    When I log in as "tenantadmin1"
    # Go away from the Dashboard.
    And I am on course index
    Then I should see "Footer's Link1" in the ".customfooter" "css_element"
    And I follow "Footer's Link1"
    # Make sure we are on the dashboard now, the "Timeline" block is only present on the dashboard.
    Then "Timeline" "block" should exist
    And I log out
