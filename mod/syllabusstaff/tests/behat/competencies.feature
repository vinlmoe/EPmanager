@mod @mod_syllabusstaff
Feature: Competencies of the modules, along the curriculum
  In order to see how the curriculum covers a competency framework
  As a module leader and the academic office
  I need to link my module to competencies with a level, and see the matrix and the progression

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
      | deve1    | Anne      | Durand   | deve1@example.com    |
    And the following "courses" exist:
      | fullname   | shortname |
      | Anatomy    | M1        |
      | Physiology | M2        |
      | Team       | TEAM      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | M1     | editingteacher |
      | teacher1 | TEAM   | teacher        |
      | deve1    | TEAM   | manager        |
    And the following "activities" exist:
      | activity      | course | name            | code | studyyear | semester |
      | syllabus      | M1     | Anatomy         | UE1  | 1         | S1       |
      | syllabus      | M2     | Physiology      | UE2  | 2         | S3       |
      | syllabusstaff | TEAM   | Module tracking |      |           |          |
    And the following "mod_syllabus > leaders" exist:
      | syllabus | user     |
      | Anatomy  | teacher1 |
    And the following "mod_syllabus > frameworks" exist:
      | name       | idnumber | competencies                                                  |
      | Vet skills | VET      | C1;;Clinical reasoning\|C1.1;C1;Clinical exam\|C1.2;C1;Diagnosis |

  Scenario: A leader links the module, the matrix and the progression show it
    Given I am on the "Anatomy" "mod_syllabus > Competencies" page logged in as teacher1
    When I set the field with xpath "//tr[contains(., 'Clinical exam')]//select" to "Introduced"
    And I press "Save changes"
    Then I should see "Competencies saved: 1 added"
    When I am on the "Module tracking" "mod_syllabusstaff > Matrix" page
    Then I should see "Competency matrix"
    And I should see "UE1"
    And "//td[normalize-space(.)='I']" "xpath_element" should exist
    When I follow "Clinical exam"
    Then I should see "Competency progression"
    And I should see "Anatomy"
    And I should see "No module targets this competency at the \"Mastered\" level."
