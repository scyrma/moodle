@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure departments management
  As a manager
  I want to be able to modify departments

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
    And the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
    And the following "roles" exist:
      | shortname  | name                 | archetype |
      | orgmanager | Organisation manager |           |
    And the following "role assigns" exist:
      | user  | role       | contextlevel | reference |
      | user1 | orgmanager | System       |           |
      | user2 | orgmanager | System       |           |
    And the following "permission overrides" exist:
      | capability                          | permission | role       | contextlevel | reference |
      | tool/organisation:managedepartments | Allow      | orgmanager | System       |           |
      | moodle/site:configview              | Allow      | orgmanager | System       |           |

  Scenario: Create and edit departments and frameworks
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 12345 |
    And I click on "Save" "button" in the "New department framework" "dialogue"
    And I click on "Edit department framework 'Framework1'" "link"
    Then the field "Name" matches value "Framework1"
    And the field "ID number" matches value "12345"
    And I set the field "Name" to "New framework1"
    And I click on "Save" "button" in the "Edit department framework 'Framework1'" "dialogue"
    And I follow "New department for framework 'New framework1'"
    And I set the following fields to these values:
      | Name | Department1 |
      | ID number | 999 |
    And I click on "Save" "button" in the "New department for framework 'New framework1'" "dialogue"
    Then I should see "Department1"
    And I set the field "Edit name" in the "Department1" "tool_wp > Table tree node" to "New department1"
    And I should see "New department1"
    And I should not see "Department1"
    # Edit link is not updated after name update.
    And I click on "Edit department 'Department1'" "link"
    And I set the following fields to these values:
      | Name | New department2 |
      | ID number | 123 |
      | Description | Any description|
    And I click on "Save" "button" in the "Edit department 'Department1'" "dialogue"
    Then  I should see "New department2"
    And I should not see "New department1"
    Then I click on "Edit department 'New department2'" "link"
    And the field "ID number" matches value "123"
    And the field "Description" matches value "Any description"
    And I click on "Cancel" "button" in the "Edit department 'New department2'" "dialogue"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Using top as parent department
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New framework"
    And I set the following fields to these values:
      | Name      | Framework1 |
      | ID number | 12345      |
    And I click on "Save" "button" in the "New department framework" "dialogue"
    And I follow "New department for framework 'Framework1'"
    And I set the following fields to these values:
      | Name      | Department1 |
      | ID number | 999         |
    And I should see "Top"
    And I open the autocomplete suggestions list
    And "Top" "autocomplete_suggestions" should exist
    And I click on "Top" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Save" "button" in the "New department for framework 'Framework1'" "dialogue"
    And I should see "Department1"
    And I follow "New subdepartment for department 'Department1'"
    And I set the following fields to these values:
      | Name      | Department2 |
      | ID number | 998         |
    And I should see "Department1"
    And I open the autocomplete suggestions list
    And "Top" "autocomplete_suggestions" should exist
    And "Department1" "autocomplete_suggestions" should exist
    And I click on "Top" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Save" "button" in the "New subdepartment for department 'Department1'" "dialogue"
    And I should see "Department2"
    And "Department2" "text" should appear after "Department1" "text"
    And I follow "New department for framework 'Framework1'"
    And I set the following fields to these values:
      | Name      | Department3 |
      | ID number | 997         |
    And I should see "Top"
    And I open the autocomplete suggestions list
    And "Top" "autocomplete_suggestions" should exist
    And "Department1" "autocomplete_suggestions" should exist
    And "Department2" "autocomplete_suggestions" should exist
    And I click on "Department2" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Save" "button" in the "New department for framework 'Framework1'" "dialogue"
    Then "Department3" "text" should appear after "Department2" "text"
    And "Department2" "text" should appear after "Department1" "text"

  Scenario: Changing sort order of departments
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
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Framework12_" "text" should appear after "Framework11_" "text"
    And I click on "Move department framework 'Framework12_'" "button"
    And I follow "To the top of the list"
    And "Framework11_" "text" should appear after "Framework12_" "text"
    And I click on "Expand department framework 'Framework11_'" "button"
    And "Department1111_" "text" should appear after "Department111_" "text"
    And "Department112_" "text" should appear after "Department1111_" "text"
    And "Department113_" "text" should appear after "Department112_" "text"
    And I click on "Move" "button" in the "Department111_" "tool_wp > Table tree node"
    And I should see "Department112_" in the "Move" "dialogue"
    And I should see "Department113_" in the "Move" "dialogue"
    And I should not see "Department121_" in the "Move" "dialogue"
    And I follow "After \" Department113_ \""
    And "Department113_" "text" should appear after "Department112_" "text"
    And "Department111_" "text" should appear after "Department113_" "text"
    And "Department1111_" "text" should appear after "Department111_" "text"
    And I log out

  Scenario: Changing department parent field
    And the following departments exist in organisation structure:
      | tenant     | name               | parent         |
      | Tenant1    | Framework11_       |                |
      | Tenant1    | Framework12_       |                |
      | Tenant1    | Department111_     | Framework11_   |
      | Tenant1    | Department112_     | Framework11_   |
      | Tenant1    | Department1111_    | Department111_ |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "Expand department framework 'Framework11_'" "button"
    And "Department1111_" "text" should appear after "Department111_" "text"
    And "Department112_" "text" should appear after "Department1111_" "text"
    And I click on "Edit department 'Department1111_'" "link"
    And I open the autocomplete suggestions list in the "Edit department 'Department1111_'" "dialogue"
    And "Department1111_" "autocomplete_suggestions" should not exist
    And I click on "Department112_" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Save" "button" in the "Edit department 'Department1111_'" "dialogue"
    And "Department112_" "text" should appear after "Department111_" "text"
    And "Department1111_" "text" should appear after "Department112_" "text"
    And I log out

  Scenario: Create child departments
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Test Framework_1 |
      | ID number | FRWK_1001 |
    And I click on "Save" "button" in the "New department framework" "dialogue"
    And I follow "New department for framework 'Test Framework_1'"
    And I set the following fields to these values:
      | Name | Test Department_1 |
      | ID number | DEPT_2001 |
    And I click on "Save" "button" in the "New department for framework 'Test Framework_1'" "dialogue"
    Then I should see "Test Department_1"
    And I click on "New subdepartment for department 'Test Department_1'" "link"
    And I set the following fields to these values:
      | Name | Test Department_1_1 |
      | ID number | DEPT-2002 |
    And I click on "Save" "button" in the "New subdepartment for department 'Test Department_1'" "dialogue"
    Then I should see "Department_1_1"
    And "Test Department_1_1" "text" should appear after "Test Department_1" "text"
    And I click on "New subdepartment for department 'Test Department_1_1'" "link"
    And I set the following fields to these values:
      | Name | Test Department_1_1_1 |
      | ID number | DEPT-2003 |
    And I click on "Save" "button" in the "New subdepartment for department 'Test Department_1_1'" "dialogue"
    And I should see "Test Department_1_1_1"
    And "Test Department_1_1_1" "text" should appear after "Test Department_1_1" "text"
    And I log out

  Scenario: Delete departments and frameworks
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 12345 |
    And I click on "Save" "button" in the "New department framework" "dialogue"
    And I click on "Edit department framework 'Framework1'" "link"
    Then the field "Name" matches value "Framework1"
    And the field "ID number" matches value "12345"
    And I set the field "Name" to "New framework1"
    And I click on "Save" "button" in the "Edit department framework 'Framework1'" "dialogue"
    And I follow "New department for framework 'New framework1'"
    And I set the following fields to these values:
      | Name | Department1 |
      | ID number | 999 |
    And I click on "Save" "button" in the "New department for framework 'New framework1'" "dialogue"
    And I click on "Delete department 'Department1'" "link"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    Then I should not see "Department1"
    And I click on "Delete department framework 'New framework1'" "link"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    Then I should not see "Framework1"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Tenant switch and browse organisation structure
    Given "2" tenants exist with "1" users and "1" courses in each
    When I log in as "admin"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "Departments"
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework0 |
      | ID number | 00000 |
    And I press "Save"
    And I switch to tenant "Tenant1"
    Then I should see "Departments and positions need to be created to proceed with job assignments"
    And I follow "Departments"
    And I should not see "Framework0"
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 11111 |
    And I press "Save"
    Then I should see "Framework1"
    And I should not see "Framework0"
    And I switch to tenant "Tenant2"
    Then I should see "Departments and positions need to be created to proceed with job assignments"
    And I follow "Departments"
    And I should not see "Framework0"
    And I should not see "Framework1"
    And I follow "New framework"
    And I set the following fields to these values:
      | Name | Framework2 |
      | ID number | 22222 |
    And I press "Save"
    Then I should see "Framework2"
    And I should not see "Framework0"
    And I should not see "Framework1"
    And I switch to tenant "Default tenant"
    And I follow "Departments"
    Then I should see "Framework0"
    And I should not see "Framework1"
    And I should not see "Framework2"
    And I switch to tenant "Tenant1"
    And I follow "Departments"
    Then I should see "Framework1"
    And I should not see "Framework0"
    And I should not see "Framework2"
    And I switch to tenant "Tenant2"
    And I follow "Departments"
    Then I should see "Framework2"
    And I should not see "Framework0"
    And I should not see "Framework1"

  Scenario: User with assigninlocked capability can create,edit and delete locked departments
    When the following "permission overrides" exist:
      | capability                       | permission | role       | contextlevel | reference |
      | tool/organisation:assigninlocked | Allow      | orgmanager | System       |           |
    And I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I follow "New framework"
    And I set the following fields to these values:
      | Name      | Framework1 |
      | ID number | 12345      |
    And I click on "Locked" "checkbox"
    And I click on "Save" "button" in the "New department framework" "dialogue"
    And "Edit department framework 'Framework1'" "link" should exist
    And "New department for framework 'Framework1'" "link" should exist
    And "Delete department framework 'Framework1'" "link" should exist
    # Remove capability.
    And the following "permission overrides" exist:
      | capability                       | permission | role       | contextlevel | reference |
      | tool/organisation:assigninlocked | Prohibit   | orgmanager | System       |           |
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    Then "Edit department framework 'Framework1'" "link" should not exist
    And "New department for framework 'Framework1'" "link" should not exist
    And "Delete department framework 'Framework1'" "link" should not exist
    # Users without the capability can not lock frameworks.
    And I follow "New framework"
    And I should not see "Locked" in the "New department framework" "dialogue"
