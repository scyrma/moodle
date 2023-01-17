@tool @tool_certification @moodleworkplace @javascript @theme_workplace
Feature: Use tags in a certification
  In order to introduce tags into a certification
  As a manager
  I need to add a tag in the certification details page

  Background:
    Given "2" tenants exist with "4" users and "1" courses in each
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | certification_tags | idnumber |
      | Certification1 | 0        | Tenant1 | blue, white        |  num1    |
      | Certification2 | 0        | Tenant1 | blue, yellow       |  num2    |
      | Certification3 | 0        | Tenant2 | white, yellow      |  num3    |
      | Certification4 | 0        | Tenant2 | green, blue        |  num4    |

  Scenario: Add tags to existing certifications
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
      | admin    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role     | contextlevel | reference |
      | manager1 | manager  | System       |           |
      | manager2 | manager  | System       |           |
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    # This steps needs at least two columns in order to work even if we just need to check one column.
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification2     |      |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification3     |      |
      | Certification4     |      |
    Then I click on "Certification1" "link" in the "Certification1" "table_row"
    And I navigate to "Details" in current page administration
    Then I should see "Certification full name"
    And I should see "Certification tags"
    And I set the field "Certification tags" to "Tag example 1"
    Then I press "Save changes"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certification1"
    And I should see "Tag example 1" in the "Certification1" "table_row"
    And I should not see "Tag example 1" in the "Certification2" "table_row"
    Then I click on "Certification2" "link" in the "Certification2" "table_row"
    And I navigate to "Details" in current page administration
    Then I should see "Certification full name"
    And I should see "Certification tags"
    And I set the field "Certification tags" to "Tag example 2"
    Then I press "Save changes"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certification1"
    And I should see "Tag example 1" in the "Certification1" "table_row"
    And I should see "Certification2"
    And I should see "Tag example 2" in the "Certification2" "table_row"
    Then I click on "Certification2" "link" in the "Certification2" "table_row"
    And I navigate to "Details" in current page administration
    And I should see "Tag example 2"
    And I should not see "Tag example 1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Certification1" "link" in the "Certification1" "table_row"
    And I navigate to "Details" in current page administration
    And I should see "Tag example 1"
    And I should not see "Tag example 2"
    And I click on "[data-value='Tag example 1']" "css_element"
    Then I press "Save changes"
    And I reload the page
    And I should not see "Tag example 2"
    And I should not see "Tag example 1"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should not see "Nothing to display"
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification2     |      |
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification3     |      |
      | Certification4     |      |
    And I log out

  Scenario: Search by tags on existing certifications
    Given user "user13" has a manager position over users "user11,user12" with permissions "7"
    When I log in as "user13"
    And I change window size to "large"
    And I switch editing mode on
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "blue"
    And I should see "Certification1"
    And I should see "Certification2"
    And I should not see "Certification3"
    And I should not see "Certification4"
    And I click on "Certification1" "link"
    And I should see "Certification1"
    And I should see "Certification"
    And I should see "Users"
    And I should see "Dynamic rules"
    Then I follow "Dashboard"
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "green"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    And I should not see "Certification4"
    And I log out
    Then I log in as "user11"
    And I switch editing mode on
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "blue"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    And I should not see "Certification4"
    And I log out

  Scenario: Certifications should work with custom tag collections
    When the following users allocations to tenants exist:
      | user     | tenant  |
      | admin    | Tenant1 |
    And I log in as "admin"
    And I navigate to "Appearance > Manage tags" in site administration
    # Add a tag into the Default collection.
    And I follow "Default collection"
    And I follow "Add standard tags"
    And I set the field "Enter comma-separated list of new tags" to "StandardTag"
    And I press "Continue"
    And I navigate to "Appearance > Manage tags" in site administration
    # Create a new collection.
    And I follow "Add tag collection"
    And I set the following fields to these values:
      | Name | My personal collection |
    And I press "Create"
    # Add a tag into the personal collection.
    And I follow "My personal collection"
    And I follow "Add standard tags"
    And I set the field "Enter comma-separated list of new tags" to "PersonalTag"
    And I press "Continue"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Certification1" "link" in the "Certification1" "table_row"
    And I navigate to "Details" in current page administration
    And I open the autocomplete suggestions list
    And "StandardTag" "autocomplete_suggestions" should exist
    And "PersonalTag" "autocomplete_suggestions" should not exist
    # Set program to work with the personal collection instead of the default one.
    And I navigate to "Appearance > Manage tags" in site administration
    Then I set the field "Change tag collection" in the "//table[contains(@class,'tag-areas-table')]//tr[contains(.,'Certifications')]" "xpath_element" to "My personal collection"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Certification1" "link" in the "Certification1" "table_row"
    And I navigate to "Details" in current page administration
    And I open the autocomplete suggestions list
    And "StandardTag" "autocomplete_suggestions" should not exist
    And "PersonalTag" "autocomplete_suggestions" should exist
