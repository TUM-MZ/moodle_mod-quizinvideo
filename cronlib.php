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
 * Library code used by quizinvideo cron.
 *
 * @package   mod_quizinvideo
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');


/**
 * This class holds all the code for automatically updating all attempts that have
 * gone over their time limit.
 *
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizinvideo_overdue_attempt_updater {

    /**
     * Do the processing required.
     * @param int $timenow the time to consider as 'now' during the processing.
     * @param int $processto only process attempt with timecheckstate longer ago than this.
     * @return array with two elements, the number of attempt considered, and how many different quizinvideos that was.
     */
    public function update_overdue_attempts($timenow, $processto) {
        global $DB;

        $attemptstoprocess = $this->get_list_of_overdue_attempts($processto);

        $course = null;
        $quizinvideo = null;
        $cm = null;

        $count = 0;
        $quizinvideocount = 0;
        foreach ($attemptstoprocess as $attempt) {
            try {

                // If we have moved on to a different quizinvideo, fetch the new data.
                if (!$quizinvideo || $attempt->quizinvideo != $quizinvideo->id) {
                    $quizinvideo = $DB->get_record('quizinvideo', array('id' => $attempt->quizinvideo), '*', MUST_EXIST);
                    $cm = get_coursemodule_from_instance('quizinvideo', $attempt->quizinvideo);
                    $quizinvideocount += 1;
                }

                // If we have moved on to a different course, fetch the new data.
                if (!$course || $course->id != $quizinvideo->course) {
                    $course = $DB->get_record('course', array('id' => $quizinvideo->course), '*', MUST_EXIST);
                }

                // Make a specialised version of the quizinvideo settings, with the relevant overrides.
                $quizinvideoforuser = clone($quizinvideo);
                $quizinvideoforuser->timeclose = $attempt->usertimeclose;
                $quizinvideoforuser->timelimit = $attempt->usertimelimit;

                // Trigger any transitions that are required.
                $attemptobj = new quizinvideo_attempt($attempt, $quizinvideoforuser, $cm, $course);
                $attemptobj->handle_if_time_expired($timenow, false);
                $count += 1;

            } catch (moodle_exception $e) {
                // If an error occurs while processing one attempt, don't let that kill cron.
                mtrace("Error while processing attempt {$attempt->id} at {$attempt->quizinvideo} quizinvideo:");
                mtrace($e->getMessage());
                mtrace($e->getTraceAsString());
                // Close down any currently open transactions, otherwise one error
                // will stop following DB changes from being committed.
                $DB->force_transaction_rollback();
            }
        }

        $attemptstoprocess->close();
        return array($count, $quizinvideocount);
    }

    /**
     * @return moodle_recordset of quizinvideo_attempts that need to be processed because time has
     *     passed. The array is sorted by courseid then quizinvideoid.
     */
    public function get_list_of_overdue_attempts($processto) {
        global $DB;


        // SQL to compute timeclose and timelimit for each attempt:
        $quizinvideoausersql = quizinvideo_get_attempt_usertime_sql(
                "iquizinvideoa.state IN ('inprogress', 'overdue') AND iquizinvideoa.timecheckstate <= :iprocessto");

        // This query should have all the quizinvideo_attempts columns.
        return $DB->get_recordset_sql("
         SELECT quizinvideoa.*,
                quizinvideoauser.usertimeclose,
                quizinvideoauser.usertimelimit

           FROM {quizinvideo_attempts} quizinvideoa
           JOIN {quizinvideo} quizinvideo ON quizinvideo.id = quizinvideoa.quizinvideo
           JOIN ( $quizinvideoausersql ) quizinvideoauser ON quizinvideoauser.id = quizinvideoa.id

          WHERE quizinvideoa.state IN ('inprogress', 'overdue')
            AND quizinvideoa.timecheckstate <= :processto
       ORDER BY quizinvideo.course, quizinvideoa.quizinvideo",

                array('processto' => $processto, 'iprocessto' => $processto));
    }
}
