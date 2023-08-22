@tool @tool_tenant @tool_custompage @moodleworkplace @javascript
Feature: Manage custom page specific tenant audience
  In order to manage custom page audiences
  As a user
  I need to be able to manage specific tenant users audience

  Scenario: View users who match specific tenant audience in global page
    Given "1" tenants exist with "1" users and "0" courses in each
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email              | tenant  |
      | user01   | User      | One      | user01@example.com | Tenant1 |
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | -1      |
      | global  | 1       |
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I am on the "My page" "tool_custompage > Manage" page
    And I select "Audience" from secondary navigation
    And I click on "Add audience 'Tenant users'" "link"
    And I press "Save changes"
    And I should see "Audience saved"
    And I select "Access" from secondary navigation
    Then I should see "User One" in the "reportbuilder-table" "table"
    And I should see "Tenantadmin 1" in the "reportbuilder-table" "table"
    And I should see "Admin User" in the "reportbuilder-table" "table"
    And I select "Audience" from secondary navigation
    And I press "Edit audience 'Tenant users'"
    And I click on "Users in the following tenants..." "radio"
    And I set the field "Users in the following tenants" in the "//div[contains(@id, 'fitem_id_onlytenants')]" "xpath_element" to "Tenant1"
    And I press "Save changes"
    And I should see "Audience saved"
    And I select "Access" from secondary navigation
    Then I should see "User One" in the "reportbuilder-table" "table"
    And I should see "Tenantadmin 1" in the "reportbuilder-table" "table"
    And I should not see "Admin User" in the "reportbuilder-table" "table"
    And I select "Audience" from secondary navigation
    And I press "Edit audience 'Tenant users'"
    And I click on "Users in all tenants except the following..." "radio"
    And I set the field "Users in all tenants except the following" in the "//div[contains(@id, 'fitem_id_excepttenants')]" "xpath_element" to "Tenant1"
    And I press "Save changes"
    And I should see "Audience saved"
    And I select "Access" from secondary navigation
    Then I should not see "User One" in the "reportbuilder-table" "table"
    And I should not see "Tenantadmin 1" in the "reportbuilder-table" "table"
    And I should see "Admin User" in the "reportbuilder-table" "table"
