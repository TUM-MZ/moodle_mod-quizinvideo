<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for (some of) mod/quizinvideo/locallib.php.
 *
 * @package    mod_quizinvideo
 * @category   test
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quizinvideo/lib.php');

/**
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class mod_quizinvideo_lib_testcase extends advanced_testcase {
    public function test_quizinvideo_has_grades() {
        $quizinvideo = new stdClass();
        $quizinvideo->grade = '100.0000';
        $quizinvideo->sumgrades = '100.0000';
        $this->assertTrue(quizinvideo_has_grades($quizinvideo));
        $quizinvideo->sumgrades = '0.0000';
        $this->assertFalse(quizinvideo_has_grades($quizinvideo));
        $quizinvideo->grade = '0.0000';
        $this->assertFalse(quizinvideo_has_grades($quizinvideo));
        $quizinvideo->sumgrades = '100.0000';
        $this->assertFalse(quizinvideo_has_grades($quizinvideo));
    }

    public function test_quizinvideo_format_grade() {
        $quizinvideo = new stdClass();
        $quizinvideo->decimalpoints = 2;
        $this->assertEquals(quizinvideo_format_grade($quizinvideo, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(quizinvideo_format_grade($quizinvideo, 0), format_float(0, 2));
        $this->assertEquals(quizinvideo_format_grade($quizinvideo, 1.000000000000), format_float(1, 2));
        $quizinvideo->decimalpoints = 0;
        $this->assertEquals(quizinvideo_format_grade($quizinvideo, 0.12345678), '0');
    }

    public function test_quizinvideo_get_grade_format() {
        $quizinvideo = new stdClass();
        $quizinvideo->decimalpoints = 2;
        $this->assertEquals(quizinvideo_get_grade_format($quizinvideo), 2);
        $this->assertEquals($quizinvideo->questiondecimalpoints, -1);
        $quizinvideo->questiondecimalpoints = 2;
        $this->assertEquals(quizinvideo_get_grade_format($quizinvideo), 2);
        $quizinvideo->decimalpoints = 3;
        $quizinvideo->questiondecimalpoints = -1;
        $this->assertEquals(quizinvideo_get_grade_format($quizinvideo), 3);
        $quizinvideo->questiondecimalpoints = 4;
        $this->assertEquals(quizinvideo_get_grade_format($quizinvideo), 4);
    }

    public function test_quizinvideo_format_question_grade() {
        $quizinvideo = new stdClass();
        $quizinvideo->decimalpoints = 2;
        $quizinvideo->questiondecimalpoints = 2;
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 0), format_float(0, 2));
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 1.000000000000), format_float(1, 2));
        $quizinvideo->decimalpoints = 3;
        $quizinvideo->questiondecimalpoints = -1;
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 0), format_float(0, 3));
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 1.000000000000), format_float(1, 3));
        $quizinvideo->questiondecimalpoints = 4;
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 0), format_float(0, 4));
        $this->assertEquals(quizinvideo_format_question_grade($quizinvideo, 1.000000000000), format_float(1, 4));
    }

    /**
     * Test deleting a quizinvideo instance.
     */
    public function test_quizinvideo_delete_instance() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a quizinvideo with 1 standard and 1 random question.
        $quizinvideogenerator = $this->getDataGenerator()->get_plugin_generator('mod_quizinvideo');
        $quizinvideo = $quizinvideogenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        quizinvideo_add_quizinvideo_question($standardq->id, $quizinvideo);
        quizinvideo_add_random_questions($quizinvideo, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        quizinvideo_delete_instance($quizinvideo->id);

        // Check that the random question was deleted.
        $count = $DB->count_records('question', array('id' => $randomq->id));
        $this->assertEquals(0, $count);
        // Check that the standard question was not deleted.
        $count = $DB->count_records('question', array('id' => $standardq->id));
        $this->assertEquals(1, $count);

        // Check that all the slots were removed.
        $count = $DB->count_records('quizinvideo_slots', array('quizinvideoid' => $quizinvideo->id));
        $this->assertEquals(0, $count);

        // Check that the quizinvideo was removed.
        $count = $DB->count_records('quizinvideo', array('id' => $quizinvideo->id));
        $this->assertEquals(0, $count);
    }

    /**
     * Test checking the completion state of a quizinvideo.
     */
    public function test_quizinvideo_get_completion_state() {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and student.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => true));
        $passstudent = $this->getDataGenerator()->create_user();
        $failstudent = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);

        // Enrol students.
        $this->assertTrue($this->getDataGenerator()->enrol_user($passstudent->id, $course->id, $studentrole->id));
        $this->assertTrue($this->getDataGenerator()->enrol_user($failstudent->id, $course->id, $studentrole->id));

        // Make a scale and an outcome.
        $scale = $this->getDataGenerator()->create_scale();
        $data = array('courseid' => $course->id,
                      'fullname' => 'Team work',
                      'shortname' => 'Team work',
                      'scaleid' => $scale->id);
        $outcome = $this->getDataGenerator()->create_grade_outcome($data);

        // Make a quizinvideo with the outcome on.
        $quizinvideogenerator = $this->getDataGenerator()->get_plugin_generator('mod_quizinvideo');
        $data = array('course' => $course->id,
                      'outcome_'.$outcome->id => 1,
                      'grade' => 100.0,
                      'questionsperpage' => 0,
                      'sumgrades' => 1,
                      'completion' => COMPLETION_TRACKING_AUTOMATIC,
                      'completionpass' => 1);
        $quizinvideo = $quizinvideogenerator->create_instance($data);
        $cm = get_coursemodule_from_id('quizinvideo', $quizinvideo->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        quizinvideo_add_quizinvideo_question($question->id, $quizinvideo);

        $quizinvideoobj = quizinvideo::create($quizinvideo->id, $passstudent->id);

        // Set grade to pass.
        $item = grade_item::fetch(array('courseid' => $course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'quizinvideo', 'iteminstance' => $quizinvideo->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quizinvideo', $quizinvideoobj->get_context());
        $quba->set_preferred_behaviour($quizinvideoobj->get_quizinvideo()->preferredbehaviour);

        $timenow = time();
        $attempt = quizinvideo_create_attempt($quizinvideoobj, 1, false, $timenow, false, $passstudent->id);
        quizinvideo_start_new_attempt($quizinvideoobj, $quba, $attempt, 1, $timenow);
        quizinvideo_attempt_save_started($quizinvideoobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = quizinvideo_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = quizinvideo_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Start the failing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quizinvideo', $quizinvideoobj->get_context());
        $quba->set_preferred_behaviour($quizinvideoobj->get_quizinvideo()->preferredbehaviour);

        $timenow = time();
        $attempt = quizinvideo_create_attempt($quizinvideoobj, 1, false, $timenow, false, $failstudent->id);
        quizinvideo_start_new_attempt($quizinvideoobj, $quba, $attempt, 1, $timenow);
        quizinvideo_attempt_save_started($quizinvideoobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = quizinvideo_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '0'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = quizinvideo_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check the results.
        $this->assertTrue(quizinvideo_get_completion_state($course, $cm, $passstudent->id, 'return'));
        $this->assertFalse(quizinvideo_get_completion_state($course, $cm, $failstudent->id, 'return'));
    }
}
