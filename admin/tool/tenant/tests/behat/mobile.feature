@tool @tool_tenant @moodleworkplace @javascript
Feature: Test mobile settings in multi-tenancy
  As an admin or tenant admin
  I want to be able to set up branded mobile apps per tenant

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each

  Scenario: Admin can force mobile settings for individual tenants
    Given the following config values are set as admin:
      | enablemobilewebservice | 1         |             |
      | enablesmartappbanners  | 1         | tool_mobile |
      | iosappid               | 222222222 | tool_mobile |
      | airnotifieraccesskey   | 12356789  |             |
    When I log in as "admin"
    And I navigate to "Mobile app > Mobile appearance" in site administration
    And I click on "Force for all tenants" "text" in the "Android app's unique identifier" "tool_wp > Row"
    And I press "Save changes"
    And I navigate to "General > Messaging > Mobile" in site administration
    And I click on "Force for all tenants" "text" in the "Airnotifier app name" "tool_wp > Row"
    And I press "Save changes"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I navigate to "Mobile" in current page administration
    And I should see "iOS app"
    And I should not see "Android app"
    And I should see "Default: 222222222"
    And I should not see "Airnotifier app name"
    And I should see "Airnotifier access key"
    And I should see "Default: 12356789"

  Scenario: Admin can override mobile settings for individual tenants
    Given the following config values are set as admin:
      | enablemobilewebservice | 1         |             |
      | enablesmartappbanners  | 1         | tool_mobile |
      | iosappid               | 222222222 | tool_mobile |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I navigate to "Mobile" in current page administration
    And I click on "Override" "radio" in the "[data-groupname=\"tool_mobile__iosappid_custom_group\"]" "css_element"
    And I set the field "iOS app's unique identifier" to "333333333"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I log in as "user21"
    Then html head should contain "<meta name=\"apple-itunes-app\" content=\"app-id=222222222,"
    When I log in as "user11"
    Then html head should contain "<meta name=\"apple-itunes-app\" content=\"app-id=333333333,"

  Scenario: Tenant administrator by default can not override mobile settings
    Given the following config values are set as admin:
      | enablemobilewebservice | 1 |             |
      | enablesmartappbanners  | 1 | tool_mobile |
    And I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I should not see "Mobile"

  Scenario: Tenant administrator can be allowed to override mobile settings
    Given the following config values are set as admin:
      | enablemobilewebservice | 1                    |             |
      | enablesmartappbanners  | 1                    | tool_mobile |
      | androidappid           | com.moodle.workplace | tool_mobile |
    And the following "permission overrides" exist:
      | capability               | permission | role              | contextlevel | reference |
      | tool/tenant:mobileconfig | Allow      | tool_tenant_admin | System       |           |
    And I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I navigate to "Mobile" in current page administration
    And I click on "Override" "radio" in the "[data-groupname=\"tool_mobile__androidappid_custom_group\"]" "css_element"
    And I set the field "Android app's unique identifier" to "completely.different.app"
    And I press "Save changes"
    When I log in as "user21"
    And mobile manifest should contain "com.moodle.workplace"
    And mobile manifest should not contain "completely.different.app"
    When I log in as "user11"
    And mobile manifest should contain "completely.different.app"
    And mobile manifest should not contain "com.moodle.workplace"

  Scenario: Global admin will see default mobile settings in site administration even if they are overridden for their tenant
    Given the following config values are set as admin:
      | enablemobilewebservice | 1         |             |
      | enablesmartappbanners  | 1         | tool_mobile |
      | iosappid               | 222222222 | tool_mobile |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I navigate to "Mobile" in current page administration
    And I click on "Override" "radio" in the "[data-groupname=\"tool_mobile__iosappid_custom_group\"]" "css_element"
    And I set the field "iOS app's unique identifier" to "333333333"
    And I press "Save changes"
    And I am on homepage
    Then html head should contain "<meta name=\"apple-itunes-app\" content=\"app-id=333333333,"
    And I navigate to "Mobile app > Mobile appearance" in site administration
    And the field "iOS app's unique identifier" matches value "222222222"
    And the field "iOS app's unique identifier" does not match value "333333333"
