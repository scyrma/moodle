@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure jobs management
  As a manager
  I want to be able to modify jobs

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | user1     | User      | 1         | user1@address.invalid |
      | user2     | User      | 2         | user2@address.invalid |
      | user3     | User      | 3         | user3@address.invalid |
      | user11    | User      | 11        | user11@address.invalid |
      | user12    | User      | 12        | user12@address.invalid |
      | user13    | User      | 13        | user13@address.invalid |
      | user14    | User      | 14        | user14@address.invalid |
      | user21    | User      | 21        | user21@address.invalid |
      | user22    | User      | 22        | user22@address.invalid |
      | user23    | User      | 23        | user23@address.invalid |
    And the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following users allocations to tenants exist:
      | user      | tenant  |
      | user1     | Tenant1 |
      | user2     | Tenant2 |
      | user3     | Tenant1 |
      | user11    | Tenant1 |
      | user12    | Tenant1 |
      | user13    | Tenant1 |
      | user14    | Tenant1 |
      | user21    | Tenant2 |
      | user22    | Tenant2 |
      | user23    | Tenant2 |
    And the following "roles" exist:
      | shortname   | name                   | archetype |
      | orgmanager  | Organisation manager   |           |
    And the following "role assigns" exist:
      | user  | role        | contextlevel | reference |
      | user1 | orgmanager  | System       |           |
      | user2 | orgmanager  | System       |           |
      | user3 | tool_organisation_manager | System       |           |
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
      | Tenant1    | Position111_    | Framework11_   |      1        |       7           |       1           |        3              |
      | Tenant1    | Position112_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant1    | Position113_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant1    | Position1111_   | Position111_   |      1        |       7           |       1           |        3              |
      | Tenant1    | Position121_    | Framework12_   |      1        |       7           |       1           |        5              |
      | Tenant2    | Framework21_    |                |      0        |       0           |       0           |        0              |
      | Tenant2    | Framework22_    |                |      0        |       0           |       0           |        0              |
      | Tenant2    | Position211_    | Framework21_   |      1        |       7           |       1           |        7              |
    And the following "permission overrides" exist:
      | capability                   | permission | role       | contextlevel | reference |
      | tool/organisation:assignjobs | Allow      | orgmanager | System       |           |
      | moodle/site:configview       | Allow      | orgmanager | System       |           |

  Scenario: Assign job to users
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Jobs" tab because all other tabs are disabled.
    And I should see "Nothing to display"
    And I follow "New job"
    Then I open the autocomplete suggestions list in the "New job" "dialogue"
    And I should see "User 11" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User 12" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 12" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press the escape key
    And I set the following fields in the "New job" "dialogue" to these values:
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
      | enddate[enabled] | 1               |
      | enddate[year]    | 2030            |
    And I click on "Save" "button" in the "New job" "dialogue"
    Then the following should exist in the "report-table" table:
      | User      | Department       | Position       | Permissions | Start date         | End date          |
      | User 11   | Department111_   | Position111_   |             |  ##today##%d/%m/%y##  | ##today##%d/%m/30## |
      | User 12   | Department111_   | Position111_   |             |  ##today##%d/%m/%y##  | ##today##%d/%m/30## |
    # 'Permissions' column contents are checked now.
    And "Manager" "text" should exist in the "User 11" "table_row"
    And "Department lead" "text" should exist in the "User 11" "table_row"
    And "organisation:receivenotificationsglob" permission should be "enabled" in the "User 11" "table_row"
    And "organisation:viewusersreportglob" permission should be "enabled" in the "User 11" "table_row"
    And "organisation:allocateuserstoprogramcertificationsglob" permission should be "enabled" in the "User 11" "table_row"
    And "organisation:allocateuserstoprogramcertificationsdept" permission should be "enabled" in the "User 11" "table_row"
    And "organisation:viewusersreportdept" permission should be "enabled" in the "User 11" "table_row"
    And "organisation:receivenotificationsdept" permission should be "disabled" in the "User 11" "table_row"
    And I log out

  Scenario: Filter jobs by position
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11,User 12 |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Position" to "Position112_"
    Then I should see "Nothing to display"

  Scenario: Filter jobs by position including subpositions
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I open the autocomplete suggestions list in the "New job" "dialogue"
    And I click on "User 11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 12" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press the escape key
    And I set the following fields in the "New job" "dialogue" to these values:
      | positionid       | Position1111_   |
      | departmentid     | Department111_  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Position" to "Position111_"
    And I set the field "Include subpositions" to "1"
    Then "Position1111_" "text" should exist in the "User 11" "table_row"
    And "Position1111_" "text" should exist in the "User 12" "table_row"

  Scenario: Filter jobs by department
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11,User 12 |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Department" to "Department112_"
    Then I should see "Nothing to display"

  Scenario: Filter jobs by department including subdepartments
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11,User 12 |
      | positionid       | Position111_    |
      | departmentid     | Department1111_  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Department" to "Department111_"
    And I set the field "Include subdepartments" to "1"
    Then "Department1111_" "text" should exist in the "User 11" "table_row"
    And "Department1111_" "text" should exist in the "User 12" "table_row"

  Scenario: Filter show past jobs
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Jobs" tab because all other tabs are disabled.
    And I should see "Nothing to display"
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11         |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
      | startdate[year]  | 2017            |
      | enddate[enabled] | 1               |
      | enddate[year]    | 2018            |
    And I click on "Save" "button" in the "New job" "dialogue"
    Then I should not see "Position111_"
    Then I click on "Show/hide filters sidebar" "button"
    And I set the field "Show past jobs" to "1"
    And "Position111_" "text" should exist in the "User 11" "table_row"

  Scenario: Always show future jobs
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11        |
      | positionid       | Position111_   |
      | departmentid     | Department111_ |
      | Start date       | ##tomorrow##   |
    And I click on "Save" "button" in the "New job" "dialogue"
    Then "Position111_" "text" should exist in the "User 11" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Show past jobs" to "1"
    And "Position111_" "text" should exist in the "User 11" "table_row"

  Scenario: Filter user fullname
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11,User 12 |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Full name value" to "User 11"
    And I press the enter key
    Then I should see "User 11"
    And I should not see "User 12"
    And I set the field "Full name value" to "User 12"
    And I press the enter key
    Then I should see "User 12"
    And I should not see "User 11"
    And I set the field "Full name value" to "User 42"
    And I press the enter key
    Then I should see "Nothing to display"

  Scenario: Edit jobs
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Jobs" tab because all other tabs are disabled.
    And I should see "Nothing to display"
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11         |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
      | startdate[year]  | 2017            |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Edit job" "link" in the "User 11" "table_row"
    And I set the following fields in the "Edit job for 'User 11'" "dialogue" to these values:
      | enddate[enabled]  | 1               |
      | enddate[year]     | 2018            |
    And I click on "Save" "button" in the "Edit job for 'User 11'" "dialogue"
    Then I should not see "Position111_"
    Then I click on "Show/hide filters sidebar" "button"
    And I set the field "Show past jobs" to "1"
    And "Position111_" "text" should exist in the "User 11" "table_row"

  Scenario: Assign additional job to user
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11         |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "New job" "link" in the "User 11" "table_row"
    And I set the following fields in the "New job for 'User 11'" "dialogue" to these values:
      | positionid       | Position112_    |
      | departmentid     | Department111_  |
    And I click on "Save" "button" in the "New job for 'User 11'" "dialogue"
    And I should see "Position111_"
    And I should see "Position112_"
    And I log out

  Scenario: Delete jobs
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Jobs" tab because all other tabs are disabled.
    And I should see "Nothing to display"
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11         |
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
      | startdate[year]  | 2017            |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I click on "Edit job" "link" in the "User 11" "table_row"
    And I click on "Delete" "button" in the "Edit job for 'User 11'" "dialogue"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    And I click on "Cancel" "button" in the "Edit job for 'User 11'" "dialogue"
    Then "Position111_" "text" should exist in the "User 11" "table_row"
    And I click on "Edit job" "link" in the "User 11" "table_row"
    And I wait "2" seconds
    And I click on "Delete" "button" in the "Edit job for 'User 11'" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Position111_"

  Scenario: Cannot delete departments and positions with jobs assigned
    Given the following departments exist in organisation structure:
      | tenant     | name           | parent     |
      | Tenant1    | Dep Framework1 |            |
      | Tenant1    | Department1    | Dep Framework1 |
      | Tenant1    | Department2    | Dep Framework1 |
    And the following positions exist in organisation structure:
      | tenant     | name           | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Pos Framework1 |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position1      | Pos Framework1 |      1        |       7           |       1           |        3              |
      | Tenant1    | Position2      | Pos Framework1 |      1        |       7           |       1           |        3              |
    When I log in as "user3"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users | User 1      |
      | positionid   | Position1   |
      | departmentid | Department1 |
    And I click on "Save" "button" in the "New job" "dialogue"
    And I follow "Departments"
    And I click on "Expand department framework 'Dep Framework1'" "button"
    And "Delete department 'Department2'" "link" should exist
    And "Delete department 'Department1'" "link" should not exist
    And I follow "Positions"
    And I click on "Expand position framework 'Pos Framework1'" "button"
    And "Delete position 'Position2'" "link" should exist
    And "Delete position 'Position1'" "link" should not exist
    And I log out

  Scenario: Jobs from deleted users in Moodle should not appear in the jobs list
    Given the following job assignments exist in organisation structure:
      | user   | department     | position      |
      | user1  | Department111_ | Position111_  |
      | user11 | Department111_ | Position1111_ |
      | user12 | Department111_ | Position1111_ |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    And I should see "User 11"
    And I should see "User 12"
    And I log out
    Then I log in as "admin"
    And I navigate to "Users > Accounts > Bulk user actions" in site administration
    And the "Available" select box should contain "User 11"
    And I set the field "Available" to "User 11"
    And I press "Add to selection"
    And I set the field "id_action" to "Delete"
    And I press "Go"
    And I press "Yes"
    And I should see "Changes saved"
    And I press "Continue"
    And I log out
    Then I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    And I should not see "User 11"
    And I should see "User 12"
    And I log out

  Scenario: Jobs from users who moved tenant should be labelled when viewed as tenantmanager
    Given the following job assignments exist in organisation structure:
      | user   | department     | position      |
      | user1  | Department111_ | Position111_  |
      | user11 | Department111_ | Position1111_ |
      | user12 | Department111_ | Position1111_ |
    And the following "roles" exist:
      | shortname         | name            | archetype |
      | tenantusermanager | Tenants manager |           |
    And the following "role assigns" exist:
      | user   | role             | contextlevel | reference |
      | user1  | tenantusermanager | System       |           |
    And the following "permission overrides" exist:
      | capability           | permission | role              | contextlevel | reference |
      | tool/tenant:manage   | Allow      | tenantusermanager | System       |           |
      | tool/tenant:allocate | Allow      | tenantusermanager | System       |           |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    And I should see "User 11"
    And "Job tenant does not match user tenant" "icon" should not exist in the "User 11" "table_row"
    And I should see "User 12"
    And "Job tenant does not match user tenant" "icon" should not exist in the "User 12" "table_row"
    # Move user to other tenant
    Then I navigate to "All users" in workplace launcher
    And the following should exist in the "report-table" table:
      | Full name | Tenant  |
      | User 11   | Tenant1 |
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    And I should see "1 user(s) moved to tenant: Tenant2"
    And the following should exist in the "report-table" table:
      | Full name | Tenant  |
      | User 11   | Tenant2 |
    # Check moved user is labelled
    Then I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    Then the following should exist in the "report-table" table:
      | User      | Department       | Position        | Permissions | Start date                | End date          |
      | User 11   | Department111_   | Position1111_   |             |  ##yesterday##%d/%m/%y##  | ##today##%d/%m/%y##  |
      | User 12   | Department111_   | Position1111_   |             |  ##yesterday##%d/%m/%y##  |                   |
    And "Job tenant does not match user tenant" "icon" should exist in the "User 11" "table_row"
    And "Job tenant does not match user tenant" "icon" should not exist in the "User 12" "table_row"
    And I log out

  Scenario: Jobs from users who moved tenant should not be listed when viewed as orgmanager
    Given the following job assignments exist in organisation structure:
      | user   | department     | position      |
      | user1  | Department111_ | Position111_  |
      | user11 | Department111_ | Position1111_ |
      | user12 | Department111_ | Position1111_ |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    And I should see "User 11"
    And "Job tenant does not match user tenant" "icon" should not exist in the "User 11" "table_row"
    And I should see "User 12"
    And "Job tenant does not match user tenant" "icon" should not exist in the "User 12" "table_row"
    And I log out
    Then I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And "Tenant1" "text" should exist in the "User 11" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    And I should see "1 user(s) moved to tenant: Tenant2"
    And "Tenant2" "text" should exist in the "User 11" "table_row"
    And I log out
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    And I should not see "User 11"
    And I should see "User 12"
    And "Job tenant does not match user tenant" "icon" should not exist in the "User 12" "table_row"
    And I log out
