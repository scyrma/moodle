@tool @tool_custompage @moodleworkplace @javascript
Feature: Manage single custom page
  In order to manage a custom page
  As a user
  I need to be able to view and edit its content

  Scenario: Duplicate custom page
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Pages" exist:
      | name        | weight | tenant  |
      | Tenant page | 0      | Tenant1 |
    And I am on the "Tenant page" "tool_custompage > Manage" page logged in as "tenantadmin1"
    When I open the action menu in "page-header" "region"
    And I choose "Duplicate" in the open action menu
    And I click on "Duplicate" "button" in the "Duplicate page" "dialogue"
    Then I should see "Tenant page (copy)"

  Scenario: Delete custom page
    Given the following "tool_custompage > Pages" exist:
      | name     | weight |
      | Page one | 0      |
      | Page two | 1      |
    And I am on the "Page one" "tool_custompage > Manage" page logged in as "admin"
    When I open the action menu in "page-header" "region"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Delete page" "dialogue"
    Then I should see "Deleted page"
    And I should not see "Page one" in the "reportbuilder-table" "table"

  Scenario: View content of custom page
    Given the following "tool_custompage > Page" exists:
      | name   | My page |
      | title  |         |
      | weight | 0       |
    And the following "tool_custompage > Blocks" exist:
      | page    | blockname      | defaultregion |
      | My page | calendar_month | content       |
      | My page | online_users   | side-pre      |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I select "Content" from secondary navigation
    Then "Calendar" "text" should exist in the "[data-region-name='Content']" "css_element"
    And "Online users" "text" should exist in the "[data-region-name='Right']" "css_element"

  Scenario: Edit content of custom page
    Given the following "tool_custompage > Page" exists:
      | name   | My page |
      | title  |         |
      | weight | 0       |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I click on "Edit page" "link"
    And I switch editing mode on
    And I click on "Add a block" "link" in the ".drawer-right" "css_element"
    And I click on "Calendar" "link" in the "Add a block" "dialogue"
    Then "Calendar" "block" should exist in the ".drawer-right" "css_element"
    # Make sure we can add blocks typically reserved for "/my".
    And I click on "Add a block" "link" in the "region-main" "region"
    And I click on "Starred courses" "link" in the "Add a block" "dialogue"
    And "Starred courses" "block" should exist in the "region-main" "region"

  Scenario: Move blocks in a custom page
    Given the following "tool_custompage > Page" exists:
      | name   | My page |
      | title  |         |
      | weight | 0       |
    And the following "tool_custompage > Blocks" exist:
      | page    | blockname      | defaultregion |
      | My page | starredcourses | content       |
      | My page | calendar_month | content       |
      | My page | online_users   | side-pre      |
    And I am on the "My page" "tool_custompage > Edit" page logged in as "admin"
    When I switch editing mode on
    # Move Calendar from main to right region.
    And I click on "Move Calendar block" "menuitem"
    And I click on "To item \"Online users\"" "button" in the "Move Calendar" "dialogue"
    Then "Calendar" "block" should exist in the ".drawer-right" "css_element"
    # Move Online users from right to main region.
    And I click on "Move Online users block" "menuitem"
    And I click on "To item \"Starred courses\"" "button" in the "Move Online users" "dialogue"
    And "Online users" "block" should exist in the "region-main" "region"

  Scenario: Edit details of custom page
    Given the following "tool_custompage > Page" exists:
      | name   | My page |
      | title  |         |
      | weight | 0       |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "admin"
    When I select "Details" from secondary navigation
    And I set the following fields to these values:
      | name   |                  |
      | title  | My updated title |
      | weight | 5                |
    And I press "Save"
    And I should see "You must supply a value here."
    And I set the following fields to these values:
      | name   | My page updated  |
    And I press "Save"
    Then I should see "Changes saved"
    And I reload the page
    And the following fields match these values:
      | name   | My page updated  |
      | title  | My updated title |
      | weight | 5                |

  Scenario: Preview custom page
    Given "1" tenants exist with "0" users and "0" courses in each
    And the following "tool_custompage > Page" exists:
      | name   | My page  |
      | title  | My title |
      | weight | -7       |
      | global | 1        |
    And the following "tool_custompage > Audience" exists:
      | page       | My page |
      | configdata |         |
    And the following "tool_custompage > Blocks" exist:
      | page    | blockname      |
      | My page | calendar_month |
    And I am on the "My page" "tool_custompage > Manage" page logged in as "tenantadmin1"
    When I select "Details" from secondary navigation
    Then the following fields match these values:
      | Name  | My page  |
      | Title | My title |
    And I should not see "Save"
    And I select "Content" from secondary navigation
    And "Calendar" "text" should exist
    And I click on "Preview" "link"
    And "Calendar" "block" should exist

  Scenario: Manage the default My teams custom page
    Given "1" tenants exist with "4" users and "0" courses in each
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user12" has a department lead position over users "user13" with permissions "7"
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I am on the "My teams" "tool_custompage > Manage" page
    And I select "Content" from secondary navigation
    Then "Team overview" "text" should exist in the "[data-region-name='Content']" "css_element"
    And I select "Audience" from secondary navigation
    And I should see "Permissions: Manager or department lead" in the "audiences-container" "region"
    And I select "Access" from secondary navigation
    And I should see "User 11" in the "reportbuilder-table" "table"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"

  # We need to ensure that in each block the "Display on page types" edit configuration setting
  # in section "Where this block appears" show the right page type based on where is added the block.
  Scenario: Edit block config in custom page and dashboard
    Given the following "tool_custompage > Page" exists:
      | name   | My custom page |
      | title  |                |
      | weight | 0              |
    And I log in as "admin"
    And I switch editing mode on
    And I click on "Add a block" "link" in the ".drawer-right" "css_element"
    And I click on "Courses" "link" in the "Add a block" "dialogue"
    And "Courses" "block" should exist in the ".drawer-right" "css_element"
    And I configure the "Calendar" block
    And "Dashboard page" "text" should exist in the "//div[@id='fitem_id_bui_staticpagetypepattern']" "xpath_element"
    And "Display on page types" "select" should not exist in the "#id_whereheadercontainer" "css_element"
    And I am on the "My custom page" "tool_custompage > Manage" page
    When I click on "Edit page" "link"
    And I click on "Add a block" "link" in the ".drawer-right" "css_element"
    And I click on "Calendar" "link" in the "Add a block" "dialogue"
    Then "Calendar" "block" should exist in the ".drawer-right" "css_element"
    And I configure the "Calendar" block
    And "Custom pages" "text" should exist in the "//div[@id='fitem_id_bui_staticpagetypepattern']" "xpath_element"
    And "Display on page types" "select" should not exist in the "#id_whereheadercontainer" "css_element"
