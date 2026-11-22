@enrol @enrol_domain
Feature: Self enrolment limited to email domains
  In order to open a course to one school only
  As a teacher
  I need only people with that school's email address to be able to enrol themselves

  Background:
    Given the following "users" exist:
      | username | email          |
      | teacher1 | t1@example.com |
      | ana      | ana@school.edu |
      | eve      | eve@gmail.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Email domain self enrolment" "table_row"
    And I add "Email domain self enrolment" enrolment method in "Course 1" with:
      | Allowed email domains | school.edu |
    And I log out

  Scenario: A user at the domain enrols, another is told why not
    Given I log in as "ana"
    And I am on "Course 1" course homepage
    When I press "Enrol me"
    Then I should see "Course 1"
    And I should not see "Enrol me"
    And I log out
    And I log in as "eve"
    And I am on "Course 1" course homepage
    And I should see "Only people with an email address at school.edu can enrol themselves in this course."
    And "Enrol me" "button" should not exist
