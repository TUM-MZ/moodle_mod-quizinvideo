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
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');


/**
 * Unit tests for (some of) mod/quizinvideo/locallib.php.
 *
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizinvideo_locallib_testcase extends basic_testcase {

    public function test_quizinvideo_rescale_grade() {
        $quizinvideo = new stdClass();
        $quizinvideo->decimalpoints = 2;
        $quizinvideo->questiondecimalpoints = 3;
        $quizinvideo->grade = 10;
        $quizinvideo->sumgrades = 10;
        $this->assertEquals(quizinvideo_rescale_grade(0.12345678, $quizinvideo, false), 0.12345678);
        $this->assertEquals(quizinvideo_rescale_grade(0.12345678, $quizinvideo, true), format_float(0.12, 2));
        $this->assertEquals(quizinvideo_rescale_grade(0.12345678, $quizinvideo, 'question'),
            format_float(0.123, 3));
        $quizinvideo->sumgrades = 5;
        $this->assertEquals(quizinvideo_rescale_grade(0.12345678, $quizinvideo, false), 0.24691356);
        $this->assertEquals(quizinvideo_rescale_grade(0.12345678, $quizinvideo, true), format_float(0.25, 2));
        $this->assertEquals(quizinvideo_rescale_grade(0.12345678, $quizinvideo, 'question'),
            format_float(0.247, 3));
    }

    public function test_quizinvideo_attempt_state_in_progress() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::IN_PROGRESS;
        $attempt->timefinish = 0;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = 0;

        $this->assertEquals(mod_quizinvideo_display_options::DURING, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_recently_submitted() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::FINISHED;
        $attempt->timefinish = time() - 10;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = 0;

        $this->assertEquals(mod_quizinvideo_display_options::IMMEDIATELY_AFTER, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_sumitted_quizinvideo_never_closes() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::FINISHED;
        $attempt->timefinish = time() - 7200;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = 0;

        $this->assertEquals(mod_quizinvideo_display_options::LATER_WHILE_OPEN, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_sumitted_quizinvideo_closes_later() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::FINISHED;
        $attempt->timefinish = time() - 7200;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = time() + 3600;

        $this->assertEquals(mod_quizinvideo_display_options::LATER_WHILE_OPEN, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_sumitted_quizinvideo_closed() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::FINISHED;
        $attempt->timefinish = time() - 7200;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = time() - 3600;

        $this->assertEquals(mod_quizinvideo_display_options::AFTER_CLOSE, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_never_sumitted_quizinvideo_never_closes() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::ABANDONED;
        $attempt->timefinish = 1000; // A very long time ago!

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = 0;

        $this->assertEquals(mod_quizinvideo_display_options::LATER_WHILE_OPEN, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_never_sumitted_quizinvideo_closes_later() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::ABANDONED;
        $attempt->timefinish = time() - 7200;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = time() + 3600;

        $this->assertEquals(mod_quizinvideo_display_options::LATER_WHILE_OPEN, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_attempt_state_never_sumitted_quizinvideo_closed() {
        $attempt = new stdClass();
        $attempt->state = quizinvideo_attempt::ABANDONED;
        $attempt->timefinish = time() - 7200;

        $quizinvideo = new stdClass();
        $quizinvideo->timeclose = time() - 3600;

        $this->assertEquals(mod_quizinvideo_display_options::AFTER_CLOSE, quizinvideo_attempt_state($quizinvideo, $attempt));
    }

    public function test_quizinvideo_question_tostring() {
        $question = new stdClass();
        $question->qtype = 'multichoice';
        $question->name = 'The question name';
        $question->questiontext = '<p>What sort of <b>inequality</b> is x &lt; y<img alt="?" src="..."></p>';
        $question->questiontextformat = FORMAT_HTML;

        $summary = quizinvideo_question_tostring($question);
        $this->assertEquals('<span class="questionname">The question name</span> ' .
                '<span class="questiontext">What sort of INEQUALITY is x &lt; y[?]</span>', $summary);
    }
}
