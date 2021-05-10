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

  Scenario: Upload users allocating them on jobs as admin having switched tenant
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
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Position1_" "text" should exist in the "Tom Jones" "table_row"
    And "Position2_" "text" should exist in the "Trent Reznor" "table_row"
    And "Position2_" "text" should exist in the "Jon Whatever" "table_row"

  Scenario: Upload users updating jobs in the same tenant
    Given "1" tenants exist with "5" users and "0" courses in each
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
      | user   | department     | position   | startdate  | enddate    |
      | user11 | Department1_   | Position1_ | 2018-10-10 | 2030-10-10 |
      | user12 | Department1_   | Position1_ | 2018-10-10 | 2030-10-10 |
      | user13 | Department1_   | Position2_ | 2018-10-10 | 2030-10-10 |
      | user14 | Department1_   | Position1_ | 2018-10-10 | 2030-10-10 |
    And the following "permission overrides" exist:
      | capability                   | permission | role        | contextlevel | reference |
      | tool/organisation:assignjobs | Allow      | jobassigner | System       |           |
      | moodle/site:configview       | Allow      | jobassigner | System       |           |
      | moodle/site:uploadusers      | Allow      | jobassigner | System       |           |
    When I log in as "jobassigner"
    And I change window size to "large"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users_update.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Position1_" "text" should exist in the "User 11" "table_row"
    # End date not modified for user11 (2030-10-10), start date is set to 2019-05-12
    And "12/05/19" "text" should exist in the "User 11" "table_row"
    And "10/10/30" "text" should exist in the "User 11" "table_row"
    And "Position1_" "text" should exist in the "User 12" "table_row"
    # Both start and end date are modified for user12
    And "14/05/19" "text" should exist in the "User 12" "table_row"
    And "9/01/32" "text" should exist in the "User 12" "table_row"
    # End date is removed for user13, start date is set to 2019-05-12
    And "Position2_" "text" should exist in the "User 13" "table_row"
    And "12/05/19" "text" should exist in the "User 13" "table_row"
    And "10/10/30" "text" should not exist in the "User 13" "table_row"
    # User14 was not modified.
    And "Position1_" "text" should exist in the "User 14" "table_row"
    And "10/10/18" "text" should exist in the "User 14" "table_row"
    And "10/10/30" "text" should exist in the "User 14" "table_row"
    And I log out

  Scenario: Upload users updating jobs in other tenant as admin in default tenant
    Given "1" tenants exist with "5" users and "0" courses in each
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
      | user   | department     | position   | startdate  | enddate    |
      | user11 | Department1_   | Position1_ | 2018-10-10 | 2030-10-10 |
      | user12 | Department1_   | Position1_ | 2018-10-10 | 2030-10-10 |
      | user13 | Department1_   | Position2_ | 2018-10-10 | 2030-10-10 |
      | user14 | Department1_   | Position1_ | 2018-10-10 | 2030-10-10 |
    # Upload jobs in default tenant
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users_update.csv" file to "File" filemanager
    And I press "Upload users"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I should see "Nothing to display"
    # Jobs are created in tenant1
    And I switch to tenant "Tenant1"
    And "Position1_" "text" should exist in the "User 11" "table_row"
    # End date not modified for user11 (2030-10-10), start date is set to 2019-05-12
    And "12/05/19" "text" should exist in the "User 11" "table_row"
    And "10/10/30" "text" should exist in the "User 11" "table_row"
    And "Position1_" "text" should exist in the "User 12" "table_row"
    # Both start and end date are modified for user12
    And "14/05/19" "text" should exist in the "User 12" "table_row"
    And "9/01/32" "text" should exist in the "User 12" "table_row"
    # End date is removed for user13, start date is set to 2019-05-12
    And "Position2_" "text" should exist in the "User 13" "table_row"
    And "12/05/19" "text" should exist in the "User 13" "table_row"
    And "10/10/30" "text" should not exist in the "User 13" "table_row"
    # User14 was not modified.
    And "Position1_" "text" should exist in the "User 14" "table_row"
    And "10/10/18" "text" should exist in the "User 14" "table_row"
    And "10/10/30" "text" should exist in the "User 14" "table_row"
    And I log out

  Scenario: Upload users allocating them on jobs as global admin using shared departments and positions
    Given shared space is enabled
    And the following departments exist in organisation structure:
      | tenant     | name           | parent        | idnumber          |
      | -          | FrameworkS1_   |               |                   |
      | -          | DepartmentS11_ | FrameworkS1_  | exampleshareddep  |
      | -          | DepartmentS12_ | FrameworkS1_  | exampleshareddep2 |
    And the following positions exist in organisation structure:
      | tenant     | name        | parent       | globalmanager | globalpermissions | departmentmanager | departmentpermissions | idnumber          |
      | -        | FrameworkS1_  |              | 0             | 0                 | 0                 | 0                     |                   |
      | -        | PositionS11_  | FrameworkS1_ | 1             | 0                 | 1                 | 0                     | examplesharedpos  |
      | -        | PositionS12_  | FrameworkS1_ | 0             | 0                 | 0                 | 0                     | examplesharedpos2 |
    When I log in as "admin"
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/organisation/tests/fixtures/upload_users_shared.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I should see "jobdepartment1"
    And I should see "jobposition1"
    And I should see "jobstartdate1"
    And I should see "jobenddate1"
    And I should see "exampleshareddep"
    And I should see "exampleshareddep2"
    And I should see "examplesharedpos"
    And I should see "examplesharedpos2"
    And I press "Upload users"
    And I press "Continue"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "PositionS11_" "text" should exist in the "Tom Jones" "table_row"
    And "PositionS12_" "text" should exist in the "Trent Reznor" "table_row"
    And "PositionS12_" "text" should exist in the "Jon Whatever" "table_row"
    And "DepartmentS11_" "text" should exist in the "Tom Jones" "table_row"
    And "DepartmentS12_" "text" should exist in the "Trent Reznor" "table_row"
    And "DepartmentS12_" "text" should exist in the "Jon Whatever" "table_row"
    And I log out
