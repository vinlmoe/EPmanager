@mod @mod_syllabus
Feature: Student feedback collected each academic year, past years kept
  In order to follow a module from one year to the next
  As the academic office and a module leader
  I need opening feedback in a new academic year to archive the previous one, and past years to stay readable

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
      | deve1    | Anne      | Durand   | deve1@example.com    |
      | student1 | Zoé       | Martin   | student1@example.com |
      | student2 | Paul      | Petit    | student2@example.com |
      | student3 | Léa       | Roux     | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Anatomy  | M1        |
      | Team     | TEAM      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | M1     | editingteacher |
      | deve1    | M1     | manager        |
      | deve1    | TEAM   | manager        |
      | student1 | M1     | student        |
      | student2 | M1     | student        |
      | student3 | M1     | student        |
    And the following "mod_syllabus > common questions" exist:
      | text                           | kind  | required |
      | The module was well organised. | scale | 1        |
    And the following "activities" exist:
      | activity      | course | name            | evalopen     | evalclose   |
      | syllabus      | M1     | Anatomy         | ##-2 days##  | ##+2 days## |
      | syllabusstaff | TEAM   | Module tracking |              |             |
    And the following "mod_syllabus > leaders" exist:
      | syllabus | user     |
      | Anatomy  | teacher1 |
    # Last year's feedback: three answers, mean 3.
    And the following "mod_syllabus > feedback" exist:
      | syllabus | user     | value |
      | Anatomy  | student1 | 4     |
      | Anatomy  | student2 | 2     |
      | Anatomy  | student3 | 3     |
    And the following "mod_syllabus > feedback years" exist:
      | syllabus | evalyear  |
      | Anatomy  | 2020-2021 |

  Scenario: Opening feedback in a new academic year archives last year and starts afresh
    Given I am on the "Module tracking" "mod_syllabusstaff > All modules" page logged in as deve1
    When I set the field with xpath "//input[@name='syllabusids[]']" to "1"
    And I set the field "bulkopen" to "##-1 hour##%Y-%m-%dT%H:%M##"
    And I set the field "bulkclose" to "##+3 days##%Y-%m-%dT%H:%M##"
    And I press "Apply"
    Then I should see "Window set for 1 module(s)"
    # Last year's results are kept; this year starts with no answer.
    When I am on the "Anatomy" "mod_syllabus > Results" page
    Then I should see "Academic year"
    And I should see "2020-2021"
    And I should see "0 response(s)"
    And I should see "Change in means from one year to the next"
    # The leader reads last year's results while this year's collection is still open.
    When I am on the "Anatomy" "mod_syllabus > Results" page logged in as teacher1
    Then I should see "Feedback results 2020-2021"
    And I should see "3 response(s)"
    And I should see "3 / 5"
    # Students can give their feedback again this year.
    When I am on the "Anatomy" "mod_syllabus > View" page logged in as student1
    Then I should see "Give my feedback"
