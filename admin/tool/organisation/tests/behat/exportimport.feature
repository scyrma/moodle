@tool @tool_organisation @moodleworkplace @javascript
Feature: Export and import or organisation structure
  As a tenant admin
  I should be able to export and import organisation structure and jobs

  @_file_upload
  Scenario: Importing organisation structure into a different tenant
    Given "2" tenants exist with "4" users and "0" courses in each
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file | admin/tool/organisation/tests/fixtures/orgstructure.zip |
      | Step 2 | Select tenant | 1                                                       |
      | Step 2 | Choose tenant | Tenant2                                                 |
    And I should see "Tenant2" in the "Organisation structure" "table_row"
    And I should see "Success" in the "Organisation structure" "table_row"
    # The default tenant still does not have any positions or departments.
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Departments"
    And I should see "Nothing to display"
    And I follow "Positions"
    And I should see "Nothing to display"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant2" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    And I follow "Departments"
    And I should see "Department frameworks"
    And I should see "country"
    And I follow "Positions"
    And I should see "Position frameworks"
    And I should see "Main hierarchy"
    And I log out

  @_file_upload
  Scenario: Importing jobs creating new positions
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following "users" exist:
      | username | firstname | lastname | email              |
      | user48   | Anna      | Smith    | user48@example.com |
    And the following users allocations to tenants exist:
      | user   | tenant  |
      | user48 | Tenant1 |
    And the following positions exist in organisation structure:
      | tenant  | name          | parent |
      | Tenant1 | Posframework1 |        |
      | Tenant1 | Posframework2 |        |
    And the following departments exist in organisation structure:
      | tenant  | name         | parent       |
      | Tenant1 | Depframework |              |
      | Tenant1 | Australia    | Depframework |
      | Tenant1 | Spain        | Depframework |
      | Tenant1 | first floor  | Depframework |
    When I log in as "tenantadmin1"
    And I perform a new import with these options:
      | Step 1 | Upload a file          | admin/tool/organisation/tests/fixtures/jobs.zip |
      | Step 4 | Create in framework... | 1                                               |
      | Step 4 | Position framework     | Posframework2                                   |
    And I click on "View import" "link" in the "Organisation structure jobs" "table_row"
    And I click on "[data-action=show-log]" "css_element"
    And I should see "Created new job for 'Anna Smith' - CTO (Australia)"
    And I should see "Position 'CTO' was created"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Positions"
    And I click on "Expand position framework 'Posframework2'" "button"
    And I should see "CTO"

  @_file_upload
  Scenario: Importing jobs creating new departments
    Given "2" tenants exist with "4" users and "0" courses in each
    And the following "users" exist:
      | username | firstname | lastname | email              |
      | user48   | Anna      | Smith    | user48@example.com |
    And the following users allocations to tenants exist:
      | user   | tenant  |
      | user48 | Tenant1 |
    And the following positions exist in organisation structure:
      | tenant  | name         | parent       |
      | Tenant1 | Posframework |              |
      | Tenant1 | CTO          | Posframework |
      | Tenant1 | Team lead    | CTO          |
      | Tenant1 | team member  | Team lead    |
    And the following departments exist in organisation structure:
      | tenant  | name          | parent |
      | Tenant1 | Depframework1 |        |
      | Tenant1 | Depframework2 |        |
    When I log in as "tenantadmin1"
    And I perform a new import with these options:
      | Step 1 | Upload a file          | admin/tool/organisation/tests/fixtures/jobs.zip |
      | Step 4 | Create in framework... | 1                                               |
      | Step 4 | Department framework   | Depframework2                                   |
    And I click on "View import" "link" in the "Organisation structure jobs" "table_row"
    And I click on "[data-action=show-log]" "css_element"
    And I should see "Created new job for 'Anna Smith' - CTO (Australia)"
    And I should see "Department 'Australia' was created"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Departments"
    And I click on "Expand department framework 'Depframework2'" "button"
    And I should see "Australia"

  Scenario: Export user does not have capability to export dep/pos but can export jobs
    Given "2" tenants exist with "1" users and "0" courses in each
    Given the following "permission overrides" exist:
      | capability                              | permission  | role               | contextlevel  | reference |
      | tool/organisation:managedepartments     | Prevent     | tool_tenant_admin  | System        |           |
      | tool/organisation:managepositions       | Prevent     | tool_tenant_admin  | System        |           |
    And the following positions exist in organisation structure:
      | tenant  | name         | parent       |
      | Tenant1 | Posframework |              |
      | Tenant1 | CTO          | Posframework |
      | Tenant1 | Team lead    | CTO          |
      | Tenant1 | team member  | Team lead    |
    And the following departments exist in organisation structure:
      | tenant  | name          | parent |
      | Tenant1 | Depframework1 |        |
      | Tenant1 | Depframework2 |        |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I should not see "Organisation structure frameworks"
    And I click on "Organisation structure jobs" "radio"
    And I press "Next"
    And the "Department and position frameworks" "checkbox" should be disabled
    And I click on "Select all jobs in any of the selected frameworks..." "radio"
    And I press "Next"
    And I should see "Required"
    And I should see "Step 2"
    And I log out

  @_file_upload
  Scenario: Import user does not have capability to import dep/pos but can import jobs
    Given "2" tenants exist with "1" users and "0" courses in each
    Given the following "permission overrides" exist:
      | capability                              | permission  | role               | contextlevel  | reference |
      | tool/organisation:managedepartments     | Prevent     | tool_tenant_admin  | System        |           |
      | tool/organisation:managepositions       | Prevent     | tool_tenant_admin  | System        |           |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/orgstructure.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Department frameworks (2)"
    And I should see "Position frameworks (2)"
    And I should not see "Next"
    And I should see "We could not find an available importer for this file"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/jobs.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Job assignments (4)"
    And I press "Next"
    And I click on "Select all jobs from specific frameworks..." "radio"
    And the "Department and position frameworks" "checkbox" should be disabled
    And I press "Next"
    And I should see "Required"
    And I should see "Step 3"
    And I log out

  Scenario: Export form validation
    Given "2" tenants exist with "1" users and "0" courses in each
    And the following positions exist in organisation structure:
      | tenant  | name         | parent       |
      | Tenant1 | Posframework |              |
      | Tenant1 | CTO          | Posframework |
      | Tenant1 | Team lead    | CTO          |
      | Tenant1 | team member  | Team lead    |
    And the following departments exist in organisation structure:
      | tenant  | name          | parent |
      | Tenant1 | Depframework1 |        |
      | Tenant1 | Depframework2 |        |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Organisation structure frameworks" "radio"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Required"
    And I should see "Step 2"
    And I log out

  @_file_upload
  Scenario: Import form validation
    Given "2" tenants exist with "1" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/orgstructure.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Department frameworks (2)"
    And I should see "Position frameworks (2)"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Required"
    And I should see "Step 3"
    And I log out

  @_file_upload
  Scenario: Import departments form validation with csv file upload
    Given "2" tenants exist with "1" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/departments.csv" file to "Upload a file" filemanager
    And I press "Next"
    And I click on "Departments importer (CSV)" "radio"
    And I press "Next"
    And the following fields match these values:
      | Create a generic framework  | 1  |
      | Hierarchy of departments... | 1  |
      | Department identifier       | id |
    And I should not see "Department framework idnumber"
    And I set the following fields to these values:
      | Parent       | Not specified |
    And I press "Next"
    And I should see "When hierarchy is selected, the 'Parent' column must have mapping"
    And I set the following fields to these values:
      | Parent       | parent |
    And I press "Next"
    And I should see "Instances (25)"
    And I log out

  @_file_upload
  Scenario: Import positions form validation with csv file upload
    Given "2" tenants exist with "1" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/positions.csv" file to "Upload a file" filemanager
    And I press "Next"
    And I click on "Positions importer (CSV)" "radio"
    And I press "Next"
    And the following fields match these values:
      | Create a generic framework  | 1  |
      | Hierarchy of positions...   | 1  |
      | Position identifier         | id |
    And I should not see "Position framework idnumber"
    And I set the following fields to these values:
      | Parent       | Not specified |
    And I press "Next"
    And I should see "When hierarchy is selected, the 'Parent' column must have mapping"
    And I set the following fields to these values:
      | Parent       | parent |
    And I press "Next"
    And I should see "Instances (25)"
    And I log out

  @_file_upload
  Scenario: Import departments using departments CSV exporter into a new framework
    Given "2" tenants exist with "1" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I perform a new import with these options:
      | Step 1 | Upload a file              | admin/tool/organisation/tests/fixtures/departments.csv |
      | Step 2 | Departments importer (CSV) | 1                                                      |
      | Step 3 | Create a generic framework | 1                                                      |
    And I click on "View import" "link" in the "Departments importer (CSV)" "table_row"
    And I should see "Show 22 more..."
    And I click on "[data-action=show-log]" "css_element"
    And I should see "Created new department framework 'departments'"
    And I should see "Created new department 'Sales'"
    And I follow "departments"
    And I should see "Organisation structure" in the "#page-navbar" "css_element"
    And I click on "Expand department framework 'departments'" "button"
    And I should see "Sales"
    And I log out

  @_file_upload
  Scenario: Import departments using departments CSV exporter into an existing framework
    Given "2" tenants exist with "1" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I perform a new import with these options:
      | Step 1 | Upload a file              | admin/tool/organisation/tests/fixtures/departments.csv |
      | Step 2 | Departments importer (CSV) | 1                                                      |
      | Step 3 | Select existing framework  | 1                                                      |
      | Step 3 | Department framework       | Framework12_                                           |
    And I click on "View import" "link" in the "Departments importer (CSV)" "table_row"
    And I should see "Show 22 more..."
    And I click on "[data-action=show-log]" "css_element"
    And I should not see "Created new department framework"
    And I should see "Created new department 'Sales'"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Departments"
    And I click on "Expand department framework 'Framework12_'" "button"
    And I should see "Sales"
    And I log out

  @_file_upload
  Scenario: Import positions using positions CSV exporter into a new framework
    Given "2" tenants exist with "1" users and "0" courses in each
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I perform a new import with these options:
      | Step 1 | Upload a file              | admin/tool/organisation/tests/fixtures/positions.csv |
      | Step 2 | Positions importer (CSV) | 1                                                      |
      | Step 3 | Create a generic framework | 1                                                    |
    And I click on "View import" "link" in the "Positions importer (CSV)" "table_row"
    And I should see "Show 22 more..."
    And I click on "[data-action=show-log]" "css_element"
    And I should see "Created new position framework 'positions'"
    And I should see "Created new position 'Chief Executive Officer'"
    And I follow "positions"
    And I should see "Organisation structure" in the "#page-navbar" "css_element"
    And I click on "Expand position framework 'positions'" "button"
    And I should see "Chief Executive Officer"
    And I log out

  @_file_upload
  Scenario: Import positions using positions CSV exporter into an existing framework
    Given "2" tenants exist with "1" users and "0" courses in each
    And the following positions exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I perform a new import with these options:
      | Step 1 | Upload a file              | admin/tool/organisation/tests/fixtures/positions.csv   |
      | Step 2 | Positions importer (CSV)   | 1                                                      |
      | Step 3 | Select existing framework  | 1                                                      |
      | Step 3 | Position framework         | Framework12_                                           |
    And I click on "View import" "link" in the "Positions importer (CSV)" "table_row"
    And I should see "Show 22 more..."
    And I click on "[data-action=show-log]" "css_element"
    And I should not see "Created new position framework"
    And I should see "Created new position 'Chief Executive Officer'"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Positions"
    And I click on "Expand position framework 'Framework12_'" "button"
    And I should see "Chief Executive Officer"
    And I log out

  @_file_upload
  Scenario: Importing jobs into Shared space should not be possible
    Given shared space is enabled
    When I log in as "admin"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/organisation/tests/fixtures/jobs.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I click on "Select tenant..." "radio"
    And I set the field "Choose tenant" to "Shared space"
    And I press "Next"
    And I should see "Job assignments"
    And I press "Next"
    And I should see "Jobs can not be imported into Shared space"
    And I log out

  Scenario: Export organisation structure from Shared space
    Given shared space is enabled
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | -          | FrameworkS1_    |                |
      | -          | DepartmentS11_  | FrameworkS1_   |
      | -          | DepartmentS112_ | DepartmentS11_ |
      | -          | DepartmentS12_  | FrameworkS1_   |
    And the following positions exist in organisation structure:
      | tenant   | name          | parent       | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | -        | FrameworkS1_  |              | 0             | 0                 | 0                 | 0                     |
      | -        | FrameworkS2_  |              | 0             | 0                 | 0                 | 0                     |
      | -        | PositionS11_  | FrameworkS1_ | 1             | 0                 | 1                 | 0                     |
      | -        | PositionS112_ | PositionS11_ | 0             | 0                 | 0                 | 0                     |
      | -        | PositionS12_  | FrameworkS1_ | 0             | 0                 | 0                 | 0                     |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Organisation structure frameworks" "radio"
    And I press "Next"
    And I should see "Select all department and position frameworks"
    And I press "Next"
    And I should see "FrameworkS1_ (Department framework)"
    And I should see "FrameworkS1_ (Position framework)"
    And I should see "FrameworkS2_ (Position framework)"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button"
    Then the following should exist in the "report-table" table:
      | Exporter                          | Status    | Tenant       |
      | Organisation structure frameworks | Scheduled | Shared space |
    And I log out

  @_file_upload
  Scenario: Importing organisation structure into the Shared space
    Given shared space is enabled
    When I log in as "admin"
    # The default tenant still does not have any positions or departments.
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Departments"
    And I should see "Nothing to display"
    And I follow "Positions"
    And I should see "Nothing to display"
    And I perform a new import with these options:
      | Step 1 | Upload a file | admin/tool/organisation/tests/fixtures/orgstructure.zip |
      | Step 2 | Select tenant | 1                                                       |
      | Step 2 | Choose tenant | Shared space                                            |
    And I should see "Shared space" in the "Organisation structure" "table_row"
    And I should see "Success" in the "Organisation structure" "table_row"
    # The default tenant now has shared positions and shared departments.
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "Departments"
    And I should not see "Nothing to display"
    And I follow "Positions"
    And I should not see "Nothing to display"
    And I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Shared space" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    And I follow "Departments"
    And I should see "Department frameworks"
    And I should see "country"
    And I follow "Positions"
    And I should see "Position frameworks"
    And I should see "Main hierarchy"
    And I log out

  @_file_upload
  Scenario: Importing export created when jobs DR conditions were single-value selects
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file | admin/tool/organisation/tests/fixtures/dr_export_using_singleselects.zip |
    And I navigate to "All tenants" in workplace launcher
    And I switch to tenant "Test DR export import"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Users who have a job in the department 'dep1'"
    And I should see "Users who have position 'pos1'"
    And I should see "Users who don't have a job in the department 'dep1'"
    And I should see "Users who don't have position 'pos1'"
    Then I follow "indep1"
    And I should see "1 total matches"
    Given the following "tool_tenant > users" exist:
      | username | firstname | lastname | email              | tenant                |
      | u2       | U2        | U2       | u2@address.invalid | Test DR export import |
      | u3       | U3        | U3       | u3@address.invalid | Test DR export import |
    And I navigate to "Dynamic rules" in workplace launcher
    Then I click on "Enable rule" "link" in the "indep1" "table_row"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    Then I click on "Enable rule" "link" in the "inpos1" "table_row"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    Then I click on "Enable rule" "link" in the "not in dep1" "table_row"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    Then I click on "Enable rule" "link" in the "not in pos1" "table_row"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I follow "View report for 'indep1'"
    And I should see "DR1 U1"
    And I should not see "U2 U2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'not in dep1'"
    And I should not see "DR1 U1"
    And I should see "U2 U2"
    And I should see "U3 U3"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'inpos1'"
    And I should see "DR1 U1"
    And I should not see "U2 U2"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "View report for 'not in pos1'"
    And I should not see "DR1 U1"
    And I should see "U2 U2"
    And I should see "U3 U3"
