@tool @tool_tenant @_file_upload @javascript @moodleworkplace @tool_uploaduser
Feature: Allocate users to tenants in upload users
  In order to add users to tenants
  As an admin
  I need to upload files containing the users data

  Scenario: Upload users allocating them on tenants
    Given the following "tool_tenant > tenants" exist:
      | name           | idnumber |
      | Big company    | big      |
      | Small company  | small    |
      | Middle company | middle   |
    When I log in as "admin"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/tenant/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I should see "tenant"
    And I should see "small"
    And I should see "big"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Big company" "link" in the "Big company" "tool_wp > Table tree node"
    And I should see "Tom Jones"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Small company" "link" in the "Small company" "tool_wp > Table tree node"
    And I should see "Trent Reznor"

  Scenario: Upload users as tenantadmin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following "custom profile fields" exist:
      | datatype | shortname  | name        |
      | text     | superfield | Super field |
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/tenant/tests/fixtures/upload_users_notenant.csv" file to "File" filemanager
    And I press "Upload users"
    And I should see "Upload users preview"
    And I should not see "tenant1"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > User management" in site administration
    Then I should see "Tom Jones"
    And I should see "Trent Reznor"
    And I follow "Tom Jones"
    And I should see "Superman"
    And I log out
    And I log in as "tenantadmin2"
    And I navigate to "Users > Organisation > User management" in site administration
    Then I should not see "Tom Jones"
    And I should not see "Trent Reznor"
    And I log out

  Scenario: Upload users to change tenant
    Given "3" tenants exist with "4" users and "0" courses in each
    Given the following "users" exist:
      | username        | firstname | lastname  | email                           |
      | tenantallocator | Tenant    | Allocator | tenantallocator@address.invalid |
    And the following "roles" exist:
      | shortname     | name | archetype |
      | tenantallocator | Tenant user allocator | |
    And the following "role assigns" exist:
      | user            | role           | contextlevel | reference |
      | tenantallocator | tenantallocator| System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role            | contextlevel | reference |
      | moodle/site:uploadusers | Allow      | tenantallocator | System       |           |
      | tool/tenant:allocate    | Allow      | tenantallocator | System       |           |
      | moodle/site:configview  | Allow      | tenantallocator | System       |           |
    When I log in as "tenantallocator"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/tenant/tests/fixtures/upload_users_changetenants.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I log out
    And I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    Then I should see "User 13"
    Then I should see "User 21"
    And I should see "User 32"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    Then I should see "User 11"
    And I should see "User 22"
    And I should see "User 33"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant3" "link" in the "Tenant3" "tool_wp > Table tree node"
    Then I should see "User 12"
    And I should see "User 23"
    And I should see "User 31"

  Scenario: Upload users with locked profile field
    Given "1" tenants exist with "1" users and "0" courses in each
    And the following "custom profile fields" exist:
      | datatype | shortname | name    | locked |
      | text     | f0        | PField0 | 0      |
      | text     | f1        | PField1 | 1      |
      | text     | f2        | PField2 | 0      |
      | text     | f3        | PField3 | 1      |
    And the following config values are set as admin:
      | showuseridentity | institution,department,phone1,city,profile_field_f0,profile_field_f1,profile_field_f2,profile_field_f3 |
    And I log in as "tenantadmin1"
    And I navigate to "Users" in workplace launcher
    And I follow "Authentication"
    And I click on "Settings" "link" in the "Manual accounts" "tool_wp > Row"
    And I set the following fields to these values:
      | field_lock_institution_custom | Custom |
      | Lock value (Institution)      | Locked |
      | field_lock_city_custom        | Custom |
      | Lock value (City/town)        | Locked |
    And I press "Save changes"
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    # Contains one user with values for "f0" and "f1" set.
    And I upload "admin/tool/tenant/tests/fixtures/upload_users_locked_profile_fields.csv" file to "File" filemanager
    And I press "Upload users"
    And I click on "Expand all" "link"
    And I click on "Show more..." "link"
    # Make sure that fields that are set in the file, are not shown in the default values.
    And "input[name=profile_field_f0]" "css_element" should not exist
    And "input[name=profile_field_f0]" "css_element" should not exist
    And "input[name=institution]" "css_element" should not exist
    And "input[name=department]" "css_element" should not exist
    # Make sure that the locked fields are not locked for the tenant admin.
    And "input[name=profile_field_f2][disabled]" "css_element" should not exist
    And I set the field "PField2" to "VF2T1"
    And "input[name=profile_field_f3][disabled]" "css_element" should not exist
    And I set the field "PField3" to "VF3T1"
    And "input[name=phone][disabled]" "css_element" should not exist
    And I set the field "Phone" to "PhoneField"
    And "input[name=city][disabled]" "css_element" should not exist
    And I set the field "City/town" to "CityField"
    And I press "Upload users"
    And I press "Continue"
    When I navigate to "Users" in workplace launcher
    # Make sure that the default value for the field was set.
    Then the following should exist in the "reportbuilder-table" table:
      | Full name | Institution      | Department      | Phone      | City/town | PField0 | PField1 | PField2 | PField3 |
      | T1 1      | InstitutionField | DepartmentField | PhoneField | CityField | VF0T1   | VF1T1   | VF2T1   | VF3T1   |
