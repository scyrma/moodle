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
    And "User 2" "autocomplete_suggestions" should not exist
    And I click on "User 11" item in the autocomplete list
    And I click on "User 12" item in the autocomplete list
    And I press the escape key
    And I set the following fields in the "New job" "dialogue" to these values:
      | positionid       | Position111_    |
      | departmentid     | Department111_  |
      | enddate[enabled] | 1               |
      | enddate[year]    | 2030            |
    And I click on "Save" "button" in the "New job" "dialogue"
    Then the following should exist in the "reportbuilder-table" table:
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
    And I should see "Job created" in the ".toast-message" "css_element"

  Scenario Outline: Filter jobs by position
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Filters" "button"
    And I set the following fields in the "Position" "core_reportbuilder > Filter" to these values:
      | Position | <position> |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "<expected>" in the ".reportbuilder-report" "css_element"
    Examples:
      | position     | expected           |
      | Position111_ | User 11            |
      | Position112_ | Nothing to display |

  Scenario Outline: Filter jobs by position including subpositions
    Given the following job assignments exist in organisation structure:
      | user   | department     | position      |
      | user11 | Department111_ | Position111_  |
      | user12 | Department111_ | Position1111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Filters" "button"
    And I set the following fields in the "Position" "core_reportbuilder > Filter" to these values:
      | Position             | Position111_  |
      | Include subpositions | <subposition> |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "User 11" in the "reportbuilder-table" "table"
    And I <shouldornotsee> "User 12" in the "reportbuilder-table" "table"
    Examples:
      | subposition | shouldornotsee |
      | 0           | should not see |
      | 1           | should see     |

  Scenario Outline: Filter jobs by department
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Filters" "button"
    And I set the following fields in the "Department" "core_reportbuilder > Filter" to these values:
      | Department | <department> |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "<expected>" in the ".reportbuilder-report" "css_element"
    Examples:
      | department     | expected           |
      | Department111_ | User 11            |
      | Department112_ | Nothing to display |

  Scenario Outline: Filter jobs by department including subdepartments
    Given the following job assignments exist in organisation structure:
      | user   | department      | position     |
      | user11 | Department111_  | Position111_ |
      | user12 | Department1111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Filters" "button"
    And I set the following fields in the "Department" "core_reportbuilder > Filter" to these values:
      | Department             | Department111_  |
      | Include subdepartments | <subdepartment> |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "User 11" in the "reportbuilder-table" "table"
    And I <shouldornotsee> "User 12" in the "reportbuilder-table" "table"
    Examples:
      | subdepartment | shouldornotsee |
      | 0             | should not see |
      | 1             | should see     |

  Scenario Outline: Filter past jobs
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | startdate  | enddate                |
      | user11 | Department111_ | Position111_ | 2020-01-01 | ##+1 month##%Y-%m-%d## |
      | user12 | Department111_ | Position111_ | 2020-01-01 | 2020-12-31             |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Filters" "button"
    And I set the following fields in the "Show past jobs" "core_reportbuilder > Filter" to these values:
      | Show past jobs value | <pastjobs> |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "User 11" in the "reportbuilder-table" "table"
    And I <shouldornotsee> "User 12" in the "reportbuilder-table" "table"
    Examples:
      | pastjobs | shouldornotsee |
      | No       | should not see |
      | Yes      | should see     |

  Scenario: Filter user fullname
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
      | user12 | Department111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Filters" "button"
    And I set the following fields in the "Full name" "core_reportbuilder > Filter" to these values:
      | Full name operator | Is equal to |
      | Full name value    | User 11     |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "User 11" in the "reportbuilder-table" "table"
    And I should not see "User 12" in the "reportbuilder-table" "table"

  Scenario: Edit jobs dates
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | enddate              |
      | user11 | Department111_ | Position111_ | ##+1 day##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I press "Edit dates" action in the "User 11" report row
    And I set the following fields in the "Edit job dates for 'User 11'" "dialogue" to these values:
      | enddate[enabled] | 1            |
      | End date         | ##+1 month## |
    And I click on "Save" "button" in the "Edit job dates for 'User 11'" "dialogue"
    Then the following should exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | End date               |
      | User 11 | Department111_ | Position111_ | ##+1 month##%d/%m/%y## |
    And the following should not exist in the "reportbuilder-table" table:
      | User    | End date             |
      | User 11 | ##+1 day##%d/%m/%y## |
    And I should see "Job updated" in the ".toast-message" "css_element"

  Scenario: Finish job
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | startdate              |
      | user11 | Department111_ | Position111_ | ##-1 day##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I press "Set job as finished" action in the "User 11" report row
    And I set the following fields in the "Set job as finished" "dialogue" to these values:
      | End date | ##-1 month## |
    And I click on "Proceed" "button" in the "Set job as finished" "dialogue"
    And I should see "The end date must be after the start date." in the "Set job as finished" "dialogue"
    And I set the following fields in the "Set job as finished" "dialogue" to these values:
      | End date         | ##+1 month## |
    And I click on "Proceed" "button" in the "Set job as finished" "dialogue"
    Then the following should exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | End date               |
      | User 11 | Department111_ | Position111_ | ##+1 month##%d/%m/%y## |
    And I should see "Job updated" in the ".toast-message" "css_element"

  Scenario: Transfer to new job
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | startdate               |
      | user11 | Department111_ | Position111_ | ##yesterday##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I press "Transfer this user to a new job" action in the "User 11" report row
    And I should see "Current: Position111_" in the "Transfer 'User 11' to a new job" "dialogue"
    And I should see "Current: Department111_" in the "Transfer 'User 11' to a new job" "dialogue"
    And I set the following fields in the "Transfer 'User 11' to a new job" "dialogue" to these values:
      | Position         | Position112_   |
      | Department       | Department112_ |
      | startdate[day]   | ##today##%d##  |
      | startdate[month] | ##today##%B##  |
      | startdate[year]  | ##today##%Y##  |
    And I click on "Proceed" "button" in the "Transfer 'User 11' to a new job" "dialogue"
    Then the following should exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | Start date          |
      | User 11 | Department112_ | Position112_ | ##today##%d/%m/%y## |
    And the following should not exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | Start date              | End date                |
      | User 11 | Department111_ | Position111_ | ##yesterday##%d/%m/%y## | ##yesterday##%d/%m/%y## |
    And I should see "User transfered to a new job" in the ".toast-message" "css_element"
    And I click on "Filters" "button"
    And I set the following fields in the "Show past jobs" "core_reportbuilder > Filter" to these values:
      | Show past jobs | Yes |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | Start date              | Start date              |
      | User 11 | Department112_ | Position112_ | ##today##%d/%m/%y##     |                         |
      | User 11 | Department111_ | Position111_ | ##yesterday##%d/%m/%y## | ##yesterday##%d/%m/%y## |
    # Filters modal stays open. Reloading the page keeps the filtering and hides the modal.
    And I reload the page
    And I open the action menu in "Department111_" "table_row"
    And I should not see "Transfer this user to a new job"
    # Action menu stays open covering the rest of the options. Reloading the page keeps the filtering and hides menu.
    And I reload the page
    And I open the action menu in "Department112_" "table_row"
    And I should see "Transfer this user to a new job"

  Scenario: Error messages on transfer to new job
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | startdate               |
      | user11 | Department111_ | Position111_ | ##yesterday##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I press "Transfer this user to a new job" action in the "User 11" report row
    And I should see "Position111_" in the "Transfer 'User 11' to a new job" "dialogue"
    And I should see "Department111_" in the "Transfer 'User 11' to a new job" "dialogue"
    And I set the following fields in the "Transfer 'User 11' to a new job" "dialogue" to these values:
      | Position         | Position112_      |
      | Department       | Department112_    |
      | startdate[day]   | ##yesterday##%d## |
      | startdate[month] | ##yesterday##%B## |
      | startdate[year]  | ##yesterday##%Y## |
    And I click on "Proceed" "button" in the "Transfer 'User 11' to a new job" "dialogue"
    And I should see "The start date of the new job date must be after the start date of the old job." in the "Transfer 'User 11' to a new job" "dialogue"
    And I set the following fields in the "Transfer 'User 11' to a new job" "dialogue" to these values:
      | Position         | Position111_   |
      | Department       | Department111_ |
      | startdate[day]   | ##today##%d##  |
      | startdate[month] | ##today##%B##  |
      | startdate[year]  | ##today##%Y##  |
    And I click on "Proceed" "button" in the "Transfer 'User 11' to a new job" "dialogue"
    And I should see "Either the department or the position have to be different from the current ones." in the "Transfer 'User 11' to a new job" "dialogue"
    And I set the following fields in the "Transfer 'User 11' to a new job" "dialogue" to these values:
      | Position         | Position112_   |
      | Department       | Department112_ |
      | startdate[day]   | ##today##%d##  |
      | startdate[month] | ##today##%B##  |
      | startdate[year]  | ##today##%Y##  |
    And I click on "Proceed" "button" in the "Transfer 'User 11' to a new job" "dialogue"
    Then the following should exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | Start date          |
      | User 11 | Department112_ | Position112_ | ##today##%d/%m/%y## |
    And the following should not exist in the "reportbuilder-table" table:
      | User    | Department     | Position     | Start date              | End date                |
      | User 11 | Department111_ | Position111_ | ##yesterday##%d/%m/%y## | ##yesterday##%d/%m/%y## |

  Scenario: Assign additional job to user
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I press "Assign another job for this user" action in the "User 11" report row
    And I set the following fields in the "New job for 'User 11'" "dialogue" to these values:
      | Position   | Position112_    |
      | Department | Department111_  |
    And I click on "Proceed" "button" in the "New job for 'User 11'" "dialogue"
    And I should see "Position111_"
    And I should see "Position112_"
    And I should see "Job created" in the ".toast-message" "css_element"

  Scenario: Delete jobs
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I press "Delete job" action in the "User 11" report row
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    Then "Position111_" "text" should exist in the "User 11" "table_row"
    And I press "Delete job" action in the "User 11" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Position111_"
    And I should see "Job deleted" in the ".toast-message" "css_element"

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
    And the following should exist in the "reportbuilder-table" table:
      | Full name | Tenant name |
      | User 11   | Tenant1     |
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    And I should see "1 user(s) moved to tenant: Tenant2"
    And the following should exist in the "reportbuilder-table" table:
      | Full name | Tenant name |
      | User 11   | Tenant2     |
    # Check moved user is labelled
    Then I navigate to "Organisation structure" in workplace launcher
    And I follow "Job assignments"
    Then the following should exist in the "reportbuilder-table" table:
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

  Scenario: Assign job to users in locked departments and positions is only possible with assigninlocked capability
    Given the following departments exist in organisation structure:
      | tenant     | name               | parent              | locked  |
      | Tenant1    | Frameworklocked_   |                     | 1       |
      | Tenant1    | Departmentlocked_  |   Frameworklocked_  | 0       |
    And the following "permission overrides" exist:
      | capability                       | permission | role                      | contextlevel | reference |
      | tool/organisation:assigninlocked | Allow      | tool_organisation_manager | System       |           |
    And the following positions exist in organisation structure:
      | tenant     | name             | parent            | locked  | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Frameworklocked_ |                   | 1       |      0        |       0           |       0           |        0              |
      | Tenant1    | Positionlocked_  | Frameworklocked_  | 0       |      0        |       0           |       0           |        0              |
    When I log in as "user3"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New job"
    Then I open the autocomplete suggestions list in the "New job" "dialogue"
    And I click on "User 11" item in the autocomplete list
    And I press the escape key
    And I set the following fields in the "New job" "dialogue" to these values:
      | positionid       | Positionlocked_    |
      | departmentid     | Departmentlocked_  |
      | enddate[enabled] | 1                  |
      | enddate[year]    | 2030               |
    And I click on "Save" "button" in the "New job" "dialogue"
    And the "Edit dates" item should exist in the "Actions" action menu of the "User 11" "table_row"
    And the "Delete job completely" item should exist in the "Actions" action menu of the "User 11" "table_row"
    And the "Transfer this user to a new job" item should exist in the "Actions" action menu of the "User 11" "table_row"
    And the "Assign another job for this user" item should exist in the "Actions" action menu of the "User 11" "table_row"
    # Remove capability.
    And the following "permission overrides" exist:
      | capability                       | permission | role                      | contextlevel | reference |
      | tool/organisation:assigninlocked | Prohibit   | tool_organisation_manager | System       |           |
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # User should not be able to edit jobs with locked department/positions without the capability.
    And "Actions" "actionmenu" should not exist in the "User 11" "table_row"
    And I follow "New job"
    # User should not be able to see locked positions and departments without the capability.
    And "//select[@name='positionid']//optgroup[@label='Framework11_']/option[.='Position111_']" "xpath_element" should exist
    And "//select[@name='positionid']//optgroup[@label='Frameworklocked_']/option[.='Positionlocked_']" "xpath_element" should not exist
    And "//select[@name='departmentid']//optgroup[@label='Framework11_']/option[.='Department111_']" "xpath_element" should exist
    And "//select[@name='departmentid']//optgroup[@label='Frameworklocked_']/option[.='Departmentlocked_']" "xpath_element" should not exist
