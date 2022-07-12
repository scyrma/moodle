@tool @tool_certificate @tool_organisation @moodleworkplace
Feature: Being able to view the certificates from people user is manager over
  In order to ensure that a user can view the certificates from people user is manager over
  As a manager
  I need to view the certificates from people I am manager over

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following certificate templates exist:
      | name |
      | Certificate 1 |
      | Certificate 2 |
    And the following certificate issues exist:
      | template | user |
      | Certificate 1 | student1 |

  Scenario: View the certificates from people user is manager over
    Given user "manager1" has a manager position over users "student1" with permissions "7"
    When I log in as "manager1"
    And I click on "View profile" "link" in the "Student 1" "table_row"
    And I follow "Certificates"
    Then I should see "Certificate 1"
