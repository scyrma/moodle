@tool @tool_tenant @moodleworkplace @javascript
Feature: Tenant dashboard management
  In order to manage tenant dashboards
  As a tenant administrator
  I should be able to link and un-link tenant dashboards, and manage dashboard blocks

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following "tool_tenant > tenants" exist:
      | name    | dashboardlinked |
      | Tenant3 | 0               |
      | Tenant4 | 0               |
    And the following "tool_tenant > users" exist:
      | username  | firstname | lastname  | email              | tenant  |
      | user31    | User      | 31        | user31@example.com | Tenant2 |
      | user41    | User      | 41        | user41@example.com | Tenant3 |

  Scenario: Manage tenant dashboards as admin
    When I log in as "admin"
    And I navigate to "Appearance" in site administration
    # Check that tenant dashboard editing link does not appear in administration if the tenant is linked.
    Then "Default Dashboard page" "link" should not exist
    # Check Default site dashboard.
    And "Default site dashboard page" "link" should exist
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I should see "Status: Linked to 'Default site dashboard page'"
    And I follow "Edit"
    And I should see "You are editing the Default site dashboard page"
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    # Check managing a linked tenant that is not the current one.
    And I navigate to "All tenants" in workplace launcher
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I should see "Status: Linked to 'Default site dashboard page'"
    And I press "Create personalised dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I press "Edit dashboard"
    And I should see "You are editing the dashboard for 'Tenant1'"
    And I press "Blocks editing on"
    And I add the "Latest announcements" block
    And I open the "Latest announcements" blocks action menu
    And I follow "Delete Latest announcements block"
    And I press "Yes"

  Scenario: Manage own dashboard as tenantadmin
    When I log in as "tenantadmin1"
    # Site default dashboard blocks are shown.
    Then I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I should not see "Latest announcements"
    And I navigate to "Appearance" in site administration
    # Check Default site dashboard is not shown.
    And "Default site dashboard page" "link" should not exist
    # Check that tenant dashboard editing link does not appear in administration if the tenant is linked.
    Then "Default Dashboard page" "link" should not exist
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I should see "Status: Linked to 'Default site dashboard page'"
    And I press "Create personalised dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I press "Edit dashboard"
    And I should see "You are editing the dashboard for 'Acceptance test site'"
    # Site default dashboard blocks are copied in tenant dashboard.
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I press "Blocks editing on"
    And I add the "Latest announcements" block
    And I add the "HTML" block
    And I configure the "(new HTML block)" block
    And I set the following fields to these values:
      | HTML block title  | Hello                         |
      | Content           | It's me you're looking for?   |
    And I press "Save changes"
    And I open the "Hello" blocks action menu
    And I follow "Delete Hello block"
    And I press "Yes"
    And I navigate to "Appearance" in site administration
    # Check that tenant dashboard editing link does appear now.
    And "Default Dashboard page" "link" should exist
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    # Reset all dashboards
    And I press "Reset dashboard for all users..."
    And I click on "Reset" "button" in the "Confirmation" "dialogue"
    And I follow "Dashboard"
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I should see "Latest announcements"
    # Add a block and reset own dashboard
    And I press "Customise this page"
    And I add the "HTML" block
    And I press "Reset page to default"
    And I should not see "HTML block title"

  Scenario: Use an un-linked tenant dashboard
    # Check the site dashboard first.
    When I log in as "user11"
    Then I should see "Private files" in the "Private files" "block"
    And I log out
    Then I log in as "tenantadmin1"
    # Check first that administration links.
    And I navigate to "Appearance" in site administration
    And "Default Dashboard page" "link" should not exist
    And "Default site dashboard page" "link" should not exist
    # Un-link the tenant and customize the tenant dashboard.
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    Then I should see "Status: Linked to 'Default site dashboard page'"
    And I press "Create personalised dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I should see "Status: Un-linked"
    And I press "Edit dashboard"
    # Site name is shown if no tenant site name is defined.
    And I should see "You are editing the dashboard for 'Acceptance test site'"
    # Default site dashboard blocks should be copied to the personalised tenant dashboard.
    And I should see "Private files" in the "Private files" "block"
    And I press "Blocks editing on"
    And I add the "Latest announcements" block
    And I log out
    # Check the dashboard (already created with a site dashboard copy) and customize it as user.
    Then I log in as "user11"
    And I should not see "Latest announcements"
    And I press "Customise this page"
    And I press "Reset page to default"
    And I should see "Private files"
    And I should see "Latest announcements" in the "Latest announcements" "block"
    And I press "Customise this page"
    And I add the "HTML" block
    And I log out
    # Check the dashboard as user (first access).
    Then I log in as "user12"
    And I should see "Private files"
    And I should see "Latest announcements" in the "Latest announcements" "block"
    And I log out
    # Reset the dashboard as tenant admin.
    Then I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I press "Reset dashboard for all users..."
    And I click on "Reset" "button" in the "Confirmation" "dialogue"
    And I log out
    # Check the reset dashboard.
    Then I log in as "user11"
    And I should see "Latest announcements" in the "Latest announcements" "block"
    And I should not see "HTML block title"

  Scenario: Use a linked tenant dashboard
    # Check the tenant1 dashboard is linked.
    Then I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I should see "Status: Linked to 'Default site dashboard page'"
    And "Edit" "link" should not exist
    And I press "Create personalised dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I press "Remove personalised dashboard, use site default dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I log out
    # Check the dashboard as user (first access) and customize it.
    Then I log in as "user11"
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I press "Customise this page"
    And I add the "HTML" block
    # Reset the dashboard as user
    And I press "Reset page to default"
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I should not see "HTML block title"
    # Customize the user dashboard with "Latest badged" block again.
    And I press "Customise this page"
    And I add the "HTML" block
    And I log out
    # Reset the dashboard as tenant admin.
    Then I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I press "Reset dashboard for all users..."
    And I click on "Reset" "button" in the "Confirmation" "dialogue"
    And I log out
    # Check the reset dashboard.
    Then I log in as "user11"
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I should not see "HTML block title"

  Scenario: Link all dashboards
    When I log in as "admin"
    # Link all tenants.
    And I navigate to "Appearance > Default site dashboard page" in site administration
    And I press "Link all tenants..."
    And I click on "Proceed" "button"
    # Check that tenants are linked.
    Then I switch to tenant "Tenant2"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I should see "Linked to 'Default site dashboard page'"
    And I switch to tenant "Tenant3"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "link" in the "[role=tablist]" "css_element"
    And I should see "Linked to 'Default site dashboard page'"
    And I log out

  Scenario: Link and reset all dashboards
    # Add some custom blocks to users' dashboards.
    When I log in as "user31"
    And I press "Customise this page"
    And I add the "HTML" block
    And I log out
    And I log in as "user41"
    And I press "Customise this page"
    And I add the "HTML" block
    And I log out
    # Link all tenants with the reset option.
    Then I log in as "admin"
    And I navigate to "Appearance > Default site dashboard page" in site administration
    And I press "Link all tenants..."
    And I set the field "Also reset the dashboard for all users in the affected tenants" to "1"
    And I click on "Proceed" "button"
    And I log out
    # Check that user dashboards were reset.
    And I log in as "user31"
    And I should not see "HTML block title"
    And I log out
    And I log in as "user41"
    And I should not see "HTML block title"
    # Check that 'Reset to default' copies 'Default site dashboard' blocks, because it's now linked.
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"
    And I log out

  Scenario: Reset dashboard for all users in linked tenants
    # Add some custom blocks to users' dashboards.
    When I log in as "user11"
    And I press "Customise this page"
    And I add the "HTML" block
    And I log out
    And I log in as "user21"
    And I press "Customise this page"
    And I add the "HTML" block
    And I log out
    # Reset the dashboard fot all users in linked tenants.
    When I log in as "admin"
    And I navigate to "Appearance > Default site dashboard page" in site administration
    And I press "Reset dashboard for all users in linked tenants..."
    And I click on "Reset" "button" in the "Confirmation" "dialogue"
    Then I log out
    # Check that user dashboards were reset.
    And I log in as "user11"
    And I should not see "HTML block title"
    And I log out
    And I log in as "user21"
    And I should not see "HTML block title"
    And I should see "Private files" in the "Private files" "block"
    And I should see "Online users" in the "Online users" "block"

  Scenario: Reset dashboard for all users to un-linked tenant dashboard
    Given the following "permission overrides" exist:
      | capability              | permission | role               | contextlevel | reference |
      | moodle/my:manageblocks  | Prevent    | user               | System       |           |
      | moodle/my:manageblocks  | Allow      | tool_tenant_admin  | System       |           |
    When I log in as "user11"
    And I should not see "Latest announcements"
    Then I log out
    And I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "tool_wp > Tab"
    Then I should see "Status: Linked to 'Default site dashboard page'"
    And I press "Create personalised dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I should see "Status: Un-linked"
    And I press "Edit dashboard"
    And I press "Blocks editing on"
    And I add the "Latest announcements" block
    Then I press "Reset Dashboard for all users"
    And I click on "Reset" "button" in the "Confirmation" "dialogue"
    And I log out
    # Check the reset dashboard.
    Then I log in as "user11"
    And I should see "Latest announcements" in the "Latest announcements" "block"

  Scenario: Tenant dashboards do not interfere with the profile dashboards
    Given the following "permission overrides" exist:
      | capability              | permission | role               | contextlevel | reference |
      | moodle/my:manageblocks  | Prevent    | user               | System       |           |
      | moodle/user:manageownblocks | Prevent  | user               | System       |           |
      | moodle/my:manageblocks  | Allow      | tool_tenant_admin  | System       |           |
    When I log in as "admin"
    And I navigate to "Appearance > Default profile page" in site administration
    And I press "Blocks editing on"
    And I add the "Latest announcements" block
    And I log out
    # User can see the "Latest announcements" block in their profile
    When I log in as "user11"
    And I follow "Profile" in the user menu
    And I should see "Latest announcements"
    Then I log out
    # Tenant admin creates a personalised dashboard for Tenant 1
    And I log in as "tenantadmin1"
    And I navigate to "Appearance" in workplace launcher
    And I click on "Dashboard" "tool_wp > Tab"
    Then I should see "Status: Linked to 'Default site dashboard page'"
    And I press "Create personalised dashboard..."
    And I click on "Proceed" "button" in the "Confirmation" "dialogue"
    And I log out
    # User can still see the "Latest announcements" block in their profile
    Then I log in as "user11"
    And I follow "Profile" in the user menu
    And I should see "Latest announcements"
    And I log out
