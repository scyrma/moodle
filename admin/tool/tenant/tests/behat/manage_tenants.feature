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
      | name  | category | idnumber |
      | Bacon |          | C001     |
    And I log in as "admin"
    And I set the following system permissions of "Tenants add manager" role:
      | capability             | permission |
      | tool/tenant:manage     | Allow      |
      | moodle/site:configview | Allow      |
    And I set the following system permissions of "Tenant user allocation manager" role:
      | capability | permission |
      | tool/tenant:allocate | Allow |
      | moodle/site:configview | Allow |
    And I log out

  Scenario: Create, edit, and archive empty tenants
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    Then "Default tenant" "text" should exist in the "#activetenants" "css_element"
    And I follow "Add tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 |
      | Site name   | Site name for tenant 1 |
      | ID number   | 123          |
      | Choose an existing category | 1 |
      | Category    | Miscellaneous |
    And I press "Save" in the modal form dialogue
    And "New tenant 1" "text" should exist in the ".page-header-headings" "css_element"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 1" "text" should appear after "Default tenant" "text"
    And I should see "Miscellaneous" in the "region-main" "region"
    And I follow "Edit tenant 'New tenant 1'"
    And the field "Tenant name" matches value "New tenant 1"
    And the field "Site name" matches value "Site name for tenant 1"
    And the field "ID number" matches value "123"
    And the field "Choose an existing category" matches value "1"
    And the field "Category" matches value "Miscellaneous"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 edited |
      | Category    | Bacon               |
    And I press "Save" in the modal form dialogue
    And "New tenant 1 edited" "text" should appear after "Default tenant" "text"
    And I should see "Bacon" in the "region-main" "region"
    And I follow "Add tenant"
    And I set the field "Choose an existing category" to "1"
    And I should not see "Bacon" in the "Category" "select"
    And I set the following fields to these values:
      | Tenant name | Second tenant |
      | Site name   | The second tenant |
      | ID number   | 321           |
      | Category    | Miscellaneous |
    And I press "Save" in the modal form dialogue
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Edit tenant 'Second tenant'"
    And I should see "Miscellaneous" in the "Category" "select"
    And I press "Cancel" in the modal form dialogue
    And I click on "Archive tenant" "link" in the "New tenant 1 edited" table tree node
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
    And I follow "Add tenant"
    Then I should see "No category"
    And I should not see "Create a new category"
    And I should see "Choose an existing category"
    And I press "Cancel" in the modal form dialogue
    And I log out
    And I log in as "admin"
    And I set the following system permissions of "Tenants add manager" role:
      | capability             | permission |
      | moodle/category:manage | Allow      |
    And I log out
    And I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Add tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 |
      | Site name   | Site name for tenant 1 |
      | ID number   | 123          |
      | Create a new category | 1 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 1" "text" should appear after "Default tenant" "text"
    And the following should not exist in the "activetenantstable" table:
      | Tenant name  | Category     |
      | New tenant 1 | New tenant 1 |
    And I follow "Edit tenant 'New tenant 1'"
    And the field "Choose an existing category" matches value "1"
    And the field "Category" matches value "New tenant 1"
    And I press "Cancel" in the modal form dialogue
    And I log out

  Scenario: Allocate a user to a tenant and set as tenant admin
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Add tenant"
    And I set the following fields to these values:
      | Tenant name | Tenant3 |
      | Choose an existing category | 1 |
      | Category    | Miscellaneous |
    And I press "Save" in the modal form dialogue
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "4" "text" should exist in the "Default tenant" table tree node
    And I click on "Default tenant" "link" in the "Default tenant" table tree node
    And I set the field "Select user 'User 1'" to "1"
    And I set the field "With selected users..." to "Tenant3"
    And I press "Allocate users"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Edit tenant 'Tenant3'" "link" in the "Tenant3" table tree node
    And I open the autocomplete suggestions list in the dialog
    And I click on "User 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Administrators"
    And I press "Save" in the modal form dialogue
    And I click on "Edit tenant 'Tenant3'" "link" in the "Tenant3" table tree node
    And I should see "User 1"
    And I press "Cancel" in the modal form dialogue
    And I log out

  Scenario: Recover and delete empty tenants
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Add tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 1 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 1" "text" should appear after "Default tenant" "text"
    And I follow "Add tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 2 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 2" "text" should appear after "New tenant 1" "text"
    And I follow "Add tenant"
    And I set the following fields to these values:
      | Tenant name | New tenant 3 |
    And I press "Save" in the modal form dialogue
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "New tenant 3" "text" should appear after "New tenant 1" "text"
    And I click on "Archive tenant" "link" in the "New tenant 1" table tree node
    And I click on "Yes" "button"
    And I click on "Archive tenant" "link" in the "New tenant 2" table tree node
    And I click on "Yes" "button"
    And I click on "Archive tenant" "link" in the "New tenant 3" table tree node
    And I click on "Yes" "button"
    And I follow "Archived tenants"
    And I click on "Restore tenant" "link" in the "New tenant 1" table tree node
    And I click on "Yes" "button"
    And I follow "Archived tenants"
    And I click on "Delete tenant" "link" in the "New tenant 2" table tree node
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
    And I click on "Delete tenant" "link" in the "New tenant 3" table tree node
    And I click on "Yes" "button"
    And I follow "Active tenants"
    And I should see "Default tenant"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Reorder tenants
    Given the following tenants exist:
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
    Given the following tenants exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Edit name" "link" in the "Middle company" table tree node
    And I set the field "New name for 'Middle company'" to "Other company"
    And I press key "13" in the field "New name for 'Middle company'"
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
    Given the following tenants exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Middle company" "text" in the "Middle company" table tree node
    And I click on "Edit details" "button"
    And I set the field "Tenant name" to "Other company"
    And I press "Save" in the modal form dialogue
    And I should not see "Middle company"
    And I should see "Other company"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I should not see "Middle company"
    And I should see "Other company"
    And I log out

  Scenario: Allocate users
    Given the following tenants exist:
      | name           |
      | Big company    |
      | Small company  |
      | Middle company |
    When I log in as "user2"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "4" "text" should exist in the "Default tenant" table tree node
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
    And "2" "text" should exist in the "Small company" table tree node
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
    And I follow "New department framework"
    And I set the following fields to these values:
      | Name | Framework0 |
      | ID number | 00000 |
    And I press "Save" in the modal form dialogue
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    Then I should see "Departments need to be created to proceed with job assignments"
    And I should not see "Framework0"
    And I follow "New department framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 11111 |
    And I press "Save" in the modal form dialogue
    Then I should see "Framework1"
    And I should not see "Framework0"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant2" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    Then I should see "Departments need to be created to proceed with job assignments"
    And I should not see "Framework0"
    And I should not see "Framework1"
    And I follow "New department framework"
    And I set the following fields to these values:
      | Name | Framework2 |
      | ID number | 22222 |
    And I press "Save" in the modal form dialogue
    Then I should see "Framework2"
    And I should not see "Framework0"
    And I should not see "Framework1"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Default tenant" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    Then I should see "Framework0"
    And I should not see "Framework1"
    And I should not see "Framework2"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    Then I should see "Framework1"
    And I should not see "Framework0"
    And I should not see "Framework2"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant2" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    Then I should see "Framework2"
    And I should not see "Framework0"
    And I should not see "Framework1"
