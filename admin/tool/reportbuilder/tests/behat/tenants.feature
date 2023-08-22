@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Access reports within a users own tenant
  As a user
  I want to access reports within my own tenant

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant2 | tool_reportbuilder\test\mock_report |

  Scenario Outline: Tenant admin can view and manage only those reports within their own tenant
    When I log in as "<user>"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    Then the following should exist in the "report-table" table:
      | Report name    | Plugin         |
      | <reporttenant> | Report builder |
    And I should not see "<reportnontenant>" in the "report-table" "table"
    And "Edit content" "link" should exist in the "<reporttenant>" "table_row"
    Examples:
      | user         | reporttenant | reportnontenant |
      | tenantadmin1 | Report1      | Report2         |
      | tenantadmin2 | Report2      | Report1         |

  Scenario Outline: User who can view all reports can view only those within their own tenant
    Given the following "roles" exist:
      | shortname    | name           | archetype |
      | reportviewer | Reports viewer |           |
    And the following "role assigns" exist:
      | user   | role         | contextlevel | reference |
      | <user> | reportviewer | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role         | contextlevel | reference |
      | tool/reportbuilder:read | Allow      | reportviewer | System       |           |
    When I log in as "<user>"
    And I follow "Reports" in the user menu
    And I click on "View reports" "link" in the ".tool_reportbuilder-warning" "css_element"
    Then I should see "<reporttenant>" in the "report-table" "table"
    And I should not see "<reportnontenant>" in the "report-table" "table"
    And "Edit content" "link" should not exist in the "<reporttenant>" "table_row"
    Examples:
      | user   | reporttenant | reportnontenant |
      | user11 | Report1      | Report2         |
      | user21 | Report2      | Report1         |
