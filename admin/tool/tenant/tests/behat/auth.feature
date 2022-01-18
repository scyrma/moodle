@tool @tool_tenant @moodleworkplace @javascript @tool_tenant_auth
Feature: Test authentication in mutli-tenancy
  As an admin
  I want to be able to configure user authentication

  Scenario: When new user signs up and the account is created in the correct tenant
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  |
      | Tenant1 | SITENAME1 |
      | Tenant2 | Wrong tenant |
    And the following config values are set as admin:
      | registerauth    | email |
      | passwordpolicy  | 0     |
    When I am on homepage
    And I should see "Acceptance test site"
    And I am on homepage for tenant "Tenant1"
    And I should see "SITENAME1"
    And I should not see "Acceptance test site"
    And I follow "Log in"
    And I follow "Create new account"
    And I set the following fields to these values:
      | Username      | user1                 |
      | Password      | user1                 |
      | Email address | user1@address.invalid |
      | Email (again) | user1@address.invalid |
      | First name    | User1                 |
      | Surname       | L1                    |
    And I press "Create my new account"
    Then I should see "Confirm your account"
    And I should see "SITENAME1"
    And I am on homepage for tenant "Tenant2"
    And I should not see "SITENAME1"
    And I should see "Wrong tenant"
    And I confirm email for "user1"
    And I should see "SITENAME1"
    And I should see "Thanks, User1 L1"
    And I should see "Your registration has been confirmed"
    And I open my profile in edit mode
    And the field "First name" matches value "User1"
    And I log out
    # Confirm that user can login and browse the site (edit their profile).
    And I log in as "user1"
    And I should see "SITENAME1"
    And I open my profile in edit mode
    And the field "First name" matches value "User1"

  Scenario: Site admin can allow tenants to enable and disable optional auth plugins
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename     | useloginurlid |
      | Tenant1 | SITENAME1    | 1             |
      | Tenant2 | Wrong tenant | 1             |
    Given the following "tool_tenant > users" exist:
      | username     | firstname | lastname | email                | tenant  | tenantadmin |
      | tenantadmin1 | Test      | T        | test@address.invalid | Tenant1 | 1           |
    When I log in as "admin"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I should not see "Multi-tenant" in the "External database" "table_row"
    And I should see "Multi-tenant" in the "Email-based self-registration" "table_row"
    And I should see "Multi-tenant" in the "OAuth 2" "table_row"
    And I click on "Edit status" "link" in the "External database" "table_row"
    And I set the field "New status for External database" to "Enabled"
    And I click on "Edit status" "link" in the "Email-based self-registration" "table_row"
    And I set the field "New status for Email-based self-registration" to "Disabled, available"
    And I click on "Edit status" "link" in the "OAuth 2" "table_row"
    And I set the field "New status for OAuth 2" to "Enabled, optional"
    And I click on "Move up" "link" in the "OAuth 2" "table_row"
    And "OAuth 2" "text" should appear before "External database" "text"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I click on "Enable" "link" in the "Email-based self-registration" "tool_wp > Row"
    And I follow "Common settings"
    And I should see "Self registration"
    And I click on "Override" "text" in the "Self registration" "form_row"
    And I set the field "Self registration" to "Email-based self-registration"
    And I press "Save changes"
    And I log out
    # The "Create new account" link is only visible in Tenant1 and not visible in Default tenant and Tenant2.
    And I am on homepage for tenant "Default tenant"
    And I follow "Log in"
    Then I should not see "Create new account"
    And I am on homepage for tenant "Tenant1"
    And I follow "Log in"
    And I should see "Create new account"
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    And I should not see "Create new account"

  Scenario: Some tenants may disable self-registration plugin
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  |
      | Tenant1 | SITENAME1 |
      | Tenant2 | Wrong tenant |
    And the following config values are set as admin:
      | registerauth    | email |
      | passwordpolicy  | 0     |
    When I log in as "admin"
    And I change window size to "large"
    # Enable self-registration plugin for all tenants except Default tenant.
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Edit status" "link" in the "Email-based self-registration" "table_row"
    And I set the field "New status for Email-based self-registration" to "Enabled, optional"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I click on "Disable" "link" in the "Email-based self-registration" "tool_wp > Row"
    And I log out
    # Default tenant does not have self-registration link on the login page but other tenant does.
    And I am on homepage for tenant "Default tenant"
    And I follow "Log in"
    Then I should not see "Create new account"
    And I am on homepage for tenant "Tenant1"
    And I follow "Log in"
    And I should see "Create new account"
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    And I should see "Create new account"

  Scenario: Site admin can allow and prevent tenants from overriding common settings
    Given "2" tenants exist with "5" users and "0" courses in each
    When I log in as "admin"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Force for all tenants" "text" in the "Self registration" "tool_wp > Row"
    And I press "Save changes"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I follow "Common settings"
    And I should not see "Self registration"
    And I should see "Prevent account creation when authenticating"
    And I press "Save changes"

  Scenario: Tenant administrator can change settings in the multi-tenant auth plugins
    Given the following "tool_tenant > tenants" exist:
      | name    |
      | Tenant1 |
    Given the following "tool_tenant > users" exist:
      | username     | firstname | lastname | email                  | tenant  | tenantadmin | auth   | city  |
      | tenantadmin1 | Test      | T        | test@address.invalid   | Tenant1 | 1           | manual |       |
      | user11       | User      | 11       | user11@address.invalid | Tenant1 | 0           | email  |       |
      | user12       | User      | 12       | user12@address.invalid | Tenant1 | 0           | manual |       |
      | user13       | User      | 13       | user13@address.invalid | Tenant1 | 0           | email  | Perth |
    When I log in as "admin"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Settings" "link" in the "Email-based self-registration" "table_row"
    And I set the field "Lock value (Surname)" to "Unlocked if empty"
    And I set the field "Lock value (Email address)" to "Locked"
    And I click on "Force for all tenants" "text" in the "Lock value (Email address)" "tool_wp > Row"
    And I press "Save changes"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I click on "Settings" "link" in the "Email-based self-registration" "tool_wp > Row"
    And the following fields in the "Email-based self-registration. Settings" "dialogue" match these values:
      | field_lock_firstname_custom | Site default (Unlocked) |
      | field_lock_lastname_custom | Site default (Unlocked if empty) |
    And I should see "Locked" in the "Lock value (Email address)" "tool_wp > Row"
    And "select" "css_element" should not exist in the "Lock value (Email address)" "tool_wp > Row"
    And I set the following fields to these values:
      | field_lock_firstname_custom | Custom            |
      | Lock value (First name)     | Unlocked if empty |
      | field_lock_city_custom      | Custom            |
      | Lock value (City/town)      | Unlocked if empty |
    And I press "Save changes"
    And I click on "Users" "tool_wp > Tab"
    And I change window size to "large"
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    # Can not use the step 'the "..." "field" should be disabled' because it targets the report filter instead
    And "input[name=firstname][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=lastname][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=email][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And I set the field "City/town" in the "Edit user 'User 11'" "dialogue" to "Somewhere"
    And I press "Save"
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    And "input[name=firstname][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=lastname][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=email][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=city][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And the following fields in the "Edit user 'User 11'" "dialogue" match these values:
      | First name    | User                   |
      | Surname       | 11                     |
      | Email address | user11@address.invalid |
      | City/town     | Somewhere              |
    And I press "Save"

  Scenario: When admin edits another user the auth settings are set for the user's tenant
    Given the following "tool_tenant > tenants" exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                  | tenant  | auth   | city |
      | user11   | User      | 11       | user11@address.invalid | Tenant1 | oauth2 |      |
      | user21   | User      | 21       | user21@address.invalid | Tenant2 | oauth2 |      |
    When I log in as "admin"
    And I change window size to "large"
    # Enable the OAuth 2 plugin for the Tenant1 only.
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Edit status" "link" in the "OAuth 2" "table_row"
    And I set the field "New status for OAuth 2" to "Disabled, available"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I click on "Enable" "link" in the "OAuth 2" "tool_wp > Row"
    And I navigate to "All users" in workplace launcher
    And I follow "User 11"
    And I follow "Edit profile"
    And "//select[@name='auth']//optgroup[@label='Enabled']/option[.='OAuth 2']" "xpath_element" should exist
    And I navigate to "All users" in workplace launcher
    And I follow "User 21"
    And I follow "Edit profile"
    And "//select[@name='auth']//optgroup[@label='Enabled']/option[.='OAuth 2']" "xpath_element" should not exist
    And "//select[@name='auth']//optgroup[@label='Disabled']/option[.='OAuth 2']" "xpath_element" should exist

  Scenario: When manager edits another user's profile the auth settings are set for the user's tenant
    Given the following config values are set as admin:
      | auth | email,oauth2 |
    Given the following "tool_tenant > tenants" exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                   | tenant         | auth   | city |
      | user11   | User      | 11       | user11@address.invalid  | Tenant1        | oauth2 |      |
      | user21   | User      | 21       | user21@address.invalid  | Tenant2        | oauth2 |      |
      | manager  | Manager   | M        | manager@address.invalid | Default tenant | manual |      |
    And the following "role assigns" exist:
      | user    | role    | contextlevel | reference |
      | manager | manager | System       |           |
    And the following "permission overrides" exist:
      | capability         | permission | role    | contextlevel | reference |
      | moodle/user:update | Prevent    | manager | System       |           |
      | tool/tenant:manage | Allow      | manager | System       |           |
    When I log in as "admin"
    # Lock the fields City and first name in the OAuth 2 auth method for the Tenant1 only
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I click on "Settings" "link" in the "OAuth 2" "tool_wp > Row"
    And I set the following fields to these values:
      | field_lock_city_custom      | Custom            |
      | Lock value (City/town)      | Unlocked if empty |
      | field_lock_firstname_custom | Custom            |
      | Lock value (First name)     | Unlocked if empty |
    And I press "Save changes"
    And I log out
    And I log in as "manager"
    # Login as manager who does not have capability to update users but has cap to edit profiles.
    And I navigate to "All users" in workplace launcher
    And I follow "User 11"
    And I follow "Edit profile"
    And the "First name" "field" should be disabled
    And I navigate to "All users" in workplace launcher
    And I follow "User 21"
    And I follow "Edit profile"
    And the "First name" "field" should be enabled

  Scenario: Capability to config authentication can be removed from Tenant administrator role
    Given "2" tenants exist with "5" users and "0" courses in each
    And the following "permission overrides" exist:
      | capability             | permission | role              | contextlevel | reference |
      | tool/tenant:authconfig | Prevent    | tool_tenant_admin | System       |           |
    When I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    Then "Users" "tool_wp > Tab" should exist
    And "Authentication" "tool_wp > Tab" should not exist
    And I log out
    And I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I should see "Common settings"
    And I should see "Email-based self-registration"

  Scenario: Oauth2 sign up with tenant allocation
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  |
      | Tenant1 | SITENAME1 |
      | Tenant2 | Wrong tenant |
    Given the following "tool_tenant > users" exist:
      | username     | firstname | lastname | email                | tenant  | tenantadmin |
      | tenantadmin1 | Test      | T        | test@address.invalid | Tenant1 | 1           |
    And the test OAuth2 issuer with the following properties exists:
      | name                | Moodle test OAuth2 |
      | requireconfirmation | 0                  |
    # Enable oauth2 for everybody except Tenant2.
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Edit status" "link" in the "OAuth 2" "table_row"
    And I set the field "New status for OAuth 2" to "Enabled, optional"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I click on "Disable" "link" in the "OAuth 2" "tool_wp > Row"
    And I log out
    # Guest can see oauth login button only in Default tenant and Tenant1, when they log in from Tenant1 login page they are allocated to Tenant1.
    And I am on homepage for tenant "Default tenant"
    And I follow "Log in"
    And I should see "Moodle test OAuth2"
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    And I should not see "Moodle test OAuth2"
    And I am on homepage for tenant "Tenant1"
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test1@example.com"
    And I press "Login"
    And I should see "SITENAME1"
    And I should see "Edit profile"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I should see "test1@example.com"

  Scenario: Oauth2 sign in from a wrong tenant
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  |
      | Tenant1 | SITENAME1 |
      | Tenant2 | SITENAME2 |
    Given the following "tool_tenant > users" exist:
      | username     | firstname | lastname | email                | tenant  | tenantadmin |
      | tenantadmin1 | Test      | T        | test@address.invalid | Tenant1 | 1           |
    And the test OAuth2 issuer with the following properties exists:
      | name                | Moodle test OAuth2 |
      | requireconfirmation | 0                  |
    # Enable oauth2 for everybody.
    When I log in as "admin"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Edit status" "link" in the "OAuth 2" "table_row"
    And I set the field "New status for OAuth 2" to "Enabled, optional"
    And I log out
    # Sign up from Tenant2 login page.
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test2@example.com"
    And I press "Login"
    And I set the field "First name" to "Mary"
    And I set the field "Surname" to "Jones"
    And I press "Update profile"
    And I log out
    # Log in to the same account from Tenant1 login page
    And I am on homepage for tenant "Tenant1"
    And I should see "SITENAME1"
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test2@example.com"
    And I press "Login"
    And I should see "SITENAME2"
    And I log out
    # As admin disable oauth2 for tenant2
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I click on "Disable" "link" in the "OAuth 2" "tool_wp > Row"
    And I log out
    # Try to login from Tenant1 login page.
    # TODO MDL-70255 currently not possible to check for exceptions
#    And I am on homepage for tenant "Tenant1"
#    And I should see "SITENAME1"
#    And I follow "Log in"
#    And I follow "Moodle test OAuth2"
#    And I set the field "Email" to "test2@example.com"
#    And I start ignoring exceptions
#    And I press "Login"
#    And I should see "Sorry, OAuth 2 authentication plugin is not enabled"
#    And I follow "Home"

  Scenario: Confirm OAuth2 account from a wrong tenant
    Given the following config values are set as admin:
      | auth | email,oauth2 |
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  |
      | Tenant1 | SITENAME1 |
      | Tenant2 | SITENAME2 |
    Given the test OAuth2 issuer with the following properties exists:
      | name                | Moodle test OAuth2 |
      | requireconfirmation | 1                  |
    And I am on homepage for tenant "Tenant1"
    And I follow "Log in"
    And I follow "Moodle test OAuth2"
    And I set the field "Email" to "test1@example.com"
    And I press "Login"
    And I should see "SITENAME1"
    And I should see "An email should have been sent to your address at test1@example.com"
    And I press "Continue"
    # Confirm and login
    And I am on homepage for tenant "Tenant2"
    And I confirm OAuth2 email for "test1@example.com"
    And I should see "SITENAME1"
    And I follow "Dashboard"
    And I should see "Edit profile"

  Scenario: Warning message is shown when not all tenants have the same sign up/sign on options.
    Given the following config values are set as admin:
      | auth | email |
    Given the following "tool_tenant > tenants" exist:
      | name    |
      | Tenant1 |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I should not see "Some tenants are using specific authentication methods."
    And I should not see "To offer a consistent experience for mobile app users, it's recommended to change the setting 'typeoflogin'"
    And I follow "Common settings"
    # Test that warning is shown when auth_email is enabled and registerauth is set to email at least for one tenant.
    And I click on "Override" "radio" in the "[data-groupname=\"registerauth_custom_group\"]" "css_element"
    And I select "Email-based self-registration" from the "registerauth" singleselect
    And I press "Save changes"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I should see "Some tenants are using specific authentication methods."
    And I should see "To offer a consistent experience for mobile app users, it's recommended to change the setting 'typeoflogin'"

  Scenario: Warning message is shown when not all tenants have the same sign up/sign on options #2.
    Given the following config values are set as admin:
      | auth | oauth2 |
    Given the following "tool_tenant > tenants" exist:
      | name    |
      | Tenant1 |
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I should see "Some tenants are using specific authentication methods."
    And I should see "To offer a consistent experience for mobile app users, it's recommended to change the setting 'typeoflogin'"
    And I follow "Common settings"
    And I click on "Override" "radio" in the "[data-groupname=\"authpreventaccountcreation_custom_group\"]" "css_element"
    And I click on "Prevent account creation when authenticating" "checkbox"
    And I press "Save changes"
    # Test that warning is shown when oauth2 is enabled and authpreventaccountcreation is false for at least some tenants.
    And I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I follow "Common settings"
    And I click on "Override" "radio" in the "[data-groupname=\"authpreventaccountcreation_custom_group\"]" "css_element"
    And I click on "Prevent account creation when authenticating" "checkbox"
    And I press "Save changes"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I should not see "Some tenants are using specific authentication methods."
    And I should not see "To offer a consistent experience for mobile app users, it's recommended to change the setting 'typeoflogin'"

  Scenario: Tenant administrator can change settings in the multi-tenant manual auth
    Given "1" tenants exist with "3" users and "0" courses in each
    When I log in as "admin"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Settings" "link" in the "Manual accounts" "table_row"
    Then I should not see "Force for all tenants" in the "Enable password expiry" "tool_wp > Row"
    Then I should not see "Force for all tenants" in the "Password duration" "tool_wp > Row"
    Then I should not see "Force for all tenants" in the "Notification threshold" "tool_wp > Row"
    And I set the field "Lock value (First name)" to "Unlocked if empty"
    # Lock Email address for all tenants.
    And I set the field "Lock value (Email address)" to "Locked"
    And I click on "Force for all tenants" "text" in the "Lock value (Email address)" "tool_wp > Row"
    And I press "Save changes"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I click on "Settings" "link" in the "Manual accounts" "tool_wp > Row"
    And the following fields in the "Manual accounts. Settings" "dialogue" match these values:
      | field_lock_firstname_custom | Site default (Unlocked if empty) |
      | field_lock_lastname_custom  | Site default (Unlocked)          |
    Then I should see "Locked" in the "Lock value (Email address)" "tool_wp > Row"
    And "select" "css_element" should not exist in the "Lock value (Email address)" "tool_wp > Row"
    # Lock Last name for Tenant1
    And I set the following fields to these values:
      | field_lock_lastname_custom | Custom |
      | Lock value (Surname)       | Locked |
    And I press "Save changes"
    And I click on "Users" "tool_wp > Tab"
    And I change window size to "large"
    And I click on "Edit user account" "link" in the "User 11" "table_row"
    Then "input[name=firstname][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=email][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=lastname][disabled]" "css_element" should exist in the "Edit user 'User 11'" "dialogue"
    And I click on "Cancel" "button" in the "Edit user 'User 11'" "dialogue"

  Scenario: Create new account with user limit
    Given the following config values are set as admin:
      | userlimitenabled | 1     |
      | userlimit        | 3     |
      | registerauth     | email |
    And I follow "Log in"
    And ".workplacelogin .signup" "css_element" should exist
    Then the following config values are set as admin:
      | userlimitenabled | 1 |
      | userlimit        | 1 |
    And I am on homepage
    And ".workplacelogin .signup" "css_element" should not exist

  Scenario: Self-authenticated users are not logged out when email auth is disabled for the default tenant
    Given the following config values are set as admin:
      | auth           | email |
      | registerauth   | email |
      | passwordpolicy | 0     |
    And "2" tenants exist with "1" users and "0" courses in each
    # Enable email registration for everybody except Default tenant.
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Authentication > Manage authentication" in site administration
    And I click on "Edit status" "link" in the "Email-based self-registration" "table_row"
    And I set the field "New status for Email-based self-registration" to "Enabled, optional"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    And I click on "Authentication" "tool_wp > Tab"
    And I click on "Disable" "link" in the "Email-based self-registration" "tool_wp > Row"
    And I log out
    # Self-register as a user in tenant2
    And I am on homepage for tenant "Tenant2"
    And I follow "Log in"
    And I follow "Create new account"
    And I set the following fields to these values:
      | Username      | user1             |
      | Password      | user1             |
      | Email address | user1@example.com |
      | Email (again) | user1@example.com |
      | First name    | Test              |
      | Surname       | User 1            |
    And I press "Create my new account"
    And I confirm email for "user1"
    # Run session cleanup and make sure the user is still logged in
    And I trigger cron
    And I open my profile in edit mode
    And the field "First name" matches value "Test"

  Scenario Outline: Users with same email created in different tenants can sign in
    Given the following "tool_tenant > tenants" exist:
      | name    | sitename  |
      | Tenant1 | SITENAME1 |
      | Tenant2 | SITENAME2 |
      | Tenant3 | SITENAME3 |
    And the following config values are set as admin:
      | authloginviaemail | 1 |
    And the following "tool_tenant > users" exist:
      | username  | firstname | lastname | email                | tenant  |
      | test1     | Test      | 1        | test1@example.com    | Tenant1 |
      | test2     | Test      | 2        | test1@example.com    | Tenant2 |
      | test3     | Test      | 3        | test1@example.com    | Tenant3 |
      | test4     | Test      | 4        | test4@example.com    | Tenant1 |
    # Log in with the same account email from each tenant login page
    When I am on homepage for tenant "<tenant>"
    Then I should see "<tenantname>"
    And I follow "Log in"
    And I set the field "Username / email" to "<useremail>"
    And I set the field "Password" to "<password>"
    And I press "Log in"
    When I follow "Profile" in the user menu
    Then I should see "<fullname>"
    And I log out
    Examples:
      | tenant  | tenantname  | useremail           | fullname  | password |
      | Tenant1 | SITENAME1   | test1@example.com   | Test 1    | test1    |
      | Tenant2 | SITENAME2   | test1@example.com   | Test 2    | test2    |
      | Tenant3 | SITENAME3   | test1@example.com   | Test 3    | test3    |
      | Tenant1 | SITENAME1   | test4@example.com   | Test 4    | test4    |
      | Tenant3 | SITENAME3   | test4@example.com   | Test 4    | test4    |
