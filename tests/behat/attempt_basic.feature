@mod @mod_quizinvideo
Feature: Attemp a quizinvideo where some questions require that the previous question has been answered.
  As a student
  In order to demonstrate what I know
  I need to be able to attempt quizinvideozes

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quizinvideo       | quizinvideo 1 | quizinvideo 1 description | C1     | quizinvideo1    |

  @javascript
  Scenario: Attempt a quizinvideo with a single unnamed section
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And quizinvideo "quizinvideo 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |
      | TF2      | 1    | 3.0     |
    When I log in as "student"
    And I follow "Course 1"
    And I follow "quizinvideo 1"
    And I press "Attempt quizinvideo now"
    And I click on "True" "radio" in the "First question" "question"
    And I click on "False" "radio" in the "Second question" "question"
    And I press "Next"
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    Then I should see "25.00 out of 100.00"

  @javascript
  Scenario: Attempt a quizinvideo with mulitple sections
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
      | Test questions   | truefalse   | TF3   | Third question  |
      | Test questions   | truefalse   | TF4   | Fourth question |
      | Test questions   | truefalse   | TF5   | Fifth question  |
      | Test questions   | truefalse   | TF6   | Sixth question  |
    And quizinvideo "quizinvideo 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 1    |
      | TF3      | 2    |
      | TF4      | 3    |
      | TF5      | 4    |
      | TF6      | 4    |
    And quizinvideo "quizinvideo 1" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section 1 | 1         | 1       |
      | Section 2 | 3         | 0       |
      |           | 4         | 1       |
      | Section 3 | 5         | 0       |

    When I log in as "student"
    And I follow "Course 1"
    And I follow "quizinvideo 1"
    And I press "Attempt quizinvideo now"

    Then I should see "Section 1" in the "quizinvideo navigation" "block"
    And I should see question "1" in section "Section 1" in the quizinvideo navigation
    And I should see question "2" in section "Section 1" in the quizinvideo navigation
    And I should see question "3" in section "Section 2" in the quizinvideo navigation
    And I should see question "4" in section "Section 2" in the quizinvideo navigation
    And I should see question "5" in section "Section 3" in the quizinvideo navigation
    And I should see question "6" in section "Section 3" in the quizinvideo navigation

    And I follow "Finish attempt ..."
    And I should see question "1" in section "Section 1" in the quizinvideo navigation
    And I should see question "2" in section "Section 1" in the quizinvideo navigation
    And I should see question "3" in section "Section 2" in the quizinvideo navigation
    And I should see question "4" in section "Section 2" in the quizinvideo navigation
    And I should see question "5" in section "Section 3" in the quizinvideo navigation
    And I should see question "6" in section "Section 3" in the quizinvideo navigation
    And I should see "Section 1" in the "quizinvideosummaryofattempt" "table"
    And I should see "Section 2" in the "quizinvideosummaryofattempt" "table"
    And I should see "Section 3" in the "quizinvideosummaryofattempt" "table"

    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I should see question "1" in section "Section 1" in the quizinvideo navigation
    And I should see question "2" in section "Section 1" in the quizinvideo navigation
    And I should see question "3" in section "Section 2" in the quizinvideo navigation
    And I should see question "4" in section "Section 2" in the quizinvideo navigation
    And I should see question "5" in section "Section 3" in the quizinvideo navigation
    And I should see question "6" in section "Section 3" in the quizinvideo navigation
