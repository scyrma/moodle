@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Export and import dynamic rules
  As a tenant administrator
  I want to export and import dynamic rules

  Background:
    Given "2" tenants exist with "1" users and "0" courses in each

  Scenario: Export a rule
    When the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |
      | Rule2 | Tenant2 |
    And I log in as "tenantadmin1"
    And I perform a new export with these options:
      | Step 1 | Dynamic rules                                  | 1        |
      | Step 2 | Select all Dynamic rules (excluding archived)  | 1        |
    And I press "View export" action in the "Dynamic rules" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Rule1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should not see "Rule2" in the "[data-region=\"exportimport-instances\"]" "css_element"

  @_file_upload
  Scenario: Import a rule into current tenant
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/dynamicrule/tests/fixtures/dynamic-rules-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Dynamic rules (2)" in the "Content" "table_row"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Required" in the "[data-fieldtype=\"autocomplete\"]" "css_element"
    And I expand the "Dynamic rules" autocomplete
    And "Rule 2 (archived)" "autocomplete_suggestions" should exist
    And I click on "Rule 1" item in the autocomplete list
    And I take focus off "Dynamic rules" "field"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Rule 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View import" action in the "Dynamic rules" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Rule 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I click on "[data-action=\"show-log\"]" "css_element"
    And I should see "Created new rule 'Rule 1' with 1 conditions and 1 actions"
    And I navigate to "Dynamic rules" in workplace launcher
    # There are two "Actions" columns in the table, and Behat matches the one we don't want.
    # In this rule "Scheduled task" badge is displayed next to the rule name.
    And the following should exist in the "reportbuilder-table" table:
      | Name                  | Conditions                             | -4-                             |
      | Rule 1 Scheduled task | Users who have logged in at least once | Send notification 'Hi' to users |

  @_file_upload
  Scenario: Import a rule into a different tenant
    When I log in as "admin"
    And I perform a new import with these options:
      | Step 1 | Upload a file       | admin/tool/dynamicrule/tests/fixtures/dynamic-rules-export.zip |
      | Step 2 | Select tenant       | 1                                                              |
      | Step 2 | Choose tenant       | Tenant2                                                        |
      | Step 3 | Select manually...  | 1                                                              |
      | Step 3 | Dynamic rules       | Rule 1                                                         |
    And I navigate to "Dynamic rules" in workplace launcher
    Then the following should not exist in the "reportbuilder-table" table:
      | Name   | Conditions                             |
      | Rule 1 | Users who have logged in at least once |
    And I switch to tenant "Tenant2"
    # There are two "Actions" columns in the table, and Behat matches the one we don't want.
    # In this rule "Scheduled task" badge is displayed next to the rule name.
    And the following should exist in the "reportbuilder-table" table:
      | Name                  | Conditions                             | -4-                             |
      | Rule 1 Scheduled task | Users who have logged in at least once | Send notification 'Hi' to users |

  @_file_upload
  Scenario: Import a rule with custom profile fields
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name                 |  visible |
      | text     | countrycode       | CUSTOM country code  |  2       |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/dynamicrule/tests/fixtures/dynamic-rules-export-profile-field.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Dynamic rules (1)" in the "Content" "table_row"
    And I press "Next"
    And I click on "Select all Dynamic rules in this file" "radio"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Rule profile field" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View import" action in the "Dynamic rules" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Rule profile field" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I click on "[data-action=\"show-log\"]" "css_element"
    And I should see "Created new rule 'Rule profile field' with 3 conditions and 0 actions"
    And I navigate to "Dynamic rules" in workplace launcher
    And the following should exist in the "reportbuilder-table" table:
      | Name               | Conditions                                                               | -4-                     |
      | Rule profile field | Users whose value for profile field 'Country' is 'Australia'             | No actions on this rule |
      | Rule profile field | Users whose value for profile field 'CUSTOM country code' is equal to 61 | No actions on this rule |
    And I should see "Error" in the "Rule profile field" "table_row"
