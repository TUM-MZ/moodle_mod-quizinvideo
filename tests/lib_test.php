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
}
