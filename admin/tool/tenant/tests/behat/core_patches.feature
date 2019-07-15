@tool @tool_tenant @moodleworkplace @javascript
Feature: Test multitenancy core patches
  As an admin
  I want to be able to create, update, archive and delete tenants

  Background:
    Given "2" tenants exist with "5" users and "3" courses in each

  Scenario: Override the site name
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I follow "Edit tenant 'Default tenant'"
    And I set the field "Site name" to "Custom site name"
    And I press "Save" in the modal form dialogue
    # Check the site name is replaced in multiple places
    And I am on site homepage
    And I should not see "Acceptance test site"
    And I should see "Custom site name"
    And "Custom site name" "text" should exist in the ".navbar" "css_element"
    And "Custom site name" "text" should exist in the ".page-header-headings" "css_element"
    And I follow "Calendar"
    And I should not see "Acceptance test site"
    And "Custom site name" "text" should exist in the ".page-header-headings" "css_element"
    And I follow "Site administration"
    And I should not see "Acceptance test site"
    And "Custom site name" "text" should exist in the ".page-header-headings" "css_element"
    And I follow "Front page settings"
    And the field "Full site name" matches value "Acceptance test site"
    And the field "Short name for site (eg single word)" matches value "Acceptance test site"
    And I set the following administration settings values:
      | Full site name | Overridden site fullname |
      | Short name for site (eg single word) | Overridden site shortname |
    And I am on site homepage
    And I should not see "Acceptance test site"
    And I should not see "Overridden"
    And I should see "Custom site name"
    And I log out
    # Now check as another tenant
    And I log in as "tenantadmin1"
    And I am on site homepage
    And I should not see "Acceptance test site"
    And I should not see "Custom site name"
    And I should see "Overridden site fullname"
    And I log out

  Scenario: User inside a tenant can only enrol users from the same tenant
    When I log in as "tenantadmin1"
    And I navigate to "Courses" in workplace launcher
    And I should not see "Miscellaneous"
    And I should not see "Category 2"
    And I should not see "Course2"
    And I click on course "Course11" in the management interface
    And I follow "Enrolled users"
    And I press "Enrol users"
    And I open the autocomplete suggestions list in the dialog
    And I should not see "User 2"
    And I should not see "Tenantadmin2"
    And I should not see "Admin user"
    And I click on "User 11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Tenantadmin 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select users"
    And I click on "Enrol users" "button" in the "Enrol users" "dialogue"
    Then I should see "Student" in the "User 11" "table_row"
    And I should not see "Tenant user"
    And I should see "Student" in the "Tenantadmin 1" "table_row"
    And I should see "Tenant manager" in the "Tenantadmin 1" "table_row"
    And I navigate to "Users > Enrolment methods" in current page administration
    And I should see "2" in the "Manual enrolments" "table_row"
    And I click on "Enrol users" "link" in the "Manual enrolments" "table_row"
    And "optgroup[label='Not enrolled users (3)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='Enrolled users (2)']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Not enrolled users" select box should contain "User 12 (user12@invalid.com)"
    And the "Not enrolled users" select box should contain "User 13 (user13@invalid.com)"
    And the "Not enrolled users" select box should contain "User 14 (user14@invalid.com)"
    And the "Enrolled users" select box should contain "Tenantadmin 1 (tenantadmin1@invalid.com)"
    And the "Enrolled users" select box should contain "User 11 (user11@invalid.com)"
    And I should not see "User 2"
    And I set the field "Not enrolled users" to "User 12"
    And I press "Add"
    And "optgroup[label='Not enrolled users (2)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='Enrolled users (3)']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Enrolled users" select box should contain "User 12 (user12@invalid.com)"
    And I follow "Participants"
    And I should see "Student" in the "User 12" "table_row"
    And I log out

  Scenario: Tenant admin can only assign category roles to the users from the same tenant
    When I log in as "tenantadmin2"
    And I navigate to "Courses" in workplace launcher
    And I click on "assignroles" action for "Category2" in management category listing
    And I should see "1" in the "Tenant manager" "table_row"
    And I should see "0" in the "Course creator" "table_row"
    And I follow "Course creator"
    And "optgroup[label='Potential users (5)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='None']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Potential users" select box should contain "User 22 (user22@invalid.com)"
    And I should not see "User 1"
    And I set the field "Potential users" to "User 22"
    And I press "Add"
    And "optgroup[label='Potential users (4)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='Users in this Category (1)']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Existing users" select box should contain "User 22 (user22@invalid.com)"
    And I navigate to "Courses" in workplace launcher
    And I click on "checkroles" action for "Category2" in management category listing
    And "optgroup[label='Potential users (5)']" "css_element" should exist in the "#reportuser" "css_element"
    And I should not see "User 1"
    And I set the field "Select a user" to "User 22"
    And I press "Show this user's permissions"
    And I should see "Roles for user User 22"
    And I should see "Permissions for user User 22"
    And I log out

  Scenario: Tenant admin can only assign system roles to the users from the same tenant
    # First allow tenant admin assign some role in system context.
    And I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I follow "Tenant administrator"
    And I follow "Allow role assignments"
    And I set the field "Allow users with role Tenant administrator to assign the role Manager" to "1"
    And I press "Save changes"
    And I log out
    # Now log in as tenant administrator and try to assign this role
    And I log in as "tenantadmin1"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    And I should see "0" in the "Manager" "table_row"
    And I follow "Manager"
    And "optgroup[label='Potential users (5)']" "css_element" should exist in the "#addselect" "css_element"
    And I should not see "User 2"
    And "optgroup[label='None']" "css_element" should exist in the "#removeselect" "css_element"
    And I set the field "Potential users" to "User 11"
    And I press "Add"
    And "optgroup[label='Potential users (4)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='Existing users (1)']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Existing users" select box should contain "User 11 (user11@invalid.com)"
    And the "Assign another role" select box should contain "Manager (1)"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    And I should see "1" in the "Manager" "table_row"
    And I should see "User 11" in the "Manager" "table_row"
    And I log out
    # Make sure another tenant administrator does not see managers assigned in another tenant
    And I log in as "tenantadmin2"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    And I should see "0" in the "Manager" "table_row"
    And I should not see "User 1"
    And I follow "Manager"
    And "optgroup[label='Potential users (5)']" "css_element" should exist in the "#addselect" "css_element"
    And I should not see "User 1"
    And "optgroup[label='None']" "css_element" should exist in the "#removeselect" "css_element"
    And I set the field "Potential users" to "User 21"
    And I press "Add"
    And "optgroup[label='Potential users (4)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='Existing users (1)']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Existing users" select box should contain "User 21 (user21@invalid.com)"
    And the "Assign another role" select box should contain "Manager (1)"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    And I should see "1" in the "Manager" "table_row"
    And I should see "User 21" in the "Manager" "table_row"
    And I log out
    # Admin can see everybody
    And I log in as "admin"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    And I should see "2" in the "Manager" "table_row"
    And I should see "2" in the "Tenant administrator" "table_row"
    And I should see "User 21" in the "Manager" "table_row"
    And I should see "User 11" in the "Manager" "table_row"
    And I follow "Manager"
    And "optgroup[label='Potential users (9)']" "css_element" should exist in the "#addselect" "css_element"
    And "optgroup[label='Existing users (2)']" "css_element" should exist in the "#removeselect" "css_element"
    And the "Existing users" select box should contain "User 21 (user21@invalid.com)"
    And the "Existing users" select box should contain "User 11 (user11@invalid.com)"
    And the "Potential users" select box should contain "User 12 (user12@invalid.com)"
    And the "Potential users" select box should contain "User 22 (user22@invalid.com)"
    And the "Potential users" select box should contain "Admin User (moodle@example.com)"
    And the "Assign another role" select box should contain "Manager (2)"
    And the "Assign another role" select box should contain "Tenant administrator (2)"
    And I log out

  Scenario: Remember tenant after logout in the cookie
    Given the following tenants exist:
      | name    | sitename  |
      | Tenant3 | SITENAME3 |
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | test     | Test      | T        | test@address.invalid |
    And the following users allocations to tenants exist:
      | user     | tenant   |
      | test     | Tenant3  |
    And I am on site homepage
    And I should see "Acceptance test site"
    And I should not see "SITENAME3"
    And I follow "Log in"
    And I should see "Acceptance test site"
    And I should not see "SITENAME3"
    And I log in as "test"
    And I should not see "Acceptance test site"
    And I should see "SITENAME3"
    And I log out
    And I should not see "Acceptance test site"
    And I should see "SITENAME3"
    And I am on site homepage

  Scenario: Set tenant in the URL and remember it in the cookie
    Given the following tenants exist:
      | name    | sitename  |
      | Tenant4 | SITENAME4 |
    When I am on site homepage
    And I should see "Acceptance test site"
    And I should not see "SITENAME4"
    And I am on homepage for tenant "Tenant4"
    And I should not see "Acceptance test site"
    And I should see "SITENAME4"
    When I follow "Log in"
    And I set the field "Username" to "testuser"
    And I set the field "Password" to "unexisting"
    And I press "Log in"
    Then I should see "Invalid login, please try again"
    And I should not see "Acceptance test site"
    And I should see "SITENAME4"

  Scenario: User inside a tenant can only award badges to users from the same tenant
    When I log in as "admin"
    And I navigate to "Badges > Add a new badge" in site administration
    And I set the following fields to these values:
      | Name | Site Badge 1 |
      | Description | Site badge 1 description |
      | issuername | Tester of site badge |
    And I upload "badges/tests/behat/badge.png" file to "Image" filemanager
    And I press "Create badge"
    And I set the field "type" to "Manual issue by role"
    And I set the field "Tenant administrator" to "1"
    And I press "Save"
    And I press "Enable access"
    And I press "Continue"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Badges > Manage badges" in site administration
    And I click on "0" "link" in the "Site Badge 1" "table_row"
    And I press "Award badge"
    Then I should see "Tenantadmin 1"
    And I should see "User 11"
    And I should see "User 12"
    And I should see "User 13"
    And I should see "User 14"
    And I should not see "Tenantadmin 2"
    And I should not see "User 21"
    And I should not see "User 22"
    And I should not see "User 23"
    And I should not see "User 24"
    And I set the field "potentialrecipients[]" to "User 11 (user11@invalid.com)"
    And I press "Award badge"
    And I follow "Site Badge 1"
    And I follow "Recipients (1)"
    And I should see "User 11"
    And I log out
    And I log in as "tenantadmin2"
    And I navigate to "Badges > Manage badges" in site administration
    And I should see "0" in the "Site Badge 1" "table_row"
    And I follow "Site Badge 1"
    And I should see "This badge has not been earned yet."
    And I follow "Recipients (0)"
    And I should not see "User 11"
