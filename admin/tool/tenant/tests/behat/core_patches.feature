@tool @tool_tenant @moodleworkplace @javascript
Feature: Test multitenancy core patches
  As an admin
  I want to be able to create, update, archive and delete tenants

  Scenario: Override the site name
    Given "2" tenants exist with "5" users and "3" courses in each
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I navigate to "Details" in current page administration
    And I set the field "Site name" to "Custom site name"
    And I set the field "Site short name" to "Custom site name"
    And I press "Save changes"
    # Check the site name is replaced in multiple places
    And I am on site homepage
    And I should not see "Acceptance test site"
    And I should see "Custom site name"
    And "Custom site name" "text" should exist in the ".navbar" "css_element"
    And "Custom site name" "text" should exist in the ".page-header-headings" "css_element"
    And I follow "Site administration"
    And I should not see "Acceptance test site"
    And "Custom site name" "text" should exist in the ".navbar" "css_element"
    And I follow "Site home settings"
    And the field "Full site name" matches value "Acceptance test site"
    And the field "Short name for site (eg single word)" matches value "Acceptance test site"
    And I set the following administration settings values:
      | Full site name                       | Overridden site fullname  |
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
    Given "2" tenants exist with "5" users and "3" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Courses" in workplace launcher
    And I should not see "Miscellaneous"
    And I should not see "Category 2"
    And I should not see "Course2"
    And I click on course "Course11" in the management interface
    And I follow "Enrolled users"
    And I press "Enrol users"
    And I open the autocomplete suggestions list in the "Enrol users" "dialogue"
    And I should not see "User 2"
    And I should not see "Tenantadmin2"
    And I should not see "Admin user"
    And I click on "User 11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Tenantadmin 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press the escape key
    And I click on "Enrol users" "button" in the "Enrol users" "dialogue"
    Then I should see "Student" in the "User 11" "table_row"
    And I should not see "Tenant user"
    And I should see "Student" in the "Tenantadmin 1" "table_row"
    And I should see "Tenant administrator in course category" in the "Tenantadmin 1" "table_row"
    And I am on the "Course11" "enrolment methods" page
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
    Given "2" tenants exist with "5" users and "3" courses in each
    When I log in as "tenantadmin2"
    And I navigate to "Courses" in workplace launcher
    And I click on "permissions" action for "Category2" in management category listing
    And I select "Assign roles" from the "jump" singleselect
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
    And I click on "permissions" action for "Category2" in management category listing
    And I select "Check permissions" from the "jump" singleselect
    And "optgroup[label='Potential users (5)']" "css_element" should exist in the "#reportuser" "css_element"
    And I should not see "User 1"
    And I set the field "Select a user" to "User 22"
    And I press "Show this user's permissions"
    And I should see "Roles for user User 22"
    And I should see "Permissions for user User 22"
    And I log out

  Scenario: Tenant admin can only assign system roles to the users from the same tenant
    Given "2" tenants exist with "5" users and "3" courses in each
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
    And I log out

  Scenario: Remember tenant after logout in the cookie
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  | siteshortname  |
      | Tenant3 | SITENAME3 | SITENAME3      |
    Given the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                | tenant  |
      | test     | Test      | T        | test@address.invalid | Tenant3 |
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
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  | siteshortname |
      | Tenant4 | SITENAME4 | SITENAME4     |
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

  Scenario: Tenants can have different login URLs
    Given "2" tenants exist with "5" users and "3" courses in each
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  | siteshortname | idnumber | useloginurlid | useloginurlidnumber |
      | Tenant5 | SITENAME5 | SITENAME5     | tenant5  | 1             | 0                   |
      | Tenant6 | SITENAME6 | SITENAME6     | tenant6  | 0             | 1                   |
      | Tenant7 | SITENAME7 | SITENAME7     | tenant7  | 0             | 0                   |
    When I am on homepage for tenant "Tenant5"
    And I should not see "Acceptance test site"
    And I should see "SITENAME5"
    And I am on homepage for tenant "Tenant1"
    When I am on homepage for tenant "Tenant6"
    And I should see "Acceptance test site"
    And I should not see "SITENAME"
    And I am on homepage for tenant "Tenant1"
    When I am on homepage for tenant "tenant6" using idnumber
    And I should not see "Acceptance test site"
    And I should see "SITENAME6"
    And I am on homepage for tenant "Tenant1"
    When I am on homepage for tenant "Tenant7"
    And I should see "Acceptance test site"
    And I should not see "SITENAME"
    And I am on homepage for tenant "Tenant1"
    When I am on homepage for tenant "tenant7" using idnumber
    And I should see "Acceptance test site"
    And I should not see "SITENAME"
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And "/?tenantid=" "text" should exist in the "Tenant5" "tool_wp > Table tree node"
    And "/?tenant=" "text" should not exist in the "Tenant5" "tool_wp > Table tree node"
    And "/?tenantid=" "text" should not exist in the "Tenant6" "tool_wp > Table tree node"
    And "/?tenant=tenant6" "text" should exist in the "Tenant6" "tool_wp > Table tree node"
    And "/?tenantid=" "text" should not exist in the "Tenant7" "tool_wp > Table tree node"
    And "/?tenant=" "text" should not exist in the "Tenant7" "tool_wp > Table tree node"
    And "Not specified" "text" should exist in the "Tenant7" "tool_wp > Table tree node"
    And I log out

  @_file_upload
  Scenario: User inside a tenant can only award badges to users from the same tenant
    Given "2" tenants exist with "5" users and "3" courses in each
    When I log in as "admin"
    And I navigate to "Badges > Add a new badge" in site administration
    And I set the following fields to these values:
      | Name        | Site Badge 1             |
      | Description | Site badge 1 description |
    And I upload "badges/tests/behat/badge.png" file to "Image" filemanager
    And I press "Create badge"
    And I set the field "type" to "Manual issue by role"
    # Be specific, make sure we don't select "Tenant administrator in category" role due to partial matching.
    And I set the field with xpath "//label[normalize-space(.)='Tenant administrator']//input[@type='checkbox']" to "1"
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
    And I select "Recipients (1)" from the "jump" singleselect
    And I should see "User 11"
    And I log out
    And I log in as "tenantadmin2"
    And I navigate to "Badges > Manage badges" in site administration
    And I should see "0" in the "Site Badge 1" "table_row"
    And I follow "Site Badge 1"
    And I should see "This badge has not been earned yet."
    And I select "Recipients (0)" from the "jump" singleselect
    And I should not see "User 11"

  Scenario: Admin can modify tenant roles but can not add capabilities that are not whitelisted to Tenant administrator role
    When I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I follow "Tenant administrator"
    Then "tool_tenant_admin" "text" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Short name')]" "xpath_element"
    And "Tenant administrator" "text" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Custom full name')]" "xpath_element"
    And "None" "text" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Allow role overrides')]" "xpath_element"
    And "None" "text" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Allow role switches')]" "xpath_element"
    And "Allow" "text" should exist in the "moodle/site:configview" "table_row"
    And I press "Edit"
    And "This role can not be assigned manually in any context" "text" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Context types where this role may be assigned')]" "xpath_element"
    And "select" "css_element" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Allow role assignments')]" "xpath_element"
    And "select" "css_element" should not exist in the "//div[contains(@class, 'fitem row') and contains(.,'Allow role overrides')]" "xpath_element"
    And "select" "css_element" should not exist in the "//div[contains(@class, 'fitem row') and contains(.,'Allow role switches')]" "xpath_element"
    And "select" "css_element" should exist in the "//div[contains(@class, 'fitem row') and contains(.,'Allow role to view')]" "xpath_element"
    # Custom full name is actually empty.
    And the field "Custom full name" does not match value "e"
    # Core capability that can be set for this role:
    And I should see "moodle/site:configview"
    # Core capability that can never be set for this role:
    And I should not see "moodle/user:create"
    # Plugin capability that can be set for this role:
    And I should see "tool/organisation:assignjobs"
    And I should see "Capabilities that are not compatible with Multi-tenancy are not listed here"

  Scenario: Admin can not manually assign any tenant roles because these roles can only be assigned automatically when allocating to tenant
    When I log in as "admin"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    Then I should see "Manager"
    And I should see "Course creator"
    And "Manager" "link" should exist in the "admintable" "table"
    And "Course creator" "link" should exist in the "admintable" "table"
    And "Tenant admnistrator" "link" should not exist in the "admintable" "table"
    And "Tenant" "text" should not exist in the "admintable" "table"
    And I go to the courses management page
    And I should see the "Course categories and courses" management page
    And I click on "permissions" action for "Category 1" in management category listing
    And I select "Assign roles" from the "jump" singleselect
    And I should see "Assign roles in Category: Category 1"
    And "Manager" "link" should exist in the "admintable" "table"
    And "Course creator" "link" should exist in the "admintable" "table"
    And "Tenant admnistrator" "link" should not exist in the "admintable" "table"
    And "Tenant" "text" should not exist in the "admintable" "table"
    And I log out

  Scenario: Admin cannot move category associated with tenant
    Given "2" tenants exist with "5" users and "3" courses in each
    Given the following "categories" exist:
      | name      | category | idnumber  |
      | Category3 | 0        | Category3 |
    When I log in as "admin"
    And I go to the courses management page
    And I should see the "Course categories and courses" management page
    And I should see "Category1" in the "#category-listing ul" "css_element"
    And I should see "Category2" in the "#category-listing ul" "css_element"
    And I should see "Category3" in the "#category-listing ul" "css_element"
    And I select category "Category1" in the management interface
    And I set the field "menumovecategoriesto" to "Category3"
    When I press "bulkmovecategories"
    Then I should see "You cannot move category 'Category1' into the selected category."
    And I log out

  Scenario: Limiting user profile categories to tenants
    Given "2" tenants exist with "5" users and "3" courses in each
    Given shared space is enabled
    When I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I click on "Create a new profile field" "link"
    And I click on "Text input" "link"
    And I set the following fields to these values:
      | Short name                    | notvisible_field |
      | Name                          | notvisible_field |
      | Display on signup page?       | Yes              |
      | Who is this field visible to? | Not visible      |
    And I click on "Save changes" "button"
    # Creating categories
    And I click on "Create a new profile category" "button"
    And I set the following fields to these values:
      | Category name                                            | cat1    |
      | This category is available only to the following tenants | 1       |
      | Select tenants                                           | Tenant1 |
    And I press "Save changes"
    And I click on "Create a new profile category" "button"
    And I set the following fields to these values:
      | Category name                                                  | cat2    |
      | This category is available to all tenants except the following | 1       |
    And I set the field "Select tenants" in the "//div[contains(@id, 'fitem_id_excepttenants')]" "xpath_element" to "Tenant1"
    And I press "Save changes"
    And I should see "Tenants: Tenant1" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And I should not see "Tenant2" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And I should see "Tenants: Default tenant, Tenant2" in the "//div[@data-category-id and .//h3[contains(.,'cat2')]]" "xpath_element"
    And I should not see "Shared space" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And I should not see "Tenant1" in the "//div[@data-category-id and .//h3[contains(.,'cat2')]]" "xpath_element"
    # Editing category
    And I click on "Edit" "link" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And the following fields match these values:
      | Category name                                           | cat1    |
      | This category is available only to the following tenants | 1       |
    And I should see "Tenant1" in the ".form-autocomplete-selection" "css_element"
    And I click on "Cancel" "button" in the "Editing category: cat1" "dialogue"
    # Testing delete event listener
    And I click on "Delete" "link" in the "//div[@data-category-id and .//h3[contains(.,'cat1')]]" "xpath_element"
    And I should not see "cat1"
    And I should see "cat2"

  Scenario: Course other users list hides users from other tenants and some multi-tenancy roles
    Given "5" tenants exist with "9" users and "1" courses in each
    When I log in as "admin"
    And I am on "Course11" course homepage
    And I navigate to course participants
    And I select "Other users" from the "jump" singleselect
    Then I should see "Tenantadmin 1"
    And I should not see "Tenantadmin 2"
    And I should not see "Tenant user"
    And I should see "Tenant administrator in course category (Inherited from course category)"
    And I should not see "Tenant administrator (Assigned at site level)"
    # Nobody should see users who have 'tool_tenant_userrole' role in the course category because it is ignored.
    And I should not see "User 11"
    And I should not see "User 21"
    # Now add more users with system and category roles and another tenant
    Given the following "tool_tenant > users" exist:
      | username    | firstname   | lastname | email                  | tenant  | tenantadmin |
      | othertenant | OtherTenant | 1        | ouser0@address.invalid | Tenant1 | 1           |
      | other1      | Otheruser   | 1        | ouser1@address.invalid | Tenant1 | 1           |
      | other2      | Otheruser   | 2        | ouser2@address.invalid | Tenant1 | 0           |
      | other3      | Otheruser   | 3        | ouser3@address.invalid | Tenant1 | 0           |
      | other4      | Otheruser   | 4        | ouser4@address.invalid | Tenant2 | 0           |
      | other5      | Otheruser   | 5        | ouser5@address.invalid | Tenant2 | 0           |
    And the following "role assigns" exist:
      | user   | role                      | contextlevel | reference |
      | other1 | tool_organisation_manager | System       |           |
      | other2 | manager                   | Category     | CAT1      |
      | other3 | manager                   | System       |           |
      | other4 | manager                   | Category     | CAT2      |
      | other5 | manager                   | System       |           |
    And I am on "Course11" course homepage
    And I navigate to course participants
    And I select "Other users" from the "jump" singleselect
    And I change window size to "large"
    # Admin can see all users who have course and category roles, from all tenants.
    And I should see "Tenantadmin 1"
    And I should see "OtherTenant 1"
    And I should see "Otheruser 1"
    And I should see "Otheruser 2"
    And I should see "Otheruser 3"
    And I should see "Otheruser 5"
    # Admin should not see Tenantadmins from other tenants because the 'tool_tenant_adminrole' is ignored.
    And I should not see "Tenantadmin 2"
    # Nobody should see users who have 'tool_tenant_userrole' role in the course category because it is ignored.
    And I should not see "User 11"
    # User 'Otheruser 4' does not have any roles in this category or system contexts.
    And I should not see "Otheruser 4"
    And I log out
    And I log in as "tenantadmin1"
    And I am on "Course11" course homepage
    And I navigate to course participants
    And I select "Other users" from the "jump" singleselect
    # Tenant admin can see all users who have course and category roles, but only from the same tenant.
    And I should see "Tenantadmin 1"
    And I should see "OtherTenant 1"
    And I should see "Otheruser 1"
    And I should see "Otheruser 2"
    And I should see "Otheruser 3"
    # Tenant admin can not see users from other tenants, even if they have system roles
    And I should not see "Tenantadmin 2"
    And I should not see "User 2"
    And I should not see "Otheruser 4"
    And I should not see "Otheruser 5"
    # Nobody should see users who have 'tool_tenant_userrole' role in the course category because it is ignored.
    And I should not see "User 11"

  Scenario: Create custom reports per tenant
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant 1 |
      | Tenant 2 |
    And I log in as "admin"
    And I switch to tenant "Tenant 1"
    When I navigate to "Reports > Report builder > Custom reports" in site administration
    And I click on "New report" "button"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Name                  | Tenant 1 report |
      | Report source         | Users           |
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Close 'Tenant 1 report' editor" "button"
    And I press "View report" action in the "Tenant 1 report" report row
    Then I should see "Tenant 1 report"
    And I am on homepage
    And I switch to tenant "Tenant 2"
    And I navigate to "Reports > Report builder > Custom reports" in site administration
    And I should see "Nothing to display"

  Scenario Outline: View custom reports per tenant
    Given the following "tool_tenant > tenants" exist:
      | name     |
      | Tenant 1 |
      | Tenant 2 |
    And the following "tool_tenant > users" exist:
      | tenant   | username | firstname | lastname |
      | Tenant 1 | user1    | User      | One      |
      | Tenant 2 | user2    | User      | Two      |
    And the following "tool_tenant > reports" exist:
      | tenant   | name     | source                                   |
      | Tenant 1 | Report 1 | core_user\reportbuilder\datasource\users |
      | Tenant 2 | Report 2 | core_user\reportbuilder\datasource\users |
    And the following "core_reportbuilder > Audiences" exist:
      | report   | configdata |
      | Report 1 |            |
      | Report 2 |            |
    When I log in as "<user>"
    And I follow "Reports" in the user menu
    Then I should not see "<othertenantreport>" in the "reportbuilder-table" "table"
    And I click on "<mytenantreport>" "link" in the "reportbuilder-table" "table"
    And I should see "<mytenantreport>"
    Examples:
      | user  | mytenantreport | othertenantreport |
      | user1 | Report 1       | Report 2          |
      | user2 | Report 2       | Report 1          |
