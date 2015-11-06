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
 * Library of functions for the quizinvideo module.
 *
 * This contains functions that are called also from outside the quizinvideo module
 * Functions that are only called by the quizinvideo module itself are in {@link locallib.php}
 *
 * @package    mod_quizinvideo
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/eventslib.php');
require_once($CFG->dirroot . '/calendar/lib.php');


/**#@+
 * Option controlling what options are offered on the quizinvideo settings form.
 */
define('quizinvideo_MAX_ATTEMPT_OPTION', 10);
define('quizinvideo_MAX_QPP_OPTION', 50);
define('quizinvideo_MAX_DECIMAL_OPTION', 5);
define('quizinvideo_MAX_Q_DECIMAL_OPTION', 7);
/**#@-*/

/**#@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user
 */
define('quizinvideo_GRADEHIGHEST', '1');
define('quizinvideo_GRADEAVERAGE', '2');
define('quizinvideo_ATTEMPTFIRST', '3');
define('quizinvideo_ATTEMPTLAST',  '4');
/**#@-*/

/**
 * @var int If start and end date for the quizinvideo are more than this many seconds apart
 * they will be represented by two separate events in the calendar
 */
define('quizinvideo_MAX_EVENT_LENGTH', 5*24*60*60); // 5 days.

/**#@+
 * Options for navigation method within quizinvideos.
 */
define('quizinvideo_NAVMETHOD_FREE', 'free');
define('quizinvideo_NAVMETHOD_SEQ',  'sequential');
/**#@-*/

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $quizinvideo the data that came from the form.
 * @return mixed the id of the new instance on success,
 *          false or a string error message on failure.
 */
function quizinvideo_add_instance($quizinvideo) {
    global $DB;
    $cmid = $quizinvideo->coursemodule;

    // Process the options from the form.
    $quizinvideo->created = time();
    $result = quizinvideo_process_options($quizinvideo);
    if ($result && is_string($result)) {
        return $result;
    }

    // Try to store it in the database.
    $quizinvideo->id = $DB->insert_record('quizinvideo', $quizinvideo);

    // Create the first section for this quizinvideo.
    $DB->insert_record('quizinvideo_sections', array('quizinvideoid' => $quizinvideo->id,
            'firstslot' => 1, 'heading' => '', 'shufflequestions' => 0));

    // Do the processing required after an add or an update.
    quizinvideo_after_add_or_update($quizinvideo);

    return $quizinvideo->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $quizinvideo the data that came from the form.
 * @return mixed true on success, false or a string error message on failure.
 */
function quizinvideo_update_instance($quizinvideo, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

    // Process the options from the form.
    $result = quizinvideo_process_options($quizinvideo);
    if ($result && is_string($result)) {
        return $result;
    }

    // Get the current value, so we can see what changed.
    $oldquizinvideo = $DB->get_record('quizinvideo', array('id' => $quizinvideo->instance));

    // We need two values from the existing DB record that are not in the form,
    // in some of the function calls below.
    $quizinvideo->sumgrades = $oldquizinvideo->sumgrades;
    $quizinvideo->grade     = $oldquizinvideo->grade;

    // Update the database.
    $quizinvideo->id = $quizinvideo->instance;
    $DB->update_record('quizinvideo', $quizinvideo);

    // Do the processing required after an add or an update.
    quizinvideo_after_add_or_update($quizinvideo);

    if ($oldquizinvideo->grademethod != $quizinvideo->grademethod) {
        quizinvideo_update_all_final_grades($quizinvideo);
        quizinvideo_update_grades($quizinvideo);
    }

    $quizinvideodateschanged = $oldquizinvideo->timelimit   != $quizinvideo->timelimit
                     || $oldquizinvideo->timeclose   != $quizinvideo->timeclose
                     || $oldquizinvideo->graceperiod != $quizinvideo->graceperiod;
    if ($quizinvideodateschanged) {
        quizinvideo_update_open_attempts(array('quizinvideoid' => $quizinvideo->id));
    }

    // Delete any previous preview attempts.
    quizinvideo_delete_previews($quizinvideo);

    // Repaginate, if asked to.
    if (!empty($quizinvideo->repaginatenow)) {
        quizinvideo_repaginate_questions($quizinvideo->id, $quizinvideo->questionsperpage);
    }

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id the id of the quizinvideo to delete.
 * @return bool success or failure.
 */
function quizinvideo_delete_instance($id) {
    global $DB;

    $quizinvideo = $DB->get_record('quizinvideo', array('id' => $id), '*', MUST_EXIST);

    quizinvideo_delete_all_attempts($quizinvideo);
    quizinvideo_delete_all_overrides($quizinvideo);

    // Look for random questions that may no longer be used when this quizinvideo is gone.
    $sql = "SELECT q.id
              FROM {quizinvideo_slots} slot
              JOIN {question} q ON q.id = slot.questionid
             WHERE slot.quizinvideoid = ? AND q.qtype = ?";
    $questionids = $DB->get_fieldset_sql($sql, array($quizinvideo->id, 'random'));

    // We need to do this before we try and delete randoms, otherwise they would still be 'in use'.
    $DB->delete_records('quizinvideo_slots', array('quizinvideoid' => $quizinvideo->id));
    $DB->delete_records('quizinvideo_sections', array('quizinvideoid' => $quizinvideo->id));

    foreach ($questionids as $questionid) {
        question_delete_question($questionid);
    }

    $DB->delete_records('quizinvideo_feedback', array('quizinvideoid' => $quizinvideo->id));

    quizinvideo_access_manager::delete_settings($quizinvideo);

    $events = $DB->get_records('event', array('modulename' => 'quizinvideo', 'instance' => $quizinvideo->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    quizinvideo_grade_item_delete($quizinvideo);
    $DB->delete_records('quizinvideo', array('id' => $quizinvideo->id));

    return true;
}

/**
 * Deletes a quizinvideo override from the database and clears any corresponding calendar events
 *
 * @param object $quizinvideo The quizinvideo object.
 * @param int $overrideid The id of the override being deleted
 * @return bool true on success
 */
function quizinvideo_delete_override($quizinvideo, $overrideid) {
    global $DB;

    if (!isset($quizinvideo->cmid)) {
        $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $quizinvideo->course);
        $quizinvideo->cmid = $cm->id;
    }

    $override = $DB->get_record('quizinvideo_overrides', array('id' => $overrideid), '*', MUST_EXIST);

    // Delete the events.
    $events = $DB->get_records('event', array('modulename' => 'quizinvideo',
            'instance' => $quizinvideo->id, 'groupid' => (int)$override->groupid,
            'userid' => (int)$override->userid));
    foreach ($events as $event) {
        $eventold = calendar_event::load($event);
        $eventold->delete();
    }

    $DB->delete_records('quizinvideo_overrides', array('id' => $overrideid));

    // Set the common parameters for one of the events we will be triggering.
    $params = array(
        'objectid' => $override->id,
        'context' => context_module::instance($quizinvideo->cmid),
        'other' => array(
            'quizinvideoid' => $override->quizinvideo
        )
    );
    // Determine which override deleted event to fire.
    if (!empty($override->userid)) {
        $params['relateduserid'] = $override->userid;
        $event = \mod_quizinvideo\event\user_override_deleted::create($params);
    } else {
        $params['other']['groupid'] = $override->groupid;
        $event = \mod_quizinvideo\event\group_override_deleted::create($params);
    }

    // Trigger the override deleted event.
    $event->add_record_snapshot('quizinvideo_overrides', $override);
    $event->trigger();

    return true;
}

/**
 * Deletes all quizinvideo overrides from the database and clears any corresponding calendar events
 *
 * @param object $quizinvideo The quizinvideo object.
 */
function quizinvideo_delete_all_overrides($quizinvideo) {
    global $DB;

    $overrides = $DB->get_records('quizinvideo_overrides', array('quizinvideo' => $quizinvideo->id), 'id');
    foreach ($overrides as $override) {
        quizinvideo_delete_override($quizinvideo, $override->id);
    }
}

/**
 * Updates a quizinvideo object with override information for a user.
 *
 * Algorithm:  For each quizinvideo setting, if there is a matching user-specific override,
 *   then use that otherwise, if there are group-specific overrides, return the most
 *   lenient combination of them.  If neither applies, leave the quizinvideo setting unchanged.
 *
 *   Special case: if there is more than one password that applies to the user, then
 *   quizinvideo->extrapasswords will contain an array of strings giving the remaining
 *   passwords.
 *
 * @param object $quizinvideo The quizinvideo object.
 * @param int $userid The userid.
 * @return object $quizinvideo The updated quizinvideo object.
 */
function quizinvideo_update_effective_access($quizinvideo, $userid) {
    global $DB;

    // Check for user override.
    $override = $DB->get_record('quizinvideo_overrides', array('quizinvideo' => $quizinvideo->id, 'userid' => $userid));

    if (!$override) {
        $override = new stdClass();
        $override->timeopen = null;
        $override->timeclose = null;
        $override->timelimit = null;
        $override->attempts = null;
        $override->password = null;
    }

    // Check for group overrides.
    $groupings = groups_get_user_groups($quizinvideo->course, $userid);

    if (!empty($groupings[0])) {
        // Select all overrides that apply to the User's groups.
        list($extra, $params) = $DB->get_in_or_equal(array_values($groupings[0]));
        $sql = "SELECT * FROM {quizinvideo_overrides}
                WHERE groupid $extra AND quizinvideo = ?";
        $params[] = $quizinvideo->id;
        $records = $DB->get_records_sql($sql, $params);

        // Combine the overrides.
        $opens = array();
        $closes = array();
        $limits = array();
        $attempts = array();
        $passwords = array();

        foreach ($records as $gpoverride) {
            if (isset($gpoverride->timeopen)) {
                $opens[] = $gpoverride->timeopen;
            }
            if (isset($gpoverride->timeclose)) {
                $closes[] = $gpoverride->timeclose;
            }
            if (isset($gpoverride->timelimit)) {
                $limits[] = $gpoverride->timelimit;
            }
            if (isset($gpoverride->attempts)) {
                $attempts[] = $gpoverride->attempts;
            }
            if (isset($gpoverride->password)) {
                $passwords[] = $gpoverride->password;
            }
        }
        // If there is a user override for a setting, ignore the group override.
        if (is_null($override->timeopen) && count($opens)) {
            $override->timeopen = min($opens);
        }
        if (is_null($override->timeclose) && count($closes)) {
            if (in_array(0, $closes)) {
                $override->timeclose = 0;
            } else {
                $override->timeclose = max($closes);
            }
        }
        if (is_null($override->timelimit) && count($limits)) {
            if (in_array(0, $limits)) {
                $override->timelimit = 0;
            } else {
                $override->timelimit = max($limits);
            }
        }
        if (is_null($override->attempts) && count($attempts)) {
            if (in_array(0, $attempts)) {
                $override->attempts = 0;
            } else {
                $override->attempts = max($attempts);
            }
        }
        if (is_null($override->password) && count($passwords)) {
            $override->password = array_shift($passwords);
            if (count($passwords)) {
                $override->extrapasswords = $passwords;
            }
        }

    }

    // Merge with quizinvideo defaults.
    $keys = array('timeopen', 'timeclose', 'timelimit', 'attempts', 'password', 'extrapasswords');
    foreach ($keys as $key) {
        if (isset($override->{$key})) {
            $quizinvideo->{$key} = $override->{$key};
        }
    }

    return $quizinvideo;
}

/**
 * Delete all the attempts belonging to a quizinvideo.
 *
 * @param object $quizinvideo The quizinvideo object.
 */
function quizinvideo_delete_all_attempts($quizinvideo) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
    question_engine::delete_questions_usage_by_activities(new qubaids_for_quizinvideo($quizinvideo->id));
    $DB->delete_records('quizinvideo_attempts', array('quizinvideo' => $quizinvideo->id));
    $DB->delete_records('quizinvideo_grades', array('quizinvideo' => $quizinvideo->id));
}

/**
 * Get the best current grade for a particular user in a quizinvideo.
 *
 * @param object $quizinvideo the quizinvideo settings.
 * @param int $userid the id of the user.
 * @return float the user's current grade for this quizinvideo, or null if this user does
 * not have a grade on this quizinvideo.
 */
function quizinvideo_get_best_grade($quizinvideo, $userid) {
    global $DB;
    $grade = $DB->get_field('quizinvideo_grades', 'grade',
            array('quizinvideo' => $quizinvideo->id, 'userid' => $userid));

    // Need to detect errors/no result, without catching 0 grades.
    if ($grade === false) {
        return null;
    }

    return $grade + 0; // Convert to number.
}

/**
 * Is this a graded quizinvideo? If this method returns true, you can assume that
 * $quizinvideo->grade and $quizinvideo->sumgrades are non-zero (for example, if you want to
 * divide by them).
 *
 * @param object $quizinvideo a row from the quizinvideo table.
 * @return bool whether this is a graded quizinvideo.
 */
function quizinvideo_has_grades($quizinvideo) {
    return $quizinvideo->grade >= 0.000005 && $quizinvideo->sumgrades >= 0.000005;
}

/**
 * Does this quizinvideo allow multiple tries?
 *
 * @return bool
 */
function quizinvideo_allows_multiple_tries($quizinvideo) {
    $bt = question_engine::get_behaviour_type($quizinvideo->preferredbehaviour);
    return $bt->allows_multiple_submitted_responses();
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $quizinvideo
 * @return object|null
 */
function quizinvideo_user_outline($course, $user, $mod, $quizinvideo) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $grades = grade_get_grades($course->id, 'mod', 'quizinvideo', $quizinvideo->id, $user->id);

    if (empty($grades->items[0]->grades)) {
        return null;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $result = new stdClass();
    $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

    // Datesubmitted == time created. dategraded == time modified or time overridden
    // if grade was last modified by the user themselves use date graded. Otherwise use
    // date submitted.
    // TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704.
    if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
        $result->time = $grade->dategraded;
    } else {
        $result->time = $grade->datesubmitted;
    }

    return $result;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $quizinvideo
 * @return bool
 */
function quizinvideo_user_complete($course, $user, $mod, $quizinvideo) {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

    $grades = grade_get_grades($course->id, 'mod', 'quizinvideo', $quizinvideo->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($attempts = $DB->get_records('quizinvideo_attempts',
            array('userid' => $user->id, 'quizinvideo' => $quizinvideo->id), 'attempt')) {
        foreach ($attempts as $attempt) {
            echo get_string('attempt', 'quizinvideo', $attempt->attempt) . ': ';
            if ($attempt->state != quizinvideo_attempt::FINISHED) {
                echo quizinvideo_attempt_state_name($attempt->state);
            } else {
                echo quizinvideo_format_grade($quizinvideo, $attempt->sumgrades) . '/' .
                        quizinvideo_format_grade($quizinvideo, $quizinvideo->sumgrades);
            }
            echo ' - '.userdate($attempt->timemodified).'<br />';
        }
    } else {
        print_string('noattempts', 'quizinvideo');
    }

    return true;
}

/**
 * quizinvideo periodic clean-up tasks.
 */
function quizinvideo_cron() {
    global $CFG;

    require_once($CFG->dirroot . '/mod/quizinvideo/cronlib.php');
    mtrace('');

    $timenow = time();
    $overduehander = new mod_quizinvideo_overdue_attempt_updater();

    $processto = $timenow - get_config('quizinvideo', 'graceperiodmin');

    mtrace('  Looking for quizinvideo overdue quizinvideo attempts...');

    list($count, $quizinvideocount) = $overduehander->update_overdue_attempts($timenow, $processto);

    mtrace('  Considered ' . $count . ' attempts in ' . $quizinvideocount . ' quizinvideos.');

    // Run cron for our sub-plugin types.
    cron_execute_plugin_type('quizinvideo', 'quizinvideo reports');
    cron_execute_plugin_type('quizinvideoaccess', 'quizinvideo access rules');

    return true;
}

/**
 * @param int $quizinvideoid the quizinvideo id.
 * @param int $userid the userid.
 * @param string $status 'all', 'finished' or 'unfinished' to control
 * @param bool $includepreviews
 * @return an array of all the user's attempts at this quizinvideo. Returns an empty
 *      array if there are none.
 */
function quizinvideo_get_user_attempts($quizinvideoid, $userid, $status = 'finished', $includepreviews = false) {
    global $DB, $CFG;
    // TODO MDL-33071 it is very annoying to have to included all of locallib.php
    // just to get the quizinvideo_attempt::FINISHED constants, but I will try to sort
    // that out properly for Moodle 2.4. For now, I will just do a quick fix for
    // MDL-33048.
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

    $params = array();
    switch ($status) {
        case 'all':
            $statuscondition = '';
            break;

        case 'finished':
            $statuscondition = ' AND state IN (:state1, :state2)';
            $params['state1'] = quizinvideo_attempt::FINISHED;
            $params['state2'] = quizinvideo_attempt::ABANDONED;
            break;

        case 'unfinished':
            $statuscondition = ' AND state IN (:state1, :state2)';
            $params['state1'] = quizinvideo_attempt::IN_PROGRESS;
            $params['state2'] = quizinvideo_attempt::OVERDUE;
            break;
    }

    $previewclause = '';
    if (!$includepreviews) {
        $previewclause = ' AND preview = 0';
    }

    $params['quizinvideoid'] = $quizinvideoid;
    $params['userid'] = $userid;
    return $DB->get_records_select('quizinvideo_attempts',
            'quizinvideo = :quizinvideoid AND userid = :userid' . $previewclause . $statuscondition,
            $params, 'attempt ASC');
}

/**
 * Return grade for given user or all users.
 *
 * @param int $quizinvideoid id of quizinvideo
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades. They should
 * be processed with quizinvideo_format_grade for display.
 */
function quizinvideo_get_user_grades($quizinvideo, $userid = 0) {
    global $CFG, $DB;

    $params = array($quizinvideo->id);
    $usertest = '';
    if ($userid) {
        $params[] = $userid;
        $usertest = 'AND u.id = ?';
    }
    return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                qg.grade AS rawgrade,
                qg.timemodified AS dategraded,
                MAX(qa.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {quizinvideo_grades} qg ON u.id = qg.userid
            JOIN {quizinvideo_attempts} qa ON qa.quizinvideo = qg.quizinvideo AND qa.userid = u.id

            WHERE qg.quizinvideo = ?
            $usertest
            GROUP BY u.id, qg.grade, qg.timemodified", $params);
}

/**
 * Round a grade to to the correct number of decimal places, and format it for display.
 *
 * @param object $quizinvideo The quizinvideo table row, only $quizinvideo->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function quizinvideo_format_grade($quizinvideo, $grade) {
    if (is_null($grade)) {
        return get_string('notyetgraded', 'quizinvideo');
    }
    return format_float($grade, $quizinvideo->decimalpoints);
}

/**
 * Determine the correct number of decimal places required to format a grade.
 *
 * @param object $quizinvideo The quizinvideo table row, only $quizinvideo->decimalpoints is used.
 * @return integer
 */
function quizinvideo_get_grade_format($quizinvideo) {
    if (empty($quizinvideo->questiondecimalpoints)) {
        $quizinvideo->questiondecimalpoints = -1;
    }

    if ($quizinvideo->questiondecimalpoints == -1) {
        return $quizinvideo->decimalpoints;
    }

    return $quizinvideo->questiondecimalpoints;
}

/**
 * Round a grade to the correct number of decimal places, and format it for display.
 *
 * @param object $quizinvideo The quizinvideo table row, only $quizinvideo->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function quizinvideo_format_question_grade($quizinvideo, $grade) {
    return format_float($grade, quizinvideo_get_grade_format($quizinvideo));
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $quizinvideo the quizinvideo settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 */
function quizinvideo_update_grades($quizinvideo, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($quizinvideo->grade == 0) {
        quizinvideo_grade_item_update($quizinvideo);

    } else if ($grades = quizinvideo_get_user_grades($quizinvideo, $userid)) {
        quizinvideo_grade_item_update($quizinvideo, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        quizinvideo_grade_item_update($quizinvideo, $grade);

    } else {
        quizinvideo_grade_item_update($quizinvideo);
    }
}

/**
 * Create or update the grade item for given quizinvideo
 *
 * @category grade
 * @param object $quizinvideo object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function quizinvideo_grade_item_update($quizinvideo, $grades = null) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');

    if (array_key_exists('cmidnumber', $quizinvideo)) { // May not be always present.
        $params = array('itemname' => $quizinvideo->name, 'idnumber' => $quizinvideo->cmidnumber);
    } else {
        $params = array('itemname' => $quizinvideo->name);
    }

    if ($quizinvideo->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $quizinvideo->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    // What this is trying to do:
    // 1. If the quizinvideo is set to not show grades while the quizinvideo is still open,
    //    and is set to show grades after the quizinvideo is closed, then create the
    //    grade_item with a show-after date that is the quizinvideo close date.
    // 2. If the quizinvideo is set to not show grades at either of those times,
    //    create the grade_item as hidden.
    // 3. If the quizinvideo is set to show grades, create the grade_item visible.
    $openreviewoptions = mod_quizinvideo_display_options::make_from_quizinvideo($quizinvideo,
            mod_quizinvideo_display_options::LATER_WHILE_OPEN);
    $closedreviewoptions = mod_quizinvideo_display_options::make_from_quizinvideo($quizinvideo,
            mod_quizinvideo_display_options::AFTER_CLOSE);
    if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks < question_display_options::MARK_AND_MAX) {
        $params['hidden'] = 1;

    } else if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks >= question_display_options::MARK_AND_MAX) {
        if ($quizinvideo->timeclose) {
            $params['hidden'] = $quizinvideo->timeclose;
        } else {
            $params['hidden'] = 1;
        }

    } else {
        // Either
        // a) both open and closed enabled
        // b) open enabled, closed disabled - we can not "hide after",
        //    grades are kept visible even after closing.
        $params['hidden'] = 0;
    }

    if (!$params['hidden']) {
        // If the grade item is not hidden by the quizinvideo logic, then we need to
        // hide it if the quizinvideo is hidden from students.
        if (property_exists($quizinvideo, 'visible')) {
            // Saving the quizinvideo form, and cm not yet updated in the database.
            $params['hidden'] = !$quizinvideo->visible;
        } else {
            $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id);
            $params['hidden'] = !$cm->visible;
        }
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $gradebook_grades = grade_get_grades($quizinvideo->course, 'mod', 'quizinvideo', $quizinvideo->id);
    if (!empty($gradebook_grades->items)) {
        $grade_item = $gradebook_grades->items[0];
        if ($grade_item->locked) {
            // NOTE: this is an extremely nasty hack! It is not a bug if this confirmation fails badly. --skodak.
            $confirm_regrade = optional_param('confirm_regrade', 0, PARAM_INT);
            if (!$confirm_regrade) {
                if (!AJAX_SCRIPT) {
                    $message = get_string('gradeitemislocked', 'grades');
                    $back_link = $CFG->wwwroot . '/mod/quizinvideo/report.php?q=' . $quizinvideo->id .
                            '&amp;mode=overview';
                    $regrade_link = qualified_me() . '&amp;confirm_regrade=1';
                    echo $OUTPUT->box_start('generalbox', 'notice');
                    echo '<p>'. $message .'</p>';
                    echo $OUTPUT->container_start('buttons');
                    echo $OUTPUT->single_button($regrade_link, get_string('regradeanyway', 'grades'));
                    echo $OUTPUT->single_button($back_link,  get_string('cancel'));
                    echo $OUTPUT->container_end();
                    echo $OUTPUT->box_end();
                }
                return GRADE_UPDATE_ITEM_LOCKED;
            }
        }
    }

    return grade_update('mod/quizinvideo', $quizinvideo->course, 'mod', 'quizinvideo', $quizinvideo->id, 0, $grades, $params);
}

/**
 * Delete grade item for given quizinvideo
 *
 * @category grade
 * @param object $quizinvideo object
 * @return object quizinvideo
 */
function quizinvideo_grade_item_delete($quizinvideo) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/quizinvideo', $quizinvideo->course, 'mod', 'quizinvideo', $quizinvideo->id, 0,
            null, array('deleted' => 1));
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every quizinvideo event in the site is checked, else
 * only quizinvideo events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @return bool
 */
function quizinvideo_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$quizinvideos = $DB->get_records('quizinvideo')) {
            return true;
        }
    } else {
        if (!$quizinvideos = $DB->get_records('quizinvideo', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($quizinvideos as $quizinvideo) {
        quizinvideo_update_events($quizinvideo);
    }

    return true;
}

/**
 * Returns all quizinvideo graded users since a given time for specified quizinvideo
 */
function quizinvideo_get_recent_mod_activity(&$activities, &$index, $timestart,
        $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

    $course = get_course($courseid);
    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $quizinvideo = $DB->get_record('quizinvideo', array('id' => $cm->instance));

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['timestart'] = $timestart;
    $params['quizinvideoid'] = $quizinvideo->id;

    $ufields = user_picture::fields('u', null, 'useridagain');
    if (!$attempts = $DB->get_records_sql("
              SELECT qa.*,
                     {$ufields}
                FROM {quizinvideo_attempts} qa
                     JOIN {user} u ON u.id = qa.userid
                     $groupjoin
               WHERE qa.timefinish > :timestart
                 AND qa.quizinvideo = :quizinvideoid
                 AND qa.preview = 0
                     $userselect
                     $groupselect
            ORDER BY qa.timefinish ASC", $params)) {
        return;
    }

    $context         = context_module::instance($cm->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $context);
    $grader          = has_capability('mod/quizinvideo:viewreports', $context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    $usersgroups = null;
    $aname = format_string($cm->name, true);
    foreach ($attempts as $attempt) {
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // Grade permission required.
                continue;
            }

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                $usersgroups = groups_get_all_groups($course->id,
                        $attempt->userid, $cm->groupingid);
                $usersgroups = array_keys($usersgroups);
                if (!array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $options = quizinvideo_get_review_options($quizinvideo, $attempt, $context);

        $tmpactivity = new stdClass();

        $tmpactivity->type       = 'quizinvideo';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->name       = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = $attempt->timefinish;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->attemptid = $attempt->id;
        $tmpactivity->content->attempt   = $attempt->attempt;
        if (quizinvideo_has_grades($quizinvideo) && $options->marks >= question_display_options::MARK_AND_MAX) {
            $tmpactivity->content->sumgrades = quizinvideo_format_grade($quizinvideo, $attempt->sumgrades);
            $tmpactivity->content->maxgrade  = quizinvideo_format_grade($quizinvideo, $quizinvideo->sumgrades);
        } else {
            $tmpactivity->content->sumgrades = null;
            $tmpactivity->content->maxgrade  = null;
        }

        $tmpactivity->user = user_picture::unalias($attempt, null, 'useridagain');
        $tmpactivity->user->fullname  = fullname($tmpactivity->user, $viewfullnames);

        $activities[$index++] = $tmpactivity;
    }
}

function quizinvideo_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user, array('courseid' => $courseid));
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo '<img src="' . $OUTPUT->pix_url('icon', $activity->type) . '" ' .
                'class="icon" alt="' . $modname . '" />';
        echo '<a href="' . $CFG->wwwroot . '/mod/quizinvideo/view.php?id=' .
                $activity->cmid . '">' . $activity->name . '</a>';
        echo '</div>';
    }

    echo '<div class="grade">';
    echo  get_string('attempt', 'quizinvideo', $activity->content->attempt);
    if (isset($activity->content->maxgrade)) {
        $grades = $activity->content->sumgrades . ' / ' . $activity->content->maxgrade;
        echo ': (<a href="' . $CFG->wwwroot . '/mod/quizinvideo/review.php?attempt=' .
                $activity->content->attemptid . '">' . $grades . '</a>)';
    }
    echo '</div>';

    echo '<div class="user">';
    echo '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $activity->user->id .
            '&amp;course=' . $courseid . '">' . $activity->user->fullname .
            '</a> - ' . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';

    return;
}

/**
 * Pre-process the quizinvideo options form data, making any necessary adjustments.
 * Called by add/update instance in this file.
 *
 * @param object $quizinvideo The variables set on the form.
 */
function quizinvideo_process_options($quizinvideo) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');

    $quizinvideo->timemodified = time();

    // quizinvideo name.
    if (!empty($quizinvideo->name)) {
        $quizinvideo->name = trim($quizinvideo->name);
    }

    // quizinvideo url
    if(!empty($quizinvideo->video)){
        $quizinvideo->video = trim($quizinvideo->video);
        $quizinvideo->video = process_rtmp_urls($quizinvideo);
    }

    // Password field - different in form to stop browsers that remember passwords
    // getting confused.
    $quizinvideo->password = $quizinvideo->quizinvideopassword;
    unset($quizinvideo->quizinvideopassword);

    // quizinvideo feedback.
    if (isset($quizinvideo->feedbacktext)) {
        // Clean up the boundary text.
        for ($i = 0; $i < count($quizinvideo->feedbacktext); $i += 1) {
            if (empty($quizinvideo->feedbacktext[$i]['text'])) {
                $quizinvideo->feedbacktext[$i]['text'] = '';
            } else {
                $quizinvideo->feedbacktext[$i]['text'] = trim($quizinvideo->feedbacktext[$i]['text']);
            }
        }

        // Check the boundary value is a number or a percentage, and in range.
        $i = 0;
        while (!empty($quizinvideo->feedbackboundaries[$i])) {
            $boundary = trim($quizinvideo->feedbackboundaries[$i]);
            if (!is_numeric($boundary)) {
                if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                    $boundary = trim(substr($boundary, 0, -1));
                    if (is_numeric($boundary)) {
                        $boundary = $boundary * $quizinvideo->grade / 100.0;
                    } else {
                        return get_string('feedbackerrorboundaryformat', 'quizinvideo', $i + 1);
                    }
                }
            }
            if ($boundary <= 0 || $boundary >= $quizinvideo->grade) {
                return get_string('feedbackerrorboundaryoutofrange', 'quizinvideo', $i + 1);
            }
            if ($i > 0 && $boundary >= $quizinvideo->feedbackboundaries[$i - 1]) {
                return get_string('feedbackerrororder', 'quizinvideo', $i + 1);
            }
            $quizinvideo->feedbackboundaries[$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;

        // Check there is nothing in the remaining unused fields.
        if (!empty($quizinvideo->feedbackboundaries)) {
            for ($i = $numboundaries; $i < count($quizinvideo->feedbackboundaries); $i += 1) {
                if (!empty($quizinvideo->feedbackboundaries[$i]) &&
                        trim($quizinvideo->feedbackboundaries[$i]) != '') {
                    return get_string('feedbackerrorjunkinboundary', 'quizinvideo', $i + 1);
                }
            }
        }
        for ($i = $numboundaries + 1; $i < count($quizinvideo->feedbacktext); $i += 1) {
            if (!empty($quizinvideo->feedbacktext[$i]['text']) &&
                    trim($quizinvideo->feedbacktext[$i]['text']) != '') {
                return get_string('feedbackerrorjunkinfeedback', 'quizinvideo', $i + 1);
            }
        }
        // Needs to be bigger than $quizinvideo->grade because of '<' test in quizinvideo_feedback_for_grade().
        $quizinvideo->feedbackboundaries[-1] = $quizinvideo->grade + 1;
        $quizinvideo->feedbackboundaries[$numboundaries] = 0;
        $quizinvideo->feedbackboundarycount = $numboundaries;
    } else {
        $quizinvideo->feedbackboundarycount = -1;
    }

    // Combing the individual settings into the review columns.
    $quizinvideo->reviewattempt = quizinvideo_review_option_form_to_db($quizinvideo, 'attempt');
    $quizinvideo->reviewcorrectness = quizinvideo_review_option_form_to_db($quizinvideo, 'correctness');
    $quizinvideo->reviewmarks = quizinvideo_review_option_form_to_db($quizinvideo, 'marks');
    $quizinvideo->reviewspecificfeedback = quizinvideo_review_option_form_to_db($quizinvideo, 'specificfeedback');
    $quizinvideo->reviewgeneralfeedback = quizinvideo_review_option_form_to_db($quizinvideo, 'generalfeedback');
    $quizinvideo->reviewrightanswer = quizinvideo_review_option_form_to_db($quizinvideo, 'rightanswer');
    $quizinvideo->reviewoverallfeedback = quizinvideo_review_option_form_to_db($quizinvideo, 'overallfeedback');
    $quizinvideo->reviewattempt |= mod_quizinvideo_display_options::DURING;
    $quizinvideo->reviewoverallfeedback &= ~mod_quizinvideo_display_options::DURING;
}

/**
 * Helper function for {@link quizinvideo_process_options()}.
 * @param object $fromform the sumbitted form date.
 * @param string $field one of the review option field names.
 */
function quizinvideo_review_option_form_to_db($fromform, $field) {
    static $times = array(
        'during' => mod_quizinvideo_display_options::DURING,
        'immediately' => mod_quizinvideo_display_options::IMMEDIATELY_AFTER,
        'open' => mod_quizinvideo_display_options::LATER_WHILE_OPEN,
        'closed' => mod_quizinvideo_display_options::AFTER_CLOSE,
    );

    $review = 0;
    foreach ($times as $whenname => $when) {
        $fieldname = $field . $whenname;
        if (isset($fromform->$fieldname)) {
            $review |= $when;
            unset($fromform->$fieldname);
        }
    }

    return $review;
}

/**
 * This function is called at the end of quizinvideo_add_instance
 * and quizinvideo_update_instance, to do the common processing.
 *
 * @param object $quizinvideo the quizinvideo object.
 */
function quizinvideo_after_add_or_update($quizinvideo) {
    global $DB;
    $cmid = $quizinvideo->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $quizinvideo->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Save the feedback.
    $DB->delete_records('quizinvideo_feedback', array('quizinvideoid' => $quizinvideo->id));

    for ($i = 0; $i <= $quizinvideo->feedbackboundarycount; $i++) {
        $feedback = new stdClass();
        $feedback->quizinvideoid = $quizinvideo->id;
        $feedback->feedbacktext = $quizinvideo->feedbacktext[$i]['text'];
        $feedback->feedbacktextformat = $quizinvideo->feedbacktext[$i]['format'];
        $feedback->mingrade = $quizinvideo->feedbackboundaries[$i];
        $feedback->maxgrade = $quizinvideo->feedbackboundaries[$i - 1];
        $feedback->id = $DB->insert_record('quizinvideo_feedback', $feedback);
        $feedbacktext = file_save_draft_area_files((int)$quizinvideo->feedbacktext[$i]['itemid'],
                $context->id, 'mod_quizinvideo', 'feedback', $feedback->id,
                array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
                $quizinvideo->feedbacktext[$i]['text']);
        $DB->set_field('quizinvideo_feedback', 'feedbacktext', $feedbacktext,
                array('id' => $feedback->id));
    }

    // Store any settings belonging to the access rules.
    quizinvideo_access_manager::save_settings($quizinvideo);

    // Update the events relating to this quizinvideo.
    quizinvideo_update_events($quizinvideo);

    // Update related grade item.
    quizinvideo_grade_item_update($quizinvideo);
}

/**
 * This function updates the events associated to the quizinvideo.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @uses quizinvideo_MAX_EVENT_LENGTH
 * @param object $quizinvideo the quizinvideo object.
 * @param object optional $override limit to a specific override
 */
function quizinvideo_update_events($quizinvideo, $override = null) {
    global $DB;

    // Load the old events relating to this quizinvideo.
    $conds = array('modulename'=>'quizinvideo',
                   'instance'=>$quizinvideo->id);
    if (!empty($override)) {
        // Only load events for this override.
        $conds['groupid'] = isset($override->groupid)?  $override->groupid : 0;
        $conds['userid'] = isset($override->userid)?  $override->userid : 0;
    }
    $oldevents = $DB->get_records('event', $conds);

    // Now make a todo list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the quizinvideo, so we
        // need to add all the overrides.
        $overrides = $DB->get_records('quizinvideo_overrides', array('quizinvideo' => $quizinvideo->id));
        // As well as the original quizinvideo (empty override).
        $overrides[] = new stdClass();
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid)?  $current->groupid : 0;
        $userid    = isset($current->userid)? $current->userid : 0;
        $timeopen  = isset($current->timeopen)?  $current->timeopen : $quizinvideo->timeopen;
        $timeclose = isset($current->timeclose)? $current->timeclose : $quizinvideo->timeclose;

        // Only add open/close events for an override if they differ from the quizinvideo default.
        $addopen  = empty($current->id) || !empty($current->timeopen);
        $addclose = empty($current->id) || !empty($current->timeclose);

        if (!empty($quizinvideo->coursemodule)) {
            $cmid = $quizinvideo->coursemodule;
        } else {
            $cmid = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $quizinvideo->course)->id;
        }

        $event = new stdClass();
        $event->description = format_module_intro('quizinvideo', $quizinvideo, $cmid);
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $quizinvideo->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'quizinvideo';
        $event->instance    = $quizinvideo->id;
        $event->timestart   = $timeopen;
        $event->timeduration = max($timeclose - $timeopen, 0);
        $event->visible     = instance_is_visible('quizinvideo', $quizinvideo);
        $event->eventtype   = 'open';

        // Determine the event name.
        if ($groupid) {
            $params = new stdClass();
            $params->quizinvideo = $quizinvideo->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'quizinvideo', $params);
        } else if ($userid) {
            $params = new stdClass();
            $params->quizinvideo = $quizinvideo->name;
            $eventname = get_string('overrideusereventname', 'quizinvideo', $params);
        } else {
            $eventname = $quizinvideo->name;
        }
        if ($addopen or $addclose) {
            if ($timeclose and $timeopen and $event->timeduration <= quizinvideo_MAX_EVENT_LENGTH) {
                // Single event for the whole quizinvideo.
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->name = $eventname;
                // The method calendar_event::create will reuse a db record if the id field is set.
                calendar_event::create($event);
            } else {
                // Separate start and end events.
                $event->timeduration  = 0;
                if ($timeopen && $addopen) {
                    if ($oldevent = array_shift($oldevents)) {
                        $event->id = $oldevent->id;
                    } else {
                        unset($event->id);
                    }
                    $event->name = $eventname.' ('.get_string('quizinvideoopens', 'quizinvideo').')';
                    // The method calendar_event::create will reuse a db record if the id field is set.
                    calendar_event::create($event);
                }
                if ($timeclose && $addclose) {
                    if ($oldevent = array_shift($oldevents)) {
                        $event->id = $oldevent->id;
                    } else {
                        unset($event->id);
                    }
                    $event->name      = $eventname.' ('.get_string('quizinvideocloses', 'quizinvideo').')';
                    $event->timestart = $timeclose;
                    $event->eventtype = 'close';
                    calendar_event::create($event);
                }
            }
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function quizinvideo_get_view_actions() {
    return array('view', 'view all', 'report', 'review');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function quizinvideo_get_post_actions() {
    return array('attempt', 'close attempt', 'preview', 'editquestions',
            'delete attempt', 'manualgrade');
}

/**
 * @param array $questionids of question ids.
 * @return bool whether any of these questions are used by any instance of this module.
 */
function quizinvideo_questions_in_use($questionids) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    list($test, $params) = $DB->get_in_or_equal($questionids);
    return $DB->record_exists_select('quizinvideo_slots',
            'questionid ' . $test, $params) || question_engine::questions_in_use(
            $questionids, new qubaid_join('{quizinvideo_attempts} quizinvideoa',
            'quizinvideoa.uniqueid', 'quizinvideoa.preview = 0'));
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the quizinvideo.
 *
 * @param $mform the course reset form that is being built.
 */
function quizinvideo_reset_course_form_definition($mform) {
    $mform->addElement('header', 'quizinvideoheader', get_string('modulenameplural', 'quizinvideo'));
    $mform->addElement('advcheckbox', 'reset_quizinvideo_attempts',
            get_string('removeallquizinvideoattempts', 'quizinvideo'));
    $mform->addElement('advcheckbox', 'reset_quizinvideo_user_overrides',
            get_string('removealluseroverrides', 'quizinvideo'));
    $mform->addElement('advcheckbox', 'reset_quizinvideo_group_overrides',
            get_string('removeallgroupoverrides', 'quizinvideo'));
}

/**
 * Course reset form defaults.
 * @return array the defaults.
 */
function quizinvideo_reset_course_form_defaults($course) {
    return array('reset_quizinvideo_attempts' => 1,
                 'reset_quizinvideo_group_overrides' => 1,
                 'reset_quizinvideo_user_overrides' => 1);
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid
 * @param string optional type
 */
function quizinvideo_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $quizinvideos = $DB->get_records_sql("
            SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
            FROM {modules} m
            JOIN {course_modules} cm ON m.id = cm.module
            JOIN {quizinvideo} q ON cm.instance = q.id
            WHERE m.name = 'quizinvideo' AND cm.course = ?", array($courseid));

    foreach ($quizinvideos as $quizinvideo) {
        quizinvideo_grade_item_update($quizinvideo, 'reset');
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * quizinvideo attempts for course $data->courseid, if $data->reset_quizinvideo_attempts is
 * set and true.
 *
 * Also, move the quizinvideo open and close dates, if the course start date is changing.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function quizinvideo_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');

    $componentstr = get_string('modulenameplural', 'quizinvideo');
    $status = array();

    // Delete attempts.
    if (!empty($data->reset_quizinvideo_attempts)) {
        question_engine::delete_questions_usage_by_activities(new qubaid_join(
                '{quizinvideo_attempts} quizinvideoa JOIN {quizinvideo} quizinvideo ON quizinvideoa.quizinvideo = quizinvideo.id',
                'quizinvideoa.uniqueid', 'quizinvideo.course = :quizinvideocourseid',
                array('quizinvideocourseid' => $data->courseid)));

        $DB->delete_records_select('quizinvideo_attempts',
                'quizinvideo IN (SELECT id FROM {quizinvideo} WHERE course = ?)', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('attemptsdeleted', 'quizinvideo'),
            'error' => false);

        // Remove all grades from gradebook.
        $DB->delete_records_select('quizinvideo_grades',
                'quizinvideo IN (SELECT id FROM {quizinvideo} WHERE course = ?)', array($data->courseid));
        if (empty($data->reset_gradebook_grades)) {
            quizinvideo_reset_gradebook($data->courseid);
        }
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('gradesdeleted', 'quizinvideo'),
            'error' => false);
    }

    // Remove user overrides.
    if (!empty($data->reset_quizinvideo_user_overrides)) {
        $DB->delete_records_select('quizinvideo_overrides',
                'quizinvideo IN (SELECT id FROM {quizinvideo} WHERE course = ?) AND userid IS NOT NULL', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('useroverridesdeleted', 'quizinvideo'),
            'error' => false);
    }
    // Remove group overrides.
    if (!empty($data->reset_quizinvideo_group_overrides)) {
        $DB->delete_records_select('quizinvideo_overrides',
                'quizinvideo IN (SELECT id FROM {quizinvideo} WHERE course = ?) AND groupid IS NOT NULL', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('groupoverridesdeleted', 'quizinvideo'),
            'error' => false);
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        $DB->execute("UPDATE {quizinvideo_overrides}
                         SET timeopen = timeopen + ?
                       WHERE quizinvideo IN (SELECT id FROM {quizinvideo} WHERE course = ?)
                         AND timeopen <> 0", array($data->timeshift, $data->courseid));
        $DB->execute("UPDATE {quizinvideo_overrides}
                         SET timeclose = timeclose + ?
                       WHERE quizinvideo IN (SELECT id FROM {quizinvideo} WHERE course = ?)
                         AND timeclose <> 0", array($data->timeshift, $data->courseid));

        shift_course_mod_dates('quizinvideo', array('timeopen', 'timeclose'),
                $data->timeshift, $data->courseid);

        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('openclosedatesupdated', 'quizinvideo'),
            'error' => false);
    }

    return $status;
}

/**
 * Prints quizinvideo summaries on MyMoodle Page
 * @param arry $courses
 * @param array $htmlarray
 */
function quizinvideo_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;
    // These next 6 Lines are constant in all modules (just change module name).
    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$quizinvideos = get_all_instances_in_courses('quizinvideo', $courses)) {
        return;
    }

    // Fetch some language strings outside the main loop.
    $strquizinvideo = get_string('modulename', 'quizinvideo');
    $strnoattempts = get_string('noattempts', 'quizinvideo');

    // We want to list quizinvideos that are currently available, and which have a close date.
    // This is the same as what the lesson does, and the dabate is in MDL-10568.
    $now = time();
    foreach ($quizinvideos as $quizinvideo) {
        if ($quizinvideo->timeclose >= $now && $quizinvideo->timeopen < $now) {
            // Give a link to the quizinvideo, and the deadline.
            $str = '<div class="quizinvideo overview">' .
                    '<div class="name">' . $strquizinvideo . ': <a ' .
                    ($quizinvideo->visible ? '' : ' class="dimmed"') .
                    ' href="' . $CFG->wwwroot . '/mod/quizinvideo/view.php?id=' .
                    $quizinvideo->coursemodule . '">' .
                    $quizinvideo->name . '</a></div>';
            $str .= '<div class="info">' . get_string('quizinvideocloseson', 'quizinvideo',
                    userdate($quizinvideo->timeclose)) . '</div>';

            // Now provide more information depending on the uers's role.
            $context = context_module::instance($quizinvideo->coursemodule);
            if (has_capability('mod/quizinvideo:viewreports', $context)) {
                // For teacher-like people, show a summary of the number of student attempts.
                // The $quizinvideo objects returned by get_all_instances_in_course have the necessary $cm
                // fields set to make the following call work.
                $str .= '<div class="info">' .
                        quizinvideo_num_attempt_summary($quizinvideo, $quizinvideo, true) . '</div>';
            } else if (has_any_capability(array('mod/quizinvideo:reviewmyattempts', 'mod/quizinvideo:attempt'),
                    $context)) { // Student
                // For student-like people, tell them how many attempts they have made.
                if (isset($USER->id) &&
                        ($attempts = quizinvideo_get_user_attempts($quizinvideo->id, $USER->id))) {
                    $numattempts = count($attempts);
                    $str .= '<div class="info">' .
                            get_string('numattemptsmade', 'quizinvideo', $numattempts) . '</div>';
                } else {
                    $str .= '<div class="info">' . $strnoattempts . '</div>';
                }
            } else {
                // For ayone else, there is no point listing this quizinvideo, so stop processing.
                continue;
            }

            // Add the output for this quizinvideo to the rest.
            $str .= '</div>';
            if (empty($htmlarray[$quizinvideo->course]['quizinvideo'])) {
                $htmlarray[$quizinvideo->course]['quizinvideo'] = $str;
            } else {
                $htmlarray[$quizinvideo->course]['quizinvideo'] .= $str;
            }
        }
    }
}

/**
 * Return a textual summary of the number of attempts that have been made at a particular quizinvideo,
 * returns '' if no attempts have been made yet, unless $returnzero is passed as true.
 *
 * @param object $quizinvideo the quizinvideo object. Only $quizinvideo->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param bool $returnzero if false (default), when no attempts have been
 *      made '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string a string like "Attempts: 123", "Attemtps 123 (45 from your groups)" or
 *          "Attemtps 123 (45 from this group)".
 */
function quizinvideo_num_attempt_summary($quizinvideo, $cm, $returnzero = false, $currentgroup = 0) {
    global $DB, $USER;
    $numattempts = $DB->count_records('quizinvideo_attempts', array('quizinvideo'=> $quizinvideo->id, 'preview'=>0));
    if ($numattempts || $returnzero) {
        if (groups_get_activity_groupmode($cm)) {
            $a = new stdClass();
            $a->total = $numattempts;
            if ($currentgroup) {
                $a->group = $DB->count_records_sql('SELECT COUNT(DISTINCT qa.id) FROM ' .
                        '{quizinvideo_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE quizinvideo = ? AND preview = 0 AND groupid = ?',
                        array($quizinvideo->id, $currentgroup));
                return get_string('attemptsnumthisgroup', 'quizinvideo', $a);
            } else if ($groups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid)) {
                list($usql, $params) = $DB->get_in_or_equal(array_keys($groups));
                $a->group = $DB->count_records_sql('SELECT COUNT(DISTINCT qa.id) FROM ' .
                        '{quizinvideo_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE quizinvideo = ? AND preview = 0 AND ' .
                        "groupid $usql", array_merge(array($quizinvideo->id), $params));
                return get_string('attemptsnumyourgroups', 'quizinvideo', $a);
            }
        }
        return get_string('attemptsnum', 'quizinvideo', $numattempts);
    }
    return '';
}

/**
 * Returns the same as {@link quizinvideo_num_attempt_summary()} but wrapped in a link
 * to the quizinvideo reports.
 *
 * @param object $quizinvideo the quizinvideo object. Only $quizinvideo->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param object $context the quizinvideo context.
 * @param bool $returnzero if false (default), when no attempts have been made
 *      '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string HTML fragment for the link.
 */
function quizinvideo_attempt_summary_link_to_reports($quizinvideo, $cm, $context, $returnzero = false,
        $currentgroup = 0) {
    global $CFG;
    $summary = quizinvideo_num_attempt_summary($quizinvideo, $cm, $returnzero, $currentgroup);
    if (!$summary) {
        return '';
    }

    require_once($CFG->dirroot . '/mod/quizinvideo/report/reportlib.php');
    $url = new moodle_url('/mod/quizinvideo/report.php', array(
            'id' => $cm->id, 'mode' => quizinvideo_report_default_report($context)));
    return html_writer::link($url, $summary);
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool True if quizinvideo supports feature
 */
function quizinvideo_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                    return true;
        case FEATURE_GROUPINGS:                 return true;
        case FEATURE_MOD_INTRO:                 return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:   return true;
        case FEATURE_COMPLETION_HAS_RULES:      return true;
        case FEATURE_GRADE_HAS_GRADE:           return true;
        case FEATURE_GRADE_OUTCOMES:            return true;
        case FEATURE_BACKUP_MOODLE2:            return true;
        case FEATURE_SHOW_DESCRIPTION:          return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY: return true;
        case FEATURE_USES_QUESTIONS:            return true;

        default: return null;
    }
}

/**
 * @return array all other caps used in module
 */
function quizinvideo_get_extra_capabilities() {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    $caps = question_get_all_capabilities();
    $caps[] = 'moodle/site:accessallgroups';
    return $caps;
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $quizinvideonode
 * @return void
 */
function quizinvideo_extend_settings_navigation($settings, $quizinvideonode) {
    global $PAGE, $CFG;

    // Require {@link questionlib.php}
    // Included here as we only ever want to include this file if we really need to.
    require_once($CFG->libdir . '/questionlib.php');

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $quizinvideonode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/quizinvideo:manageoverrides', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/quizinvideo/overrides.php', array('cmid'=>$PAGE->cm->id));
        $node = navigation_node::create(get_string('groupoverrides', 'quizinvideo'),
                new moodle_url($url, array('mode'=>'group')),
                navigation_node::TYPE_SETTING, null, 'mod_quizinvideo_groupoverrides');
        $quizinvideonode->add_node($node, $beforekey);

        $node = navigation_node::create(get_string('useroverrides', 'quizinvideo'),
                new moodle_url($url, array('mode'=>'user')),
                navigation_node::TYPE_SETTING, null, 'mod_quizinvideo_useroverrides');
        $quizinvideonode->add_node($node, $beforekey);
    }

    if (has_capability('mod/quizinvideo:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('editquizinvideo', 'quizinvideo'),
                new moodle_url('/mod/quizinvideo/edit.php', array('cmid'=>$PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_quizinvideo_edit',
                new pix_icon('t/edit', ''));
        $quizinvideonode->add_node($node, $beforekey);
    }

    if (has_capability('mod/quizinvideo:preview', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/quizinvideo/startattempt.php',
                array('cmid'=>$PAGE->cm->id, 'sesskey'=>sesskey()));
        $node = navigation_node::create(get_string('preview', 'quizinvideo'), $url,
                navigation_node::TYPE_SETTING, null, 'mod_quizinvideo_preview',
                new pix_icon('i/preview', ''));
        $quizinvideonode->add_node($node, $beforekey);
    }

    if (has_any_capability(array('mod/quizinvideo:viewreports', 'mod/quizinvideo:grade'), $PAGE->cm->context)) {
        require_once($CFG->dirroot . '/mod/quizinvideo/report/reportlib.php');
        $reportlist = quizinvideo_report_list($PAGE->cm->context);

        $url = new moodle_url('/mod/quizinvideo/report.php',
                array('id' => $PAGE->cm->id, 'mode' => reset($reportlist)));
        $reportnode = $quizinvideonode->add_node(navigation_node::create(get_string('results', 'quizinvideo'), $url,
                navigation_node::TYPE_SETTING,
                null, null, new pix_icon('i/report', '')), $beforekey);

        foreach ($reportlist as $report) {
            $url = new moodle_url('/mod/quizinvideo/report.php',
                    array('id' => $PAGE->cm->id, 'mode' => $report));
            $reportnode->add_node(navigation_node::create(get_string($report, 'quizinvideo_'.$report), $url,
                    navigation_node::TYPE_SETTING,
                    null, 'quizinvideo_report_' . $report, new pix_icon('i/item', '')));
        }
    }

    question_extend_settings_navigation($quizinvideonode, $PAGE->cm->context)->trim_if_empty();
}

/**
 * Serves the quizinvideo files.
 *
 * @package  mod_quizinvideo
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function quizinvideo_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$quizinvideo = $DB->get_record('quizinvideo', array('id'=>$cm->instance))) {
        return false;
    }

    // The 'intro' area is served by pluginfile.php.
    $fileareas = array('feedback');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $feedbackid = (int)array_shift($args);
    if (!$feedback = $DB->get_record('quizinvideo_feedback', array('id'=>$feedbackid))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_quizinvideo/$filearea/$feedbackid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Called via pluginfile.php -> question_pluginfile to serve files belonging to
 * a question in a question_attempt when that attempt is a quizinvideo attempt.
 *
 * @package  mod_quizinvideo
 * @category files
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this quizinvideo attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function quizinvideo_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

    $attemptobj = quizinvideo_attempt::create_from_usage_id($qubaid);
    require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

    if ($attemptobj->is_own_attempt() && !$attemptobj->is_finished()) {
        // In the middle of an attempt.
        if (!$attemptobj->is_preview_user()) {
            $attemptobj->require_capability('mod/quizinvideo:attempt');
        }
        $isreviewing = false;

    } else {
        // Reviewing an attempt.
        $attemptobj->check_review_capability();
        $isreviewing = true;
    }

    if (!$attemptobj->check_file_access($slot, $isreviewing, $context->id,
            $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function quizinvideo_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array(
        'mod-quizinvideo-*'       => get_string('page-mod-quizinvideo-x', 'quizinvideo'),
        'mod-quizinvideo-view'    => get_string('page-mod-quizinvideo-view', 'quizinvideo'),
        'mod-quizinvideo-attempt' => get_string('page-mod-quizinvideo-attempt', 'quizinvideo'),
        'mod-quizinvideo-summary' => get_string('page-mod-quizinvideo-summary', 'quizinvideo'),
        'mod-quizinvideo-review'  => get_string('page-mod-quizinvideo-review', 'quizinvideo'),
        'mod-quizinvideo-edit'    => get_string('page-mod-quizinvideo-edit', 'quizinvideo'),
        'mod-quizinvideo-report'  => get_string('page-mod-quizinvideo-report', 'quizinvideo'),
    );
    return $module_pagetype;
}

/**
 * @return the options for quizinvideo navigation.
 */
function quizinvideo_get_navigation_options() {
    return array(
        quizinvideo_NAVMETHOD_FREE => get_string('navmethod_free', 'quizinvideo'),
        quizinvideo_NAVMETHOD_SEQ  => get_string('navmethod_seq', 'quizinvideo')
    );
}

/**
 * Obtains the automatic completion state for this quizinvideo on any conditions
 * in quizinvideo settings, such as if all attempts are used or a certain grade is achieved.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function quizinvideo_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    global $CFG;

    $quizinvideo = $DB->get_record('quizinvideo', array('id' => $cm->instance), '*', MUST_EXIST);
    if (!$quizinvideo->completionattemptsexhausted && !$quizinvideo->completionpass) {
        return $type;
    }

    // Check if the user has used up all attempts.
    if ($quizinvideo->completionattemptsexhausted) {
        $attempts = quizinvideo_get_user_attempts($quizinvideo->id, $userid, 'finished', true);
        if ($attempts) {
            $lastfinishedattempt = end($attempts);
            $context = context_module::instance($cm->id);
            $quizinvideoobj = quizinvideo::create($quizinvideo->id, $userid);
            $accessmanager = new quizinvideo_access_manager($quizinvideoobj, time(),
                    has_capability('mod/quizinvideo:ignoretimelimits', $context, $userid, false));
            if ($accessmanager->is_finished(count($attempts), $lastfinishedattempt)) {
                return true;
            }
        }
    }

    // Check for passing grade.
    if ($quizinvideo->completionpass) {
        require_once($CFG->libdir . '/gradelib.php');
        $item = grade_item::fetch(array('courseid' => $course->id, 'itemtype' => 'mod',
                'itemmodule' => 'quizinvideo', 'iteminstance' => $cm->instance, 'outcomeid' => null));
        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, array($userid), false);
            if (!empty($grades[$userid])) {
                return $grades[$userid]->is_passed($item);
            }
        }
    }
    return false;
}


/**
 * Changes URL for rtmp domains
 *
 * @param Object quizinvideo object
 * @return string Updated URL
 */
function process_rtmp_urls($quizinvideoobj){
    $url = $quizinvideoobj->video;
    if (strpos($url, '&') != FALSE) return $url;
    $lrzStrings = explode( ',', $quizinvideoobj->rtmpurls);

    foreach ($lrzStrings as $lrzString) {
        $lrzString = trim($lrzString);
        if(strpos($url, $lrzString) === 0){
            $remainingUrl = str_replace($lrzString,"",$url);
            $appString ="&" . substr(strrchr($remainingUrl,'.'),1) . ":";
            return $lrzString . $appString . $remainingUrl;
        }
    }
    return $url;
}