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
 * Implementaton of the quizinvideoaccess_openclosedate plugin.
 *
 * @package    quizinvideoaccess
 * @subpackage openclosedate
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/accessrule/accessrulebase.php');


/**
 * A rule enforcing open and close dates.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizinvideoaccess_openclosedate extends quizinvideo_access_rule_base {

    public static function make(quizinvideo $quizinvideoobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the quizinvideo has no open or close date.
        return new self($quizinvideoobj, $timenow);
    }

    public function description() {
        $result = array();
        if ($this->timenow < $this->quizinvideo->timeopen) {
            $result[] = get_string('quizinvideonotavailable', 'quizinvideoaccess_openclosedate',
                    userdate($this->quizinvideo->timeopen));
            if ($this->quizinvideo->timeclose) {
                $result[] = get_string('quizinvideocloseson', 'quizinvideo', userdate($this->quizinvideo->timeclose));
            }

        } else if ($this->quizinvideo->timeclose && $this->timenow > $this->quizinvideo->timeclose) {
            $result[] = get_string('quizinvideoclosed', 'quizinvideo', userdate($this->quizinvideo->timeclose));

        } else {
            if ($this->quizinvideo->timeopen) {
                $result[] = get_string('quizinvideoopenedon', 'quizinvideo', userdate($this->quizinvideo->timeopen));
            }
            if ($this->quizinvideo->timeclose) {
                $result[] = get_string('quizinvideocloseson', 'quizinvideo', userdate($this->quizinvideo->timeclose));
            }
        }

        return $result;
    }

    public function prevent_access() {
        $message = get_string('notavailable', 'quizinvideoaccess_openclosedate');

        if ($this->timenow < $this->quizinvideo->timeopen) {
            return $message;
        }

        if (!$this->quizinvideo->timeclose) {
            return false;
        }

        if ($this->timenow <= $this->quizinvideo->timeclose) {
            return false;
        }

        if ($this->quizinvideo->overduehandling != 'graceperiod') {
            return $message;
        }

        if ($this->timenow <= $this->quizinvideo->timeclose + $this->quizinvideo->graceperiod) {
            return false;
        }

        return $message;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $this->quizinvideo->timeclose && $this->timenow > $this->quizinvideo->timeclose;
    }

    public function end_time($attempt) {
        if ($this->quizinvideo->timeclose) {
            return $this->quizinvideo->timeclose;
        }
        return false;
    }

    public function time_left_display($attempt, $timenow) {
        // If this is a teacher preview after the close date, do not show
        // the time.
        if ($attempt->preview && $timenow > $this->quizinvideo->timeclose) {
            return false;
        }
        // Otherwise, return to the time left until the close date, providing that is
        // less than quizinvideo_SHOW_TIME_BEFORE_DEADLINE.
        $endtime = $this->end_time($attempt);
        if ($endtime !== false && $timenow > $endtime - quizinvideo_SHOW_TIME_BEFORE_DEADLINE) {
            return $endtime - $timenow;
        }
        return false;
    }
}
