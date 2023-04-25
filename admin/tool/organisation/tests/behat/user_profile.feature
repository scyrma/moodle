@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure jobs listing on user profile page
  As a manager or user
  I want to be able to see user jobs

  Background:
    Given shared space is enabled
    And the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | user1     | User      | 1         | user1@address.invalid |
      | user2     | User      | 2         | user2@address.invalid |
      | user11    | User      | 11        | user11@address.invalid |
    And the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following users allocations to tenants exist:
      | user      | tenant  |
      | user1     | Tenant1 |
      | user2     | Tenant2 |
      | user11    | Tenant1 |
    And the following "roles" exist:
      | shortname   | name                   | archetype |
      | orgmanager  | Organisation manager   |           |
    And the following "role assigns" exist:
      | user  | role        | contextlevel | reference |
      | user1 | orgmanager  | System       |           |
      | user2 | orgmanager  | System       |           |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | -          | FrameworkS1_    |                |
      | -          | DepartmentS12_  | FrameworkS1_   |
      | Tenant1    | Framework11_    |                |
      | Tenant1    | Department111_  | Framework11_   |
      | Tenant1    | Department112_  | Framework11_   |
      | Tenant2    | Framework21_    |                |
      | Tenant2    | Department211_  | Framework21_   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | -          | FrameworkS1_    |                | 0             | 0                 | 0                 | 0                     |
      | -          | PositionS12_    | FrameworkS1_   | 0             | 0                 | 0                 | 0                     |
      | Tenant1    | Framework11_    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position111_    | Framework11_   |      1        |       7           |       1           |        3              |
      | Tenant1    | Position112_    | Framework11_   |      1        |       7           |       1           |        5              |
      | Tenant2    | Framework21_    |                |      0        |       0           |       0           |        0              |
      | Tenant2    | Position211_    | Framework21_   |      1        |       7           |       1           |        7              |
    And the following "permission overrides" exist:
      | capability                   | permission | role       | contextlevel | reference |
      | tool/organisation:assignjobs | Allow      | orgmanager | System       |           |
      | moodle/site:configview       | Allow      | orgmanager | System       |           |

  Scenario: View job assignments on a users profile page as user
    Given the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | user1 | Department111_ | Position111_ |
      | user1 | Department112_ | Position112_ |
      | user1 | DepartmentS12_ | PositionS12_ |
    When I log in as "user1"
    And I change window size to "large"
    And I follow "Profile" in the user menu
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should see "Position111_" in the ".wp-jobs" "css_element"
    And I should see "Department111_" in the ".wp-jobs" "css_element"
    And I should see "Position112_" in the ".wp-jobs" "css_element"
    And I should see "Department112_" in the ".wp-jobs" "css_element"
    And I should see "PositionS12_" in the ".wp-jobs" "css_element"
    And I should see "DepartmentS12_" in the ".wp-jobs" "css_element"
    And I log out

  Scenario: View job assignments on a users profile page as user after switching tenant
    Given the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | user1 | Department111_ | Position111_ |
      | user1 | Department112_ | Position112_ |
      | user1 | DepartmentS12_ | PositionS12_ |
    # Move tenant
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And "Tenant1" "text" should exist in the "User 1" "table_row"
    And I set the field "Select user 'User 1'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    Then I should see "1 user(s) moved to tenant: Tenant2"
    And I log out
    When I log in as "user1"
    And I change window size to "large"
    And I follow "Profile" in the user menu
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should not see "Position111_" in the ".wp-jobs" "css_element"
    And I should not see "Department111_" in the ".wp-jobs" "css_element"
    And I should not see "Position112_" in the ".wp-jobs" "css_element"
    And I should not see "Department112_" in the ".wp-jobs" "css_element"
    And I should see "PositionS12_" in the ".wp-jobs" "css_element"
    And I should see "DepartmentS12_" in the ".wp-jobs" "css_element"
    And I log out

  Scenario: View job assignments on a users profile page as organisation manager
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user1  | Department111_ | Position111_ |
      | user11 | Department111_ | Position111_ |
      | user11 | Department112_ | Position112_ |
      | user11 | DepartmentS12_ | PositionS12_ |
    When I log in as "user1"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "User 11"
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should see "Position111_" in the ".wp-jobs" "css_element"
    And I should see "Department111_" in the ".wp-jobs" "css_element"
    And I should see "Position112_" in the ".wp-jobs" "css_element"
    And I should see "Department112_" in the ".wp-jobs" "css_element"
    And I should see "PositionS12_" in the ".wp-jobs" "css_element"
    And I should see "DepartmentS12_" in the ".wp-jobs" "css_element"

  Scenario: View job assignments on a users profile page as organisation manager after switching user tenant
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user1  | Department111_ | Position111_ |
      | user11 | Department111_ | Position111_ |
      | user11 | Department112_ | Position112_ |
      | user11 | DepartmentS12_ | PositionS12_ |
      | user2  | Department211_ | Position211_ |
    # Move tenant
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And "Tenant1" "text" should exist in the "User 11" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    Then I should see "1 user(s) moved to tenant: Tenant2"
    And I log out
    And the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department211_ | Position211_ |
    When I log in as "user2"
    And I navigate to "Organisation structure" in workplace launcher
    And I follow "User 11"
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should not see "Position111_" in the ".wp-jobs" "css_element"
    And I should not see "Department111_" in the ".wp-jobs" "css_element"
    And I should not see "Position112_" in the ".wp-jobs" "css_element"
    And I should not see "Department112_" in the ".wp-jobs" "css_element"
    And I should see "PositionS12_" in the ".wp-jobs" "css_element"
    And I should see "DepartmentS12_" in the ".wp-jobs" "css_element"
    And I should see "Position211_" in the ".wp-jobs" "css_element"
    And I should see "Department211_" in the ".wp-jobs" "css_element"

  Scenario: View job assignments on a users profile page as admin in the default tenant
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
      | user11 | Department112_ | Position112_ |
      | user11 | DepartmentS12_ | PositionS12_ |
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I follow "User 11"
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should see "Position111_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "Department111_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "Position112_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "Department112_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "PositionS12_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "DepartmentS12_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"

  Scenario Outline: View job assignments on a users profile page as admin when switching current tenant
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
      | user11 | Department112_ | Position112_ |
      | user11 | DepartmentS12_ | PositionS12_ |
    When I log in as "admin"
    And I switch to tenant "<tenant>"
    And I navigate to "All users" in workplace launcher
    And I follow "User 11"
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should see "Position111_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "Department111_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "Position112_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "Department112_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "PositionS12_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    And I should see "DepartmentS12_" in the "//h3[text() = 'Job assignments']/following-sibling::ul/descendant::div[contains(@class, 'wp-jobs')]" "xpath_element"
    Examples:
      | tenant  |
      | Tenant1 |
      | Tenant2 |

  Scenario: View job assignments on a users profile page as admin when switching user tenant.
    Given the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department111_ | Position111_ |
      | user11 | Department112_ | Position112_ |
      | user11 | DepartmentS12_ | PositionS12_ |
    # Move tenant
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And "Tenant1" "text" should exist in the "User 11" "table_row"
    And I set the field "Select user 'User 11'" to "1"
    And I set the field "With selected users..." to "Tenant2"
    And I press "Allocate users"
    Then I should see "1 user(s) moved to tenant: Tenant2"
    And I log out
    And the following job assignments exist in organisation structure:
      | user   | department     | position     |
      | user11 | Department211_ | Position211_ |
    When I log in as "admin"
    And I navigate to "All users" in workplace launcher
    And I follow "User 11"
    Then I should see "Job assignments" in the ".profile_tree" "css_element"
    And I should see "Position111_" in the ".wp-jobs" "css_element"
    And I should see "Department111_" in the ".wp-jobs" "css_element"
    And "//div[contains(text(), 'Department111_')]/following-sibling::div/i[contains(@title, 'Job tenant does not match user tenant')]" "xpath_element" should exist
    And I should see "Position112_" in the ".wp-jobs" "css_element"
    And I should see "Department112_" in the ".wp-jobs" "css_element"
    And "//div[contains(text(), 'Department112_')]/following-sibling::div/i[contains(@title, 'Job tenant does not match user tenant')]" "xpath_element" should exist
    And I should see "PositionS12_" in the ".wp-jobs" "css_element"
    And I should see "DepartmentS12_" in the ".wp-jobs" "css_element"
    And "//div[contains(text(), 'DepartmentS12_')]/following-sibling::div/i[contains(@title, 'Job tenant does not match user tenant')]" "xpath_element" should not exist
    And I should see "Position211_" in the ".wp-jobs" "css_element"
    And I should see "Department211_" in the ".wp-jobs" "css_element"
    And "//div[contains(text(), 'Department211_')]/following-sibling::div/i[contains(@title, 'Job tenant does not match user tenant')]" "xpath_element" should not exist

  Scenario: Confirm job assignments are not shown on a users profile page when they don't have any
    When I log in as "user2"
    And I follow "Profile" in the user menu
    Then I should not see "Job assignments" in the ".profile_tree" "css_element"
