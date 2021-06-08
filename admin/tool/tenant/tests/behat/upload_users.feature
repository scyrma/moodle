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
    And I click on "Manage tenant 'Big company'" "link" in the "Big company" "tool_wp > Table tree node"
    And I should see "Tom Jones"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Manage tenant 'Small company'" "link" in the "Small company" "tool_wp > Table tree node"
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
    And I click on "Manage tenant 'Tenant1'" "link"
    Then I should see "User 13"
    Then I should see "User 21"
    And I should see "User 32"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Manage tenant 'Tenant2'" "link"
    Then I should see "User 11"
    And I should see "User 22"
    And I should see "User 33"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Manage tenant 'Tenant3'" "link"
    Then I should see "User 12"
    And I should see "User 23"
    And I should see "User 31"
