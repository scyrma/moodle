@tool @tool_certification @moodleworkplace @javascript
Feature: Archived table pagination
  In order to manage certifications
  As a manager
  I need to be view the archived certifications table with a pagination

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
    Given the following tool certification data "certifications" exist:
      | fullname | archived | tenant  | program   |
      | Cert b | 1 |  Tenant1 | Program1  |
      | Cert i | 1 |  Tenant1 | Program1  |
      | Cert j | 1 |  Tenant1 | Program1  |
      | Cert c | 1 |  Tenant1 | Program1  |
      | Cert g | 1 |  Tenant1 | Program1  |
      | Cert d | 1 |  Tenant1 | Program1  |
      | Cert h | 1 |  Tenant1 | Program1  |
      | Cert e | 1 |  Tenant1 | Program1  |
      | Cert f | 1 |  Tenant1 | Program1  |
      | Cert k | 1 |  Tenant1 | Program1  |
      | Cert l | 1 |  Tenant1 | Program1  |
      | Cert a | 1 |  Tenant1 | Program1  |
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
      | user     | role           | contextlevel | reference |
      | manager1 | tool_certification_manager  | System       |           |
      | manager2 | tool_certification_manager  | System       |           |

  @javascript
  Scenario: Change page
    When I log in as "manager1"
    Then I navigate to "Certifications" in site administration
    Then I click on "Archived" "link"
    And I should see "Cert a" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert b" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert c" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert d" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert e" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert f" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert g" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert h" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert i" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert j" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert k" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert l" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And ".tool_reportbuilder_report .pagination" "css_element" should exist
    And I should see "Next" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Cert a" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert b" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert c" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert d" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert e" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert f" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert g" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert h" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert i" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert j" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert k" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert l" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    # Begin - Check events in action still working.
    Then I click on ".restore_certification" "css_element" in the "Cert k" "table_row"
    Then I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Cert k" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    Then I click on ".delete_certification" "css_element" in the "Cert l" "table_row"
    Then I click on "Cancel" "button" in the ".confirmation-dialogue" "css_element"
    # End - Check events in action still working.
    And I should see "Prev" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "Prev" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "Cert a" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert b" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert c" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert d" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert e" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert f" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert g" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert h" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert i" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert j" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert k" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert l" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I click on "Next" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Cert a" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert b" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert c" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert d" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert e" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert f" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert g" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert h" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert i" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should not see "Cert j" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I should see "Cert l" in the "#certification_manager_list_archived_tab table.report-table" "css_element"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Archived" "link"
    And I should see "Nothing to display"
    And I log out