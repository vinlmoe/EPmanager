@mod @mod_syllabusstudent
Feature: A student gives feedback on modules from "My modules"
  In order to give my feedback on each of my modules
  As a student
  I need to find the modules awaiting my feedback, answer, and come back to the list

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Zoé       | Martin   | student1@example.com |
    And the following "courses" exist:
      | fullname        | shortname |
      | Anatomy         | M1        |
      | Physiology      | M2        |
      | Promotion 2026  | P2026     |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | M1     | student |
      | student1 | M2     | student |
      | student1 | P2026  | student |
    And the following "mod_syllabus > common questions" exist:
      | text                           | kind  | required |
      | The module was well organised. | scale | 1        |
    And the following "activities" exist:
      | activity        | course | name       | evalopen    | evalclose   | studyyear | semester |
      | syllabus        | M1     | Anatomy    | ##-1 day##  | ##+5 days## | 1         | S1       |
      | syllabus        | M2     | Physiology | ##-1 day##  | ##+5 days## | 1         | S2       |
      | syllabusstudent | P2026  | My modules |             |             |           |          |

  Scenario: The student answers, comes back to "My modules" and goes on with the next module
    Given I am on the "My modules" "mod_syllabusstudent > View" page logged in as student1
    Then I should see "Feedback to give (2)"
    And I should see "S1"
    When I follow "Give my feedback on \"Anatomy\""
    Then I should see "You can only answer once this year"
    When I set the field with xpath "//input[starts-with(@name, 'q')][@type='radio'][@value='4']" to "1"
    And I press "Send my feedback"
    Then I should see "Thank you: your feedback has been recorded."
    # Back on the list: one module left.
    And I should see "Feedback to give (1)"
    And I should see "Feedback given"
    And I should see "Give my feedback on \"Physiology\""

  Scenario: A required question must be answered
    Given I am on the "Anatomy" "mod_syllabus > Give feedback" page logged in as student1
    When I press "Send my feedback"
    Then I should see "Required"
    And I should not see "Thank you: your feedback has been recorded."
