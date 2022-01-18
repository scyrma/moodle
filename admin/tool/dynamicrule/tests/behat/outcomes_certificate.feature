@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Issue certificate with dynamic rules
  In order to issue a certificate in a dynamic rule
  As a manager
  I need to be able to create and edit certificate outcome

  Scenario: Add a rule with certificate outcome
    Given the following certificate templates exist:
      | name |
      | Certificate 1 |
      | Certificate 2 |
    When I log in as "admin"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Actions"
    And I follow "Issue certificate"
    And I set the field "Select certificate" to "Certificate 1"
    And I press "Save changes"
    Then I should see "Issue certificate 'Certificate 1' to users"
    And I follow "Edit action"
    And I set the field "Select certificate" to "Certificate 2"
    And I press "Save changes"
    Then I should see "Issue certificate 'Certificate 2' to users"

  Scenario: Issue a certificate on course completion
    Given "2" tenants exist with "3" users and "2" courses in each
    And the following "course enrolments" exist:
      | user   | course | role    |
      | user11 | C11    | student |
    Given the following certificate templates exist:
      | name          | category  | numberofpages |
      | Certificate 1 | Category1 | 1             |
      | Certificate 2 | Category2 | 1             |
    And I log in as "tenantadmin1"
    # Create a certificate that prints the course full name.
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Edit content" "link" in the "Certificate 1" "table_row"
    And I add the element "Dynamic fields" to page "1" of the "Certificate 1" site certificate template
    And I set the field "Field" in the "Add 'Dynamic fields' element" "dialogue" to "Course full name"
    And I press "Save"
    # Create a dynamic rule that gives this certificate on completion of a course.
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Course completed"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I follow "Actions"
    And I follow "Issue certificate"
    And I set the field "Select certificate" to "Certificate 1"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    # Complete course as a student.
    And I log in as "user11"
    And I am on "Course11" course homepage
    And I click on "[data-modulename='URL1']" "css_element"
    And I click on "[data-modulename='URL2']" "css_element"
    And I log out
    # Check certificate was issued.
    And I log in as "tenantadmin1"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I click on "View" "link" in the "User 11" "table_row"
    # TODO: Hard to validate that issued certificate contains the "Course11" inside pdf file.
    And I close all opened windows
    And I am on homepage
    And I log out

  Scenario: Issue a certificate on program completion
    Given "2" tenants exist with "3" users and "2" courses in each
    And the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    Given the following "tool_program > program_courses" exist:
      | program  | course |
      | Program1 | C11    |
    And the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user11 |
    Given the following certificate templates exist:
      | name          | category  | numberofpages |
      | Certificate 1 | Category1 | 1             |
      | Certificate 2 | Category2 | 1             |
    And I log in as "tenantadmin1"
    And I change window size to "large"
    # Create a certificate that prints the course full name.
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Edit content" "link" in the "Certificate 1" "table_row"
    And I add the element "Dynamic fields" to page "1" of the "Certificate 1" site certificate template
    And I set the field "Field" in the "Add 'Dynamic fields' element" "dialogue" to "Program name"
    And I press "Save"
    # Create a dynamic rule that gives this certificate on completion of a course.
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Program completed"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I follow "Actions"
    And I follow "Issue certificate"
    And I set the field "Select certificate" to "Certificate 1"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    # Complete course as a student.
    And I log in as "user11"
    And I am on "Course11" course homepage
    And I click on "[data-modulename='URL1']" "css_element"
    And I click on "[data-modulename='URL2']" "css_element"
    And I log out
    # Check certificate was issued.
    And I log in as "tenantadmin1"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I click on "View" "link" in the "User 11" "table_row"
    # TODO: Hard to validate that issued certificate contains the "Program1" inside pdf file.
    And I close all opened windows
    And I am on homepage
    And I log out

  Scenario: Issue a certificate on program completion
    Given "2" tenants exist with "3" users and "2" courses in each
    Given the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    Given the following "tool_program > program_courses" exist:
      | program  | course |
      | Program1 | C11    |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program  |
      | Certification1 | 0        | Tenant1 | Program1 |
    Given the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user11 |
    Given the following certificate templates exist:
      | name          | category  | numberofpages |
      | Certificate 1 | Category1 | 1             |
      | Certificate 2 | Category2 | 1             |
    And I log in as "tenantadmin1"
    And I change window size to "large"
    # Create a certificate that prints the course full name.
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Edit content" "link" in the "Certificate 1" "table_row"
    And I add the element "Dynamic fields" to page "1" of the "Certificate 1" site certificate template
    And I set the field "Field" in the "Add 'Dynamic fields' element" "dialogue" to "Certification name"
    And I press "Save"
    # Create a dynamic rule that gives this certificate on completion of a course.
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "Certification certified"
    And I set the field "Certification" to "Certification1"
    And I press "Save changes"
    And I follow "Actions"
    And I follow "Issue certificate"
    And I set the field "Select certificate" to "Certificate 1"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    # Complete course as a student.
    And I log in as "user11"
    And I am on "Course11" course homepage
    And I click on "[data-modulename='URL1']" "css_element"
    And I click on "[data-modulename='URL2']" "css_element"
    And I log out
    # Check certificate was issued.
    And I log in as "tenantadmin1"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I click on "Certificates issued" "link" in the "Certificate 1" "table_row"
    And I click on "View" "link" in the "User 11" "table_row"
    # TODO: Hard to validate that issued certificate contains the "Certification" inside pdf file.
    And I close all opened windows
    And I am on homepage
    And I log out
