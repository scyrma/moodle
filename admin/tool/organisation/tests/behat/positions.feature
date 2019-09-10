@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure positions management
  As a manager
  I want to be able to modify positions

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
      | tool/organisation:managepositions   | Allow      |
      | moodle/site:configview              | Allow      |
    And I log out

  Scenario: Create and edit positions and frameworks
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Positions" tab because all other tabs are disabled.
    And I follow "New position framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 12345 |
    And I press "Save" in the modal form dialogue
    And I click on "Edit position framework 'Framework1'" "link"
    Then the field "Name" matches value "Framework1"
    And the field "ID number" matches value "12345"
    And I set the field "Name" to "New framework1"
    And I press "Save" in the modal form dialogue
    And I follow "New position for framework 'New framework1'"
    And I set the following fields to these values:
      | Name | Position1 |
      | ID number | 999 |
    And I press "Save" in the modal form dialogue
    Then I should see "Position1"
    And I click on "Edit name" "link" in the "Position1" table tree node
    And I set the field "New name for 'Position1'" to "New position1"
    And I press key "13" in the field "New name for 'Position1'"
    And I should see "New position1"
    And I should not see "Position1"
    # Edit link is not updated after name update.
    And I click on "Edit position 'Position1'" "link"
    And I set the following fields to these values:
      | Name | New position2 |
      | ID number | 123 |
      | Description | Any description|
    And I press "Save" in the modal form dialogue
    Then I should see "New position2"
    And I should not see "New position1"
    Then I click on "Edit position 'New position2'" "link"
    And the field "ID number" matches value "123"
    And the field "Description" matches value "Any description"
    And I press "Cancel" in the modal form dialogue
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Changing sort order of positions
    And the following positions exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
      | Tenant1    | Position111_    | Framework11_   |
      | Tenant1    | Position112_    | Framework11_   |
      | Tenant1    | Position113_    | Framework11_   |
      | Tenant1    | Position1111_   | Position111_   |
      | Tenant1    | Position121_    | Framework12_   |
      | Tenant2    | Framework21_    |                |
      | Tenant2    | Framework22_    |                |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And "Framework12_" "text" should appear after "Framework11_" "text"
    And I click on "Move position framework 'Framework12_'" "button"
    And I follow "To the top of the list"
    And "Framework11_" "text" should appear after "Framework12_" "text"
    And I click on "Expand position framework 'Framework11_'" "button"
    And "Position1111_" "text" should appear after "Position111_" "text"
    And "Position112_" "text" should appear after "Position1111_" "text"
    And "Position113_" "text" should appear after "Position112_" "text"
    And I click on "Move" "button" in the "Position111_" table tree node
    And I should see "Position112_" in the "Move" "dialogue"
    And I should see "Position113_" in the "Move" "dialogue"
    And I should not see "Position121_" in the "Move" "dialogue"
    And I follow "After \" Position113_ \""
    And "Position113_" "text" should appear after "Position112_" "text"
    And "Position111_" "text" should appear after "Position113_" "text"
    And "Position1111_" "text" should appear after "Position111_" "text"
    And I log out

  Scenario: Create child positions
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Positions" tab because all other tabs are disabled.
    And I follow "New position framework"
    And I set the following fields to these values:
      | Name | Test Framework_1 |
      | ID number | FRWK-1001 |
    And I press "Save" in the modal form dialogue
    And I follow "New position for framework 'Test Framework_1'"
    And I set the following fields to these values:
      | Name | Test Position_1 |
      | ID number | POS-2001 |
    And I press "Save" in the modal form dialogue
    Then I should see "Test Position_1"
    And I click on "New subposition for position 'Test Position_1'" "link"
    And I set the following fields to these values:
      | Name | Test Position_1_1 |
      | ID number | POS-2002 |
    And I press "Save" in the modal form dialogue
    Then I should see "Test Position_1_1"
    And "Test Position_1_1" "text" should appear after "Test Position_1" "text"
    And I click on "New subposition for position 'Test Position_1_1'" "link"
    And I set the following fields to these values:
      | Name | Test Position_1_1_1 |
      | ID number | POS-2003 |
    And I press "Save" in the modal form dialogue
    Then I should see "Test Position_1_1_1"
    And "Test Position_1_1_1" "text" should appear after "Test Position_1_1" "text"
    And I log out

  Scenario: Add hardcoded permissions for position but not for framework and show them on form/list page
    And the following positions exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
      | Tenant1    | Position111_    | Framework11_   |
      | Tenant1    | Position112_    | Framework11_   |
      | Tenant1    | Position113_    | Framework11_   |
      | Tenant1    | Position1111_   | Position111_   |
      | Tenant1    | Position121_    | Framework12_   |
      | Tenant2    | Framework21_    |                |
      | Tenant2    | Framework22_    |                |
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Positions" tab because all other tabs are disabled.
    And I follow "New position framework"
    And I set the following fields to these values:
      | Name | Framework33_ |
      | ID number | FRWK-1001 |
    And I should not see "Global manager"
    And I press "Save" in the modal form dialogue
    Then "Framework33_" "text" should appear after "Framework12_" "text"
    And I click on "Expand position framework 'Framework11_'" "button"
    And I click on "Edit position 'Position111_'" "link"
    Then I should see "Global manager"
    Then I should see "Department manager"
    And I click on "Global manager" "checkbox"
    And I set the following fields to these values:
      | globmgrpermission[0] | 1 |
      | globmgrpermission[1] | 0 |
      | globmgrpermission[2] | 1 |
    And I press "Save" in the modal form dialogue
    And "Global manager" "text" should exist in the "Position111_" table tree node
    And "Department manager" "text" should not exist in the "Position111_" table tree node
    And "organisation:allocateuserstoprogramcertificationsglob" permission should be "enabled" in the "Position111_" table tree node
    And "organisation:viewusersreportglob" permission should be "disabled" in the "Position111_" table tree node
    And "organisation:receivenotificationsglob" permission should be "enabled" in the "Position111_" table tree node
    And I click on "Edit position 'Position111_'" "link"
    And the following fields match these values:
      | globmgrpermission[0] | 1 |
      | globmgrpermission[1] | 0 |
      | globmgrpermission[2] | 1 |
    And I click on "Department manager" "checkbox"
    And I set the following fields to these values:
      | deptmgrpermission[0] | 1 |
      | deptmgrpermission[1] | 1 |
      | deptmgrpermission[2] | 0 |
    And I press "Save" in the modal form dialogue
    And "Global manager" "text" should exist in the "Position111_" table tree node
    And "Department manager" "text" should exist in the "Position111_" table tree node
    And "organisation:allocateuserstoprogramcertificationsglob" permission should be "enabled" in the "Position111_" table tree node
    And "organisation:viewusersreportglob" permission should be "disabled" in the "Position111_" table tree node
    And "organisation:receivenotificationsglob" permission should be "enabled" in the "Position111_" table tree node
    And "organisation:allocateuserstoprogramcertificationsdept" permission should be "enabled" in the "Position111_" table tree node
    And "organisation:viewusersreportdept" permission should be "enabled" in the "Position111_" table tree node
    And "organisation:receivenotificationsdept" permission should be "disabled" in the "Position111_" table tree node
    And I log out

  Scenario: Delete positions and frameworks
    When I log in as "user1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    # Here I should be on a "Positions" tab because all other tabs are disabled.
    And I follow "New position framework"
    And I set the following fields to these values:
      | Name | Framework1 |
      | ID number | 12345 |
    And I press "Save" in the modal form dialogue
    And I click on "Edit position framework 'Framework1'" "link"
    Then the field "Name" matches value "Framework1"
    And the field "ID number" matches value "12345"
    And I set the field "Name" to "New framework1"
    And I press "Save" in the modal form dialogue
    And I follow "New position for framework 'New framework1'"
    And I set the following fields to these values:
      | Name | Position1 |
      | ID number | 999 |
    And I press "Save" in the modal form dialogue
    And I click on "Delete position 'Position1'" "link"
    And I click on "Delete" "button" in the ".confirmation-dialogue" "css_element"
    Then I should not see "Position1"
    And I click on "Delete position framework 'New framework1'" "link"
    And I click on "Delete" "button" in the ".confirmation-dialogue" "css_element"
    Then I should not see "New framework1"
