@tool @tool_datastore @_file_upload @javascript @moodleworkplace @tool_uploaduser
Feature: Register course completion dates in upload users
  In order to register course completion dates
  As an admin
  I need to upload files containing the users data

  Scenario: Upload users registering course completion dates
    Given the following "users" exist:
      | username | firstname | lastname | email | idnumber |
      | student1 | Student | 1 | student1@example.com | S1 |
      | student2 | Student | 2 | student2@example.com | S2 |
      | student3 | Student | 3 | student3@example.com | S3 |
      | student4 | Student | 4 | student4@example.com | S4 |
    And the following "courses" exist:
      | fullname       | shortname | format | enablecompletion |
      | Course example | CE        | topics | 1 |
    And the following "groups" exist:
      | name          | course | idnumber |
      | Groupcourse 1 | CE     | G1       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | CE     | student |
      | student2 | CE     | student |
    When I log in as "admin"
    And I am on "Course example" course homepage with editing mode on
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Manager | 1 |
    And I press "Save changes"
    And I add the "Course completion status" block
    And I navigate to "Users > Accounts > Upload users" in site administration
    And I upload "admin/tool/datastore/tests/fixtures/upload_users.csv" file to "File" filemanager
    And I press "Upload users"
    Then I should see "Upload users preview"
    And I should see "2019-02-19"
    And I set the field "Upload type" to "Update existing users only"
    And I press "Upload users"
    And I press "Continue"
    And I log out
    And I log in as "student1"
    And I am on "Course example" course homepage
    Then I should see "Status: Complete" in the "Course completion status" "block"
    And I log out
    And I log in as "student3"
    And I am on "Course example" course homepage
    Then I should see "Status: Complete" in the "Course completion status" "block"
    And I log out
