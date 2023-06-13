@tool @tool_tenant @moodleworkplace @javascript
Feature: Manage tenant users
  As a global admin or tenant admin
  I want to be able to manage users inside a specific tenant and sitewide

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following "roles" exist:
      | shortname      | name             | archetype |
      | tenantoverseer | Tenants overseer |           |
    And the following "role assigns" exist:
      | user   | role           | contextlevel | reference |
      | user11 | tenantoverseer | System       |           |
      | user21 | tenantoverseer | System       |           |
    And the following "permission overrides" exist:
      | capability             | permission | role           | contextlevel | reference |
      | tool/tenant:allocate   | Allow      | tenantoverseer | System       |           |
      | moodle/site:configview | Allow      | tenantoverseer | System       |           |

  Scenario: As a tenant admin I can create and edit users directly in the tenant.
    Given the following "custom profile fields" exist:
      | datatype | shortname  | name        |
      | text     | superfield | Super field |
    When I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And "[data-tabs-element='addbutton']" "css_element" should be visible
    And I follow "New user"
    And I expand all fieldsets
    And I should see "Preferred language"
    And I set the following fields in the "New user" "dialogue" to these values:
      | Email address | user3@address.invalid |
    And I set the following fields to these values:
      | Description   | Description for this profile |
    And I set the following fields in the ".modal-dialog" "css_element" to these values:
      | First name    | User                         |
      | Email address | user3@address.invalid        |
      | Last name       | 3                            |
      | Super field   | Superhero                    |
    And I should not see "Choose an authentication method"
    And I should see "Generate password and notify user"
    And I should see "Suspended account"
    And I press "Save"
    Then I should see "Required"
    And I set the following fields in the "New user" "dialogue" to these values:
      | Username | user3 |
    And I press "Save"
    Then I should see "Required"
    And I set the following fields to these values:
      | New password | user3isGreat! |
    And I press "Save"
    Then I should see "User 3" in the "region-main" "region"
    And I press "Edit user account" action in the "User 3" report row
    And I expand all fieldsets
    Then I should not see "Preferred language"
    And the field with xpath "//div[@class='modal-content']//input[@name='username']" matches value "user3"
    And the field with xpath "//div[@class='modal-content']//input[@name='firstname']" matches value "User"
    And the field with xpath "//div[@class='modal-content']//input[@name='lastname']" matches value "3"
    And the field with xpath "//div[@class='modal-content']//input[@name='email']" matches value "user3@address.invalid"
    And the field with xpath "//div[@class='modal-content']//textarea[@name='description_editor[text]']" matches value "Description for this profile"
    And the field with xpath "//div[@class='modal-content']//input[@name='profile_field_superfield']" matches value "Superhero"
    And I set the following fields to these values:
      | Last name | Three |
      | Description | New description for the profile |
    And I set the following fields in the ".modal-dialog" "css_element" to these values:
      | Last name     | Three  |
      | Super field | Batman |
    And I press "Save"
    And I press "Edit user account" action in the "User Three" report row
    And the field with xpath "//div[@class='modal-content']//input[@name='lastname']" matches value "Three"
    And the field with xpath "//div[@class='modal-content']//textarea[@name='description_editor[text]']" matches value "New description for the profile"
    And the field with xpath "//div[@class='modal-content']//input[@name='profile_field_superfield']" matches value "Batman"
    And I click on "Cancel" "button" in the "Edit user 'User Three'" "dialogue"
    And I log out

  Scenario: See tenant name in user form
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I press "Edit user account" action in the "User 11" report row
    And I expand all fieldsets
    And I should see "Tenant1" in the ".form-control-static" "css_element"
    And I click on "Cancel" "button" in the "Edit user 'User 11'" "dialogue"
    And I log out

  Scenario: As a tenant facilitator (can only allocate users) I can not create or edit new users.
    When I log in as "user11"
    And I navigate to "Users > Organisation > User management" in site administration
    And "[data-tabs-element='addbutton']" "css_element" should not be visible

  Scenario: As a tenant admin I can edit my own account directly in the tenant.
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > User management" in site administration
    And I press "Edit user account" action in the "Tenantadmin 1" report row
    And I set the following fields to these values:
      | New password | Abra_cadab4a |
      | First name   | John |
    And I press "Save"
    And I log out
    And I am on site homepage
    When I follow "Log in"
    And I set the field "Username" to "tenantadmin1"
    And I set the field "Password" to "Abra_cadab4a"
    And I press "Log in"
    Then I should see "You are logged in as John" in the "page-footer" "region"
    And I navigate to "Users > Organisation > User management" in site administration
    And I press "Edit user account" action in the "John 1" report row
    And the field "First name" matches value "John"
    And I click on "Cancel" "button" in the "Edit user 'John 1'" "dialogue"
    And I log out

  Scenario: As a tenant admin I can edit theme settings
    When I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I set the field "Primary colour" to "#FF"
    And I press "Save changes"
    And I should see "This colour code is not in the right format"
    And I set the field "Primary colour" to "#FF0000"
    And I press "Save changes"
    # No way to check in behat that color actually changed, so just make sure there are no errors
    And I am on site homepage
    And I navigate to "Appearance" in workplace launcher
    Then the field "Primary colour" matches value "#FF0000"
    And I log out
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I select "Branding" from secondary navigation
    Then the field "Primary colour" matches value "#FF0000"
    And I press "Save changes"
    And I should see "Theme settings were saved"
    And I navigate to "Appearance" in workplace launcher
    And I should see "Default tenant"
    And I should see "Branding"
    Then the field "Primary colour" does not match value "#FF0000"
    And I log out

  Scenario: Change tenant category
    And the following "permission overrides" exist:
      | capability                     | permission | role | contextlevel | reference |
      | moodle/category:viewcourselist | Prevent    | user | System       |           |
    Given the following "categories" exist:
      | name | category | idnumber |
      | Category3 | 0 | CAT3 |
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course3 | C3 | CAT3 |
      | Course1 | C1 | CAT1 |
    When I log in as "user12"
    And I am on course index
    Then I should see "Course1"
    And I should not see "Course3"
    And I log out
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I navigate to "Details" in current page administration
    And I set the field "Category" to "Category3"
    And I press "Save changes"
    And I log out
    When I log in as "user12"
    And I am on course index
    Then I should see "Course3"
    And I should not see "Course1"
    And I log out

  Scenario: Tenant admin can view and assign roles from users page
    # First allow tenant admin assign some role in system context.
    When I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I follow "Tenant administrator"
    And I follow "Allow role assignments"
    And I set the field "Allow users with role Tenant administrator to assign the role Manager" to "1"
    And I press "Save changes"
    And I log out
    # Now log in as tenant admin and make sure you can overview all assignable roles.
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Roles"
    And I should see "Category: Category1" in the "Course creator" "table_row"
    And I follow "Manager"
    And I should see "Assign role 'Manager' in System"
    And I navigate to "Users" in workplace launcher
    And I follow "Roles"
    And I follow "Course creator"
    And I should see "Assign role 'Course creator' in Category: Category1"
    And I log out

  Scenario: Tenant administrator can not manually assign any tenant roles
    When I log in as "tenantadmin1"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    And "Manager" "link" should not exist in the "admintable" "table"
    And "Tenant" "text" should not exist in the "admintable" "table"
    And "Organisation structure manager" "link" should exist in the "admintable" "table"
    And I navigate to "Courses" in workplace launcher
    And I should see the "Course categories and courses" management page
    And I click on "permissions" action for "Category1" in management category listing
    And I select "Assign roles" from the "jump" singleselect
    And I should see "Assign roles in Category: Category1"
    And "Course creator" "link" should exist in the "admintable" "table"
    And "Tenant" "text" should not exist in the "admintable" "table"
    And I navigate to "Users" in workplace launcher
    And I follow "Roles"
    And "Organisation structure manager" "link" should exist in the "admintable" "table"
    And "Course creator" "link" should exist in the "admintable" "table"
    And "Tenant" "text" should not exist in the "admintable" "table"
    And I log out

  Scenario: Global admin can view and assign roles for each tenant from users page
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    Then "4" "text" should exist in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I follow "Roles"
    And I should see "User 11" in the "Tenants overseer" "table_row"
    And I should not see "User 21" in the "Tenants overseer" "table_row"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    And I follow "Roles"
    And I should not see "User 11" in the "Tenants overseer" "table_row"
    And I should see "User 21" in the "Tenants overseer" "table_row"
    And I log out

  Scenario: Editing theme settings without capability to browse tenant users
    Given the following "roles" exist:
      | shortname      | name             | archetype |
      | tenantthemer   | Tenants themer   |           |
    And the following "role assigns" exist:
      | user   | role         | contextlevel | reference |
      | user12 | tenantthemer | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role         | contextlevel | reference |
      | tool/tenant:managetheme | Allow      | tenantthemer | System       |           |
      | moodle/site:configview  | Allow      | tenantthemer | System       |           |
    When I log in as "user12"
    And I navigate to "Appearance" in workplace launcher
    And I set the field "Primary colour" to "#FF"
    And I press "Save changes"
    And I should see "This colour code is not in the right format"
    And I set the field "Primary colour" to "#FF0000"
    And I press "Save changes"
    # No way to check in behat that color actually changed, so just make sure there are no errors
    And I am on site homepage
    And I navigate to "Appearance" in workplace launcher
    Then the field "Primary colour" matches value "#FF0000"
    And I log out
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I select "Branding" from secondary navigation
    Then the field "Primary colour" matches value "#FF0000"
    And I press "Save changes"
    And I should see "Theme settings were saved"
    And I navigate to "Appearance" in workplace launcher
    And I should see "Default tenant"
    And I should see "Branding"
    Then the field "Primary colour" does not match value "#FF0000"
    And I log out

  Scenario: As a tenant admin I can suspend and unsuspend single user
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > User management" in site administration
    And I press "Suspend user" action in the "User 11" report row
    And I press "Suspend user"
    And I press "Unsuspend user" action in the "User 11" report row
    And I press "Unsuspend user"
    Then "Suspend user" "link" should exist in the "User 11" "table_row"
    # Add test to suspend and unsuspend using the modal form
    And I press "Edit user account" action in the "User 11" report row
    And I expand all fieldsets
    And I set the following fields to these values:
      | Suspended account | 1 |
    And I press "Save"
    Then "Unsuspend user" "link" should exist in the "User 11" "table_row"
    And I log out

  Scenario: As a tenant admin I can delete single user
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > User management" in site administration
    And I press "Delete user" action in the "User 11" report row
    And I click on "Delete user" "button" in the "Confirm" "dialogue"
    Then I should see "1 user(s) deleted"
    And I should not see "User 11" in the "reportbuilder-table" "table"

  Scenario: As a site admin I can assign and unassign tenant admin role to single user by editing user account
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I press "Edit user account" action in the "User 11" report row
    And I expand all fieldsets
    And I set the following fields to these values:
      | Administrator | 1 |
    And I press "Save"
    Then "Tenant administrator" "text" should exist in the "User 11" "table_row"
    And I press "Edit user account" action in the "User 11" report row
    And I expand all fieldsets
    And I set the following fields to these values:
      | Administrator | 0 |
    And I press "Save"
    Then "Tenant administrator" "text" should not exist in the "User 11" "table_row"

  Scenario: As a tenant admin I can suspend and unsuspend many users in bulk
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > User management" in site administration
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 12'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Suspend users"
    And I press "Suspend users"
    Then "Unsuspend user" "link" should exist in the "User 11" "table_row"
    And "Unsuspend user" "link" should exist in the "User 12" "table_row"
    And "Unsuspend user" "link" should exist in the "User 13" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Unsuspend users"
    And I press "Unsuspend users"
    Then "Suspend user" "link" should exist in the "User 11" "table_row"
    And "Unsuspend user" "link" should exist in the "User 12" "table_row"
    And "Suspend user" "link" should exist in the "User 13" "table_row"

  Scenario: As a tenant admin I can delete many users in bulk
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > User management" in site administration
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Delete users"
    And I click on "Delete users" "button" in the "Confirm" "dialogue"
    Then I should see "2 user(s) deleted"
    And I should not see "User 11" in the "reportbuilder-table" "table"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"

  Scenario: As a site admin I can assign and unassign tenant admin role to many users in bulk
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 12'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Add to tenant administrators"
    And I press "Add to tenant administrators"
    Then "Tenant administrator" "text" should exist in the "User 11" "table_row"
    And "Tenant administrator" "text" should exist in the "User 12" "table_row"
    And "Tenant administrator" "text" should exist in the "User 13" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Remove from tenant administrators"
    And I press "Remove from tenant administrators"
    Then "Tenant administrator" "text" should not exist in the "User 11" "table_row"
    And "Tenant administrator" "text" should exist in the "User 12" "table_row"
    And "Tenant administrator" "text" should not exist in the "User 13" "table_row"

  Scenario: As a site admin I can select all users in list
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Select all" "checkbox"
    And the field "Select user 'User 11'" matches value "1"
    And the field "Select user 'User 12'" matches value "1"
    And the field "Deselect all" matches value "1"
    And I set the field "With selected users..." to "Add to tenant administrators"
    And I press "Add to tenant administrators"
    Then "Tenant administrator" "text" should exist in the "User 11" "table_row"
    And "Tenant administrator" "text" should exist in the "User 12" "table_row"
    And "Tenant administrator" "text" should exist in the "User 13" "table_row"

  Scenario: As a site admin I can see filters in users in list
    Given the following config values are set as admin:
      | showuseridentity | username,email |
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    Then I should see "Username" in the "reportbuilder-table" "table"
    And I should see "Email address" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I should see "Username" in the "[data-region='report-filters']" "css_element"
    And I should see "Email address" in the "[data-region='report-filters']" "css_element"
    When the following config values are set as admin:
      | showuseridentity | username |
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    Then I should see "Username" in the "reportbuilder-table" "table"
    And I should not see "Email address" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I should see "Username" in the "[data-region='report-filters']" "css_element"
    And I should not see "Email address" in the "[data-region='report-filters']" "css_element"

  Scenario: View tenant name in profile for tenant user
    And the following "roles" exist:
      | shortname        | name                | archetype |
      | tenantaddmanager | Tenants add manager |           |
    And the following "role assigns" exist:
      | user   | role             | contextlevel | reference |
      | user11 | tenantaddmanager | System       |           |
      | user21 | tenantaddmanager | System       |           |
    And the following "permission overrides" exist:
      | capability         | permission | role             | contextlevel | reference |
      | tool/tenant:manage | Allow      | tenantaddmanager | System       |           |
    When I log in as "user11"
    And I navigate to "Users" in workplace launcher
    And I follow "User 12"
    Then I should see "Tenant" in the ".profile_tree" "css_element"
    And I should see "Tenant1" in the "//h3[text() = 'User details']/following-sibling::ul/li[contains(@class, 'contentnode')]/dl/dt[text() = 'Tenant']/following-sibling::dd[1]" "xpath_element"
    And I log out

  Scenario: View tenant name in profile for tenant manager
    And the following "roles" exist:
      | shortname        | name                | archetype |
      | tenantaddmanager | Tenants add manager |           |
    And the following "role assigns" exist:
      | user   | role             | contextlevel | reference |
      | user11 | tenantaddmanager | System       |           |
      | user21 | tenantaddmanager | System       |           |
    And the following "permission overrides" exist:
      | capability         | permission | role             | contextlevel | reference |
      | tool/tenant:manage | Allow      | tenantaddmanager | System       |           |
    When I log in as "user11"
    And I switch to tenant "Tenant2"
    And I follow "Profile" in the user menu
    Then I should see "Tenant" in the ".profile_tree" "css_element"
    And I should see "Tenant1" in the "//h3[text() = 'User details']/following-sibling::ul/li[contains(@class, 'contentnode')]/dl/dt[text() = 'Tenant']/following-sibling::dd[1]" "xpath_element"
    And I navigate to "Users" in workplace launcher
    And I follow "User 21"
    Then I should see "Tenant" in the ".profile_tree" "css_element"
    And I should see "Tenant2" in the "//h3[text() = 'User details']/following-sibling::ul/li[contains(@class, 'contentnode')]/dl/dt[text() = 'Tenant']/following-sibling::dd[1]" "xpath_element"
    And I log out

  Scenario: Update authentication method for tenant user
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And "[data-tabs-element='addbutton']" "css_element" should be visible
    And I follow "New user"
    And I set the following fields in the "New user" "dialogue" to these values:
      | Username                        | oauthuser                 |
      | Email address                   | oauthuser@address.invalid |
      | Choose an authentication method | oauth2                    |
    Then the "Generate password and notify user" "checkbox" should be disabled
    And the "New password" "field" should be disabled
    And I set the following fields to these values:
      | First name  | OAuth                        |
      | Last name     | User                         |
      | Description | Description for this profile |
    And I press "Save"
    And I press "Edit user account" action in the "OAuth User" report row
    And the "username" "field" should be disabled
    And the "New password" "field" should be disabled
    And the field "Choose an authentication method" matches value "OAuth 2"
    And I click on "Cancel" "button" in the "Edit user 'OAuth User'" "dialogue"
    And I log out

  Scenario: Tenant admin cannot change authentication method
    When I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I press "Edit user account" action in the "User 12" report row
    And I should not see "Choose an authentication method"
    And I click on "Cancel" "button" in the "Edit user 'User 12'" "dialogue"
    And I log out

  Scenario: Confirm user
    # Single
    Given the following "users" exist:
      | username | firstname | lastname | email             | auth   | confirmed | lastip    | institution | department |
      | user1    | User      | One      | one@example.com   | manual | 0         | 127.0.1.1 | moodle      | red        |
      | user2    | User      | Two      | two@example.com   | ldap   | 0         | 0.0.0.0   | moodle      | blue       |
      | user3    | User      | Three    | three@example.com | manual | 0         | 0.0.0.0   |             |            |
      | user4    | User      | Four     | four@example.com  | manual | 1         | 0.0.0.0   |             |            |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I press "Confirm user" action in the "User One" report row
    And I press "Confirm user"
    Then "Confirm user" "link" should not exist in the "User One" "table_row"
    # Bulk
    And I set the field "Select user 'User Two'" to "1"
    And I set the field "Select user 'User Three'" to "1"
    And I set the field "Select user 'User Four'" to "1"
    And I set the field "With selected users..." to "Resend confirmation emails"
    And I press "Resend confirmation emails"
    And I should see "Confirmation emails sent to 2 user(s)"
    And I should see "1 user(s) skipped as already confirmed"
    And I log out

  Scenario: Resend confirmation email to user
    Given the following "users" exist:
      | username | firstname | lastname | email             | auth   | confirmed | lastip    | institution | department |
      | user1    | User      | One      | one@example.com   | manual | 0         | 127.0.1.1 | moodle      | red        |
      | user2    | User      | Two      | two@example.com   | ldap   | 0         | 0.0.0.0   | moodle      | blue       |
      | user3    | User      | Three    | three@example.com | manual | 0         | 0.0.0.0   |             |            |
      | user4    | User      | Four     | four@example.com  | manual | 1         | 0.0.0.0   |             |            |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    # Single
    And "Resend email to user" "link" should not exist in the "User Four" "table_row"
    And I press "Resend email to user" action in the "User Two" report row
    And I press "Resend confirmation email"
    Then I should see "Confirmation emails sent to 1 user(s)"
    # Bulk
    And I set the field "Select user 'User Two'" to "1"
    And I set the field "Select user 'User Three'" to "1"
    And I set the field "Select user 'User Four'" to "1"
    And I set the field "With selected users..." to "Resend confirmation emails"
    And I press "Resend confirmation emails"
    Then I should see "Confirmation emails sent to 2 user(s)"
    And I should see "1 user(s) skipped as already confirmed"
    And I log out

  Scenario: Create user when site user limit is reached
    Given the following config values are set as admin:
      | userlimitenabled | 1 |
      | userlimit    | 3 |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "[data-tabs-element='addbutton']" "css_element" should be visible
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I follow "New user"
    And I should see "User accounts limit reached"
    And I click on "OK" "button" in the ".modal-dialog" "css_element"
    And I log out

  Scenario: Create user when tenant user limit is reached
    Given the following config values are set as admin:
      | tool_tenant_userlimitenabled | 1  |
      | tool_tenant_userlimit        | 4  |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "[data-tabs-element='addbutton']" "css_element" should be visible
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I follow "New user"
    And I should see "User accounts limit reached"
    And I click on "OK" "button" in the ".modal-dialog" "css_element"
    And I log out

  Scenario: Create user when tenant user limit is reached and site limit is less than tenant limit
    Given the following config values are set as admin:
      | userlimitenabled             | 1  |
      | tool_tenant_userlimitenabled | 1  |
      | userlimit                    | 5  |
      | tool_tenant_userlimit        | 40 |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "[data-tabs-element='addbutton']" "css_element" should be visible
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I follow "New user"
    And I should see "User accounts limit reached"
    And I click on "OK" "button" in the ".modal-dialog" "css_element"
    And I log out

  Scenario Outline: Unsuspend user when site limit is reached
    Given the following config values are set as admin:
      | userlimitenabled | <enabled> |
      | userlimit        | <limit>   |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I press "Suspend user" action in the "User 11" report row
    And I press "Suspend user"
    And I press "Unsuspend user" action in the "User 11" report row
    And I press "Unsuspend user"
    And I should see "<result>"
    And I log out
    Examples:
      | enabled | limit | result                        |
      | 1       | 3     | User accounts limit reached   |
      | 1       | 10    | User unsuspended successfully |
      | 0       | 1     | User unsuspended successfully |

  Scenario Outline: Unsuspend user when tenant user limit is reached
    Given the following config values are set as admin:
      | tool_tenant_userlimitenabled | <enabled> |
      | tool_tenant_userlimit        | <limit>   |
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I press "Suspend user" action in the "User 11" report row
    And I press "Suspend user"
    And I press "Unsuspend user" action in the "User 11" report row
    And I press "Unsuspend user"
    And I should see "<result>"
    And I log out
    Examples:
      | enabled | limit | result                        |
      | 1       | 3     | User accounts limit reached   |
      | 1       | 6     | User unsuspended successfully |
      | 0       | 1     | User unsuspended successfully |

  Scenario: Switch between user actions
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Add to tenant administrators"
    And I should see "Are you sure you want to add the selected users to the list of tenant administrators?"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    And I set the field "With selected users..." to "Unsuspend user"
    And I should see "Are you sure you want to unsuspend the selected users?"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    And I log out

  Scenario: Filter users by authentication method
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I press "Edit user account" action in the "User 11" report row
    And I set the field "Choose an authentication method" to "OAuth 2"
    And I press "Save"
    And I click on "Filters" "button"
    And I set the following fields in the "Authentication" "core_reportbuilder > Filter" to these values:
      | Authentication operator | Is equal to |
      | Authentication value    | OAuth 2     |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 11" in the "reportbuilder-table" "table"
    And I should not see "User 12" in the "reportbuilder-table" "table"
    And I set the following fields in the "Authentication" "core_reportbuilder > Filter" to these values:
      | Authentication operator | Is equal to |
      | Authentication value    | Manual accounts     |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 11" in the "reportbuilder-table" "table"
    And I log out

  Scenario: Working with users in archived and restored tenants
    Given the following "tool_tenant > tenants" exist:
      | name           |
      | Fake tenant    |
    Given the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                | tenant      |
      | fakeuser | Jake      | Fake     | fake@address.invalid | Fake tenant |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on ".dropdown-toggle" "css_element" in the "Fake tenant" "tool_wp > Table tree node"
    And I choose "Archive" in the open action menu
    And I click on "Yes" "button"
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I should see "Jake Fake"
    And I navigate to "All users" in workplace launcher
    And "Default tenant" "text" should exist in the "Jake Fake" "table_row"
    And I navigate to "All tenants" in workplace launcher
    And I follow "Archived tenants"
    And I click on ".dropdown-toggle" "css_element" in the "Fake tenant" "tool_wp > Table tree node"
    And I choose "Restore" in the open action menu
    And I click on "Yes" "button"
    And I follow "Active tenants"
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I should not see "Jake Fake"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Fake tenant" "link" in the "Fake tenant" "tool_wp > Table tree node"
    And I should see "Jake Fake"
    And I navigate to "All users" in workplace launcher
    And "Fake tenant" "text" should exist in the "Jake Fake" "table_row"

  Scenario: Working with users in deleted tenants
    Given the following "tool_tenant > tenants" exist:
      | name           |
      | Fake tenant    |
    Given the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                | tenant      |
      | fakeuser | Jake      | Fake     | fake@address.invalid | Fake tenant |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on ".dropdown-toggle" "css_element" in the "Fake tenant" "tool_wp > Table tree node"
    And I choose "Archive" in the open action menu
    And I click on "Yes" "button"
    And I follow "Archived tenants"
    And I click on ".dropdown-toggle" "css_element" in the "Fake tenant" "tool_wp > Table tree node"
    And I choose "Delete" in the open action menu
    And I click on "Yes" "button"
    And I follow "Active tenants"
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I should see "Jake Fake"
    And I navigate to "All users" in workplace launcher
    And "Default tenant" "text" should exist in the "Jake Fake" "table_row"
