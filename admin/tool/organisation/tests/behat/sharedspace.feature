@tool @tool_organisation @moodleworkplace @javascript
Feature: Shared space in tool_organisation
  As a global admin
  I can use Organisation structure in the Shared space

  Background:
    Given "2" tenants exist with "5" users and "0" courses in each
    And shared space is enabled
    Given the following departments exist in organisation structure:
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
      | -          | FrameworkS1_    |                |
      | -          | DepartmentS11_  | FrameworkS1_   |
      | -          | DepartmentS112_ | DepartmentS11_ |
      | -          | DepartmentS12_  | FrameworkS1_   |
    And the following positions exist in organisation structure:
      | tenant   | name          | parent       | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1  | Framework11_  |              | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Framework12_  |              | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Position111_  | Framework11_ | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Position112_  | Framework11_ | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Position113_  | Framework11_ | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Position1111_ | Position111_ | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Position121_  | Framework12_ | 0             | 0                 | 0                 | 0                     |
      | Tenant2  | Framework21_  |              | 0             | 0                 | 0                 | 0                     |
      | -        | FrameworkS1_  |              | 0             | 0                 | 0                 | 0                     |
      | -        | FrameworkS2_  |              | 0             | 0                 | 0                 | 0                     |
      | -        | PositionS11_  | FrameworkS1_ | 1             | 1                 | 1                 | 0                     |
      | -        | PositionS112_ | PositionS11_ | 0             | 0                 | 0                 | 0                     |
      | -        | PositionS12_  | FrameworkS1_ | 0             | 0                 | 0                 | 0                     |
    And the following config values are set as admin:
      | custommenuitems | -My teams\|/my/courses.php?myteams=1 |

  Scenario: Viewing and editing departments in shared space as tenant administrator
    When I log in as "tenantadmin1"
    And I navigate to "Organisation structure" in workplace launcher
    Then I navigate to "Departments" in current page administration
    # Check create new department inside framework icon.
    And "New department for framework 'Framework11_'" "link" should exist
    And "New department for framework 'Framework12_'" "link" should exist
    And "New department for framework 'FrameworkS1_'" "link" should not exist
    And "New department for framework 'FrameworkS2_'" "link" should not exist
    # Check Edit department framework icon.
    And "Edit department framework 'Framework11_'" "link" should exist
    And "Edit department framework 'Framework12_'" "link" should exist
    And "Edit department framework 'FrameworkS1_'" "link" should not exist
    And "Edit department framework 'FrameworkS2_'" "link" should not exist
    # Check Delete department framework icon.
    And "Delete department framework 'Framework11_'" "link" should exist
    And "Delete department framework 'Framework12_'" "link" should exist
    And "Delete department framework 'FrameworkS1_'" "link" should not exist
    And "Delete department framework 'FrameworkS2_'" "link" should not exist
    # Check Edit name on departments.
    And I click on "Expand department framework 'Framework11_'" "button"
    And "Edit name" "link" should exist in the "Department111_" "tool_wp > Table tree node"
    And "Edit name" "link" should exist in the "Department112_" "tool_wp > Table tree node"
    And "Edit name" "link" should exist in the "Department113_" "tool_wp > Table tree node"
    And I click on "Expand department framework 'FrameworkS1_'" "button"
    And "Edit name" "link" should not exist in the "DepartmentS11_" "tool_wp > Table tree node"
    And "Edit name" "link" should not exist in the "DepartmentS12_" "tool_wp > Table tree node"
    # Tenantadmin does not see the 'Edit in Shared space' button.
    And I should not see "Edit in Shared space"
    And I click on "Expand department framework 'Framework11_'" "button"
    And "New subdepartment for department 'Department111_'" "link" should exist
    And "Edit department 'Department111_'" "link" should exist
    And "Delete department 'Department111_'" "link" should exist
    And I click on "Expand department framework 'FrameworkS1_'" "button"
    And "New subdepartment for department 'DepartmentS11_'" "link" should not exist
    And "Edit department 'DepartmentS11_'" "link" should not exist
    And "Delete department 'DepartmentS11_'" "link" should not exist
    And I log out

  Scenario: Viewing and editing positions in shared space as tenant administrator
    When I log in as "tenantadmin1"
    And I navigate to "Organisation structure" in workplace launcher
    Then I navigate to "Positions" in current page administration
    # Check create new position inside framework icon.
    And "New position for framework 'Framework11_'" "link" should exist
    And "New position for framework 'Framework12_'" "link" should exist
    And "New position for framework 'FrameworkS1_'" "link" should not exist
    And "New position for framework 'FrameworkS2_'" "link" should not exist
    # Check Edit position framework icon.
    And "Edit position framework 'Framework11_'" "link" should exist
    And "Edit position framework 'Framework12_'" "link" should exist
    And "Edit position framework 'FrameworkS1_'" "link" should not exist
    And "Edit position framework 'FrameworkS2_'" "link" should not exist
    # Check Delete position framework icon.
    And "Delete position framework 'Framework11_'" "link" should exist
    And "Delete position framework 'Framework12_'" "link" should exist
    And "Delete position framework 'FrameworkS1_'" "link" should not exist
    And "Delete position framework 'FrameworkS2_'" "link" should not exist
    # Check Edit name on positions.
    And I click on "Expand position framework 'Framework11_'" "button"
    And "Edit name" "link" should exist in the "Position111_" "tool_wp > Table tree node"
    And "Edit name" "link" should exist in the "Position112_" "tool_wp > Table tree node"
    And "Edit name" "link" should exist in the "Position113_" "tool_wp > Table tree node"
    And I click on "Expand position framework 'FrameworkS1_'" "button"
    And "Edit name" "link" should not exist in the "PositionS11_" "tool_wp > Table tree node"
    And "Edit name" "link" should not exist in the "PositionS12_" "tool_wp > Table tree node"
    # Tenantadmin does not see the 'Edit in Shared space' button.
    And I should not see "Edit in Shared space"
    And I click on "Expand position framework 'Framework11_'" "button"
    And "New subposition for position 'Position111_'" "link" should exist
    And "Edit position 'Position111_'" "link" should exist
    And "Delete position 'Position111_'" "link" should exist
    And I click on "Expand position framework 'FrameworkS1_'" "button"
    And "New subposition for position 'PositionS11_'" "link" should not exist
    And "Edit position 'PositionS11_'" "link" should not exist
    And "Delete position 'PositionS11_'" "link" should not exist
    And I log out

  Scenario: Creating, viewing and editing departments in shared space as administrator
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Organisation structure" in workplace launcher
    And I should not see "Job assignments"
    # Here I should be on a "Departments" tab because Jobs tab is disabled.
    And I follow "New framework"
    And I set the following fields to these values:
      | Name      | Framework1  |
      | ID number | 12345       |
    And I click on "Save" "button" in the "New department framework" "dialogue"
    # Check that department framework name is editable.
    And I click on "Edit department framework 'Framework1'" "link"
    And I set the field "Name" to "New framework1"
    And I click on "Save" "button" in the "Edit department framework 'Framework1'" "dialogue"
    And I follow "New department for framework 'New framework1'"
    And I set the following fields to these values:
      | Name      | Department1 |
      | ID number | 999         |
    And I click on "Save" "button" in the "New department for framework 'New framework1'" "dialogue"
    Then I should see "Department1"
    # Check that department name is editable.
    And I set the field "Edit name" in the "Department1" "tool_wp > Table tree node" to "New department1"
    And I should see "New department1"
    And I should not see "Department1"
    # Check that they show up in Tenant1.
    And I switch to tenant "Tenant1"
    And I navigate to "Organisation structure" in workplace launcher
    Then I navigate to "Departments" in current page administration
    And I should see "Framework11_"
    And I should see "Framework12_"
    And I should see "FrameworkS1_"
    And I should see "New framework1"
    # Admin sees the 'Edit in Shared space' button.
    Then I press "Edit in Shared space"
    And I should not see "Framework11_"
    And I log out

  Scenario: Creating and viewing positions in shared space as administrator
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Organisation structure" in workplace launcher
    Then I navigate to "Positions" in current page administration
    And I follow "New framework"
    And I set the following fields to these values:
      | Name      | Framework1 |
      | ID number | 12345      |
    And I click on "Save" "button" in the "New position framework" "dialogue"
    # Check that position framework name is editable.
    And I click on "Edit position framework 'Framework1'" "link"
    And I set the field "Name" to "New framework1"
    And I click on "Save" "button" in the "Edit position framework 'Framework1'" "dialogue"
    And I follow "New position for framework 'New framework1'"
    And I set the following fields to these values:
      | Name      | Position1 |
      | ID number | 999       |
    And I click on "Save" "button" in the "New position for framework 'New framework1'" "dialogue"
    Then I should see "Position1"
    # Check that position name is editable.
    And I set the field "Edit name" in the "Position1" "tool_wp > Table tree node" to "New position1"
    And I should see "New position1"
    And I should not see "Position1"
    # Check that they show up in Tenant1.
    And I switch to tenant "Tenant1"
    And I navigate to "Organisation structure" in workplace launcher
    Then I navigate to "Positions" in current page administration
    And I should see "Framework11_"
    And I should see "Framework12_"
    And I should see "FrameworkS1_"
    And I should see "New framework1"
    # Admin sees the 'Edit in Shared space' button.
    Then I press "Edit in Shared space"
    And I should not see "Framework11_"
    And I log out

  Scenario: Creating jobs is not posible in shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Organisation structure" in workplace launcher
    Then I should not see "Job assignments"
    And I should not see "New Job"

  Scenario: Creating jobs in a tenant using Departments and position from Shared space
    When I log in as "tenantadmin1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "New job"
    And I set the following fields in the "New job" "dialogue" to these values:
      | Select users     | User 11,User 12 |
      | positionid       | PositionS11_   |
      | departmentid     | DepartmentS11_ |
    And I click on "Save" "button" in the "New job" "dialogue"
    Then the following should exist in the "reportbuilder-table" table:
      | User      | Department       | Position       | Permissions | Start date            | End date   |
      | User 11   | DepartmentS11_   | PositionS11_   |             |  ##today##%d/%m/%y##  |            |
      | User 12   | DepartmentS11_   | PositionS11_   |             |  ##today##%d/%m/%y##  |            |

  Scenario: Shared departments and shared positions show up on Teams dashboard
    Given the following job assignments exist in organisation structure:
      | user          | department      | position      |
      | tenantadmin1  | DepartmentS11_  | PositionS11_  |
      | user11        | DepartmentS11_  | PositionS11_  |
      | user12        | DepartmentS11_  | PositionS112_ |
      | user13        | Department112_  | Position112_  |
      | user14        | DepartmentS112_ | Position112_  |
    And I log in as "tenantadmin1"
    And I select "My teams" from primary navigation
    And I change window size to "large"
    And I click on "Filters" "button"
    And I set the following fields in the "Organisation structure" "core_reportbuilder > Filter" to these values:
      | Show everybody reporting to me | 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 11" in the "region-main" "region"
    And I should see "User 12" in the "region-main" "region"
    And I should not see "User 13" in the "region-main" "region"
    And I should see "User 14" in the "region-main" "region"
    And I set the following fields in the "Organisation structure" "core_reportbuilder > Filter" to these values:
      | Customise... | 1            |
      | Position     | PositionS11_ |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 11" in the "region-main" "region"
    And I should not see "User 12" in the "region-main" "region"
    And I should not see "User 13" in the "region-main" "region"
    And I should not see "User 14" in the "region-main" "region"
    And I set the following fields in the "Organisation structure" "core_reportbuilder > Filter" to these values:
      | Include subpositions | 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 11" in the "region-main" "region"
    And I should see "User 12" in the "region-main" "region"
    And I should not see "User 13" in the "region-main" "region"
    And I should not see "User 14" in the "region-main" "region"
    And I set the following fields in the "Organisation structure" "core_reportbuilder > Filter" to these values:
      | Position   | Any            |
      | Department | DepartmentS11_ |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 11" in the "region-main" "region"
    And I should see "User 12" in the "region-main" "region"
    And I should not see "User 13" in the "region-main" "region"
    And I should not see "User 14" in the "region-main" "region"
    And I set the following fields in the "Organisation structure" "core_reportbuilder > Filter" to these values:
      | Include subdepartments | 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 11" in the "region-main" "region"
    And I should see "User 12" in the "region-main" "region"
    And I should not see "User 13" in the "region-main" "region"
    And I should see "User 14" in the "region-main" "region"

  Scenario: Ensure teams tab and shared programs work well with shared departments and positions
    Given the following job assignments exist in organisation structure:
      | user          | department      | position      |
      | user12        | DepartmentS11_  | PositionS11_  |
      | user13        | DepartmentS11_  | PositionS112_ |
      | user14        | DepartmentS11_  | PositionS112_  |
      | user22        | DepartmentS11_  | PositionS11_  |
      | user23        | DepartmentS11_  | PositionS112_ |
      | user24        | DepartmentS11_  | PositionS112_  |
    And the following "tool_program > programs" exist:
      | fullname      | idnumber | archived | tenant  |
      | ProgramShared | prog0    | 0        | -       |
    # Login as a manager in the first tenant
    When I log in as "user12"
    And I select "My teams" from primary navigation
    # Make sure you can only see your tenant's users in your team tab
    Then I should see "User 13"
    And I should see "User 14"
    And I should not see "User 15"
    And I should not see "User 23"
    And I should not see "User 24"
    # Go to programs and make sure you can allocate users 3 and 4 to the program.
    # Make sure you can not allocate users 1&5 and also users from other tenant to the program.
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "ProgramShared" report row
    And I press "Allocate users"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 11" "autocomplete_suggestions" should not exist
    And "User 15" "autocomplete_suggestions" should not exist
    And "User 23" "autocomplete_suggestions" should not exist
    And "User 24" "autocomplete_suggestions" should not exist
    And I click on "User 13" item in the autocomplete list
    And I click on "User 14" item in the autocomplete list
    Then I click on "Save" "button" in the "Allocate users" "dialogue"
    And I should see "User 13" in the "#program_users_tab" "css_element"
    And I should see "User 14" in the "#program_users_tab" "css_element"

  Scenario: Ensure that shared department frameworks can not be deleted from outside Shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Organisation structure" in workplace launcher
    And "Delete department framework 'FrameworkS1_'" "link" should exist
    Then I switch to tenant "Tenant1"
    And I navigate to "Organisation structure" in workplace launcher
    And I navigate to "Departments" in current page administration
    Then "Delete department framework 'FrameworkS1_'" "link" should not exist
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Organisation structure" in workplace launcher
    And I navigate to "Departments" in current page administration
    Then "Delete department framework 'FrameworkS1_'" "link" should not exist
    And I log out

  Scenario: Ensure that shared position frameworks can not be deleted from outside Shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Organisation structure" in workplace launcher
    And I navigate to "Positions" in current page administration
    And "Delete position framework 'FrameworkS1_'" "link" should exist
    Then I switch to tenant "Tenant1"
    And I navigate to "Organisation structure" in workplace launcher
    And I navigate to "Positions" in current page administration
    Then "Delete position framework 'FrameworkS1_'" "link" should not exist
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Organisation structure" in workplace launcher
    And I navigate to "Positions" in current page administration
    Then "Delete position framework 'FrameworkS1_'" "link" should not exist
    And I log out
