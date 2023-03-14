@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure generator
  As a developer
  I want to be able to use generator for the organisation structure

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
    And the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
    And the following "role assigns" exist:
      | user  | role                      | contextlevel | reference |
      | user1 | tool_organisation_manager | System       |           |
      | user2 | tool_organisation_manager | System       |           |

  Scenario: Usage of organisation department generator
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
      | Tenant1    | Department111_  | Framework11_   |
      | Tenant1    | Department112_  | Framework11_   |
      | Tenant1    | Department113_  | Framework11_   |
      | Tenant1    | Department1111_ | Department111_ |
      | Tenant1    | Department121_  | Framework12_   |
      | Tenant2    | Framework21_    |                |
      | Tenant2    | Framework22_    |                |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Departments"
    And I should not see "Framework2"
    And I should see "Framework12_"
    And I click on "Expand department framework 'Framework11_'" "button"
    And I should not see "Department12"
    And "Department1111_" "text" should appear after "Department111_" "text"
    And "Department112_" "text" should appear after "Department1111_" "text"
    And "Department113_" "text" should appear after "Department112_" "text"
    And I log out
    And I log in as "user2"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Departments"
    And I should not see "Framework1"
    And "Framework22_" "text" should appear after "Framework21_" "text"
    And I log out

  Scenario: Usage of organisation position generator
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework11_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Framework12_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position111_    | Framework11_   |      1        |       7           |       1           |        7              |
      | Tenant1    | Position112_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant1    | Position113_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant1    | Position1111_   | Position111_   |      1        |       7           |       1           |        3              |
      | Tenant1    | Position121_    | Framework12_   |      1        |       7           |       1           |        7              |
      | Tenant2    | Framework21_    |                |      0        |       0           |       0           |        0              |
      | Tenant2    | Framework22_    |                |      0        |       0           |       0           |        0              |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Positions"
    And I should not see "Framework2"
    And I should see "Framework12_"
    And I click on "Expand position framework 'Framework11_'" "button"
    And I should not see "Position12"
    And "Position1111_" "text" should appear after "Position111_" "text"
    And "Position112_" "text" should appear after "Position1111_" "text"
    And "Position113_" "text" should appear after "Position112_" "text"
    And I log out
    And I log in as "user2"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Positions"
    And I should not see "Framework1"
    And "Framework22_" "text" should appear after "Framework21_" "text"
    And I log out

  Scenario: Usage of organisation jobs generator
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
      | Tenant1    | Department111_  | Framework11_   |
      | Tenant1    | Department112_  | Framework11_   |
      | Tenant1    | Department113_  | Framework11_   |
      | Tenant1    | Department1111_ | Department111_ |
      | Tenant1    | Department121_  | Framework12_   |
      | Tenant2    | Framework21_    |                |
      | Tenant2    | Framework22_    |                |
      | Tenant2    | Department211_  | Framework21_   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework11_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Framework12_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position111_    | Framework11_   |      1        |       7           |       1           |        7              |
      | Tenant1    | Position112_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant1    | Position113_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant1    | Position1111_   | Position111_   |      1        |       7           |       1           |        3              |
      | Tenant1    | Position121_    | Framework12_   |      1        |       7           |       1           |        5              |
      | Tenant2    | Framework21_    |                |      0        |       0           |       0           |        0              |
      | Tenant2    | Framework22_    |                |      0        |       0           |       0           |        0              |
      | Tenant2    | Position211_    | Framework21_   |      1        |       7           |       1           |        7              |
    And the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | user1 | Department111_ | Position112_ |
      | user2 | Department211_ | Position211_ |
    And I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Job assignments"
    And "Position112_" "text" should exist in the "User 1" "table_row"
    And I should not see "User 2"
    And I log out
    And I log in as "user2"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Job assignments"
    And "Position211_" "text" should exist in the "User 2" "table_row"
    And I should not see "User 1"
    And I log out

  @theme_workplace
  Scenario: Organisation generator to quickly create teams
    Given "2" tenants exist with "7" users and "0" courses in each
    # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user14" has a department lead position over users "user15" with permissions "2"
    And I log in as "user11"
    And I select "My teams" from primary navigation
    And "User 12" "text" should exist in the "region-main" "region"
    And "User 13" "text" should exist in the "region-main" "region"
    And I should not see "User 14"
    And I should not see "User 15"
    And I should not see "User 16"
    And I log out
    # "User 14" is a dept manager, so by default "User 15" isn't shown on the teams tab because their job is assigned in a sub-department.
    And I log in as "user14"
    And I select "My teams" from primary navigation
    And I click on "Filters" "button"
    And I set the following fields in the "Organisation structure" "core_reportbuilder > Filter" to these values:
      | Show everybody reporting to me | 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And "User 15" "text" should exist in the "region-main" "region"
    And "User 11" "text" should not exist in the "region-main" "region"
    And I should not see "User 12"
    And I should not see "User 13"
    And I should not see "User 16"
    And I log out
