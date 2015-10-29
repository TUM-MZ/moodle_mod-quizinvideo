@mod @mod_quizinvideo
Feature: Backup and restore of quizinvideozes
  In order to reuse my quizinvideozes
  As a teacher
  I need to be able to back them up and restore them.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And I log in as "admin"

  @javascript
  Scenario: Duplicate a quizinvideo with two questions
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quizinvideo       | quizinvideo 1 | For testing backup | C1     | quizinvideo1    |
    And the following "questions" exist:
      | questioncategory | qtype       | name | questiontext    |
      | Test questions   | truefalse   | TF1  | First question  |
      | Test questions   | truefalse   | TF2  | Second question |
    And quizinvideo "quizinvideo 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    And I am on site homepage
    When I follow "Course 1"
    And I turn editing mode on
    And I duplicate "quizinvideo 1" activity editing the new copy with:
      | Name | quizinvideo 2 |
    And I follow "quizinvideo 2"
    And I follow "Edit quizinvideo"
    Then I should see "TF1"
    And I should see "TF2"

  @javascript @_file_upload
  Scenario: Restore a Moodle 2.8 quizinvideo backup
    And I am on site homepage
    When I follow "Course 1"
    And I navigate to "Restore" node in "Course administration"
    And I press "Manage backup files"
    And I upload "mod/quizinvideo/tests/fixtures/moodle_28_quizinvideo.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "moodle_28_quizinvideo.mbz" backup into "Course 1" course using this options:
    And I follow "Restored Moodle 2.8 quizinvideo"
    And I follow "Edit quizinvideo"
    Then I should see "TF1"
    And I should see "TF2"
