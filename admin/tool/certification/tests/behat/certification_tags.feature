@tool @tool_certification @moodleworkplace @javascript @theme_workplace
Feature: Use tags in a certification
  In order to introduce tags into a certification
  As a manager
  I need to add a tag in the certification details page

  Background:
    Given "2" tenants exist with "4" users and "1" courses in each
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | certification_tags |
      | Certification1 | 0        | Tenant1 | blue, white        |
      | Certification2 | 0        | Tenant1 | blue, yellow       |
      | Certification3 | 0        | Tenant2 | white, yellow      |
      | Certification4 | 0        | Tenant2 | green, blue        |

  Scenario: Add tags to existing certifications
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role     | contextlevel | reference |
      | manager1 | manager  | System       |           |
      | manager2 | manager  | System       |           |

    When I log in as "manager1"
    Then I navigate to "Courses > Certifications" in site administration
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should see "Certification2"
    And I should not see "Certification3"
    And I should not see "Certification4"
    Then I click on ".edit_details" "css_element" in the "Certification1" "table_row"
    Then I should see "Edit certification"
    And I should see "Certification full name"
    And I should see "Certification tags"
    And I set the field "Certification tags" to "Tag example 1"
    And I click on "Certification ID number" "field"
    Then I press "Save" in the modal form dialogue
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should see "Tag example 1" in the "Certification1" "table_row"
    And I should not see "Tag example 1" in the "Certification2" "table_row"
    Then I click on ".edit_details" "css_element" in the "Certification2" "table_row"
    Then I should see "Edit certification"
    And I should see "Certification full name"
    And I should see "Certification tags"
    And I set the field "Certification tags" to "Tag example 2"
    And I click on "Certification ID number" "field"
    Then I press "Save" in the modal form dialogue
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should see "Tag example 1" in the "Certification1" "table_row"
    And I should see "Certification2"
    And I should see "Tag example 2" in the "Certification2" "table_row"
    Then I click on ".edit_details" "css_element" in the "Certification2" "table_row"
    And I should see "Tag example 2" in the ".modal-dialog" "css_element"
    And I should not see "Tag example 1" in the ".modal-dialog" "css_element"
    Then I click on "Cancel" "button" in the ".modal-dialog .modal-footer" "css_element"
    Then I click on ".edit_details" "css_element" in the "Certification1" "table_row"
    And I should see "Tag example 1" in the ".modal-dialog" "css_element"
    And I should not see "Tag example 2" in the ".modal-dialog" "css_element"
    And I click on "[data-value='Tag example 1']" "css_element" in the ".modal-dialog" "css_element"
    Then I press "Save" in the modal form dialogue
    Then I click on ".edit_details" "css_element" in the "Certification1" "table_row"
    And I should not see "Tag example 2" in the ".modal-dialog" "css_element"
    And I should not see "Tag example 1" in the ".modal-dialog" "css_element"
    Then I click on "Cancel" "button" in the ".modal-dialog .modal-footer" "css_element"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should not see "Nothing to display"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should see "Certification3"
    And I should see "Certification4"
    And I log out

  Scenario: Search by tags on existing certifications
    Given user "user13" has a global manager position over users "user11,user12" with permissions "7"
    When I log in as "user13"
    And I change window size to "large"
    And I press "Customise this page"
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
    And I should see "Schedule"
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
    And I press "Customise this page"
    And I add the "Navigation" block if not present
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "blue"
    And I should not see "Certification1"
    And I should not see "Certification2"
    And I should not see "Certification3"
    And I should not see "Certification4"
    And I log out