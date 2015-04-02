@mod @mod_quizinvideo
Feature: Add a quizinvideo
  In order to evaluate students
  As a teacher
  I need to create a quizinvideo

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Terry1    | Teacher1 | teacher1@moodle.com |
      | student1 | Sam1      | Student1 | student1@moodle.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "quizinvideo" to section "1" and I fill the form with:
      | Name        | Test quizinvideo name        |
      | Description | Test quizinvideo description |
    And I add a "True/False" question to the "Test quizinvideo name" quizinvideo with:
      | Question name                      | First question                          |
      | Question text                      | Answer the first question               |
      | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      | Feedback for the response 'True'.  | So you think it is true                 |
      | Feedback for the response 'False'. | So you think it is false                |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test quizinvideo name"
    And I press "Attempt quizinvideo now"
    Then I should see "Question 1"
    And I should see "Answer the first question"
    And I set the field "True" to "1"
    And I press "Next"
    And I should see "Answer saved"
    And I press "Submit all and finish"

  @javascript
  Scenario: Add and configure small quizinvideo and perform an attempt as a student with Javascript enabled
    Then I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I should see "So you think it is true"
    And I should see "Thank you, this is the general feedback"
    And I should see "The correct answer is 'False'."
    And I follow "Finish review"
    And I should see "Highest grade: 0.00 / 10.00."
    And I log out

  Scenario: Add and configure small quizinvideo and perform an attempt as a student with Javascript disabled
    Then I should see "So you think it is true"
    And I should see "Thank you, this is the general feedback"
    And I should see "The correct answer is 'False'."
    And I follow "Finish review"
    And I should see "Highest grade: 0.00 / 10.00."
    And I log out
