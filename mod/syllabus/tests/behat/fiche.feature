@mod @mod_syllabus
Feature: Writing a module syllabus and having it reviewed by the academic office
  In order to publish the syllabus of a teaching module
  As a module leader and the academic office
  I need to write it in several sittings, submit it complete, and have it reviewed from a list

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
      | deve1    | Anne      | Durand   | deve1@example.com    |
    And the following "courses" exist:
      | fullname | shortname |
      | Anatomy  | M1        |
      | Team     | TEAM      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | M1     | editingteacher |
      | deve1    | M1     | manager        |
      | deve1    | TEAM   | manager        |
    And the following "activities" exist:
      | activity      | course | name            | code |
      | syllabus      | M1     | Anatomy         | UE1  |
      | syllabusstaff | TEAM   | Module tracking |      |
    And the following "mod_syllabus > leaders" exist:
      | syllabus | user     |
      | Anatomy  | teacher1 |

  Scenario: A leader saves a draft without leaving the form, and cannot submit an incomplete syllabus
    Given I am on the "Anatomy" "mod_syllabus > Edit" page logged in as teacher1
    When I set the field "Objectives" to "Know comparative anatomy."
    And I press "Save draft"
    Then I should see "Draft saved."
    # The leader stays on the form.
    And the field "Objectives" matches value "Know comparative anatomy."
    When I press "Save and submit to the academic office"
    Then I should see "Required before submitting the syllabus."
    And I should see "Enter at least one session (or personal work hours) before submitting the syllabus."
    When I set the following fields to these values:
      | Intended learning outcomes  | Identify, explain, apply.       |
      | Content                     | Lectures and practical classes. |
      | Assessment methods          | Written exam.                   |
      | sessionrow[0][sessiontitle] | Introduction                    |
    And I press "Save and submit to the academic office"
    Then I should see "Syllabus sent to the academic office."
    And I should see "Where this module stands"
    And I should see "Submitted to the academic office"

  Scenario: The academic office reviews a syllabus from the list and comes back to it
    Given the following "mod_syllabus > fiches" exist:
      | syllabus | status    | objectives                |
      | Anatomy  | submitted | Know comparative anatomy. |
    And I am on the "Module tracking" "mod_syllabusstaff > To review" page logged in as deve1
    And I should see "Anatomy"
    When I follow "Review"
    Then I should see "Know comparative anatomy."
    And I should see "Go to the decision"
    # Returning the syllabus requires a comment.
    When I press "Return to the module leaders"
    Then I should see "A comment is required to return or reject a syllabus."
    When I press "Publish"
    Then I should see "Syllabus published."
    And I should see "Syllabi to review (0)"
