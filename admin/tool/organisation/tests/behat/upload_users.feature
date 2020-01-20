@tool @tool_organisation @_file_upload @javascript @moodleworkplace @tool_uploaduser
Feature: Allocate users on jobs in upload users
  In order to allocate users on jobs
  As an admin
  I need to upload files containing the users data

  Scenario: Upload users allocating them on jobs as global admin
    Given the following tenants exist:
      | name    |
      | Default tenant |
    And the following users allocations to tenants exist:
      | user  | tenant  |
      | admin | Default tenant |
    And the following departments exist in organisation structure:
      | tenant     | name         | parent      | idnumber    |
      | Default tenant    | Framework1_  |             |             |
      | Default tenant    | Department1_ | Framework1_ | exampledep  |
      | Default tenant    | Department2_ | Framework1_ | exampledep2 |
    And the following positions exist in organisation structure:
      | tenant     | name        | parent      | globalmanager | globalpermissions | departmentmanager | departmentpermissions | idnumber    |
      | Default tenant    | Framework1_ |             |      0        |       0           |       0           |        0              |             |
      | Default tenant    | Position1_  | Framework1_ |      1        |       7           |       1           |        3              | examplepos  |
      | Default tenant    | Position2_  | Framework1_ |      1        |       7           |       1           |        5              | examplepos2 |
    When I log in as "admin"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I should see "jobdepartment1"
    And I should see "jobposition1"
    And I should see "jobstartdate1"
    And I should see "jobenddate1"
    And I should see "exampledep"
    And I should see "exampledep2"
    And I should see "examplepos"
    And I should see "examplepos2"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Position1_" "text" should exist in the "Tom Jones" "table_row"
    And "Position2_" "text" should exist in the "Trent Reznor" "table_row"
    And "Position2_" "text" should exist in the "Jon Whatever" "table_row"

  Scenario: Upload users allocating them on jobs as tenantadmin
    Given "2" tenants exist with "0" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name         | parent      | idnumber    |
      | Tenant1 | Framework1_  |             |             |
      | Tenant1 | Department1_ | Framework1_ | exampledep  |
      | Tenant1 | Department2_ | Framework1_ | exampledep2 |
    And the following positions exist in organisation structure:
      | tenant  | name        | parent      | globalmanager | globalpermissions | departmentmanager | departmentpermissions | idnumber    |
      | Tenant1 | Framework1_ |             |      0        |       0           |       0           |        0              |             |
      | Tenant1 | Position1_  | Framework1_ |      1        |       7           |       1           |        3              | examplepos  |
      | Tenant1 | Position2_  | Framework1_ |      1        |       7           |       1           |        5              | examplepos2 |
    When I log in as "tenantadmin1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Position1_" "text" should exist in the "Tom Jones" "table_row"
    And "Position2_" "text" should exist in the "Trent Reznor" "table_row"
    And "Position2_" "text" should exist in the "Jon Whatever" "table_row"

  Scenario: Upload users updating jobs
    Given "1" tenants exist with "4" users and "0" courses in each
    And the following "users" exist:
      | username    | firstname | lastname  | email                           |
      | jobassigner | Job       | Assigner | jobassigner@address.invalid |
    And the following users allocations to tenants exist:
      | user        | tenant  |
      | jobassigner | Tenant1 |
    And the following "roles" exist:
      | shortname   | name         | archetype |
      | jobassigner | Job assigner | |
    And the following "role assigns" exist:
      | user        | role           | contextlevel | reference |
      | jobassigner | jobassigner| System       |           |
    And the following departments exist in organisation structure:
      | tenant  | name         | parent      | idnumber    |
      | Tenant1 | Framework1_  |             |             |
      | Tenant1 | Department1_ | Framework1_ | exampledep  |
      | Tenant1 | Department2_ | Framework1_ | exampledep2 |
    And the following positions exist in organisation structure:
      | tenant  | name        | parent      | globalmanager | globalpermissions | departmentmanager | departmentpermissions | idnumber    |
      | Tenant1 | Framework1_ |             |      0        |       0           |       0           |        0              |             |
      | Tenant1 | Position1_  | Framework1_ |      1        |       7           |       1           |        3              | examplepos  |
      | Tenant1 | Position2_  | Framework1_ |      1        |       7           |       1           |        5              | examplepos2 |
    And the following job assignments exist in organisation structure:
      | user   | department     | position   |
      | user11 | Department1_   | Position1_ |
    And the following "permission overrides" exist:
      | capability                   | permission | role        | contextlevel | reference |
      | tool/organisation:assignjobs | Allow      | jobassigner | System       |           |
      | moodle/site:configview       | Allow      | jobassigner | System       |           |
      | moodle/site:uploadusers      | Allow      | jobassigner | System       |           |
    When I log in as "jobassigner"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users_update.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Position1_" "text" should exist in the "User 11" "table_row"
    And "12/05/19" "text" should exist in the "User 11" "table_row"
    And "Position1_" "text" should exist in the "User 12" "table_row"
    And "14/05/19" "text" should exist in the "User 12" "table_row"
    And "9/01/32" "text" should exist in the "User 12" "table_row"
    And "Position2_" "text" should exist in the "User 13" "table_row"
    And "12/05/19" "text" should exist in the "User 13" "table_row"
    And "9/01/32" "text" should exist in the "User 13" "table_row"
