@tool @tool_wp @moodleworkplace
Feature: Moodle workplace user policies
  As an admin
  I should not be able to define workplace roles in user policies

  Scenario Outline: User policies configuration does not list workplace roles
    When I log in as "admin"
    Then I navigate to "Users > Permissions > User policies" in site administration
    And the "<selector>" select box should not contain "Tenant administrator (tool_tenant_admin)"
    And the "<selector>" select box should not contain "Tenant user (tool_tenant_user)"
    And the "<selector>" select box should not contain "Certification manager (tool_certification_manager)"
    And the "<selector>" select box should not contain "Dynamic rules manager (tool_dynamicrule_manager)"
    And the "<selector>" select box should not contain "Organisation structure manager (tool_organisation_manager)"
    And the "<selector>" select box should not contain "Program manager (tool_program_manager)"
    And I log out
    Examples:
      | selector                      |
      | Role for visitors             |
      | Role for guest                |
      | Default role for all users    |
      | Creators' role in new courses |
      | Restorers' role in courses    |
