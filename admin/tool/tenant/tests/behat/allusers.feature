@tool @tool_tenant @moodleworkplace @javascript
Feature: Manage all users
  As a global admin or tenant admin
  I want to be able to manage all users visible to me.

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each

  Scenario: See tenant name in user form
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    And I expand all fieldsets
    And I should see "Tenant1" in the ".form-control-static" "css_element"
    And I click on "Cancel" "button" in the "Edit user 'User 11'" "dialogue"
    And I log out

  Scenario: As an admin I can suspend and unsuspend single user
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I click on "Suspend user" "link" in the "User 11" "table_row"
    And I press "Suspend user"
    And I click on "Unsuspend user" "link" in the "User 11" "table_row"
    And I press "Unsuspend user"
    Then "Suspend user" "link" should exist in the "User 11" "table_row"
    # Add test to suspend and unsuspend using the modal form
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Suspended account | 1 |
    And I press "Save"
    Then "Unsuspend user" "link" should exist in the "User 11" "table_row"
    And I log out

  Scenario: As an admin I can delete single user
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I click on "Delete user" "link" in the "User 11" "table_row"
    And I click on "Delete user" "button" in the "Confirm" "dialogue"
    Then I should see "1 user(s) deleted"
    And I should not see "User 11" in the "report-table" "table"

  Scenario: As a admin I can suspend and unsuspend many users in bulk
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
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

  Scenario: As a admin I can delete many users in bulk
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "Select user 'User 13'" to "1"
    And I set the field "With selected users..." to "Delete users"
    And I click on "Delete users" "button" in the "Confirm" "dialogue"
    Then I should see "2 user(s) deleted"
    And I should not see "User 11" in the "report-table" "table"
    And I should see "User 12" in the "report-table" "table"
    And I should not see "User 13" in the "report-table" "table"
    And I log out

  Scenario: As a site admin I can see filters in users in list
    Given the following config values are set as admin:
      | showuseridentity | username,email |
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    Then I should see "Username" in the "report-table" "table"
    And I should see "Email address" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Username" in the "[data-region='report-filters']" "css_element"
    And I should see "Email address" in the "[data-region='report-filters']" "css_element"
    When the following config values are set as admin:
      | showuseridentity | username |
    And I navigate to "All users" in workplace launcher
    Then I should see "Username" in the "report-table" "table"
    And I should not see "Email address" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Username" in the "[data-region='report-filters']" "css_element"
    And I should not see "Email address" in the "[data-region='report-filters']" "css_element"
    And I log out

  Scenario: Update authentication method for user
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
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
      | Surname     | User                         |
      | Description | Description for this profile |
    And I press "Save"
    And I click on "Edit user account" "link" in the "OAuth User" "table_row"
    And the "username" "field" should be disabled
    And the "New password" "field" should be disabled
    And the field "Choose an authentication method" matches value "OAuth 2"
    And I click on "Cancel" "button" in the "Edit user 'OAuth User'" "dialogue"
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
    And I navigate to "All users" in workplace launcher
    And I click on "Confirm user" "link" in the "User One" "table_row"
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
    And I navigate to "All users" in workplace launcher
    # Single
    And "Resend email to user" "link" should not exist in the "User Four" "table_row"
    And I click on "Resend email to user" "link" in the "User Two" "table_row"
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
    And I navigate to "All users" in workplace launcher
    And I follow "User 12"
    Then I should see "Tenant" in the ".profile_tree" "css_element"
    And I should see "Tenant1" in the "//h3[text() = 'User details']/following-sibling::ul/li[contains(@class, 'contentnode')]/dl/dt[text() = 'Tenant']/following-sibling::dd[1]" "xpath_element"
    And I log out

  Scenario: Viewing all users as a user who can edit users but can not add new ones
    And the following "roles" exist:
      | shortname      | name      | archetype |
      | usereditorrole | Test role |           |
    And the following "role assigns" exist:
      | user   | role           | contextlevel | reference |
      | user11 | usereditorrole | System       |           |
    And the following "permission overrides" exist:
      | capability         | permission | role           | contextlevel | reference |
      | moodle/user:update | Allow      | usereditorrole | System       |           |
      | tool/tenant:manage | Allow      | usereditorrole | System       |           |
    When I log in as "user11"
    And I change window size to "large"
    And I navigate to "All users" in workplace launcher
    And I should not see "New user"
    And I click on "Edit user account" "link" in the "User 22" "table_row"
    And I should see "Tenant2" in the ".form-control-static" "css_element"
    And I press "Save"
    And I log out

  Scenario: As a site admin I can assign and unassign tenant admin role to many users in bulk
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
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
    And I set the field "Select user 'User 12'" to "1"
    And I set the field "With selected users..." to "Add to tenant administrators"
    And I press "Add to tenant administrators"
    And I should see "1 user(s) skipped as they are already tenant administrators"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Remove from tenant administrators"
    And I press "Remove from tenant administrators"
    And I should see "user(s) skipped as they are not tenant administrators"
    And I log out

  Scenario: As a site admin I can move users between tenants
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    Then "Tenant1" "text" should exist in the "User 11" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Add to tenant administrators"
    And I press "Add to tenant administrators"
    Then "Tenant administrator" "text" should exist in the "User 11" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    And I should see "1 user(s) moved to tenant: Tenant2"
    And "Tenant2" "text" should exist in the "User 11" "table_row"
    Then "Tenant administrator" "text" should not exist in the "User 11" "table_row"
    And I log out

  Scenario Outline: As a site admin I can filter users by tenant
    When I log in as "admin"
    And I navigate to "Users > Accounts > All users" in site administration
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Tenant" in the "[data-region='report-filters']" "css_element"
    And I set the field "Tenant field limiter" to "is equal to"
    And I set the field "Tenant value" to "<tenant>"
    Then "<tenant>" "text" should exist in the "<user>" "table_row"
    And I should not see "<userothertenant>" in the "report-table" "table"
    And I log out
    Examples:
      | tenant  | user    | userothertenant |
      | Tenant1 | User 11 | User 22         |
      | Tenant2 | User 22 | User 11         |

  Scenario: Unsuspend user when site limit is reached
    Given the following config values are set as admin:
      | userlimitenabled | 1 |
      | userlimit        | 2 |
    When I log in as "admin"
    And I navigate to "Users > Accounts > All users" in site administration
    And I click on "Suspend user" "link" in the "User 11" "table_row"
    And I press "Suspend user"
    And I click on "Unsuspend user" "link" in the "User 11" "table_row"
    And I press "Unsuspend user"
    And I should see "User accounts limit reached"
    And I log out

  Scenario: Switch between user actions
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    Then "Tenant1" "text" should exist in the "User 11" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Add to tenant administrators"
    And I should see "Are you sure you want to add the selected users to the list of tenant administrators?"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    And I set the field "With selected users..." to "Unsuspend user"
    And I should see "Are you sure you want to unsuspend the selected users?"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    And I log out

  Scenario: As a site admin I can filter users by custom profile fields
    When I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Text input"
    And I set the following fields to these values:
      | Short name                    | Salutation          |
      | Name                          | Salutation          |
      | Display on signup page?       | Yes                 |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    And I navigate to "Users > Accounts > All users" in site administration
    And I click on "Edit" "link" in the "User 11" "table_row"
    And I set the following fields in the "Edit user 'User 11'" "dialogue" to these values:
      | Salutation | Mister. |
    And I press "Save"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Salutation" in the "[data-region='report-filters']" "css_element"
    And I set the field "Salutation field limiter" to "contains"
    And I set the field "Salutation value" to "Mister"
    And I press the enter key
    And I should not see "User 12" in the "report-table" "table"
    And I log out

  Scenario: As a site admin I can filter users by their authentication methods
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I click on "Edit" "link" in the "User 11" "table_row"
    And I set the field "Choose an authentication method" to "OAuth 2"
    And I press "Save"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Authentication" in the "[data-region='report-filters']" "css_element"
    And I set the field "Authentication method field limiter" to "is equal to"
    And I set the field "Authentication method value" to "OAuth 2"
    And I should see "User 11" in the "report-table" "table"
    And I should not see "User 12" in the "report-table" "table"
    And I set the field "Authentication method field limiter" to "is equal to"
    And I set the field "Authentication method value" to "Manual accounts"
    And I should see "User 12" in the "report-table" "table"
    And I should not see "User 11" in the "report-table" "table"
    And I log out

  Scenario: View all users list in shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I press "Enable Shared space"
    And I should see "You have switched to 'Shared space'"
    And I navigate to "All users" in workplace launcher
    # Check we have at least one user from each tenant, plus ourselves (admin).
    Then the following should exist in the "report-table" table:
      | Full name  | Tenant         |
      | Admin User | Default tenant |
      | User 11    | Tenant1        |
      | User 21    | Tenant2        |
    # And we can edit each of them.
    And "Edit user account" "link" should exist in the "Admin User" "table_row"
    And "Edit user account" "link" should exist in the "User 11" "table_row"
    And "Edit user account" "link" should exist in the "User 21" "table_row"

  Scenario Outline: Perform bulk actions from the all users list in shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I press "Enable Shared space"
    And I should see "You have switched to 'Shared space'"
    And I navigate to "All users" in workplace launcher
    # Select a user from each tenant in order to perform a bulk action on them.
    And I click on "Select user 'User 11'" "checkbox" in the "report-table" "table"
    And I click on "Select user 'User 21'" "checkbox" in the "report-table" "table"
    And I set the field "With selected users..." to "<action>"
    And I click on "<action>" "button" in the "Confirm" "dialogue"
    Then I should see "<message>"
    Examples:
      | action        | message             |
      | Suspend users | 2 user(s) suspended |
      | Delete users  | 2 user(s) deleted   |

  Scenario: Create user with site limit enabled
    Given the following config values are set as admin:
      | userlimitenabled | 1 |
      | userlimit        | 2 |
    When I log in as "admin"
    And I navigate to "Users > Accounts > All users" in site administration
    And I follow "New user"
    And I should see "User accounts limit reached"
    And I click on "OK" "button" in the ".modal-dialog" "css_element"
    And I log out

  Scenario: Create user with user tenant limit enabled
    Given the following config values are set as admin:
      | userlimitenabled             | 1 |
      | tool_tenant_userlimitenabled | 1 |
      | tool_tenant_userlimit        | 2 |
    When I log in as "admin"
    And I switch to tenant "Tenant2"
    And I navigate to "Users > Accounts > All users" in site administration
    And I follow "New user"
    And I should see "User accounts limit reached"
    And I click on "OK" "button" in the ".modal-dialog" "css_element"
    And I log out
