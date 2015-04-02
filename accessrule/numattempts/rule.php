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
 * Implementaton of the quizinvideoaccess_numattempts plugin.
 *
 * @package    quizinvideoaccess
 * @subpackage numattempts
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/accessrule/accessrulebase.php');


/**
 * A rule controlling the number of attempts allowed.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizinvideoaccess_numattempts extends quizinvideo_access_rule_base {

    public static function make(quizinvideo $quizinvideoobj, $timenow, $canignoretimelimits) {

        if ($quizinvideoobj->get_num_attempts_allowed() == 0) {
            return null;
        }

        return new self($quizinvideoobj, $timenow);
    }

    public function description() {
        return get_string('attemptsallowedn', 'quizinvideoaccess_numattempts', $this->quizinvideo->attempts);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($numprevattempts >= $this->quizinvideo->attempts) {
            return get_string('nomoreattempts', 'quizinvideo');
        }
        return false;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $numprevattempts >= $this->quizinvideo->attempts;
    }
}
