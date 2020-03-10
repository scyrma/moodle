@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Check datasources for certifications
  In order to check datasources
  As a manager
  I need be able to create reports with them

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname     | archived | tenant  |
      | Program name | 0        | Tenant1 |
    Given the following tool certification data "certifications" exist:
      | fullname         | archived | tenant  | program       |
      | Certification_1A | 0        | Tenant1 | Program name  |
      | Certification_2B | 0        | Tenant1 | Program name  |
    Given the following "users" exist:
      | username      | firstname | lastname | email                |
      | user1         | User      | a        | user1@example.com    |
      | user2         | User      | b        | user2@example.com    |
      | user3         | User      | c        | user3@example.com    |
      | orgmanager    | User      | d        | user4@example.com    |
      | user4         | User      | e        | user3@example.com    |
      | manager1      | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user1      | Tenant1 |
      | user2      | Tenant1 |
      | user3      | Tenant1 |
      | orgmanager | Tenant1 |
      | user4      | Tenant1 |
      | manager1   | Tenant1 |
    Given the following users allocations to certifications exist:
      | certification    | user   |
      | Certification_1A | user1  |
      | Certification_1A | user2  |
      | Certification_2B | user1  |
      | Certification_1A | user4  |
    And the following "role assigns" exist:
      | user       | role                       | contextlevel | reference |
      | manager1   | tool_certification_manager | System       |           |
      | manager1   | tool_reportbuilder_manager | System       |           |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1 | Framework_t1   |
      | Tenant1    | Deparment_t1_d2 | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2  | Position_t1_f1 |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f3  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f4  | Position_t1_f3 |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user        | department      | position       |
      | manager1    | Deparment_t1_d1 | Position_t1_f1 |
      | orgmanager  | Deparment_t1_d1 | Position_t1_f1 |
      | user1       | Deparment_t1_d1 | Position_t1_f2 |
      | user2       | Deparment_t1_d1 | Position_t1_f2 |
      | manager1    | Deparment_t1_d2 | Position_t1_f3 |
      | user4       | Deparment_t1_d2 | Position_t1_f4 |
    And the following custom reports exist:
      | name       | tenant  | source                                                                                 |
      | Report1    | Tenant1 | tool_certification\tool_reportbuilder\datasources\report_certification_user_allocation |
      | Report2    | Tenant1 | tool_certification\tool_reportbuilder\datasources\report_certifications                |

  Scenario: Manager can create a user allocation and a certifications report
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    # Section Titles
    Then I should see "Certification"
    And I should see "User allocation"
    And I should see "User completion"
    And I should see "Certified by"
    And I should see "Revoked by"
    And I should see "User"
    And I should see "Job assignments"
    And I should see "Current program"
    And I should see "Program user allocation"
    # Columns
    And I should see "Certification" in the "#entity_tool_certification" "css_element"
    And I should see "ID number" in the "#entity_tool_certification" "css_element"
    And I should see "Certification name" in the "#entity_tool_certification" "css_element"
    And I should see "Allocation start date" in the "#entity_tool_certification" "css_element"
    And I should see "Allocation end date" in the "#entity_tool_certification" "css_element"
    And I should see "Archived" in the "#entity_tool_certification" "css_element"
    And I should see "Archived on" in the "#entity_tool_certification" "css_element"
    And I should see "Due date" in the "#entity_tool_program_users" "css_element"
    And I should see "Start date" in the "#entity_tool_program_users" "css_element"
    And I should see "Expiry date" in the "#entity_tool_certification_compltion" "css_element"
    And I should see "Suspended" in the "#entity_tool_certification_users" "css_element"
    And I should see "Certified date" in the "#entity_tool_certification_compltion" "css_element"
    And I should see "Program" in the "#entity_tool_program" "css_element"
    And I should see "User" in the "#entity_user" "css_element"
    Then I should see "Certification_1A" in the "[data-region='report-table']" "css_element"
    And I should see "Certification_2B" in the "[data-region='report-table']" "css_element"
    And I should see "User a" in the "[data-region='report-table']" "css_element"
    And I should see "User b" in the "[data-region='report-table']" "css_element"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Archived" in the ".filters-list" "css_element"
    And I follow "Filters"
    And I should see "Allocation date" in the "ul.js-filters-list" "css_element"
    And I should see "Certified date" in the "ul.js-filters-list" "css_element"
    And I should see "Expiry date" in the "ul.js-filters-list" "css_element"
    And I should see "Certification name" in the "ul.js-filters-list" "css_element"
    And I should see "Full name" in the "ul.js-filters-list" "css_element"
    And I should see "Position" in the "ul.js-filters-list" "css_element"
    And I should see "Department" in the "ul.js-filters-list" "css_element"
    And I should see "Programs" in the "ul.js-filters-list" "css_element"
    And I should see "Certification status" in the "ul.js-filters-list" "css_element"
    Then I click on "[data-field='tool_program_users:duedate']" "css_element"
    And I should see "Due date" in the "[data-region='report-table']" "css_element"
    Then I click on "[data-field='tool_program:fullname']" "css_element"
    And I should see "Program name" in the "[data-region='report-table']" "css_element"
    # Navigate to Report2
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report2" "table_row"
    # Section Titles
    Then I should see "Certification"
    And I should see "User allocation"
    And I should see "Program"
    And I should see "Program content"
    And I should see "User"
    And I should see "Program course"
    # Columns
    Then I should see "Certification name" in the "#entity_tool_certification" "css_element"
    And I should see "ID number" in the "#entity_tool_certification" "css_element"
    And I should see "Tags" in the "#entity_tool_certification" "css_element"
    And I should see "Archived" in the "#entity_tool_certification" "css_element"
    And I should see "Archived on" in the "#entity_tool_certification" "css_element"
    And I should see "Start date" in the "#entity_tool_certification" "css_element"
    And I should see "Due date" in the "#entity_tool_certification" "css_element"
    And I should see "Expiry date" in the "#entity_tool_certification" "css_element"
    And I should see "Allocation start date" in the "#entity_tool_certification" "css_element"
    And I should see "Allocation end date" in the "#entity_tool_certification" "css_element"
    And I should see "Last modified on" in the "#entity_tool_certification" "css_element"
    And I should see "Created on" in the "#entity_tool_certification" "css_element"
    And I should see "Certification name" in the "[data-region='report-table']" "css_element"
    And I should see "Program name with image" in the "[data-region='report-table']" "css_element"
    And I should see "Number of courses" in the "[data-region='report-table']" "css_element"
    And I should see "Courses in set with links" in the "[data-region='report-table']" "css_element"
    Then I should see "Certification_1A" in the "[data-region='report-table']" "css_element"
    And I should see "Certification_2B" in the "[data-region='report-table']" "css_element"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Archived" in the ".filters-list" "css_element"
    And I should see "Visible" in the ".filters-list" "css_element"
    And I follow "Filters"
    And I should see "Certification name" in the "ul.js-filters-list" "css_element"
    And I should see "Last modified on" in the "ul.js-filters-list" "css_element"
    And I should see "Created on" in the "ul.js-filters-list" "css_element"
    And I should see "Programs" in the "ul.js-filters-list" "css_element"
    And I should see "Contains course" in the "ul.js-filters-list" "css_element"
    And I click on "Add field 'Program name' to the report" "link"