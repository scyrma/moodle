@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure reporting line management
  As a manager
  I want to be able to manager jobs and manually assigned managers

  Background:
    Given the following "users" exist:
      | username        | firstname | lastname  | email                       |
      | user1           | User      | 1         | user1@address.invalid       |
      | user2           | User      | 2         | user2@address.invalid       |
      | user3           | User      | 3         | user3@address.invalid       |
      | user11          | User      | 11        | user11@address.invalid      |
      | user12          | User      | 12        | user12@address.invalid      |
      | user13          | User      | 13        | user13@address.invalid      |
      | user14          | User      | 14        | user14@address.invalid      |
      | tenantadmin1    | Tenant    | admin     | tenantadmin1@address.invalid|
    And the following tenants exist:
      | name    |
      | Tenant1 |
    And the following users allocations to tenants exist:
      | user      | tenant  |
      | user1     | Tenant1 |
      | user3     | Tenant1 |
      | user11    | Tenant1 |
      | user12    | Tenant1 |
      | user13    | Tenant1 |
      | user14    | Tenant1 |
      | tenantadmin1    | Tenant1 |
    And the following "roles" exist:
      | shortname   | name                   | archetype |
      | orgmanager  | Organisation manager   |           |
    And the following "role assigns" exist:
      | user         | role                      | contextlevel | reference |
      | user1        | orgmanager                | System       |           |
      | user2        | orgmanager                | System       |           |
      | user3        | tool_organisation_manager | System       |           |
      | tenantadmin1 | tool_tenant_admin         | System       |           |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Framework12_    |                |
      | Tenant1    | Department111_  | Framework11_   |
      | Tenant1    | Department112_  | Framework11_   |
      | Tenant1    | Department113_  | Framework11_   |
      | Tenant1    | Department1111_ | Department111_ |
      | Tenant1    | Department121_  | Framework12_   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework11_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Framework12_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position111_    | Framework11_   |      1        |       7           |       1           |        3              |
      | Tenant1    | Position112_    | Framework11_   |      0        |       7           |       1           |        5              |
      | Tenant1    | Position113_    | Framework11_   |      1        |       7           |       0           |        5              |
      | Tenant1    | Position1111_   | Position111_   |      0        |       0           |       0           |        0              |
      | Tenant1    | Position121_    | Framework12_   |      0        |       7           |       1           |        5              |
    And the following "permission overrides" exist:
      | capability                            | permission | role       | contextlevel | reference |
      | tool/organisation:assignjobs          | Allow      | orgmanager | System       |           |
      | moodle/site:configview                | Allow      | orgmanager | System       |           |
      | tool/organisation:assignmanuallymgr   | Allow      | orgmanager | System       |           |

  Scenario: Assign job to people
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position113_ |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I press "View jobs and reporting lines" action in the "User 11" report row
    And "Manager" "text" should exist in the "Position113_" "table_row"
    And "organisation:receivenotificationsglob" permission should be "enabled" in the "Position113_" "table_row"
    And "organisation:viewusersreportglob" permission should be "enabled" in the "Position113_" "table_row"
    And "organisation:allocateuserstoprogramcertificationsglob" permission should be "enabled" in the "Position113_" "table_row"
    And I click on "Assign job" "button"
    And I set the following fields in the "New job for 'User 11'" "dialogue" to these values:
      | positionid       | Position112_    |
      | departmentid     | Department112_  |
      | enddate[enabled] | 1               |
      | enddate[year]    | 2030            |
    And I click on "Proceed" "button" in the "New job for 'User 11'" "dialogue"
    Then the following should exist in the "userjobs-section-jobsassigned" table:
      | -1-                             | -1-                                 |
      | Position111_ · Department111_ 	| From ##today##%d/%m/%y## to Present |
      | Position112_ · Department112_   | From ##today##%d/%m/%y## to Present |
    And I should see "Job created" in the ".toast-message" "css_element"
    # 'Permissions' column contents are checked now.
    And "Department lead" "text" should exist in the "Position112_" "table_row"
    And "organisation:allocateuserstoprogramcertificationsdept" permission should be "enabled" in the "Position112_" "table_row"
    And "organisation:viewusersreportdept" permission should be "disabled" in the "Position112_" "table_row"
    And "organisation:receivenotificationsdept" permission should be "enabled" in the "Position112_" "table_row"

  Scenario: Edit assigned jobs dates
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | enddate              |
      | user11 | Department111_ | Position111_ | ##+1 day##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I press "View jobs and reporting lines" action in the "User 11" report row
    And I press "Edit dates" action in the "Position111_" report row
    And I set the following fields in the "Edit job dates for 'User 11'" "dialogue" to these values:
      | enddate[enabled] | 1            |
      | End date         | ##+45 day##  |
    And I click on "Save" "button" in the "Edit job dates for 'User 11'" "dialogue"
    And I should see "Job updated" in the ".toast-message" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | -1-                            | -1-                                                |
      | Position111_ · Department111_  | From ##today##%d/%m/%y## to ##+45 day##%d/%m/%y##  |
    # Let's check that 'not active' badge is showing when enddate reach.
    And I press "Edit dates" action in the "Position111_" report row
    And I set the following fields in the "Edit job dates for 'User 11'" "dialogue" to these values:
      | enddate[enabled] | 1            |
      | End date         | ##today## |
    And I click on "Save" "button" in the "Edit job dates for 'User 11'" "dialogue"
    And I should see "Job updated" in the ".toast-message" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | -1-                            | -1-                                                |
      | Position111_ · Department111_  | From ##today##%d/%m/%y## to ##today##%d/%m/%y##  |
    And "Not active" "text" should exist in the "Position111_" "table_row"

  Scenario: Finish assigned job
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | startdate              |
      | user11 | Department111_ | Position111_ | ##-1 day##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I press "View jobs and reporting lines" action in the "User 11" report row
    And I press "Set job as finished" action in the "Position111_" report row
    And I set the following fields in the "Set job as finished" "dialogue" to these values:
      | End date | ##-1 month## |
    And I click on "Proceed" "button" in the "Set job as finished" "dialogue"
    And I should see "The end date must be after the start date." in the "Set job as finished" "dialogue"
    And I set the following fields in the "Set job as finished" "dialogue" to these values:
      | End date         | ##+1 month## |
    And I click on "Proceed" "button" in the "Set job as finished" "dialogue"
    And I should see "Job updated" in the ".toast-message" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | -1-                            | -1-                                                 |
      | Position111_ · Department111_  | From ##-1 day##%Y-%m-%d## to ##+1 month##%d/%m/%y## |

  Scenario: Transfer job to a new one
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     | startdate               |
      | user11 | Department111_ | Position111_ | ##yesterday##%Y-%m-%d## |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I press "View jobs and reporting lines" action in the "User 11" report row
    And I press "Transfer this job to a new one" action in the "Position111_" report row
    And I should see "Current: Position111_" in the "Transfer 'User 11' to a new job" "dialogue"
    And I should see "Current: Department111_" in the "Transfer 'User 11' to a new job" "dialogue"
    And I set the following fields in the "Transfer 'User 11' to a new job" "dialogue" to these values:
      | Position         | Position112_   |
      | Department       | Department112_ |
      | startdate[day]   | ##today##%d##  |
      | startdate[month] | ##today##%B##  |
      | startdate[year]  | ##today##%Y##  |
    And I click on "Proceed" "button" in the "Transfer 'User 11' to a new job" "dialogue"
    And I should see "User transfered to a new job" in the ".toast-message" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | -1-                            | -1-                                 |
      | Position112_ · Department112_  | From ##today##%d/%m/%y## to Present |
    And the following should not exist in the "reportbuilder-table" table:
      | -1-                            | -1-                                 |
      | Position111_ · Department111_  | From ##yesterday##%Y-%m-%d## to Present |
    And I reload the page
    And I open the action menu in "Department111_" "table_row"
    And I should not see "Transfer this user to a new job"

  Scenario: Delete assigned jobs
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I press "View jobs and reporting lines" action in the "User 11" report row
    And I press "Delete job" action in the "Position111_" report row
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    Then the following should exist in the "userjobs-section-jobsassigned" table:
      | -1-                             | -1-                                 |
      | Position111_ · Department111_ 	| From ##today##%d/%m/%y## to Present |
    And I press "Delete job" action in the "Position111_" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Position111_"
    And I should see "Job deleted" in the ".toast-message" "css_element"

  Scenario Outline: Assign manually assigned manager
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "<user>"
    And I click on "Assign manager" "button"
    Then I open the autocomplete suggestions list in the "Assign manager" "dialogue"
    And "User 11" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    And I press the escape key
    And I click on "View user reports" "checkbox" in the "Assign manager" "dialogue"
    And I click on "Save" "button" in the "Assign manager" "dialogue"
    And I should see "Successfully assigned" in the ".toast-message" "css_element"
    Then the following should exist in the "userjobs-section-reportsto" table:
      | -1-         |
      | User 12 	|
    # 'Permissions' column contents are checked now.
    And "Manager (assigned manually)" "text" should exist in the "User 12" "table_row"
    And "organisation:allocateuserstoprogramcertificationsmam" permission should be "disabled" in the "User 12" "table_row"
    And "organisation:viewusersreportmam" permission should be "enabled" in the "User 12" "table_row"
    And "organisation:receivenotificationsmam" permission should be "disabled" in the "User 12" "table_row"
    Examples:
      | user         |
      | user1        |
      | tenantadmin1 |

  Scenario Outline: Assign manually assigned staff
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "<user>"
    And I click on "Assign staff" "button"
    Then I open the autocomplete suggestions list in the "Assign staff" "dialogue"
    And "User 11" "autocomplete_suggestions" should not exist
    And I click on "User 14" item in the autocomplete list
    And I press the escape key
    And I click on "View user reports" "checkbox" in the "Assign staff" "dialogue"
    And I click on "Save" "button" in the "Assign staff" "dialogue"
    And I should see "Successfully assigned" in the ".toast-message" "css_element"
    Then the following should exist in the "userjobs-section-peoplereportingto" table:
      | User        |
      | User 14 	|
    # 'Permissions' column contents are checked now.
    And "Manually assigned" "text" should exist in the "User 14" "table_row"
    And "organisation:allocateuserstoprogramcertificationsmam" permission should be "disabled" in the "User 14" "table_row"
    And "organisation:viewusersreportmam" permission should be "enabled" in the "User 14" "table_row"
    And "organisation:receivenotificationsmam" permission should be "disabled" in the "User 14" "table_row"
    Examples:
      | user         |
      | user1        |
      | tenantadmin1 |

  Scenario Outline: Edit manually assigned manager
    Given user "user12" is a manually assigned manager over users "user11" with permissions "1"
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "<user>"
    Then the following should exist in the "userjobs-section-reportsto" table:
      | -1-         |
      | User 12 	|
    And "Manager (assigned manually)" "text" should exist in the "User 12" "table_row"
    And "organisation:allocateuserstoprogramcertificationsmam" permission should be "enabled" in the "User 12" "table_row"
    And "organisation:viewusersreportmam" permission should be "disabled" in the "User 12" "table_row"
    And "organisation:receivenotificationsmam" permission should be "disabled" in the "User 12" "table_row"
    And I press "Edit assignment" action in the "User 12" report row
    And I click on "View user reports" "checkbox" in the "Edit assignment" "dialogue"
    And I click on "Save" "button" in the "Edit assignment" "dialogue"
    And I should see "Successfully updated" in the ".toast-message" "css_element"
    And "organisation:allocateuserstoprogramcertificationsmam" permission should be "enabled" in the "User 12" "table_row"
    And "organisation:viewusersreportmam" permission should be "enabled" in the "User 12" "table_row"
    And "organisation:receivenotificationsmam" permission should be "disabled" in the "User 12" "table_row"
    Examples:
      | user         |
      | user1        |
      | tenantadmin1 |

  Scenario Outline: Edit manually assigned manager staff
    Given user "user11" is a manually assigned manager over users "user14" with permissions "2"
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "<user>"
    Then the following should exist in the "userjobs-section-peoplereportingto" table:
      | User        |
      | User 14 	|
    And "Manually assigned" "text" should exist in the "User 14" "table_row"
    And "organisation:allocateuserstoprogramcertificationsmam" permission should be "disabled" in the "User 14" "table_row"
    And "organisation:viewusersreportmam" permission should be "enabled" in the "User 14" "table_row"
    And "organisation:receivenotificationsmam" permission should be "disabled" in the "User 14" "table_row"
    And I press "Edit assignment" action in the "User 14" report row
    And I click on "Receive notifications" "checkbox" in the "Edit assignment" "dialogue"
    And I click on "Save" "button" in the "Edit assignment" "dialogue"
    And I should see "Successfully updated" in the ".toast-message" "css_element"
    And "organisation:allocateuserstoprogramcertificationsmam" permission should be "disabled" in the "User 14" "table_row"
    And "organisation:viewusersreportmam" permission should be "enabled" in the "User 14" "table_row"
    And "organisation:receivenotificationsmam" permission should be "enabled" in the "User 14" "table_row"
    Examples:
      | user         |
      | user1        |
      | tenantadmin1 |

  Scenario Outline: Delete manually assigned manager
    Given user "user12" is a manually assigned manager over users "user11" with permissions "1"
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "<user>"
    And I press "Un-assign manager" action in the "User 12" report row
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    Then the following should exist in the "userjobs-section-reportsto" table:
      | -1-         |
      | User 12 	|
    And I press "Un-assign manager" action in the "User 12" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "User 12"
    And I should see "Successfully un-assigned" in the ".toast-message" "css_element"
    Examples:
      | user         |
      | user1        |
      | tenantadmin1 |

  Scenario Outline: Delete manually assigned manager staff
    Given user "user11" is a manually assigned manager over users "user14" with permissions "2"
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "<user>"
    And I press "Un-assign person" action in the "User 14" report row
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    Then the following should exist in the "userjobs-section-peoplereportingto" table:
      | User        |
      | User 14 	|
    And I press "Un-assign person" action in the "User 14" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "User 14"
    And I should see "Successfully un-assigned" in the ".toast-message" "css_element"
    Examples:
      | user         |
      | user1        |
      | tenantadmin1 |

  Scenario: Navigate through reporting line pages
    Given user "user11" is a manually assigned manager over users "user14" with permissions "2"
    Given user "user12" is a manually assigned manager over users "user11" with permissions "2"
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "user1"
    Then the following should exist in the "userjobs-section-reportsto" table:
      | -1-         |
      | User 12 	|
    And the following should exist in the "userjobs-section-peoplereportingto" table:
      | User        |
      | User 14 	|
    And I press "View jobs and reporting lines" action in the "User 12" report row
    And the following should exist in the "userjobs-section-peoplereportingto" table:
      | User        |
      | User 11 	|
    And I press "View jobs and reporting lines" action in the "User 11" report row
    And I press "View jobs and reporting lines" action in the "User 14" report row
    And the following should exist in the "userjobs-section-reportsto" table:
      | -1-         |
      | User 11 	|

  Scenario: Observe reporting line changes
    Given the following job assignments exist in organisation structure:
      | user   | department     | position      |
      | user11 | Department112_ | Position1111_ |
      | user12 | Department111_ | Position111_  |
      | user14 | Department111_ | Position1111_ |
    Given user "user3" is a manually assigned manager over users "user11" with permissions "2"
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "user1"
    Then the following should exist in the "userjobs-section-reportsto" table:
      | -1-        |
      | User 12	   |
      | User 3     |
    And "Manager (assigned manually)" "text" should exist in the "User 3" "table_row"
    And "Manager" "text" should exist in the "User 12" "table_row"
    And I press "Delete job" action in the "Position1111_" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And the following should not exist in the "userjobs-section-reportsto" table:
      | -1-     |
      | User 3 	|
    And I click on "Assign job" "button"
    And I set the following fields in the "New job for 'User 11'" "dialogue" to these values:
      | positionid       | Position112_    |
      | departmentid     | Department111_  |
      | enddate[enabled] | 1               |
      | enddate[year]    | 2030            |
    And I click on "Proceed" "button" in the "New job for 'User 11'" "dialogue"
    And the following should exist in the "userjobs-section-peoplereportingto" table:
      | User    |
      | User 14 |

  Scenario: Manager position in multiple departments
    Given the following job assignments exist in organisation structure:
      | user   | department     | position      |
      | user11 | Department111_ | Position111_  |
      | user11 | Department112_ | Position111_  |
      | user12 | Department111_ | Position1111_ |
      | user13 | Department112_ | Position1111_ |
    When I am on the "user11" "tool_organisation > Jobs page" page logged in as "user1"
    Then I should see "User 12" in the ".userjobs-section-peoplereportingto" "css_element"
    And I should see "User 13" in the ".userjobs-section-peoplereportingto" "css_element"
