@tool @tool_tenant @tool_certificate @moodleworkplace @javascript
Feature: Being able to manage certificate templates and issues
  In order to manage certificate templates and user issues
  As an tenant admin
  I need to be able to manage templates and issue, revoke and regenerate certificate issues.

  Background:
    Given "2" tenants exist with "5" users and "0" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | issuer0  | Issuer    | A        | issuer0@example.com  |
      | manager0 | Manager   | A        | viewer1@example.com  |
      | manager1 | Manager   | 1        | viewer1@example.com  |
      | manager2 | Manager   | 2        | viewer1@example.com  |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following certificate templates exist:
      | name          | category  |
      | Certificate 0 |           |
      | Certificate 1 | Category1 |
      | Certificate 2 | Category2 |
    And the following "roles" exist:
      | shortname             | name                         | archetype |
      | certificatemanager    | Certificate manager          |           |
      | certificatemanagerall | Certificate manager for all  |           |
      | certificateissuer     | Certificate issuer           |           |
      | certificateissuerall  | Certificate issuer for all   |           |
      | certificateviewer     | Certificate viewer           |           |
      | configviewer          | Config viewer                |           |
    And the following "role assigns" exist:
      | user    | role                 | contextlevel | reference |
      | user14  | certificateissuer    | System       |           |
      | user14  | configviewer         | System       |           |
      | user24  | certificateissuer    | Category     | CAT2      |
      | user24  | configviewer         | System       |           |
      | issuer0 | certificateissuerall | System       |           |
    And the following "permission overrides" exist:
      | capability                           | permission | role                 | contextlevel | reference |
      | moodle/site:configview               | Allow      | configviewer         | System       |           |
      | tool/certificate:issue               | Allow      | certificateissuer    | System       |           |
      | tool/certificate:issue               | Allow      | certificateissuerall | System       |           |
      | tool/tenant:allocate                 | Allow      | certificateissuerall | System       |           |
      | moodle/site:configview               | Allow      | certificateissuerall | System       |           |
      | moodle/category:viewcourselist       | Allow      | certificateissuerall | System       |           |
      | moodle/site:configview               | Allow      | certificatemanager   | System       |           |
      | moodle/category:viewcourselist       | Allow      | certificatemanager   | System       |           |
      | tool/certificate:manage              | Allow      | certificatemanager   | System       |           |
      | tool/certificate:manage              | Allow      | certificatemanagerall | System      |           |
      | moodle/category:viewcourselist       | Allow      | certificatemanagerall | System      |           |
      | moodle/site:configview               | Allow      | certificatemanagerall | System      |           |
      | tool/certificate:viewallcertificates | Allow      | certificateviewer     | System      |           |
      | moodle/site:configview               | Allow      | certificateviewer     | System      |           |

  Scenario: Duplicating a shared site template without system-level capabilities
    When the following certificate templates exist:
      | name          | category  | numberofpages |
      | Certificate 0 |           | 1             |
      | Certificate 1 | Category1 | 1             |
      | Certificate 2 | Category2 | 1             |
    And I log in as "tenantadmin1"
    When I navigate to "Certificates > Manage certificate templates" in site administration
    And "Edit content" "link" should exist in the "Certificate 1" "table_row"
    And I should not see "Certificate 2"
    And "Edit content" "link" should not exist in the "Certificate 0" "table_row"
    And "Duplicate" "link" should not exist in the "Certificate 0" "table_row"
    And "Duplicate" "link" should exist in the "Certificate 1" "table_row"
    And I click on "Duplicate" "link" in the "Certificate 1" "table_row"
    And I click on "Duplicate" "button" in the "Confirm" "dialogue"
    Then I should see "Certificate 0"
    And I should see "Certificate 1 (copy)"
    And I log out
    # Now make sure the duplicate was created for Category1.
    And I log in as "admin"
    When I navigate to "Certificates > Manage certificate templates" in site administration
    And "Category1" "text" should exist in the "Certificate 1 (copy)" "table_row"
    And "Duplicate" "link" should exist in the "Certificate 0" "table_row"
    And "Duplicate" "link" should exist in the "Certificate 1" "table_row"
    And I log out

  Scenario: User with capability to verify certificates can verify certificates in all tenants
    Given the following certificate templates exist:
      | name          | category  |
      | Certificate 00 |           |
      | Certificate 11 | Category1 |
      | Certificate 22 | Category2 |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 11 | user11 |
      | Certificate 11 | user12 |
      | Certificate 22 | user21 |
      | Certificate 00 | user22 |
      | Certificate 00 | user12 |
    And the following "permission overrides" exist:
      | capability              | permission | role | contextlevel | reference |
      | tool/certificate:verify | Allow      | user | System       |           |
    And I log in as "user23"
    And I visit the sites certificates verification url
    And I verify the "Certificate 11" site certificate for the user "user11"
    And I verify the "Certificate 11" site certificate for the user "user12"
    And I verify the "Certificate 22" site certificate for the user "user21"
    And I verify the "Certificate 00" site certificate for the user "user22"
    And I verify the "Certificate 00" site certificate for the user "user12"
    And I log out

  Scenario: Issue certificates within a tenant with capability to issue in system context
    When I log in as "admin"
    And I set the following system permissions of "Tenant administrator" role:
    | tool/certificate:issue | Inherit |
    And I log out
    Given the following certificate issues exist:
    | template      | user   |
    | Certificate 1 | user11 |
    | Certificate 2 | user21 |
    | Certificate 0 | user12 |
    | Certificate 0 | user22 |
    And I log in as "tenantadmin2"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I should see "Certificate 0"
    And I should not see "Certificate 1"
    And "Issue certificates from this template" "link" should not exist in the "Certificate 0" "table_row"
    And "Issue certificates from this template" "link" should exist in the "Certificate 2" "table_row"
    And I click on "Certificates issued" "link" in the "Certificate 0" "table_row"
    And I should see "User 22"
    And I should not see "User 1"
    And I log out

  Scenario: Issue certificate as a tenant issuer
    Given the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 2 | user21 |
      | Certificate 0 | user12 |
      | Certificate 0 | user22 |
    When I log in as "user14"
    And I am on site homepage
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
#    And I should see "Verify certificates"
    And I should not see "Add certificate template"
#    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    # The templates from other tenants should not be visible.
    And I should not see "Certificate 2"
    # Issue a certificate for a template that belongs to the same tenant (user from my tenants that don't have certificate yet).
    And I click on "Issue certificates from this template" "link" in the "Certificate 1" "table_row"
    And I open the autocomplete suggestions list
    And I should not see "User 2"
    And I should not see "User 11"
    And I should see "User 13"
    And I click on "User 12" item in the autocomplete list
    And I press the escape key
    And I press "Save"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I should see "User 11"
    And I should see "User 12"
    And I should not see "User 13"
    And I should not see "User 2"
    And I follow "Manage certificate templates"
    # Issue a certificate for a template that is shared between tenants (user from my tenants that don't have certificate yet).
    And I click on "Issue certificates from this template" "link" in the "Certificate 0" "table_row"
    And I open the autocomplete suggestions list
    And I should not see "User 2"
    And I should not see "User 12"
    And I should see "User 11"
    And I click on "User 13" item in the autocomplete list
    And I press the escape key
    And I press "Save"
    And I click on "Certificates issued" "link" in the "Certificate 0" "table_row"
    And I should not see "User 2"
    And I should not see "User 11"
    And I should see "User 12"
    And I should see "User 13"
    And I log out

  Scenario: View issued certificates as an issuer for all tenants
    Given the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 2 | user21 |
      | Certificate 0 | user12 |
      | Certificate 0 | user22 |
    # Now make sure we can see all issues and issue for all users as issuer0.
    And I log in as "issuer0"
    And I am on site homepage
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
#    And I should see "Verify certificates"
    And I should not see "Add certificate template"
#    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Certificates issued" "link" in the "Certificate 0" "table_row"
    And I should see "User 12"
    And I should see "User 22"
    And I should not see "User 11"
    And I should not see "User 21"
    And I follow "Manage certificate templates"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I should not see "User 12"
    And I should not see "User 22"
    And I should see "User 11"
    And I should not see "User 21"
    And I follow "Manage certificate templates"
    And I click on "Certificates issued" "link" in the "Certificate 2" "table_row"
    And I should not see "User 12"
    And I should not see "User 22"
    And I should not see "User 11"
    And I should see "User 21"
    And I log out

  Scenario: Issue certificates as an issuer for all tenants
    Given the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 2 | user21 |
      | Certificate 0 | user12 |
      | Certificate 0 | user22 |
    # Now make sure we can see all issues and issue for all users as issuer0.
    And I log in as "issuer0"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    # It is possible to issue certificate0 to users from all tenants (except for those who already have this certificate).
    And I wait "3" seconds
    And I click on "Issue certificates from this template" "link" in the "Certificate 0" "table_row"
    And I open the autocomplete suggestions list
    And I should not see "User 12"
    And I should not see "User 22"
    And I should see "User 11"
    And I should see "User 21"
    And I should see "Admin User"
    And I click on "User 13" item in the autocomplete list
    And I press the escape key
    And I press "Save"
    And I click on "Certificates issued" "link" in the "Certificate 0" "table_row"
    And I should see "User 13"
    And I should see "User 22"
    And I should not see "User 11"
    And I should see "User 12"
    And I should not see "User 21"
    And I follow "Manage certificate templates"
    # It is possible to issue certificate1 only to users from tenant1 (except for those who already have this certificate).
    And I click on "Issue certificates from this template" "link" in the "Certificate 1" "table_row"
    And I open the autocomplete suggestions list
    And I should see "User 12"
    And I should see "User 13"
    And I should see "User 2"
    And I should not see "User 11"
    And I should see "Admin User"
    And I click on "User 12" item in the autocomplete list
    And I press the escape key
    And I press "Save"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I should see "User 12"
    And I should not see "User 2"
    And I should see "User 11"
    And I should not see "User 13"
    And I log out

  Scenario: Revoke issued certificate as a tenant issuer
    Given the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
    When I log in as "user14"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I should see "User 11"
    And I should see "User 12"
    And I click on "Revoke" "link" in the "User 11" "table_row"
    And I click on "Revoke" "button" in the "Confirm" "dialogue"
    And I should not see "User 11"
    And I should see "User 12"
    And I log out

  Scenario: Issue certificates within a tenant without capability to issue in system context
    When I log in as "user24"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I should not see "Certificate 0"
    And I should not see "Certificate 1"
    And I click on "Issue certificates from this template" "link" in the "Certificate 2" "table_row"
    And I open the autocomplete suggestions list
    And I should see "User 21"
    And I should not see "User 1"
    And I click on "User 22" item in the autocomplete list
    And I press the escape key
    And I press "Save"
    And I click on "Certificates issued" "link" in the "Certificate 2" "table_row"
    And I should see "User 22"
    And I should not see "User 21"
    And I log out

  Scenario: Regenerate issued certificate file as a tenant issuer
    Given the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
    When I log in as "user14"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I click on "Regenerate issue file" "link" in the "User 11" "table_row"
    And I click on "Regenerate" "button" in the "Confirm" "dialogue"
    And I should see "User 11"
    And I log out

  Scenario: View certificates in your own tenant as a certificate issuer
    And the following "role assigns" exist:
      | user     | role              | contextlevel | reference |
      | manager1 | certificateissuer | Category     | CAT1      |
      | manager1 | configviewer      | System       |           |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
      | Certificate 2 | user21 |
      | Certificate 0 | user22 |
      | Certificate 0 | user12 |
    And I log in as "manager1"
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
    And I should see "Verify certificates"
    And I should not see "Add certificate template"
    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I should not see "Certificate 2"
    And "Issue certificates from this template" "link" should exist in the "Certificate 1" "table_row"
    And I should not see "Certificate 0"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And "Issue certificates" "link" should exist
    And I should see "User 11"
    And I should see "User 12"
    And I log out

  Scenario: View certificates in your own tenant as a certificate viewer
    And the following "role assigns" exist:
      | user     | role              | contextlevel | reference |
      | manager1 | certificateviewer | Category     | CAT1      |
      | manager1 | configviewer      | System       |           |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
      | Certificate 2 | user21 |
      | Certificate 0 | user22 |
      | Certificate 0 | user12 |
    And I log in as "manager1"
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
    And I should see "Verify certificates"
    And I should not see "Add certificate template"
    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I should not see "Certificate 2"
    And "Issue certificates from this template" "link" should not exist in the "Certificate 1" "table_row"
    And I should not see "Certificate 0"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And "Issue certificates" "link" should not exist
    And I should see "User 11"
    And I should see "User 12"
    And I log out

  Scenario: View certificates as a person who can manage certificates for one tenant but can not issue
    And the following "role assigns" exist:
      | user     | role               | contextlevel | reference |
      | manager1 | certificatemanager | Category     | CAT1      |
      | manager1 | configviewer       | System       |           |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
      | Certificate 2 | user21 |
      | Certificate 0 | user22 |
      | Certificate 0 | user12 |
    And I log in as "manager1"
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
    And I should see "Verify certificates"
    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I should not see "Certificate 2"
    And "Issue certificates from this template" "link" should not exist in the "Certificate 1" "table_row"
    And I should not see "Certification 0"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And "Issue certificates" "link" should not exist
    And I should see "User 11"
    And I should see "User 12"
    And I log out

  Scenario: View certificates in all tenants as a certificate issuer
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager0 | certificateissuerall | System       |           |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
      | Certificate 2 | user21 |
      | Certificate 0 | user22 |
      | Certificate 0 | user12 |
    And I log in as "manager0"
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
    And I should see "Verify certificates"
    And I should not see "Add certificate template"
    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And "Issue certificates from this template" "link" should exist in the "Certificate 2" "table_row"
    And "Issue certificates from this template" "link" should exist in the "Certificate 1" "table_row"
    And "Issue certificates from this template" "link" should exist in the "Certificate 0" "table_row"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And "Issue certificates" "link" should exist
    And I should see "User 11"
    And I should see "User 12"
    And I follow "Manage certificate templates"
    And I click on "Certificates issued" "link" in the "Certificate 0" "table_row"
    And "Issue certificates" "link" should exist
    And I should not see "User 11"
    And I should see "User 12"
    And I should see "User 22"
    And I log out

  Scenario: View certificates in all tenants as a certificate manager
    And the following "role assigns" exist:
      | user     | role                  | contextlevel | reference |
      | manager1 | certificatemanagerall | System       |           |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
      | Certificate 2 | user21 |
      | Certificate 0 | user22 |
      | Certificate 0 | user12 |
    And I log in as "manager1"
    And I follow "Site administration"
    Then I should see "Manage certificate templates"
    And I should see "Verify certificates"
    And I should not see "Certificate images"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And "Issue certificates from this template" "link" should not exist in the "Certificate 2" "table_row"
    And "Issue certificates from this template" "link" should not exist in the "Certificate 1" "table_row"
    And "Issue certificates from this template" "link" should not exist in the "Certificate 0" "table_row"
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And "Issue certificates" "link" should not exist
    And I should see "User 12"
    And I follow "Manage certificate templates"
    And I click on "Certificates issued" "link" in the "Certificate 0" "table_row"
    And "Issue certificates" "link" should not exist
    And I should not see "User 11"
    And I should see "User 12"
    And I should not see "User 2"
    And I log out
