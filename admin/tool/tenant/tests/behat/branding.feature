@tool @tool_tenant @moodleworkplace @javascript
Feature: Test tenant branding settings
  As an tenant admin
  I want to customise the branding for each tenant

  Background:
    Given "1" tenants exist with "2" users and "0" courses in each

  Scenario: Customise tenant footer text
    When I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I set the following fields to these values:
      | Footer text | <a href="../my">Footer's Link1</p> |
    And I press "Save changes"
    Then I should see "Theme settings were saved. It may take several minutes before changes are visible on the site."
    And I log out
    When I log in as "tenantadmin1"
    # Go away from the Dashboard.
    And I am on course index
    Then I should see "Footer's Link1" in the ".footer-content-popover" "css_element"
    And I follow "Footer's Link1"
    # Make sure we are on the dashboard now, the "Timeline" block is only present on the dashboard.
    Then "Timeline" "block" should exist

  Scenario: Advanced tenant settings are not displayed without permission
    When I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    Then "Show more..." "link" should not exist in the "region-main" "region"
    And I should not see "Navigation bar colour" in the "region-main" "region"

  Scenario: Customise advanced tenant settings
    Given the following "permission overrides" exist:
      | capability                      | permission | role                | contextlevel | reference |
      | tool/tenant:managethemeadvanced | Allow      | tool_tenant_admin   | System       |           |
    When I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Show more..." "link" in the "region-main" "region"
    And I set the following fields to these values:
      | Navigation bar colour | #FFDDAA       |
      | Primary button colour | #FFCCCC       |
      | Custom SCSS           | $test: true;  |
    And I press "Save changes"
    Then I should see "Theme settings were saved. It may take several minutes before changes are visible on the site."
    And the following fields match these values:
      | Navigation bar colour | #FFDDAA       |
      | Primary button colour | #FFCCCC       |
      | Custom SCSS           | $test: true;  |

  Scenario: Reset appearance from site administration
    Given the following "permission overrides" exist:
      | capability                      | permission | role                | contextlevel | reference |
      | tool/tenant:managethemeadvanced | Allow      | tool_tenant_admin   | System       |           |
    When I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I set the following fields to these values:
      | Footer text | Test messages |
    And I press "Save changes"
    Then I should see "Theme settings were saved. It may take several minutes before changes are visible on the site."
    And I click on "Show more..." "link" in the "region-main" "region"
    And I press "Reset tenant appearance"
    And I should see "Reset appearance" in the ".modal-title" "css_element"
    And I click on "Reset appearance" "button" in the ".modal.show .modal-footer" "css_element"
    And I wait to be redirected
    Then the following fields do not match these values:
      | Footer text | Test messages |

  Scenario: Reset appearance from tenant administration
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I navigate to "Branding" in current page administration
    And I set the following fields to these values:
      | Footer text | Test messages |
    And I press "Save changes"
    Then I should see "Theme settings were saved. It may take several minutes before changes are visible on the site."
    And I click on "Show more..." "link" in the "region-main" "region"
    And I press "Reset tenant appearance"
    And I should see "Reset appearance" in the ".modal-title" "css_element"
    And I click on "Reset appearance" "button" in the ".modal.show .modal-footer" "css_element"
    And I wait to be redirected
    And I navigate to "Branding" in current page administration
    Then the following fields do not match these values:
      | Footer text | Test messages |
