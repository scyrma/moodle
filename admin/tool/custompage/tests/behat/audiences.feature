@tool @tool_custompage @moodleworkplace @javascript
Feature: Manage custom page audiences
  In order to manage custom page audiences
  As a user
  I need to be able to create, update and delete page audiences

  Scenario: Create page audience
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | user01   | User      | One      | user01@example.com |
      | user02   | User      | Two      | user02@example.com |
    And the following "tool_custompage > Pages" exist:
      | name    | weight |
      | My page | 0      |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I select "Access" from secondary navigation
    And I should see "Nothing to display"
    And I select "Audience" from secondary navigation
    And I should see "There are no audiences for this page"
    And I click on "Add audience 'Manually added users'" "link"
    Then I should see "Added audience 'Manually added users'"
    And I set the field "Add users manually" to "User One"
    And I press "Save changes"
    And I should see "Audience saved"
    And I should not see "There are no audiences for this page"
    And I select "Access" from secondary navigation
    And I should see "User One" in the "reportbuilder-table" "table"
    And I should not see "User Two" in the "reportbuilder-table" "table"
    # Confirm the audience is also displayed when listing pages.
    And I navigate to "Custom pages" in workplace launcher
    And the following should exist in the "reportbuilder-table" table:
      | Name    | Audience                        |
      | My page | Manually added users (User One) |

  Scenario: Update page audience
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | user01   | User      | One      | user01@example.com |
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
    And the following "tool_custompage > Audience" exists:
      | page       | My page                                         |
      | classname  | tool_custompage\tool_custompage\audience\manual |
      | configdata | {"users":[-1]}                                    |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I select "Audience" from secondary navigation
    And I press "Edit audience 'Manually added users'"
    And I set the field "Add users manually" to "User One"
    And I press "Save changes"
    Then I should see "Audience saved"
    And I select "Access" from secondary navigation
    And I should see "User One" in the "reportbuilder-table" "table"

  Scenario: Delete page audience
    Given the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
    And the following "tool_custompage > Audience" exists:
      | page       | My page |
      | configdata |         |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I select "Audience" from secondary navigation
    And I press "Delete audience 'All authenticated users'"
    And I click on "Delete" "button" in the "Delete audience 'All authenticated users'" "dialogue"
    Then I should see "Deleted audience 'All authenticated users'"
    And I should see "There are no audiences for this page"

  Scenario: View users who match tenant page audiences
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
      | tenant  | Tenant1 |
    And the following "tool_custompage > Audience" exists:
      | page       | My page |
      | configdata |         |
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I am on the "My page" "tool_custompage > Manage" page
    And I select "Access" from secondary navigation
    Then I should see "User 11" in the "reportbuilder-table" "table"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 21" in the "reportbuilder-table" "table"
    And I should not see "User 22" in the "reportbuilder-table" "table"
    And I click on "Filters" "button"
    And I set the following fields in the "Full name" "core_reportbuilder > Filter" to these values:
      | Full name operator | Contains |
      | Full name value    | 2        |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should not see "User 11" in the "reportbuilder-table" "table"
    And I should see "User 12" in the "reportbuilder-table" "table"

  Scenario Outline: View users who match global page audiences
    Given "2" tenants exist with "2" users and "0" courses in each
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
      | global  | 1       |
    And the following "tool_custompage > Audience" exists:
      | page       | My page |
      | configdata |         |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "<user>"
    When I select "Access" from secondary navigation
    Then I <seeadmin> "Admin User" in the "reportbuilder-table" "table"
    And I <seetadmin1> "Tenantadmin 1" in the "reportbuilder-table" "table"
    And I <seetuser1> "User 11" in the "reportbuilder-table" "table"
    And I <seetadmin2> "Tenantadmin 2" in the "reportbuilder-table" "table"
    And I <seetuser2> "User 21" in the "reportbuilder-table" "table"
    Examples:
      | user         | seeadmin       | seetadmin1     | seetuser1      | seetadmin2     | seetuser2      |
      | admin        | should see     | should see     | should see     | should see     | should see     |
      | tenantadmin1 | should not see | should see     | should see     | should not see | should not see |
      | tenantadmin2 | should not see | should not see | should not see | should see     | should see     |

  Scenario Outline: View users who match admin role audience in global page
    Given "2" tenants exist with "1" users and "0" courses in each
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
      | global  | 1       |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I select "Audience" from secondary navigation
    And I click on "Add audience 'Administrators'" "link"
    And I set the field "Select a role" to "<admin>"
    And I press "Save changes"
    And I should see "Audience saved"
    And I select "Access" from secondary navigation
    Then I should <seeadmin> "Admin User" in the "reportbuilder-table" "table"
    And I should <seetenantadmin1> "Tenantadmin 1" in the "reportbuilder-table" "table"
    And I should <seetenantadmin2> "Tenantadmin 2" in the "reportbuilder-table" "table"
    Examples:
      | admin                          | seetenantadmin1 | seetenantadmin2   | seeadmin |
      | Site administrators            | not see         | not see           | see      |
      | Tenant administrators          | see             | see               | not see  |
      | Site or tenant administrators  | see             | see               | see      |

  Scenario: View users who match category role audience in tenant page
    Given "1" tenants exist with "1" users and "0" courses in each
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email              | tenant  |
      | user01   | User      | One      | user01@example.com | Tenant1 |
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | -1      |
      | global  | 0       |
      | tenant  | Tenant1 |
    And the following "role assigns" exist:
      | user    | role       | contextlevel | reference |
      | user01  | manager    | Category     | CAT1      |
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I am on the "My page" "tool_custompage > Manage" page
    And I select "Audience" from secondary navigation
    And I click on "Add audience 'Assigned category role'" "link"
    And I set the field "Select a role" to "Manager"
    And I press "Save changes"
    And I should see "Audience saved"
    And I select "Access" from secondary navigation
    Then I should see "User One" in the "reportbuilder-table" "table"
    And I should not see "Tenantadmin 1" in the "reportbuilder-table" "table"

  Scenario: Adding/removing a user to/from an audience cohort should apply changes immediately
    Given the following "cohorts" exist:
      | name         | idnumber | visible |
      | Cohort1      | C1       | 1       |
    And the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
      | global  | 1       |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    And I select "Audience" from secondary navigation
    And I click on "Add audience 'Member of cohort'" "link"
    And I set the field "Select members from cohort" to "Cohort1"
    And I press "Save changes"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    Then I should not see "My page" in the ".primary-navigation" "css_element"
    And I press "Assign" action in the "Cohort1" report row
    And I set the field "Potential users" to "Admin User"
    And I press "Add"
    And I reload the page
    And I should see "My page" in the ".primary-navigation" "css_element"
    And I set the field "Current users" to "Admin User"
    And I press "Remove"
    And I reload the page
    And I should not see "My page" in the ".primary-navigation" "css_element"

  Scenario: Adding/removing a user to/from an audience user role should apply changes immediately
    Given the following "tool_custompage > Page" exists:
      | name    | My page |
      | weight  | 0       |
      | global  | 1       |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    And I select "Audience" from secondary navigation
    And I click on "Add audience 'Assigned system role'" "link"
    And I set the field "Select a role" to "Course creator"
    And I press "Save changes"
    And I navigate to "Users > Permissions > Assign system roles" in site administration
    Then I should not see "My page" in the ".primary-navigation" "css_element"
    And I click on "Course creator" "link"
    And I set the field "Potential users" to "Admin User"
    And I press "Add"
    And I reload the page
    And I should see "My page" in the ".primary-navigation" "css_element"
    And I set the field "Existing users" to "Admin User"
    And I press "Remove"
    And I reload the page
    And I should not see "My page" in the ".primary-navigation" "css_element"
