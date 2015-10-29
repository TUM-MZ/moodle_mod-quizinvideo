@mod @mod_quizinvideo
Feature: Set a quizinvideo to be marked complete when the student uses all attempts allowed
  In order to ensure a student has learned the material before being marked complete
  As a teacher
  I need to set a quizinvideo to complete when the student receives a passing grade, or completed_fail if they use all attempts without passing

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following config values are set as admin:
      | enablecompletion    | 1           |
      | grade_item_advanced | hiddenuntil |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name           | questiontext              |
      | Test questions   | truefalse | First question | Answer the first question |
    And the following "activities" exist:
      | activity   | name           | course | idnumber | attempts | gradepass | completion | completionattemptsexhausted |
      | quizinvideo       | Test quizinvideo name | C1     | quizinvideo1    | 2        | 5.00      | 2          | 1                           |
    And quizinvideo "Test quizinvideo name" contains the following questions:
      | question       | page |
      | First question | 1    |

  Scenario: student1 uses up both attempts without passing
    When I log in as "student1"
    And I follow "Course 1"
    And "//img[contains(@alt, 'Not completed: Test quizinvideo name')]" "xpath_element" should exist in the "li.modtype_quizinvideo" "css_element"
    And I follow "Test quizinvideo name"
    And I press "Attempt quizinvideo now"
    And I set the field "False" to "1"
    And I press "Next"
    And I press "Submit all and finish"
    And I follow "C1"
    And "//img[contains(@alt, 'Not completed: Test quizinvideo name')]" "xpath_element" should exist in the "li.modtype_quizinvideo" "css_element"
    And I follow "Test quizinvideo name"
    And I press "Re-attempt quizinvideo"
    And I set the field "False" to "1"
    And I press "Next"
    And I press "Submit all and finish"
    And I follow "C1"
    Then "//img[contains(@alt, 'Completed: Test quizinvideo name')]" "xpath_element" should exist in the "li.modtype_quizinvideo" "css_element"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I navigate to "Activity completion" node in "Course administration > Reports"
    And "//img[contains(@title,'Test quizinvideo name') and @alt='Completed']" "xpath_element" should exist in the "Student 1" "table_row"
