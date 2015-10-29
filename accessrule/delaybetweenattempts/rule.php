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
 * Implementaton of the quizinvideoaccess_delaybetweenattempts plugin.
 *
 * @package    quizinvideoaccess
 * @subpackage delaybetweenattempts
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/accessrule/accessrulebase.php');


/**
 * A rule imposing the delay between attempts settings.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizinvideoaccess_delaybetweenattempts extends quizinvideo_access_rule_base {

    public static function make(quizinvideo $quizinvideoobj, $timenow, $canignoretimelimits) {
        if (empty($quizinvideoobj->get_quizinvideo()->delay1) && empty($quizinvideoobj->get_quizinvideo()->delay2)) {
            return null;
        }

        return new self($quizinvideoobj, $timenow);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($this->quizinvideo->attempts > 0 && $numprevattempts >= $this->quizinvideo->attempts) {
            // No more attempts allowed anyway.
            return false;
        }
        if ($this->quizinvideo->timeclose != 0 && $this->timenow > $this->quizinvideo->timeclose) {
            // No more attempts allowed anyway.
            return false;
        }
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        if ($this->timenow < $nextstarttime) {
            if ($this->quizinvideo->timeclose == 0 || $nextstarttime <= $this->quizinvideo->timeclose) {
                return get_string('youmustwait', 'quizinvideoaccess_delaybetweenattempts',
                        userdate($nextstarttime));
            } else {
                return get_string('youcannotwait', 'quizinvideoaccess_delaybetweenattempts');
            }
        }
        return false;
    }

    /**
     * Compute the next time a student would be allowed to start an attempt,
     * according to this rule.
     * @param int $numprevattempts number of previous attempts.
     * @param object $lastattempt information about the previous attempt.
     * @return number the time.
     */
    protected function compute_next_start_time($numprevattempts, $lastattempt) {
        if ($numprevattempts == 0) {
            return 0;
        }

        $lastattemptfinish = $lastattempt->timefinish;
        if ($this->quizinvideo->timelimit > 0) {
            $lastattemptfinish = min($lastattemptfinish,
                    $lastattempt->timestart + $this->quizinvideo->timelimit);
        }

        if ($numprevattempts == 1 && $this->quizinvideo->delay1) {
            return $lastattemptfinish + $this->quizinvideo->delay1;
        } else if ($numprevattempts > 1 && $this->quizinvideo->delay2) {
            return $lastattemptfinish + $this->quizinvideo->delay2;
        }
        return 0;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        return $this->timenow <= $nextstarttime &&
        $this->quizinvideo->timeclose != 0 && $nextstarttime >= $this->quizinvideo->timeclose;
    }
}
