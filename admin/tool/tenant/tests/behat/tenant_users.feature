@tool @tool_tenant @moodleworkplace @javascript
Feature: Manage tenant users
  As a global admin or tenant admin
  I want to be able to manage users ina a tenant.

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following "roles" exist:
      | shortname      | name             | archetype |
      | tenantoverseer | Tenants overseer |           |
    And the following "role assigns" exist:
      | user   | role           | contextlevel | reference |
      | user11 | tenantoverseer | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Tenants overseer" role:
      | capability              | permission |
      | tool/tenant:allocate    | Allow      |
      | moodle/site:configview  | Allow      |
    And I log out

  Scenario: As a tenant admin I can create and edit users directly in the tenant.
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And "[data-tabs-element='addbutton']" "css_element" should be visible
    And I follow "New user"
    And I set the following visible fields to these values:
      | Username | user3 |
      | Email address | user3@address.invalid |
    And I set the following fields to these values:
      | New password | user3isGreat! |
      | First name | User |
      | Surname | 3 |
      | Description | Description for this profile |
    And I press "Save" in the modal form dialogue
    Then I should see "User 3" in the "region-main" "region"
    And I click on "Edit user account" "link" in the "User 3" "table_row"
    And the field with xpath "//div[@class='modal-content']//input[@name='username']" matches value "user3"
    And the field with xpath "//div[@class='modal-content']//input[@name='firstname']" matches value "User"
    And the field with xpath "//div[@class='modal-content']//input[@name='lastname']" matches value "3"
    And the field with xpath "//div[@class='modal-content']//input[@name='email']" matches value "user3@address.invalid"
    And the field with xpath "//div[@class='modal-content']//textarea[@name='description_editor[text]']" matches value "Description for this profile"
    And I set the following fields to these values:
      | Surname | Three |
      | Description | New description for the profile |
    And I press "Save" in the modal form dialogue
    And I click on "Edit user account" "link" in the "User Three" "table_row"
    And the field with xpath "//div[@class='modal-content']//input[@name='lastname']" matches value "Three"
    And the field with xpath "//div[@class='modal-content']//textarea[@name='description_editor[text]']" matches value "New description for the profile"

  Scenario: As a tenant facilitator (can only allocate users) I can not create or edit new users.
    When I log in as "user11"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And "[data-tabs-element='addbutton']" "css_element" should not be visible

  Scenario: As a tenant admin I can edit my own account directly in the tenant.
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And I click on "Edit user account" "link" in the "Tenantadmin 1" "table_row"
    And I set the following fields to these values:
      | New password | Abra_cadab4a |
      | First name   | John |
    And I press "Save" in the modal form dialogue
    And I log out
    And I am on site homepage
    When I follow "Log in"
    And I set the field "Username" to "tenantadmin1"
    And I set the field "Password" to "Abra_cadab4a"
    And I press "Log in"
    Then I should see "You are logged in as John" in the "page-footer" "region"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And I click on "Edit user account" "link" in the "John 1" "table_row"
    And the field "First name" matches value "John"
    And I press "Cancel" in the modal form dialogue
    And I log out

  Scenario: As a tenant admin I can edit theme settings
    When I log in as "tenantadmin1"
    And I navigate to "Appearance > Tenant personalisation" in site administration
    And I set the field "Links" to "#FF"
    And I press "Save changes"
    And I should see "This colour code is not in the right format"
    And I set the field "Links" to "#FF0000"
    And I press "Save changes"
    # No way to check in behat that color actually changed, so just make sure there are no errors
    And I am on site homepage
    And I navigate to "Appearance > Tenant personalisation" in site administration
    Then the field "Links" matches value "#FF0000"
    And I log out
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Manage tenant 'Tenant1'"
    And I follow "Appearance"
    Then the field "Links" matches value "#FF0000"
    And I press "Save changes"
    And I should see "Theme settings were saved"
    And I navigate to "Appearance > Tenant personalisation" in site administration
    And I should see "Default tenant"
    And I should see "Appearance"
    Then the field "Links" does not match value "#FF0000"
    And I log out

  Scenario: Change tenant category
    Given I log in as "admin"
    And I set the following system permissions of "Authenticated user" role:
      | moodle/category:viewcourselist | Prevent |
    And I log out
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
    And I click on "Edit tenant 'Tenant1'" "link" in the "Tenant1" table tree node
    And I set the field "Category" to "Category3"
    And I press "Save" in the modal form dialogue
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
    And I should see "Tenantadmin 1" in the "Tenant manager" "table_row"
    And I follow "Manager"
    And I should see "Assign role 'Manager' in System"
    And I navigate to "Users" in workplace launcher
    And I follow "Roles"
    And I follow "Course creator"
    And I should see "Assign role 'Course creator' in Category: Category1"
    And I log out

  Scenario: Global admin can view and assign roles for each tenant from users page
    When I log in as "admin"
    And I navigate to "Tenants" in workplace launcher
    Then "4" "text" should exist in the "Tenant1" table tree node
    And I click on "Tenant1" "link" in the "Tenant1" table tree node
    And I follow "Roles"
    And I should see "Tenantadmin 1" in the "Tenant manager" "table_row"
    And I should see "Tenantadmin 1" in the "Tenant administrator" "table_row"
    And I should not see "Tenantadmin 2"
    And I should see "User 11" in the "Tenants overseer" "table_row"
    And I navigate to "Tenants" in workplace launcher
    And I click on "Tenant2" "link" in the "Tenant2" table tree node
    And I follow "Roles"
    And I should see "Tenantadmin 2" in the "Tenant manager" "table_row"
    And I should see "Tenantadmin 2" in the "Tenant administrator" "table_row"
    And I should not see "Tenantadmin 1"
    And I should not see "User 11"
    And I log out

  Scenario: Editing theme settings without capability to browse tenant users
    Given the following "roles" exist:
      | shortname      | name             | archetype |
      | tenantthemer   | Tenants themer   |           |
    And the following "role assigns" exist:
      | user   | role         | contextlevel | reference |
      | user12 | tenantthemer | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Tenants themer" role:
      | capability              | permission |
      | tool/tenant:managetheme | Allow      |
      | moodle/site:configview  | Allow      |
    And I log out
    When I log in as "user12"
    And I navigate to "Appearance > Tenant personalisation" in site administration
    And I set the field "Links" to "#FF"
    And I press "Save changes"
    And I should see "This colour code is not in the right format"
    And I set the field "Links" to "#FF0000"
    And I press "Save changes"
    # No way to check in behat that color actually changed, so just make sure there are no errors
    And I am on site homepage
    And I navigate to "Appearance > Tenant personalisation" in site administration
    Then the field "Links" matches value "#FF0000"
    And I log out
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Manage tenant 'Tenant1'"
    And I follow "Appearance"
    Then the field "Links" matches value "#FF0000"
    And I press "Save changes"
    And I should see "Theme settings were saved"
    And I navigate to "Appearance > Tenant personalisation" in site administration
    And I should see "Default tenant"
    And I should see "Appearance"
    Then the field "Links" does not match value "#FF0000"
    And I log out

  Scenario: As a tenant admin I can suspend and unsuspend single user
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And I click on "Suspend user" "link" in the "User 11" "table_row"
    And I press "Suspend user"
    And I click on "Unsuspend user" "link" in the "User 11" "table_row"
    And I press "Unsuspend user"
    Then "Suspend user" "link" should exist in the "User 11" "table_row"

  Scenario: As a tenant admin I can delete single user
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And I click on "Delete user" "link" in the "User 11" "table_row"
    And I press "Delete user"
    Then I should not see "User 11"

  Scenario: As a site admin I can assign and unassign tenant admin role to single user by editing user account
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Manage tenant 'Tenant1'"
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Administrator | 1 |
    And I press "Save" in the modal form dialogue
    Then "Tenant administrator" "text" should exist in the "User 11" "table_row"
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Administrator | 0 |
    And I press "Save" in the modal form dialogue
    Then "Tenant administrator" "text" should not exist in the "User 11" "table_row"

  Scenario: As a tenant admin I can suspend and unsuspend many users in bulk
    When I log in as "tenantadmin1"
    And I navigate to "Users > Organisation > Browse list of users" in site administration
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
    And I navigate to "Users > Organisation > Browse list of users" in site administration
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Delete users"
    And I press "Delete users"
    Then I should not see "User 11"
    And I should see "User 12"
    And I should not see "User 13"

  Scenario: As a site admin I can assign and unassign tenant admin role to many users in bulk
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Manage tenant 'Tenant1'"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 12'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Assign Tenant administrator role"
    And I press "Assign Tenant administrator role"
    Then "Tenant administrator" "text" should exist in the "User 11" "table_row"
    And "Tenant administrator" "text" should exist in the "User 12" "table_row"
    And "Tenant administrator" "text" should exist in the "User 13" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Unassign Tenant administrator role"
    And I press "Unassign Tenant administrator role"
    Then "Tenant administrator" "text" should not exist in the "User 11" "table_row"
    And "Tenant administrator" "text" should exist in the "User 12" "table_row"
    And "Tenant administrator" "text" should not exist in the "User 13" "table_row"
