@tool @tool_program @moodleworkplace @javascript
Feature: Active programs table pagination
  In order to manage programs
  As a manager
  I need to be view the active programs table with a pagination

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Prog b | 0 |  Tenant1 |
      | Prog i | 0 |  Tenant1 |
      | Prog j | 0 |  Tenant1 |
      | Prog c | 0 |  Tenant1 |
      | Prog g | 0 |  Tenant1 |
      | Prog d | 0 |  Tenant1 |
      | Prog h | 0 |  Tenant1 |
      | Prog e | 0 |  Tenant1 |
      | Prog f | 0 |  Tenant1 |
      | Prog k | 0 |  Tenant1 |
      | Prog l | 0 |  Tenant1 |
      | Prog a | 0 |  Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
      | manager2 | tool_program_manager | System       |           |

  Scenario: Change page
    When I log in as "manager1"
    Then I navigate to "Programs" in site administration
    Then I click on "Active" "link"
    And I should see "Prog a" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog b" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog c" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog d" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog e" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog f" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog g" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog h" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog i" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog j" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog k" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog l" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And ".tool_reportbuilder_report .pagination" "css_element" should exist
    And I should see "Next" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Prog a" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog b" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog c" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog d" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog e" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog f" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog g" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog h" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog i" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog j" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog k" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog l" in the "#program_manager_list_active_tab table.report-table" "css_element"
    # Begin - Check events in action still working.
    Then I click on ".archive_program" "css_element" in the "#program_manager_list_active_tab td.cell.c3" "css_element"
    Then I click on "Cancel" "button" in the ".confirmation-dialogue" "css_element"
    Then I click on ".duplicate_program" "css_element" in the "#program_manager_list_active_tab td.cell.c3" "css_element"
    Then I click on "Cancel" "button" in the ".confirmation-dialogue" "css_element"
    # End - Check events in action still working.
    And I should see "Prev" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Prev" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "Prog a" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog b" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog c" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog d" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog e" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog f" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog g" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog h" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog i" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog j" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog k" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog l" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I click on "Next" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Prog a" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog b" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog c" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog d" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog e" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog f" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog g" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog h" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog i" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should not see "Prog j" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog k" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I should see "Prog l" in the "#program_manager_list_active_tab table.report-table" "css_element"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Programs" in site administration
    And I should see "Programs"
    Then I click on "Active" "link"
    And I should see "Nothing to display"
    And I log out