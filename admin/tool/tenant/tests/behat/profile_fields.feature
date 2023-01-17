@tool @tool_tenant @moodleworkplace @javascript
Feature: Viewing and editing users with different profile fields per tenant
  As a global admin or tenant admin
  I want to be able to manage users inside a specific tenant and sitewide

  Background:
    Given "3" tenants exist with "4" users and "0" courses in each
    And the following "roles" exist:
      | shortname      | name             | archetype |
      | tenantoverseer | Tenants overseer |           |
    And the following "custom profile field categories" exist:
      | name |
      | cat0 |
      | cat1 |
      | cat2 |
    And the following "custom profile fields" exist:
      | datatype | shortname | name    | param2 | category | locked |
      | text     | f0        | PField0 | 100    | cat0     | 0      |
      | text     | f1        | PField1 | 100    | cat1     | 0      |
      | text     | f2        | PField2 | 100    | cat2     | 0      |
      | text     | f3        | PField3 | 100    | cat1     | 1      |
    And profile category "cat1" is available only for tenants "Tenant1"
    And profile category "cat2" is available only for tenants "Tenant2"
    And the following config values are set as admin:
      | showuseridentity | country,profile_field_f0,profile_field_f1,profile_field_f2,profile_field_f3 |

  Scenario: User profile fields in the core users list visible according to the current tenant
    Given shared space is enabled
    When I log in as "admin"
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "PField0"
    And I should not see "PField1"
    And I should not see "PField2"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I should see "PField0"
    And I should see "PField1"
    And I should not see "PField2"
    And I switch to tenant "Shared space"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I should see "PField0"
    And I should see "PField1"
    And I should see "PField2"

  Scenario: All user profile fields are visible in the workplace users list regardless of current tenant
    Given shared space is enabled
    When I log in as "admin"
    When I navigate to "All users" in workplace launcher
    Then I should see "PField0"
    And I should see "PField1"
    And I should see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should exist
    And I switch to tenant "Tenant1"
    And I navigate to "All users" in workplace launcher
    And I should see "PField0"
    And I should see "PField1"
    And I should see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should exist
    And I switch to tenant "Shared space"
    And I navigate to "All users" in workplace launcher
    And I should see "PField0"
    And I should see "PField1"
    And I should see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should exist

  Scenario: Profile fields for respective tenant are visible in each tenant user list
    When I log in as "admin"
    When I navigate to "All tenants" in workplace launcher
    And I click on "Default tenant" "link" in the "Default tenant" "tool_wp > Table tree node"
    Then I should see "PField0"
    And I should not see "PField1"
    And I should not see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should not exist
    And "PField2" "field" should not exist
    When I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I should see "PField0"
    And I should see "PField1"
    And I should not see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should not exist
    When I navigate to "All tenants" in workplace launcher
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    And I should see "PField0"
    And I should not see "PField1"
    And I should see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should not exist
    And "PField2" "field" should exist

  Scenario: Profile fields for the current tenant are shown in the own tenant users list
    When I log in as "admin"
    When I navigate to "Users" in workplace launcher
    Then I should see "PField0"
    And I should not see "PField1"
    And I should not see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should not exist
    And "PField2" "field" should not exist
    And I log out
    When I log in as "tenantadmin1"
    When I navigate to "Users" in workplace launcher
    Then I should see "PField0"
    And I should see "PField1"
    And I should not see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should not exist
    And I log out
    When I log in as "tenantadmin2"
    When I navigate to "Users" in workplace launcher
    Then I should see "PField0"
    And I should not see "PField1"
    And I should see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should not exist
    And "PField2" "field" should exist

  Scenario: Profile fields visibility in user profiles
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                 | tenant  | profile_field_f0 | profile_field_f1 | profile_field_f2 |
      | t1       | T1        | 1        | user1@address.invalid | Tenant1 | VF0T1            | VF1T1            | VF2T1            |
      | t2       | T2        | 2        | user2@address.invalid | Tenant2 | VF0T2            | VF1T2            | VF2T2            |
      | t3       | T3        | 3        | user3@address.invalid | Tenant3 | VF0T3            | VF1T3            | VF2T3            |
    When I log in as "admin"
    When I navigate to "All users" in workplace launcher
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 | PField2 |
      | T1 1      | VF0T1   | VF1T1   |         |
      | T2 2      | VF0T2   |         | VF2T2   |
      | T3 3      | VF0T3   |         |         |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name | PField1 | PField2 |
      | T1 1      | -       | VF2T1   |
      | T2 2      | VF1T2   | -       |
      | T3 3      | VF1T3   | VF2T3   |
    And I follow "T1 1"
    And I should see "VF0T1"
    And I should see "VF1T1"
    And I should not see "VF2T1"
    And I follow "Edit profile"
    And I expand all fieldsets
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should not exist

  Scenario: User profile fields when creating and editing user in own tenant
    When I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "New user"
    And I expand all fieldsets
    And I set the following fields in the "New user" "dialogue" to these values:
      | Username                          | t1                    |
      | Generate password and notify user | 1                     |
      | Email address                     | user3@address.invalid |
      | First name                        | T1                    |
      | Last name                           | 1                     |
      | PField0                           | VF0T1                 |
      | PField1                           | VF1T1                 |
    And I should not see "PField2"
    And I press "Save"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 |
      | T1 1      | VF0T1   | VF1T1   |
    And I press "Edit user account" action in the "T1 1" report row
    And I expand all fieldsets
    And the following fields in the "Edit user 'T1 1'" "dialogue" match these values:
      | PField0 | VF0T1 |
      | PField1 | VF1T1 |
    And I should not see "PField2"
    And I set the following fields in the "Edit user 'T1 1'" "dialogue" to these values:
      | PField1 | NEWF1T1 |
    And I press "Save"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 |
      | T1 1      | VF0T1   | NEWF1T1 |

  Scenario: User profile fields when creating and editing user in another tenant
    When I log in as "admin"
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I follow "New user"
    And I expand all fieldsets
    And I set the following fields in the "New user" "dialogue" to these values:
      | Username                          | t1                    |
      | Generate password and notify user | 1                     |
      | Email address                     | user3@address.invalid |
      | First name                        | T1                    |
      | Last name                           | 1                     |
      | PField0                           | VF0T1                 |
      | PField1                           | VF1T1                 |
    And I should not see "PField2"
    And I press "Save"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 |
      | T1 1      | VF0T1   | VF1T1   |
    And I press "Edit user account" action in the "T1 1" report row
    And I expand all fieldsets
    And the following fields in the "Edit user 'T1 1'" "dialogue" match these values:
      | PField0 | VF0T1 |
      | PField1 | VF1T1 |
    And I should not see "PField2"
    And I set the following fields in the "Edit user 'T1 1'" "dialogue" to these values:
      | PField1 | NEWF1T1 |
    And I press "Save"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 |
      | T1 1      | VF0T1   | NEWF1T1 |

  @_file_upload
  Scenario: Upload users with profile fields as tenant admin
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/tenant/tests/fixtures/upload_users_profile_fields1.csv" file to "File" filemanager
    And I press "Upload users"
    And I press "Upload users"
    And I press "Continue"
    When I navigate to "Users" in workplace launcher
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 |
      | T1 1      | VF0T1   | VF1T1   |

  @_file_upload
  Scenario: Upload users with different tenants and different profile fields
    When I log in as "admin"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/tenant/tests/fixtures/upload_users_profile_fields2.csv" file to "File" filemanager
    And I press "Upload users"
    And I press "Upload users"
    And I press "Continue"
    When I navigate to "All users" in workplace launcher
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 | PField2 |
      | T1 1      | VF0T1   | VF1T1   |         |
      | T2 2      | VF0T2   |         | VF2T2   |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name | PField1 | PField2 |
      | T1 1      | -       | VF2T1   |
      | T2 2      | VF1T2   | -       |

  Scenario: Profile fields in custom reports inside a tenant
    When I log in as "tenantadmin1"
    And I navigate to "Custom reports" in workplace launcher
    And I click on "New report" "button"
    And I set the field "Name" in the "New report" "dialogue" to "TenantReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I should see "PField0" in the ".reportbuilder-sidebar-menu #card_user" "css_element"
    And I should see "PField1" in the ".reportbuilder-sidebar-menu #card_user" "css_element"
    And I should not see "PField2" in the ".reportbuilder-sidebar-menu #card_user" "css_element"
    And I click on "Show/hide 'Conditions'" "button"
    And the "Select a condition" select box should contain "PField0"
    And the "Select a condition" select box should contain "PField1"
    And the "Select a condition" select box should not contain "PField2"
    And I click on "Show/hide 'Filters'" "button"
    And the "Select a filter" select box should contain "PField0"
    And the "Select a filter" select box should contain "PField1"
    And the "Select a filter" select box should not contain "PField2"

  Scenario: Profile fields in custom reports in shared space
    Given shared space is enabled
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                 | tenant  | profile_field_f0 | profile_field_f1 | profile_field_f2 |
      | t1       | T1        | 1        | user1@address.invalid | Tenant1 | VF0T1            | VF1T1            | VF2T1            |
      | t2       | T2        | 2        | user2@address.invalid | Tenant2 | VF0T2            | VF1T2            | VF2T2            |
      | t3       | T3        | 3        | user3@address.invalid | Tenant3 | VF0T3            | VF1T3            | VF2T3            |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I click on "New report" "button"
    And I set the field "Name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Add column 'PField0'" "link"
    And I click on "Add column 'PField1'" "link"
    And I click on "Add column 'PField2'" "link"
    And I click on "Show/hide 'Conditions'" "button"
    And the "Select a condition" select box should contain "PField0"
    And the "Select a condition" select box should contain "PField1"
    And the "Select a condition" select box should contain "PField2"
    And I click on "Show/hide 'Filters'" "button"
    And I set the field "Select a filter" to "PField0"
    And I set the field "Select a filter" to "PField1"
    And I set the field "Select a filter" to "PField2"
    And I click on "Switch to preview mode" "button"
    And the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 | PField2 |
      | T1 1      | VF0T1   | VF1T1   |         |
      | T2 2      | VF0T2   |         | VF2T2   |
      | T3 3      | VF0T3   |         |         |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name | PField1 | PField2 |
      | T1 1      | -       | VF2T1   |
      | T2 2      | VF1T2   | -       |
      | T3 3      | VF1T3   | VF2T3   |
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should exist
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Custom reports" in workplace launcher
    And I follow "SharedReport"
    And the following should exist in the "reportbuilder-table" table:
      | Full name | PField0 | PField1 |
      | T1 1      | VF0T1   | VF1T1   |
    And I should not see "PField2"
    And I click on "Filters" "button"
    And "PField0" "field" should exist
    And "PField1" "field" should exist
    And "PField2" "field" should not exist

  Scenario: Capabilities to edit locked profile fields
    Given the following "roles" exist:
      | shortname               | name                | archetype |
      | tool_tenant_user_update | Update users        |           |
      | tool_tenant_user_create | Create users        |           |
      | tool_tenant_user_manage | Manage tenant users |           |
    # Give different capabilities to each of the roles.
    And the following "permission overrides" exist:
      | capability              | permission | role                    | contextlevel | reference |
      | moodle/user:update      | Allow      | tool_tenant_user_update | System       |           |
      | moodle/user:create      | Prevent    | tool_tenant_user_update | System       |           |
      | tool/tenant:manageusers | Prevent    | tool_tenant_user_update | System       |           |
      | moodle/user:update      | Prevent    | tool_tenant_user_create | System       |           |
      | moodle/user:create      | Allow      | tool_tenant_user_create | System       |           |
      | tool/tenant:manageusers | Prevent    | tool_tenant_user_create | System       |           |
      | moodle/user:update      | Prevent    | tool_tenant_user_manage | System       |           |
      | moodle/user:create      | Prevent    | tool_tenant_user_manage | System       |           |
      | tool/tenant:manageusers | Allow      | tool_tenant_user_manage | System       |           |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email               | tenantadmin |
      | Tenant1 | tuser1   | Test      | User1    | tuser1@example.com | 0           |
      | Tenant1 | tuser2   | Test      | User2    | tuser2@example.com | 0           |
      | Tenant1 | tuser3   | Test      | User3    | tuser3@example.com | 0           |
    # Assign one of the roles to each tenant admin user.
    And the following "role assigns" exist:
      | user   | role                    | contextlevel | reference |
      | tuser1 | tool_tenant_user_update | System       |           |
      | tuser2 | tool_tenant_user_create | System       |           |
      | tuser3 | tool_tenant_user_manage | System       |           |
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    # Lock the Last name field.
    And I follow "Authentication"
    And I click on "Settings" "link" in the "Manual accounts" "tool_wp > Row"
    And I set the following fields to these values:
      | field_lock_lastname_custom | Custom |
      | Lock value (Last name)       | Locked |
    And I press "Save changes"
    # Tenant admin by default CAN edit locked fields and CAN set them when creating a new user.
    When I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I press "Edit user account" action in the "User 11" report row
    Then "input[name=lastname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And I click on "Cancel" "button" in the "Edit user 'User 11'" "dialogue"
    And I follow "New user"
    And "input[name=lastname][disabled]" "css_element" should not exist in the "New user" "dialogue"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist in the "New user" "dialogue"
    # User with "update" capability CAN edit locked fields.
    When I log in as "tuser1"
    And I navigate to "Users" in workplace launcher
    And I press "Edit user account" action in the "User 11" report row
    Then "input[name=lastname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And I click on "Cancel" "button" in the "Edit user 'User 11'" "dialogue"
    # User with "create" capability CAN NOT edit locked fields but CAN set them when creating a new user.
    Then I log in as "tuser2"
    And I navigate to "Users" in workplace launcher
    And I follow "New user"
    And "input[name=lastname][disabled]" "css_element" should not exist in the "New user" "dialogue"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist in the "New user" "dialogue"
    # User with "manageusers" capability CAN edit locked fields and CAN set them when creating a new user.
    Then I log in as "tuser3"
    And I navigate to "Users" in workplace launcher
    And I press "Edit user account" action in the "User 11" report row
    Then "input[name=lastname][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist in the "Edit user 'User 11'" "dialogue"
    And I click on "Cancel" "button" in the "Edit user 'User 11'" "dialogue"
    And I follow "New user"
    And "input[name=lastname][disabled]" "css_element" should not exist in the "New user" "dialogue"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist in the "New user" "dialogue"
