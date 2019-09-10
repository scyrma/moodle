@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure departments management
  As a manager
  I want to be able to modify departments

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
    And the following "roles" exist:
      | shortname  | name                 | archetype |
      | orgmanager | Organisation manager |           |
    And the following "role assigns" exist:
      | user  | role       | contextlevel | reference |
      | user1 | orgmanager | System       |           |
      | user2 | orgmanager | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Organisation manager" role:
      | capability                          | permission |
      | tool/organisation:managedepartments | Allow      |
      | moodle/site:configview              | Allow      |
    And I log out

  Scenario: Create and edit departments and frameworks
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New department framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 12345 |
    And I press "Save" in the modal form dialogue
    And I click on "Edit department framework 'Framework1'" "link"
    Then the field "Name" matches value "Framework1"
    And the field "ID number" matches value "12345"
    And I set the field "Name" to "New framework1"
    And I press "Save" in the modal form dialogue
    And I follow "New department for framework 'New framework1'"
    And I set the following fields to these values:
      | Name | Department1 |
      | ID number | 999 |
    And I press "Save" in the modal form dialogue
    Then I should see "Department1"
    And I click on "Edit name" "link" in the "Department1" table tree node
    And I set the field "New name for 'Department1'" to "New department1"
    And I press key "13" in the field "New name for 'Department1'"
    And I should see "New department1"
    And I should not see "Department1"
    # Edit link is not updated after name update.
    And I click on "Edit department 'Department1'" "link"
    And I set the following fields to these values:
      | Name | New department2 |
      | ID number | 123 |
      | Description | Any description|
    And I press "Save" in the modal form dialogue
    Then  I should see "New department2"
    And I should not see "New department1"
    Then I click on "Edit department 'New department2'" "link"
    And the field "ID number" matches value "123"
    And the field "Description" matches value "Any description"
    And I press "Cancel" in the modal form dialogue
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

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
    And I click on "Move" "button" in the "Department111_" table tree node
    And I should see "Department112_" in the "Move" "dialogue"
    And I should see "Department113_" in the "Move" "dialogue"
    And I should not see "Department121_" in the "Move" "dialogue"
    And I follow "After \" Department113_ \""
    And "Department113_" "text" should appear after "Department112_" "text"
    And "Department111_" "text" should appear after "Department113_" "text"
    And "Department1111_" "text" should appear after "Department111_" "text"
    And I log out

  Scenario: Create child departments
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New department framework"
    And I set the following fields to these values:
      | Name | Test Framework_1 |
      | ID number | FRWK_1001 |
    And I press "Save" in the modal form dialogue
    And I follow "New department for framework 'Test Framework_1'"
    And I set the following fields to these values:
      | Name | Test Department_1 |
      | ID number | DEPT_2001 |
    And I press "Save" in the modal form dialogue
    Then I should see "Test Department_1"
    And I click on "New subdepartment for department 'Test Department_1'" "link"
    And I set the following fields to these values:
      | Name | Test Department_1_1 |
      | ID number | DEPT-2002 |
    And I press "Save" in the modal form dialogue
    Then I should see "Department_1_1"
    And "Test Department_1_1" "text" should appear after "Test Department_1" "text"
    And I click on "New subdepartment for department 'Test Department_1_1'" "link"
    And I set the following fields to these values:
      | Name | Test Department_1_1_1 |
      | ID number | DEPT-2003 |
    And I press "Save" in the modal form dialogue
    And I should see "Test Department_1_1_1"
    And "Test Department_1_1_1" "text" should appear after "Test Department_1_1" "text"
    And I log out

  Scenario: Delete departments and frameworks
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Departments" tab because all other tabs are disabled.
    And I follow "New department framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 12345 |
    And I press "Save" in the modal form dialogue
    And I click on "Edit department framework 'Framework1'" "link"
    Then the field "Name" matches value "Framework1"
    And the field "ID number" matches value "12345"
    And I set the field "Name" to "New framework1"
    And I press "Save" in the modal form dialogue
    And I follow "New department for framework 'New framework1'"
    And I set the following fields to these values:
      | Name | Department1 |
      | ID number | 999 |
    And I press "Save" in the modal form dialogue
    And I click on "Delete department 'Department1'" "link"
    And I click on "Delete" "button" in the ".confirmation-dialogue" "css_element"
    Then I should not see "Department1"
    And I click on "Delete department framework 'New framework1'" "link"
    And I click on "Delete" "button" in the ".confirmation-dialogue" "css_element"
    Then I should not see "Framework1"
