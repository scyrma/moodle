@tool @tool_tenant @moodleworkplace @javascript
Feature: Manage tenants
  As an admin
  I want to be able to create, update, archive and delete tenants

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
    And the following "roles" exist:
      | shortname | name | archetype |
      | tenantaddmanager | Tenants add manager |  |
      | tenantusermanager | Tenant user allocation manager | |
    And the following "role assigns" exist:
      | user  | role              | contextlevel | reference |
      | user1 | tenantaddmanager  | System       |           |
      | user2 | tenantusermanager | System       |           |
      | user3 | tenantaddmanager  | System       |           |
      | user3 | tenantusermanager | System       |           |
    And the following "categories" exist:
      | name   | category | idnumber |
      | Bacon  |          | C001     |
      | Newcat |          | CNEW     |
    And the following "permission overrides" exist:
      | capability             | permission | role              | contextlevel | reference |
      | tool/tenant:manage     | Allow      | tenantaddmanager  | System       |           |
      | moodle/site:configview | Allow      | tenantaddmanager  | System       |           |
      | tool/tenant:allocate   | Allow      | tenantusermanager | System       |           |
      | moodle/site:configview | Allow      | tenantusermanager | System       |           |

  Scenario: Create, edit, and archive empty tenants
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 |
      | Site name   | Site name for tenant 1 |
      | ID number   | 123          |
      | Choose an existing category | 1 |
      | Category    | Newcat |
    And I press "Save"
    And "New tenant 1" "text" should exist in the ".page-header-headings" "css_element"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 1" "text" should appear after "Default tenant" "text"
    And I should see "Newcat" in the "region-main" "region"
    And I follow "Edit tenant 'New tenant 1'"
    And the field "Tenant name" matches value "New tenant 1"
    And the field "Site name" matches value "Site name for tenant 1"
    And the field "ID number" matches value "123"
    And the field "Choose an existing category" matches value "1"
    And the field "Category" matches value "Newcat"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 edited |
      | Category    | Bacon               |
    And I press "Save"
    And "New tenant 1 edited" "text" should appear after "Default tenant" "text"
    And I should see "Bacon" in the "region-main" "region"
    And I press "New tenant"
    And I set the field "Choose an existing category" to "1"
    And I should not see "Bacon" in the "Category" "select"
    And I set the following fields to these values:
      | Tenant name | Second tenant |
      | Site name   | The second tenant |
      | ID number   | 321           |
      | Category    | Newcat |
    And I press "Save"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Edit tenant 'Second tenant'"
    And I should see "Newcat" in the "Category" "select"
    And I click on "Cancel" "button" in the "Edit tenant 'Second tenant'" "dialogue"
    And I click on "Archive tenant" "link" in the "New tenant 1 edited" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And "Default tenant" "text" should exist in the "#activetenants" "css_element"
    And "New tenant 1" "text" should not exist in the "#activetenants" "css_element"
    And I follow "Archived tenants"
    And "Default tenant" "text" should not exist in the "#archivedtenants" "css_element"
    And "New tenant 1 edited" "text" should exist in the "#archivedtenants" "css_element"
    And I follow "Active tenants"
    And "Default tenant" "text" should exist in the "#activetenants" "css_element"
    And "New tenant 1 edited" "text" should not exist in the "#activetenants" "css_element"
    And I log out

  Scenario: Create a tenant and automatically create a new category
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    Then I should see "No category"
    And I should not see "Create a new category"
    And I should see "Choose an existing category"
    And I click on "Cancel" "button" in the "New tenant" "dialogue"
    And I log out
    And the following "permission overrides" exist:
      | capability             | permission | role             | contextlevel | reference |
      | moodle/category:manage | Allow      | tenantaddmanager | System       |           |
    And I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 |
      | Site name   | Site name for tenant 1 |
      | ID number   | 123          |
      | Create a new category | 1 |
    And I press "Save"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 1" "text" should appear after "Default tenant" "text"
    And the following should not exist in the "activetenantstable" table:
      | Tenant name  | Category     |
      | New tenant 1 | New tenant 1 |
    And I follow "Edit tenant 'New tenant 1'"
    And the field "Choose an existing category" matches value "1"
    And the field "Category" matches value "New tenant 1"
    And I click on "Cancel" "button" in the "Edit tenant 'New tenant 1'" "dialogue"
    And I log out

  Scenario: Allocate a user to a tenant and set as tenant admin
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    And I set the following fields to these values:
      | Tenant name                 | Tenant3 |
      | Choose an existing category | 1       |
      | Category                    | Newcat  |
    And I press "Save"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "4" "text" should exist in the "Default tenant" "tool_wp > Table tree node"
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I set the field "Select user 'User 1'" to "1"
    And I set the field "With selected users..." to "Tenant3"
    And I press "Allocate users"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Edit tenant 'Tenant3'" "link" in the "Tenant3" "tool_wp > Table tree node"
    And I set the field "Administrators" to "User 1"
    And I press "Save"
    And I click on "Edit tenant 'Tenant3'" "link" in the "Tenant3" "tool_wp > Table tree node"
    And I should see "User 1"
    And I click on "Cancel" "button" in the "Edit tenant 'Tenant3'" "dialogue"
    And I log out

  Scenario: Recover and delete empty tenants
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 |
    And I press "Save"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 1" "text" should appear after "Default tenant" "text"
    And I press "New tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 2 |
    And I press "Save"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 2" "text" should appear after "New tenant 1" "text"
    And I press "New tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 3 |
    And I press "Save"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 3" "text" should appear after "New tenant 1" "text"
    And I click on "Archive tenant" "link" in the "New tenant 1" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I click on "Archive tenant" "link" in the "New tenant 2" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I click on "Archive tenant" "link" in the "New tenant 3" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I follow "Archived tenants"
    And I click on "Restore tenant" "link" in the "New tenant 1" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I follow "Archived tenants"
    And I click on "Delete tenant" "link" in the "New tenant 2" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And "Default tenant" "text" should not exist in the "#archivedtenants" "css_element"
    And "New tenant 1" "text" should not exist in the "#archivedtenants" "css_element"
    And "New tenant 2" "text" should not exist in the "#archivedtenants" "css_element"
    And "New tenant 3" "text" should exist in the "#archivedtenants" "css_element"
    And I follow "Active tenants"
    And "Default tenant" "text" should exist in the "#activetenants" "css_element"
    And "New tenant 1" "text" should exist in the "#activetenants" "css_element"
    And "New tenant 2" "text" should not exist in the "#activetenants" "css_element"
    And "New tenant 3" "text" should not exist in the "#activetenants" "css_element"
    And I follow "Archived tenants"
    And I click on "Delete tenant" "link" in the "New tenant 3" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I follow "Active tenants"
    And I should see "Default tenant"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Reorder tenants
    Given the following "tool_tenant > tenants" exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "Big company" "text" should appear before "Small company" "text" in the "#activetenants" "css_element"
    And "Small company" "text" should appear before "Middle company" "text" in the "#activetenants" "css_element"
    And I click on "Move tenant 'Middle company'" "button"
    And I follow "To the top of the list"
    And "Middle company" "text" should appear before "Big company" "text" in the "#activetenants" "css_element"
    And "Big company" "text" should appear before "Small company" "text" in the "#activetenants" "css_element"
    And I click on "Move tenant 'Big company'" "button"
    And I follow "After \" Small company \""
    And "Middle company" "text" should appear before "Small company" "text" in the "#activetenants" "css_element"
    And "Small company" "text" should appear before "Big company" "text" in the "#activetenants" "css_element"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "Middle company" "text" should appear before "Small company" "text" in the "#activetenants" "css_element"
    And "Small company" "text" should appear before "Big company" "text" in the "#activetenants" "css_element"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Rename tenants from manage page inplace
    Given the following "tool_tenant > tenants" exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Edit name" "link" in the "Middle company" "tool_wp > Table tree node"
    And I set the field "New name for 'Middle company'" to "Other company"
    And I press the enter key
    And I should not see "Middle company"
    And I should see "Other company"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I should not see "Middle company"
    And I should see "Other company"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Rename tenant from edit page
    Given the following "tool_tenant > tenants" exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Middle company" "text" in the "Middle company" "tool_wp > Table tree node"
    And I click on "Edit details" "button"
    And I set the field "Tenant name" to "Other company"
    And I press "Save"
    And I should not see "Middle company"
    And I should see "Other company"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I should not see "Middle company"
    And I should see "Other company"
    And I log out

  Scenario: Allocate users
    Given the following "tool_tenant > tenants" exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user2"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "4" "text" should exist in the "Default tenant" "tool_wp > Table tree node"
    And I follow "Default tenant"
    And I set the field "Select user 'User 3'" to "1"
    And I set the field "Select user 'User 2'" to "1"
    And I set the field "With selected users..." to "Small company"
    And I press "Allocate users"
    And I should see "Default tenant"
    And I should see "Admin User" in the "region-main" "region"
    And I should see "User 1" in the "region-main" "region"
    And I should not see "User 2" in the "region-main" "region"
    And I should not see "User 3" in the "region-main" "region"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "2" "text" should exist in the "Small company" "tool_wp > Table tree node"
    And I follow "Small company"
    And I should not see "Admin User" in the "region-main" "region"
    And I should not see "User 1" in the "region-main" "region"
    And I should see "User 2" in the "region-main" "region"
    And I should see "User 3" in the "region-main" "region"
    And I set the field "Select user 'User 3'" to "1"
    And I set the field "With selected users..." to "Big company"
    And I press "Allocate users"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Tenant switch and browse organisation structure
    Given "2" tenants exist with "1" users and "1" courses in each
    When I log in as "admin"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Departments"
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework0 |
      | ID number | 00000 |
    And I press "Save"
    And I switch to tenant "Tenant1"
    Then I should see "Departments and positions need to be created to proceed with job assignments"
    And I follow "Departments"
    And I should not see "Framework0"
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 11111 |
    And I press "Save"
    Then I should see "Framework1"
    And I should not see "Framework0"
    And I switch to tenant "Tenant2"
    Then I should see "Departments and positions need to be created to proceed with job assignments"
    And I follow "Departments"
    And I should not see "Framework0"
    And I should not see "Framework1"
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework2 |
      | ID number | 22222 |
    And I press "Save"
    Then I should see "Framework2"
    And I should not see "Framework0"
    And I should not see "Framework1"
    And I switch to tenant "Default tenant"
    And I follow "Departments"
    Then I should see "Framework0"
    And I should not see "Framework1"
    And I should not see "Framework2"
    And I switch to tenant "Tenant1"
    And I follow "Departments"
    Then I should see "Framework1"
    And I should not see "Framework0"
    And I should not see "Framework2"
    And I switch to tenant "Tenant2"
    And I follow "Departments"
    Then I should see "Framework2"
    And I should not see "Framework0"
    And I should not see "Framework1"

  Scenario: Creating tenants when multitenancy is not enabled
    Given the following config values are set as admin:
      | tool_tenant_tenantlimitenabled | 1 |
      | tool_tenant_tenantlimit | 1 |
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    Then I should see "Multi-tenancy feature is not enabled on this site"
    And I click on "OK" "button" in the "Tenant limit reached" "dialogue"
    And I log out

  Scenario: Creating tenants when tenant limit is reached
    Given the following config values are set as admin:
      | tool_tenant_tenantlimitenabled | 1 |
      | tool_tenant_tenantlimit | 3 |
    And "2" tenants exist with "0" users and "0" courses in each
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I press "New tenant"
    Then I should see "You can only create 3 tenants on this site. Please note that archived tenants are also counted towards this limit"
    And I click on "OK" "button" in the "Tenant limit reached" "dialogue"
    And I click on "Archive tenant" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I press "New tenant"
    Then I should see "You can only create 3 tenants on this site. Please note that archived tenants are also counted towards this limit"
    And I click on "OK" "button" in the "Tenant limit reached" "dialogue"
    And I follow "Archived tenants"
    And I click on "Delete tenant" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Yes" "button"
    And I follow "Active tenants"
    And I press "New tenant"
    And I should not see "Tenant limit reached"
    And I set the following fields to these values:
      | Tenant name | NewTenant |
    And I press "Save"
    And I should see "NewTenant"
    And I log out

  Scenario: View tenant details
    Given "2" tenants exist with "1" users and "0" courses in each
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I follow "Details"
    And "Tenant1" "text" should exist in the "[data-formclass='tool_tenant\form\view_tenant_form']" "css_element"
    And "Tenantadmin 1" "text" should exist in the "[data-formclass='tool_tenant\form\view_tenant_form']" "css_element"
    And "Acceptance test site" "text" should exist in the "[data-formclass='tool_tenant\form\view_tenant_form']" "css_element"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I follow "Details"
    And I should see "Site name"
    And I should not see "Tenant1"
    And "Tenantadmin 1" "text" should exist in the "[data-formclass='tool_tenant\form\view_tenant_form']" "css_element"
    And "Acceptance test site" "text" should exist in the "[data-formclass='tool_tenant\form\view_tenant_form']" "css_element"
    And "Category1" "text" should exist in the "[data-formclass='tool_tenant\form\view_tenant_form']" "css_element"
    And I log out

  Scenario: View default tenant details
    Given the following "tool_tenant > tenants" exist:
      | name        |
      | Big company |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Active tenants" "tool_wp > Tab"
    And I follow "Edit tenant 'Default tenant'"
    # Changing the name of the Default Tenant here to assert the Default Tenant label still exists.
    And I set the following fields to these values:
      | Tenant name           | Small Company |
      | Create a new category | 1               |
    And I press "Save"
    And I should see "Small Company" in the "Active" "tool_wp > Tab content"
    And "Default tenant" "text" should exist in the "Small Company" "tool_wp > Table tree node"
    And "Default tenant" "text" should not exist in the "Big company" "tool_wp > Table tree node"
    And I click on "Small Company" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I click on "Details" "tool_wp > Tab"
    Then I should see "This is the default tenant, therefore its configuration will always be used by the mobile app." in the "Details" "tool_wp > Tab content"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Active tenants" "tool_wp > Tab"
    And I click on "Big company" "link" in the "Big company" "tool_wp > Table tree node"
    And I click on "Details" "tool_wp > Tab"
    Then I should not see "This is the default tenant, therefore its configuration will always be used by the mobile app." in the "Details" "tool_wp > Tab content"
    And I log out
