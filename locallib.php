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
 * Library of functions used by the quizinvideo module.
 *
 * This contains functions that are called from within the quizinvideo module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 * This script also loads the code in {@link questionlib.php} which holds
 * the module-indpendent code for handling questions and which in turn
 * initialises all the questiontype classes.
 *
 * @package    mod_quizinvideo
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/lib.php');
require_once($CFG->dirroot . '/mod/quizinvideo/accessmanager.php');
require_once($CFG->dirroot . '/mod/quizinvideo/accessmanager_form.php');
require_once($CFG->dirroot . '/mod/quizinvideo/renderer.php');
require_once($CFG->dirroot . '/mod/quizinvideo/attemptlib.php');
require_once($CFG->libdir  . '/eventslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');


/**
 * @var int We show the countdown timer if there is less than this amount of time left before the
 * the quizinvideo close date. (1 hour)
 */
define('quizinvideo_SHOW_TIME_BEFORE_DEADLINE', '3600');

/**
 * @var int If there are fewer than this many seconds left when the student submits
 * a page of the quizinvideo, then do not take them to the next page of the quizinvideo. Instead
 * close the quizinvideo immediately.
 */
define('quizinvideo_MIN_TIME_TO_CONTINUE', '2');

/**
 * @var int We show no image when user selects No image from dropdown menu in quizinvideo settings.
 */
define('quizinvideo_SHOWIMAGE_NONE', 0);

/**
 * @var int We show small image when user selects small image from dropdown menu in quizinvideo settings.
 */
define('quizinvideo_SHOWIMAGE_SMALL', 1);

/**
 * @var int We show Large image when user selects Large image from dropdown menu in quizinvideo settings.
 */
define('quizinvideo_SHOWIMAGE_LARGE', 2);


// Functions related to attempts ///////////////////////////////////////////////

/**
 * Creates an object to represent a new attempt at a quizinvideo
 *
 * Creates an attempt object to represent an attempt at the quizinvideo by the current
 * user starting at the current time. The ->id field is not set. The object is
 * NOT written to the database.
 *
 * @param object $quizinvideoobj the quizinvideo object to create an attempt for.
 * @param int $attemptnumber the sequence number for the attempt.
 * @param object $lastattempt the previous attempt by this user, if any. Only needed
 *         if $attemptnumber > 1 and $quizinvideo->attemptonlast is true.
 * @param int $timenow the time the attempt was started at.
 * @param bool $ispreview whether this new attempt is a preview.
 * @param int $userid  the id of the user attempting this quizinvideo.
 *
 * @return object the newly created attempt object.
 */
function quizinvideo_create_attempt(quizinvideo $quizinvideoobj, $attemptnumber, $lastattempt, $timenow, $ispreview = false, $userid = null) {
    global $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $quizinvideo = $quizinvideoobj->get_quizinvideo();
    if ($quizinvideo->sumgrades < 0.000005 && $quizinvideo->grade > 0.000005) {
        throw new moodle_exception('cannotstartgradesmismatch', 'quizinvideo',
                new moodle_url('/mod/quizinvideo/view.php', array('q' => $quizinvideo->id)),
                    array('grade' => quizinvideo_format_grade($quizinvideo, $quizinvideo->grade)));
    }

    if ($attemptnumber == 1 || !$quizinvideo->attemptonlast) {
        // We are not building on last attempt so create a new attempt.
        $attempt = new stdClass();
        $attempt->quizinvideo = $quizinvideo->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
    } else {
        // Build on last attempt.
        if (empty($lastattempt)) {
            print_error('cannotfindprevattempt', 'quizinvideo');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;
    $attempt->state = quizinvideo_attempt::IN_PROGRESS;
    $attempt->currentpage = 0;
    $attempt->sumgrades = null;

    // If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    $timeclose = $quizinvideoobj->get_access_manager($timenow)->get_end_time($attempt);
    if ($timeclose === false || $ispreview) {
        $attempt->timecheckstate = null;
    } else {
        $attempt->timecheckstate = $timeclose;
    }

    return $attempt;
}
/**
 * Start a normal, new, quizinvideo attempt.
 *
 * @param quizinvideo      $quizinvideoobj            the quizinvideo object to start an attempt for.
 * @param question_usage_by_activity $quba
 * @param object    $attempt
 * @param integer   $attemptnumber      starting from 1
 * @param integer   $timenow            the attempt start time
 * @param array     $questionids        slot number => question id. Used for random questions, to force the choice
 *                                        of a particular actual question. Intended for testing purposes only.
 * @param array     $forcedvariantsbyslot slot number => variant. Used for questions with variants,
 *                                          to force the choice of a particular variant. Intended for testing
 *                                          purposes only.
 * @throws moodle_exception
 * @return object   modified attempt object
 */
function quizinvideo_start_new_attempt($quizinvideoobj, $quba, $attempt, $attemptnumber, $timenow,
                                $questionids = array(), $forcedvariantsbyslot = array()) {
    // Fully load all the questions in this quizinvideo.
    $quizinvideoobj->preload_questions();
    $quizinvideoobj->load_questions();

    // Add them all to the $quba.
    $questionsinuse = array_keys($quizinvideoobj->get_questions());
    foreach ($quizinvideoobj->get_questions() as $questiondata) {
        if ($questiondata->qtype != 'random') {
            if (!$quizinvideoobj->get_quizinvideo()->shuffleanswers) {
                $questiondata->options->shuffleanswers = false;
            }
            $question = question_bank::make_question($questiondata);

        } else {
            if (!isset($questionids[$quba->next_slot_number()])) {
                $forcequestionid = null;
            } else {
                $forcequestionid = $questionids[$quba->next_slot_number()];
            }

            $question = question_bank::get_qtype('random')->choose_other_question(
                $questiondata, $questionsinuse, $quizinvideoobj->get_quizinvideo()->shuffleanswers, $forcequestionid);
            if (is_null($question)) {
                throw new moodle_exception('notenoughrandomquestions', 'quizinvideo',
                                           $quizinvideoobj->view_url(), $questiondata);
            }
        }

        $quba->add_question($question, $questiondata->maxmark);
        $questionsinuse[] = $question->id;
    }

    // Start all the questions.
    if ($attempt->preview) {
        $variantoffset = rand(1, 100);
    } else {
        $variantoffset = $attemptnumber;
    }
    $variantstrategy = new question_variant_pseudorandom_no_repeats_strategy(
            $variantoffset, $attempt->userid, $quizinvideoobj->get_quizinvideoid());

    if (!empty($forcedvariantsbyslot)) {
        $forcedvariantsbyseed = question_variant_forced_choices_selection_strategy::prepare_forced_choices_array(
            $forcedvariantsbyslot, $quba);
        $variantstrategy = new question_variant_forced_choices_selection_strategy(
            $forcedvariantsbyseed, $variantstrategy);
    }

    $quba->start_all_questions($variantstrategy, $timenow);

    // Work out the attempt layout.
    $layout = array();
    if ($quizinvideoobj->get_quizinvideo()->shufflequestions) {
        $slots = $quba->get_slots();
        shuffle($slots);

        $questionsonthispage = 0;
        foreach ($slots as $slot) {
            if ($questionsonthispage && $questionsonthispage == $quizinvideoobj->get_quizinvideo()->questionsperpage) {
                $layout[] = 0;
                $questionsonthispage = 0;
            }
            $layout[] = $slot;
            $questionsonthispage += 1;
        }

    } else {
        $currentpage = null;
        foreach ($quizinvideoobj->get_questions() as $slot) {
            if ($currentpage !== null && $slot->page != $currentpage) {
                $layout[] = 0;
            }
            $layout[] = $slot->slot;
            $currentpage = $slot->page;
        }
    }

    $layout[] = 0;
    $attempt->layout = implode(',', $layout);

    return $attempt;
}

/**
 * Start a subsequent new attempt, in each attempt builds on last mode.
 *
 * @param question_usage_by_activity    $quba         this question usage
 * @param object                        $attempt      this attempt
 * @param object                        $lastattempt  last attempt
 * @return object                       modified attempt object
 *
 */
function quizinvideo_start_attempt_built_on_last($quba, $attempt, $lastattempt) {
    $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

    $oldnumberstonew = array();
    foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
        $newslot = $quba->add_question($oldqa->get_question(), $oldqa->get_max_mark());

        $quba->start_question_based_on($newslot, $oldqa);

        $oldnumberstonew[$oldslot] = $newslot;
    }

    // Update attempt layout.
    $newlayout = array();
    foreach (explode(',', $lastattempt->layout) as $oldslot) {
        if ($oldslot != 0) {
            $newlayout[] = $oldnumberstonew[$oldslot];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
    return $attempt;
}

/**
 * The save started question usage and quizinvideo attempt in db and log the started attempt.
 *
 * @param quizinvideo                       $quizinvideoobj
 * @param question_usage_by_activity $quba
 * @param object                     $attempt
 * @return object                    attempt object with uniqueid and id set.
 */
function quizinvideo_attempt_save_started($quizinvideoobj, $quba, $attempt) {
    global $DB;
    // Save the attempt in the database.
    question_engine::save_questions_usage_by_activity($quba);
    $attempt->uniqueid = $quba->get_id();
    $attempt->id = $DB->insert_record('quizinvideo_attempts', $attempt);

    // Params used by the events below.
    $params = array(
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'courseid' => $quizinvideoobj->get_courseid(),
        'context' => $quizinvideoobj->get_context()
    );
    // Decide which event we are using.
    if ($attempt->preview) {
        $params['other'] = array(
            'quizinvideoid' => $quizinvideoobj->get_quizinvideoid()
        );
        $event = \mod_quizinvideo\event\attempt_preview_started::create($params);
    } else {
        $event = \mod_quizinvideo\event\attempt_started::create($params);

    }

    // Trigger the event.
    $event->add_record_snapshot('quizinvideo', $quizinvideoobj->get_quizinvideo());
    $event->add_record_snapshot('quizinvideo_attempts', $attempt);
    $event->trigger();

    return $attempt;
}

/**
 * Returns an unfinished attempt (if there is one) for the given
 * user on the given quizinvideo. This function does not return preview attempts.
 *
 * @param int $quizinvideoid the id of the quizinvideo.
 * @param int $userid the id of the user.
 *
 * @return mixed the unfinished attempt if there is one, false if not.
 */
function quizinvideo_get_user_attempt_unfinished($quizinvideoid, $userid) {
    $attempts = quizinvideo_get_user_attempts($quizinvideoid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * Delete a quizinvideo attempt.
 * @param mixed $attempt an integer attempt id or an attempt object
 *      (row of the quizinvideo_attempts table).
 * @param object $quizinvideo the quizinvideo object.
 */
function quizinvideo_delete_attempt($attempt, $quizinvideo) {
    global $DB;
    if (is_numeric($attempt)) {
        if (!$attempt = $DB->get_record('quizinvideo_attempts', array('id' => $attempt))) {
            return;
        }
    }

    if ($attempt->quizinvideo != $quizinvideo->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to quizinvideo $attempt->quizinvideo " .
                "but was passed quizinvideo $quizinvideo->id.");
        return;
    }

    if (!isset($quizinvideo->cmid)) {
        $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $quizinvideo->course);
        $quizinvideo->cmid = $cm->id;
    }

    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
    $DB->delete_records('quizinvideo_attempts', array('id' => $attempt->id));

    // Log the deletion of the attempt.
    $params = array(
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'context' => context_module::instance($quizinvideo->cmid),
        'other' => array(
            'quizinvideoid' => $quizinvideo->id
        )
    );
    $event = \mod_quizinvideo\event\attempt_deleted::create($params);
    $event->add_record_snapshot('quizinvideo_attempts', $attempt);
    $event->trigger();

    // Search quizinvideo_attempts for other instances by this user.
    // If none, then delete record for this quizinvideo, this user from quizinvideo_grades
    // else recalculate best grade.
    $userid = $attempt->userid;
    if (!$DB->record_exists('quizinvideo_attempts', array('userid' => $userid, 'quizinvideo' => $quizinvideo->id))) {
        $DB->delete_records('quizinvideo_grades', array('userid' => $userid, 'quizinvideo' => $quizinvideo->id));
    } else {
        quizinvideo_save_best_grade($quizinvideo, $userid);
    }

    quizinvideo_update_grades($quizinvideo, $userid);
}

/**
 * Delete all the preview attempts at a quizinvideo, or possibly all the attempts belonging
 * to one user.
 * @param object $quizinvideo the quizinvideo object.
 * @param int $userid (optional) if given, only delete the previews belonging to this user.
 */
function quizinvideo_delete_previews($quizinvideo, $userid = null) {
    global $DB;
    $conditions = array('quizinvideo' => $quizinvideo->id, 'preview' => 1);
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('quizinvideo_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        quizinvideo_delete_attempt($attempt, $quizinvideo);
    }
}

/**
 * @param int $quizinvideoid The quizinvideo id.
 * @return bool whether this quizinvideo has any (non-preview) attempts.
 */
function quizinvideo_has_attempts($quizinvideoid) {
    global $DB;
    return $DB->record_exists('quizinvideo_attempts', array('quizinvideo' => $quizinvideoid, 'preview' => 0));
}

// Functions to do with quizinvideo layout and pages //////////////////////////////////

/**
 * Repaginate the questions in a quizinvideo
 * @param int $quizinvideoid the id of the quizinvideo to repaginate.
 * @param int $slotsperpage number of items to put on each page. 0 means unlimited.
 */
function quizinvideo_repaginate_questions($quizinvideoid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $slots = $DB->get_records('quizinvideo_slots', array('quizinvideoid' => $quizinvideoid),
            'slot');

    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if ($slotsonthispage && $slotsonthispage == $slotsperpage) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('quizinvideo_slots', 'page', $currentpage, array('id' => $slot->id));
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();
}

// Functions to do with quizinvideo grades ////////////////////////////////////////////

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this quizinvideo.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param object $quizinvideo the quizinvideo object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param bool|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded'
 *      if the $grade is null.
 */
function quizinvideo_rescale_grade($rawgrade, $quizinvideo, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($quizinvideo->sumgrades >= 0.000005) {
        $grade = $rawgrade * $quizinvideo->grade / $quizinvideo->sumgrades;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = quizinvideo_format_question_grade($quizinvideo, $grade);
    } else if ($format) {
        $grade = quizinvideo_format_grade($quizinvideo, $grade);
    }
    return $grade;
}

/**
 * Get the feedback text that should be show to a student who
 * got this grade on this quizinvideo. The feedback is processed ready for diplay.
 *
 * @param float $grade a grade on this quizinvideo.
 * @param object $quizinvideo the quizinvideo settings.
 * @param object $context the quizinvideo context.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function quizinvideo_feedback_for_grade($grade, $quizinvideo, $context) {
    global $DB;

    if (is_null($grade)) {
        return '';
    }

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedback = $DB->get_record_select('quizinvideo_feedback',
            'quizinvideoid = ? AND mingrade <= ? AND ? < maxgrade', array($quizinvideo->id, $grade, $grade));

    if (empty($feedback->feedbacktext)) {
        return '';
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php',
            $context->id, 'mod_quizinvideo', 'feedback', $feedback->id);
    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * @param object $quizinvideo the quizinvideo database row.
 * @return bool Whether this quizinvideo has any non-blank feedback text.
 */
function quizinvideo_has_feedback($quizinvideo) {
    global $DB;
    static $cache = array();
    if (!array_key_exists($quizinvideo->id, $cache)) {
        $cache[$quizinvideo->id] = quizinvideo_has_grades($quizinvideo) &&
                $DB->record_exists_select('quizinvideo_feedback', "quizinvideoid = ? AND " .
                    $DB->sql_isnotempty('quizinvideo_feedback', 'feedbacktext', false, true),
                array($quizinvideo->id));
    }
    return $cache[$quizinvideo->id];
}

/**
 * Update the sumgrades field of the quizinvideo. This needs to be called whenever
 * the grading structure of the quizinvideo is changed. For example if a question is
 * added or removed, or a question weight is changed.
 *
 * You should call {@link quizinvideo_delete_previews()} before you call this function.
 *
 * @param object $quizinvideo a quizinvideo.
 */
function quizinvideo_update_sumgrades($quizinvideo) {
    global $DB;

    $sql = 'UPDATE {quizinvideo}
            SET sumgrades = COALESCE((
                SELECT SUM(maxmark)
                FROM {quizinvideo_slots}
                WHERE quizinvideoid = {quizinvideo}.id
            ), 0)
            WHERE id = ?';
    $DB->execute($sql, array($quizinvideo->id));
    $quizinvideo->sumgrades = $DB->get_field('quizinvideo', 'sumgrades', array('id' => $quizinvideo->id));

    if ($quizinvideo->sumgrades < 0.000005 && quizinvideo_has_attempts($quizinvideo->id)) {
        // If the quizinvideo has been attempted, and the sumgrades has been
        // set to 0, then we must also set the maximum possible grade to 0, or
        // we will get a divide by zero error.
        quizinvideo_set_grade(0, $quizinvideo);
    }
}

/**
 * Update the sumgrades field of the attempts at a quizinvideo.
 *
 * @param object $quizinvideo a quizinvideo.
 */
function quizinvideo_update_all_attempt_sumgrades($quizinvideo) {
    global $DB;
    $dm = new question_engine_data_mapper();
    $timenow = time();

    $sql = "UPDATE {quizinvideo_attempts}
            SET
                timemodified = :timenow,
                sumgrades = (
                    {$dm->sum_usage_marks_subquery('uniqueid')}
                )
            WHERE quizinvideo = :quizinvideoid AND state = :finishedstate";
    $DB->execute($sql, array('timenow' => $timenow, 'quizinvideoid' => $quizinvideo->id,
            'finishedstate' => quizinvideo_attempt::FINISHED));
}

/**
 * The quizinvideo grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in quizinvideo_grades and quizinvideo_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * quizinvideo_update_all_attempt_sumgrades, quizinvideo_update_all_final_grades and
 * quizinvideo_update_grades.
 *
 * @param float $newgrade the new maximum grade for the quizinvideo.
 * @param object $quizinvideo the quizinvideo we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 */
function quizinvideo_set_grade($newgrade, $quizinvideo) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($quizinvideo->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    $oldgrade = $quizinvideo->grade;
    $quizinvideo->grade = $newgrade;

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the quizinvideo table.
    $DB->set_field('quizinvideo', 'grade', $newgrade, array('id' => $quizinvideo->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        quizinvideo_update_all_final_grades($quizinvideo);

    } else {
        // We can rescale the grades efficiently.
        $timemodified = time();
        $DB->execute("
                UPDATE {quizinvideo_grades}
                SET grade = ? * grade, timemodified = ?
                WHERE quizinvideo = ?
        ", array($newgrade/$oldgrade, $timemodified, $quizinvideo->id));
    }

    if ($oldgrade > 1e-7) {
        // Update the quizinvideo_feedback table.
        $factor = $newgrade/$oldgrade;
        $DB->execute("
                UPDATE {quizinvideo_feedback}
                SET mingrade = ? * mingrade, maxgrade = ? * maxgrade
                WHERE quizinvideoid = ?
        ", array($factor, $factor, $quizinvideo->id));
    }

    // Update grade item and send all grades to gradebook.
    quizinvideo_grade_item_update($quizinvideo);
    quizinvideo_update_grades($quizinvideo);

    $transaction->allow_commit();
    return true;
}

/**
 * Save the overall grade for a user at a quizinvideo in the quizinvideo_grades table
 *
 * @param object $quizinvideo The quizinvideo for which the best grade is to be calculated and then saved.
 * @param int $userid The userid to calculate the grade for. Defaults to the current user.
 * @param array $attempts The attempts of this user. Useful if you are
 * looping through many users. Attempts can be fetched in one master query to
 * avoid repeated querying.
 * @return bool Indicates success or failure.
 */
function quizinvideo_save_best_grade($quizinvideo, $userid = null, $attempts = array()) {
    global $DB, $OUTPUT, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (!$attempts) {
        // Get all the attempts made by the user.
        $attempts = quizinvideo_get_user_attempts($quizinvideo->id, $userid);
    }

    // Calculate the best grade.
    $bestgrade = quizinvideo_calculate_best_grade($quizinvideo, $attempts);
    $bestgrade = quizinvideo_rescale_grade($bestgrade, $quizinvideo, false);

    // Save the best grade in the database.
    if (is_null($bestgrade)) {
        $DB->delete_records('quizinvideo_grades', array('quizinvideo' => $quizinvideo->id, 'userid' => $userid));

    } else if ($grade = $DB->get_record('quizinvideo_grades',
            array('quizinvideo' => $quizinvideo->id, 'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->update_record('quizinvideo_grades', $grade);

    } else {
        $grade = new stdClass();
        $grade->quizinvideo = $quizinvideo->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->insert_record('quizinvideo_grades', $grade);
    }

    quizinvideo_update_grades($quizinvideo, $userid);
}

/**
 * Calculate the overall grade for a quizinvideo given a number of attempts by a particular user.
 *
 * @param object $quizinvideo    the quizinvideo settings object.
 * @param array $attempts an array of all the user's attempts at this quizinvideo in order.
 * @return float          the overall grade
 */
function quizinvideo_calculate_best_grade($quizinvideo, $attempts) {

    switch ($quizinvideo->grademethod) {

        case quizinvideo_ATTEMPTFIRST:
            $firstattempt = reset($attempts);
            return $firstattempt->sumgrades;

        case quizinvideo_ATTEMPTLAST:
            $lastattempt = end($attempts);
            return $lastattempt->sumgrades;

        case quizinvideo_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                if (!is_null($attempt->sumgrades)) {
                    $sum += $attempt->sumgrades;
                    $count++;
                }
            }
            if ($count == 0) {
                return null;
            }
            return $sum / $count;

        case quizinvideo_GRADEHIGHEST:
        default:
            $max = null;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * Update the final grade at this quizinvideo for all students.
 *
 * This function is equivalent to calling quizinvideo_save_best_grade for all
 * users, but much more efficient.
 *
 * @param object $quizinvideo the quizinvideo settings.
 */
function quizinvideo_update_all_final_grades($quizinvideo) {
    global $DB;

    if (!$quizinvideo->sumgrades) {
        return;
    }

    $param = array('iquizinvideoid' => $quizinvideo->id, 'istatefinished' => quizinvideo_attempt::FINISHED);
    $firstlastattemptjoin = "JOIN (
            SELECT
                iquizinvideoa.userid,
                MIN(attempt) AS firstattempt,
                MAX(attempt) AS lastattempt

            FROM {quizinvideo_attempts} iquizinvideoa

            WHERE
                iquizinvideoa.state = :istatefinished AND
                iquizinvideoa.preview = 0 AND
                iquizinvideoa.quizinvideo = :iquizinvideoid

            GROUP BY iquizinvideoa.userid
        ) first_last_attempts ON first_last_attempts.userid = quizinvideoa.userid";

    switch ($quizinvideo->grademethod) {
        case quizinvideo_ATTEMPTFIRST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(quizinvideoa.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'quizinvideoa.attempt = first_last_attempts.firstattempt AND';
            break;

        case quizinvideo_ATTEMPTLAST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(quizinvideoa.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'quizinvideoa.attempt = first_last_attempts.lastattempt AND';
            break;

        case quizinvideo_GRADEAVERAGE:
            $select = 'AVG(quizinvideoa.sumgrades)';
            $join = '';
            $where = '';
            break;

        default:
        case quizinvideo_GRADEHIGHEST:
            $select = 'MAX(quizinvideoa.sumgrades)';
            $join = '';
            $where = '';
            break;
    }

    if ($quizinvideo->sumgrades >= 0.000005) {
        $finalgrade = $select . ' * ' . ($quizinvideo->grade / $quizinvideo->sumgrades);
    } else {
        $finalgrade = '0';
    }
    $param['quizinvideoid'] = $quizinvideo->id;
    $param['quizinvideoid2'] = $quizinvideo->id;
    $param['quizinvideoid3'] = $quizinvideo->id;
    $param['quizinvideoid4'] = $quizinvideo->id;
    $param['statefinished'] = quizinvideo_attempt::FINISHED;
    $param['statefinished2'] = quizinvideo_attempt::FINISHED;
    $finalgradesubquery = "
            SELECT quizinvideoa.userid, $finalgrade AS newgrade
            FROM {quizinvideo_attempts} quizinvideoa
            $join
            WHERE
                $where
                quizinvideoa.state = :statefinished AND
                quizinvideoa.preview = 0 AND
                quizinvideoa.quizinvideo = :quizinvideoid3
            GROUP BY quizinvideoa.userid";

    $changedgrades = $DB->get_records_sql("
            SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

            FROM (
                SELECT userid
                FROM {quizinvideo_grades} qg
                WHERE quizinvideo = :quizinvideoid
            UNION
                SELECT DISTINCT userid
                FROM {quizinvideo_attempts} quizinvideoa2
                WHERE
                    quizinvideoa2.state = :statefinished2 AND
                    quizinvideoa2.preview = 0 AND
                    quizinvideoa2.quizinvideo = :quizinvideoid2
            ) users

            LEFT JOIN {quizinvideo_grades} qg ON qg.userid = users.userid AND qg.quizinvideo = :quizinvideoid4

            LEFT JOIN (
                $finalgradesubquery
            ) newgrades ON newgrades.userid = users.userid

            WHERE
                ABS(newgrades.newgrade - qg.grade) > 0.000005 OR
                ((newgrades.newgrade IS NULL OR qg.grade IS NULL) AND NOT
                          (newgrades.newgrade IS NULL AND qg.grade IS NULL))",
                // The mess on the previous line is detecting where the value is
                // NULL in one column, and NOT NULL in the other, but SQL does
                // not have an XOR operator, and MS SQL server can't cope with
                // (newgrades.newgrade IS NULL) <> (qg.grade IS NULL).
            $param);

    $timenow = time();
    $todelete = array();
    foreach ($changedgrades as $changedgrade) {

        if (is_null($changedgrade->newgrade)) {
            $todelete[] = $changedgrade->userid;

        } else if (is_null($changedgrade->grade)) {
            $toinsert = new stdClass();
            $toinsert->quizinvideo = $quizinvideo->id;
            $toinsert->userid = $changedgrade->userid;
            $toinsert->timemodified = $timenow;
            $toinsert->grade = $changedgrade->newgrade;
            $DB->insert_record('quizinvideo_grades', $toinsert);

        } else {
            $toupdate = new stdClass();
            $toupdate->id = $changedgrade->id;
            $toupdate->grade = $changedgrade->newgrade;
            $toupdate->timemodified = $timenow;
            $DB->update_record('quizinvideo_grades', $toupdate);
        }
    }

    if (!empty($todelete)) {
        list($test, $params) = $DB->get_in_or_equal($todelete);
        $DB->delete_records_select('quizinvideo_grades', 'quizinvideo = ? AND userid ' . $test,
                array_merge(array($quizinvideo->id), $params));
    }
}

/**
 * Efficiently update check state time on all open attempts
 *
 * @param array $conditions optional restrictions on which attempts to update
 *                    Allowed conditions:
 *                      courseid => (array|int) attempts in given course(s)
 *                      userid   => (array|int) attempts for given user(s)
 *                      quizinvideoid   => (array|int) attempts in given quizinvideo(s)
 *                      groupid  => (array|int) quizinvideos with some override for given group(s)
 *
 */
function quizinvideo_update_open_attempts(array $conditions) {
    global $DB;

    foreach ($conditions as &$value) {
        if (!is_array($value)) {
            $value = array($value);
        }
    }

    $params = array();
    $wheres = array("quizinvideoa.state IN ('inprogress', 'overdue')");
    $iwheres = array("iquizinvideoa.state IN ('inprogress', 'overdue')");

    if (isset($conditions['courseid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quizinvideoa.quizinvideo IN (SELECT q.id FROM {quizinvideo} q WHERE q.course $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'icid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquizinvideoa.quizinvideo IN (SELECT q.id FROM {quizinvideo} q WHERE q.course $incond)";
    }

    if (isset($conditions['userid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quizinvideoa.userid $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'iuid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquizinvideoa.userid $incond";
    }

    if (isset($conditions['quizinvideoid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['quizinvideoid'], SQL_PARAMS_NAMED, 'qid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quizinvideoa.quizinvideo $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['quizinvideoid'], SQL_PARAMS_NAMED, 'iqid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquizinvideoa.quizinvideo $incond";
    }

    if (isset($conditions['groupid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'gid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quizinvideoa.quizinvideo IN (SELECT qo.quizinvideo FROM {quizinvideo_overrides} qo WHERE qo.groupid $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'igid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquizinvideoa.quizinvideo IN (SELECT qo.quizinvideo FROM {quizinvideo_overrides} qo WHERE qo.groupid $incond)";
    }

    // SQL to compute timeclose and timelimit for each attempt:
    $quizinvideoausersql = quizinvideo_get_attempt_usertime_sql(
            implode("\n                AND ", $iwheres));

    // SQL to compute the new timecheckstate
    $timecheckstatesql = "
          CASE WHEN quizinvideoauser.usertimelimit = 0 AND quizinvideoauser.usertimeclose = 0 THEN NULL
               WHEN quizinvideoauser.usertimelimit = 0 THEN quizinvideoauser.usertimeclose
               WHEN quizinvideoauser.usertimeclose = 0 THEN quizinvideoa.timestart + quizinvideoauser.usertimelimit
               WHEN quizinvideoa.timestart + quizinvideoauser.usertimelimit < quizinvideoauser.usertimeclose THEN quizinvideoa.timestart + quizinvideoauser.usertimelimit
               ELSE quizinvideoauser.usertimeclose END +
          CASE WHEN quizinvideoa.state = 'overdue' THEN quizinvideo.graceperiod ELSE 0 END";

    // SQL to select which attempts to process
    $attemptselect = implode("\n                         AND ", $wheres);

   /*
    * Each database handles updates with inner joins differently:
    *  - mysql does not allow a FROM clause
    *  - postgres and mssql allow FROM but handle table aliases differently
    *  - oracle requires a subquery
    *
    * Different code for each database.
    */

    $dbfamily = $DB->get_dbfamily();
    if ($dbfamily == 'mysql') {
        $updatesql = "UPDATE {quizinvideo_attempts} quizinvideoa
                        JOIN {quizinvideo} quizinvideo ON quizinvideo.id = quizinvideoa.quizinvideo
                        JOIN ( $quizinvideoausersql ) quizinvideoauser ON quizinvideoauser.id = quizinvideoa.id
                         SET quizinvideoa.timecheckstate = $timecheckstatesql
                       WHERE $attemptselect";
    } else if ($dbfamily == 'postgres') {
        $updatesql = "UPDATE {quizinvideo_attempts} quizinvideoa
                         SET timecheckstate = $timecheckstatesql
                        FROM {quizinvideo} quizinvideo, ( $quizinvideoausersql ) quizinvideoauser
                       WHERE quizinvideo.id = quizinvideoa.quizinvideo
                         AND quizinvideoauser.id = quizinvideoa.id
                         AND $attemptselect";
    } else if ($dbfamily == 'mssql') {
        $updatesql = "UPDATE quizinvideoa
                         SET timecheckstate = $timecheckstatesql
                        FROM {quizinvideo_attempts} quizinvideoa
                        JOIN {quizinvideo} quizinvideo ON quizinvideo.id = quizinvideoa.quizinvideo
                        JOIN ( $quizinvideoausersql ) quizinvideoauser ON quizinvideoauser.id = quizinvideoa.id
                       WHERE $attemptselect";
    } else {
        // oracle, sqlite and others
        $updatesql = "UPDATE {quizinvideo_attempts} quizinvideoa
                         SET timecheckstate = (
                           SELECT $timecheckstatesql
                             FROM {quizinvideo} quizinvideo, ( $quizinvideoausersql ) quizinvideoauser
                            WHERE quizinvideo.id = quizinvideoa.quizinvideo
                              AND quizinvideoauser.id = quizinvideoa.id
                         )
                         WHERE $attemptselect";
    }

    $DB->execute($updatesql, $params);
}

/**
 * Returns SQL to compute timeclose and timelimit for every attempt, taking into account user and group overrides.
 *
 * @param string $redundantwhereclauses extra where clauses to add to the subquery
 *      for performance. These can use the table alias iquizinvideoa for the quizinvideo attempts table.
 * @return string SQL select with columns attempt.id, usertimeclose, usertimelimit.
 */
function quizinvideo_get_attempt_usertime_sql($redundantwhereclauses = '') {
    if ($redundantwhereclauses) {
        $redundantwhereclauses = 'WHERE ' . $redundantwhereclauses;
    }
    // The multiple qgo JOINS are necessary because we want timeclose/timelimit = 0 (unlimited) to supercede
    // any other group override
    $quizinvideoausersql = "
          SELECT iquizinvideoa.id,
           COALESCE(MAX(quo.timeclose), MAX(qgo1.timeclose), MAX(qgo2.timeclose), iquizinvideo.timeclose) AS usertimeclose,
           COALESCE(MAX(quo.timelimit), MAX(qgo3.timelimit), MAX(qgo4.timelimit), iquizinvideo.timelimit) AS usertimelimit

           FROM {quizinvideo_attempts} iquizinvideoa
           JOIN {quizinvideo} iquizinvideo ON iquizinvideo.id = iquizinvideoa.quizinvideo
      LEFT JOIN {quizinvideo_overrides} quo ON quo.quizinvideo = iquizinvideoa.quizinvideo AND quo.userid = iquizinvideoa.userid
      LEFT JOIN {groups_members} gm ON gm.userid = iquizinvideoa.userid
      LEFT JOIN {quizinvideo_overrides} qgo1 ON qgo1.quizinvideo = iquizinvideoa.quizinvideo AND qgo1.groupid = gm.groupid AND qgo1.timeclose = 0
      LEFT JOIN {quizinvideo_overrides} qgo2 ON qgo2.quizinvideo = iquizinvideoa.quizinvideo AND qgo2.groupid = gm.groupid AND qgo2.timeclose > 0
      LEFT JOIN {quizinvideo_overrides} qgo3 ON qgo3.quizinvideo = iquizinvideoa.quizinvideo AND qgo3.groupid = gm.groupid AND qgo3.timelimit = 0
      LEFT JOIN {quizinvideo_overrides} qgo4 ON qgo4.quizinvideo = iquizinvideoa.quizinvideo AND qgo4.groupid = gm.groupid AND qgo4.timelimit > 0
          $redundantwhereclauses
       GROUP BY iquizinvideoa.id, iquizinvideo.id, iquizinvideo.timeclose, iquizinvideo.timelimit";
    return $quizinvideoausersql;
}

/**
 * Return the attempt with the best grade for a quizinvideo
 *
 * Which attempt is the best depends on $quizinvideo->grademethod. If the grade
 * method is GRADEAVERAGE then this function simply returns the last attempt.
 * @return object         The attempt with the best grade
 * @param object $quizinvideo    The quizinvideo for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the quizinvideo
 */
function quizinvideo_calculate_best_attempt($quizinvideo, $attempts) {

    switch ($quizinvideo->grademethod) {

        case quizinvideo_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt;
            }
            break;

        case quizinvideo_GRADEAVERAGE: // We need to do something with it.
        case quizinvideo_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt;
            }
            return $final;

        default:
        case quizinvideo_GRADEHIGHEST:
            $max = -1;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                    $maxattempt = $attempt;
                }
            }
            return $maxattempt;
    }
}

/**
 * @return array int => lang string the options for calculating the quizinvideo grade
 *      from the individual attempt grades.
 */
function quizinvideo_get_grading_options() {
    return array(
        quizinvideo_GRADEHIGHEST => get_string('gradehighest', 'quizinvideo'),
        quizinvideo_GRADEAVERAGE => get_string('gradeaverage', 'quizinvideo'),
        quizinvideo_ATTEMPTFIRST => get_string('attemptfirst', 'quizinvideo'),
        quizinvideo_ATTEMPTLAST  => get_string('attemptlast', 'quizinvideo')
    );
}

/**
 * @param int $option one of the values quizinvideo_GRADEHIGHEST, quizinvideo_GRADEAVERAGE,
 *      quizinvideo_ATTEMPTFIRST or quizinvideo_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function quizinvideo_get_grading_option_name($option) {
    $strings = quizinvideo_get_grading_options();
    return $strings[$option];
}

/**
 * @return array string => lang string the options for handling overdue quizinvideo
 *      attempts.
 */
function quizinvideo_get_overdue_handling_options() {
    return array(
        'autosubmit'  => get_string('overduehandlingautosubmit', 'quizinvideo'),
        'graceperiod' => get_string('overduehandlinggraceperiod', 'quizinvideo'),
        'autoabandon' => get_string('overduehandlingautoabandon', 'quizinvideo'),
    );
}

/**
 * Get the choices for what size user picture to show.
 * @return array string => lang string the options for whether to display the user's picture.
 */
function quizinvideo_get_user_image_options() {
    return array(
        quizinvideo_SHOWIMAGE_NONE  => get_string('shownoimage', 'quizinvideo'),
        quizinvideo_SHOWIMAGE_SMALL => get_string('showsmallimage', 'quizinvideo'),
        quizinvideo_SHOWIMAGE_LARGE => get_string('showlargeimage', 'quizinvideo'),
    );
}

/**
 * Get the choices to offer for the 'Questions per page' option.
 * @return array int => string.
 */
function quizinvideo_questions_per_page_options() {
    $pageoptions = array();
    $pageoptions[0] = get_string('neverallononepage', 'quizinvideo');
    $pageoptions[1] = get_string('everyquestion', 'quizinvideo');
    for ($i = 2; $i <= quizinvideo_MAX_QPP_OPTION; ++$i) {
        $pageoptions[$i] = get_string('everynquestions', 'quizinvideo', $i);
    }
    return $pageoptions;
}

/**
 * Get the human-readable name for a quizinvideo attempt state.
 * @param string $state one of the state constants like {@link quizinvideo_attempt::IN_PROGRESS}.
 * @return string The lang string to describe that state.
 */
function quizinvideo_attempt_state_name($state) {
    switch ($state) {
        case quizinvideo_attempt::IN_PROGRESS:
            return get_string('stateinprogress', 'quizinvideo');
        case quizinvideo_attempt::OVERDUE:
            return get_string('stateoverdue', 'quizinvideo');
        case quizinvideo_attempt::FINISHED:
            return get_string('statefinished', 'quizinvideo');
        case quizinvideo_attempt::ABANDONED:
            return get_string('stateabandoned', 'quizinvideo');
        default:
            throw new coding_exception('Unknown quizinvideo attempt state.');
    }
}

// Other quizinvideo functions ////////////////////////////////////////////////////////

/**
 * @param object $quizinvideo the quizinvideo.
 * @param int $cmid the course_module object for this quizinvideo.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function quizinvideo_question_action_icons($quizinvideo, $cmid, $question, $returnurl) {
    $html = quizinvideo_question_preview_button($quizinvideo, $question) . ' ' .
            quizinvideo_question_edit_button($cmid, $question, $returnurl);
    return $html;
}

/**
 * @param int $cmid the course_module.id for this quizinvideo.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function quizinvideo_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit', $question->category) ||
                    question_has_capability_on($question, 'move', $question->category))) {
        $action = $stredit;
        $icon = '/t/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view', $question->category)) {
        $action = $strview;
        $icon = '/i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = $returnurl->out_as_local_url(false);
        }
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '" class="questioneditbutton"><img src="' .
                $OUTPUT->pix_url($icon) . '" alt="' . $action . '" />' . $contentaftericon .
                '</a>';
    } else if ($contentaftericon) {
        return '<span class="questioneditbutton">' . $contentaftericon . '</span>';
    } else {
        return '';
    }
}

/**
 * @param object $quizinvideo the quizinvideo settings
 * @param object $question the question
 * @return moodle_url to preview this question with the options from this quizinvideo.
 */
function quizinvideo_question_preview_url($quizinvideo, $question) {
    // Get the appropriate display options.
    $displayoptions = mod_quizinvideo_display_options::make_from_quizinvideo($quizinvideo,
            mod_quizinvideo_display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return question_preview_url($question->id, $quizinvideo->preferredbehaviour,
            $maxmark, $displayoptions);
}

/**
 * @param object $quizinvideo the quizinvideo settings
 * @param object $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @return the HTML for a preview question icon.
 */
function quizinvideo_question_preview_button($quizinvideo, $question, $label = false) {
    global $PAGE;
    if (!question_has_capability_on($question, 'use', $question->category)) {
        return '';
    }

    return $PAGE->get_renderer('mod_quizinvideo', 'edit')->question_preview_icon($quizinvideo, $question, $label);
}

/**
 * @param object $attempt the attempt.
 * @param object $context the quizinvideo context.
 * @return int whether flags should be shown/editable to the current user for this attempt.
 */
function quizinvideo_get_flag_option($attempt, $context) {
    global $USER;
    if (!has_capability('moodle/question:flag', $context)) {
        return question_display_options::HIDDEN;
    } else if ($attempt->userid == $USER->id) {
        return question_display_options::EDITABLE;
    } else {
        return question_display_options::VISIBLE;
    }
}

/**
 * Work out what state this quizinvideo attempt is in - in the sense used by
 * quizinvideo_get_review_options, not in the sense of $attempt->state.
 * @param object $quizinvideo the quizinvideo settings
 * @param object $attempt the quizinvideo_attempt database row.
 * @return int one of the mod_quizinvideo_display_options::DURING,
 *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
 */
function quizinvideo_attempt_state($quizinvideo, $attempt) {
    if ($attempt->state == quizinvideo_attempt::IN_PROGRESS) {
        return mod_quizinvideo_display_options::DURING;
    } else if (time() < $attempt->timefinish + 120) {
        return mod_quizinvideo_display_options::IMMEDIATELY_AFTER;
    } else if (!$quizinvideo->timeclose || time() < $quizinvideo->timeclose) {
        return mod_quizinvideo_display_options::LATER_WHILE_OPEN;
    } else {
        return mod_quizinvideo_display_options::AFTER_CLOSE;
    }
}

/**
 * The the appropraite mod_quizinvideo_display_options object for this attempt at this
 * quizinvideo right now.
 *
 * @param object $quizinvideo the quizinvideo instance.
 * @param object $attempt the attempt in question.
 * @param $context the quizinvideo context.
 *
 * @return mod_quizinvideo_display_options
 */
function quizinvideo_get_review_options($quizinvideo, $attempt, $context) {
    $options = mod_quizinvideo_display_options::make_from_quizinvideo($quizinvideo, quizinvideo_attempt_state($quizinvideo, $attempt));

    $options->readonly = true;
    $options->flags = quizinvideo_get_flag_option($attempt, $context);
    if (!empty($attempt->id)) {
        $options->questionreviewlink = new moodle_url('/mod/quizinvideo/reviewquestion.php',
                array('attempt' => $attempt->id));
    }

    // Show a link to the comment box only for closed attempts.
    if (!empty($attempt->id) && $attempt->state == quizinvideo_attempt::FINISHED && !$attempt->preview &&
            !is_null($context) && has_capability('mod/quizinvideo:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = new moodle_url('/mod/quizinvideo/comment.php',
                array('attempt' => $attempt->id));
    }

    if (!is_null($context) && !$attempt->preview &&
            has_capability('mod/quizinvideo:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;

    }

    return $options;
}

/**
 * Combines the review options from a number of different quizinvideo attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = quizinvideo_get_combined_reviewoptions(...)
 *
 * @param object $quizinvideo the quizinvideo instance.
 * @param array $attempts an array of attempt objects.
 * @param $context the roles and permissions context,
 *          normally the context for the quizinvideo module instance.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function quizinvideo_get_combined_reviewoptions($quizinvideo, $attempts) {
    $fields = array('feedback', 'generalfeedback', 'rightanswer', 'overallfeedback');
    $someoptions = new stdClass();
    $alloptions = new stdClass();
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    $someoptions->marks = question_display_options::HIDDEN;
    $alloptions->marks = question_display_options::MARK_AND_MAX;

    foreach ($attempts as $attempt) {
        $attemptoptions = mod_quizinvideo_display_options::make_from_quizinvideo($quizinvideo,
                quizinvideo_attempt_state($quizinvideo, $attempt));
        foreach ($fields as $field) {
            $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
            $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
        }
        $someoptions->marks = max($someoptions->marks, $attemptoptions->marks);
        $alloptions->marks = min($alloptions->marks, $attemptoptions->marks);
    }
    return array($someoptions, $alloptions);
}

// Functions for sending notification messages /////////////////////////////////

/**
 * Sends a confirmation message to the student confirming that the attempt was processed.
 *
 * @param object $a lots of useful information that can be used in the message
 *      subject and body.
 *
 * @return int|false as for {@link message_send()}.
 */
function quizinvideo_send_confirmation($recipient, $a) {

    // Add information about the recipient to $a.
    // Don't do idnumber. we want idnumber to be the submitter's idnumber.
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new stdClass();
    $eventdata->component         = 'mod_quizinvideo';
    $eventdata->name              = 'confirmation';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailconfirmsubject', 'quizinvideo', $a);
    $eventdata->fullmessage       = get_string('emailconfirmbody', 'quizinvideo', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailconfirmsmall', 'quizinvideo', $a);
    $eventdata->contexturl        = $a->quizinvideourl;
    $eventdata->contexturlname    = $a->quizinvideoname;

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Sends notification messages to the interested parties that assign the role capability
 *
 * @param object $recipient user object of the intended recipient
 * @param object $a associative array of replaceable fields for the templates
 *
 * @return int|false as for {@link message_send()}.
 */
function quizinvideo_send_notification($recipient, $submitter, $a) {

    // Recipient info for template.
    $a->useridnumber = $recipient->idnumber;
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new stdClass();
    $eventdata->component         = 'mod_quizinvideo';
    $eventdata->name              = 'submission';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = $submitter;
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailnotifysubject', 'quizinvideo', $a);
    $eventdata->fullmessage       = get_string('emailnotifybody', 'quizinvideo', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailnotifysmall', 'quizinvideo', $a);
    $eventdata->contexturl        = $a->quizinvideoreviewurl;
    $eventdata->contexturlname    = $a->quizinvideoname;

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Send all the requried messages when a quizinvideo attempt is submitted.
 *
 * @param object $course the course
 * @param object $quizinvideo the quizinvideo
 * @param object $attempt this attempt just finished
 * @param object $context the quizinvideo context
 * @param object $cm the coursemodule for this quizinvideo
 *
 * @return bool true if all necessary messages were sent successfully, else false.
 */
function quizinvideo_send_notification_messages($course, $quizinvideo, $attempt, $context, $cm) {
    global $CFG, $DB;

    // Do nothing if required objects not present.
    if (empty($course) or empty($quizinvideo) or empty($attempt) or empty($context)) {
        throw new coding_exception('$course, $quizinvideo, $attempt, $context and $cm must all be set.');
    }

    $submitter = $DB->get_record('user', array('id' => $attempt->userid), '*', MUST_EXIST);

    // Check for confirmation required.
    $sendconfirm = false;
    $notifyexcludeusers = '';
    if (has_capability('mod/quizinvideo:emailconfirmsubmission', $context, $submitter, false)) {
        $notifyexcludeusers = $submitter->id;
        $sendconfirm = true;
    }

    // Check for notifications required.
    $notifyfields = 'u.id, u.username, u.idnumber, u.email, u.emailstop, u.lang, u.timezone, u.mailformat, u.maildisplay, ';
    $notifyfields .= get_all_user_name_fields(true, 'u');
    $groups = groups_get_all_groups($course->id, $submitter->id);
    if (is_array($groups) && count($groups) > 0) {
        $groups = array_keys($groups);
    } else if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        // If the user is not in a group, and the quizinvideo is set to group mode,
        // then set $groups to a non-existant id so that only users with
        // 'moodle/site:accessallgroups' get notified.
        $groups = -1;
    } else {
        $groups = '';
    }
    $userstonotify = get_users_by_capability($context, 'mod/quizinvideo:emailnotifysubmission',
            $notifyfields, '', '', '', $groups, $notifyexcludeusers, false, false, true);

    if (empty($userstonotify) && !$sendconfirm) {
        return true; // Nothing to do.
    }

    $a = new stdClass();
    // Course info.
    $a->coursename      = $course->fullname;
    $a->courseshortname = $course->shortname;
    // quizinvideo info.
    $a->quizinvideoname        = $quizinvideo->name;
    $a->quizinvideoreporturl   = $CFG->wwwroot . '/mod/quizinvideo/report.php?id=' . $cm->id;
    $a->quizinvideoreportlink  = '<a href="' . $a->quizinvideoreporturl . '">' .
            format_string($quizinvideo->name) . ' report</a>';
    $a->quizinvideourl         = $CFG->wwwroot . '/mod/quizinvideo/view.php?id=' . $cm->id;
    $a->quizinvideolink        = '<a href="' . $a->quizinvideourl . '">' . format_string($quizinvideo->name) . '</a>';
    // Attempt info.
    $a->submissiontime  = userdate($attempt->timefinish);
    $a->timetaken       = format_time($attempt->timefinish - $attempt->timestart);
    $a->quizinvideoreviewurl   = $CFG->wwwroot . '/mod/quizinvideo/review.php?attempt=' . $attempt->id;
    $a->quizinvideoreviewlink  = '<a href="' . $a->quizinvideoreviewurl . '">' .
            format_string($quizinvideo->name) . ' review</a>';
    // Student who sat the quizinvideo info.
    $a->studentidnumber = $submitter->idnumber;
    $a->studentname     = fullname($submitter);
    $a->studentusername = $submitter->username;

    $allok = true;

    // Send notifications if required.
    if (!empty($userstonotify)) {
        foreach ($userstonotify as $recipient) {
            $allok = $allok && quizinvideo_send_notification($recipient, $submitter, $a);
        }
    }

    // Send confirmation if required. We send the student confirmation last, so
    // that if message sending is being intermittently buggy, which means we send
    // some but not all messages, and then try again later, then teachers may get
    // duplicate messages, but the student will always get exactly one.
    if ($sendconfirm) {
        $allok = $allok && quizinvideo_send_confirmation($submitter, $a);
    }

    return $allok;
}

/**
 * Send the notification message when a quizinvideo attempt becomes overdue.
 *
 * @param quizinvideo_attempt $attemptobj all the data about the quizinvideo attempt.
 */
function quizinvideo_send_overdue_message($attemptobj) {
    global $CFG, $DB;

    $submitter = $DB->get_record('user', array('id' => $attemptobj->get_userid()), '*', MUST_EXIST);

    if (!$attemptobj->has_capability('mod/quizinvideo:emailwarnoverdue', $submitter->id, false)) {
        return; // Message not required.
    }

    if (!$attemptobj->has_response_to_at_least_one_graded_question()) {
        return; // Message not required.
    }

    // Prepare lots of useful information that admins might want to include in
    // the email message.
    $quizinvideoname = format_string($attemptobj->get_quizinvideo_name());

    $deadlines = array();
    if ($attemptobj->get_quizinvideo()->timelimit) {
        $deadlines[] = $attemptobj->get_attempt()->timestart + $attemptobj->get_quizinvideo()->timelimit;
    }
    if ($attemptobj->get_quizinvideo()->timeclose) {
        $deadlines[] = $attemptobj->get_quizinvideo()->timeclose;
    }
    $duedate = min($deadlines);
    $graceend = $duedate + $attemptobj->get_quizinvideo()->graceperiod;

    $a = new stdClass();
    // Course info.
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    $a->courseshortname    = format_string($attemptobj->get_course()->shortname);
    // quizinvideo info.
    $a->quizinvideoname           = $quizinvideoname;
    $a->quizinvideourl            = $attemptobj->view_url();
    $a->quizinvideolink           = '<a href="' . $a->quizinvideourl . '">' . $quizinvideoname . '</a>';
    // Attempt info.
    $a->attemptduedate     = userdate($duedate);
    $a->attemptgraceend    = userdate($graceend);
    $a->attemptsummaryurl  = $attemptobj->summary_url()->out(false);
    $a->attemptsummarylink = '<a href="' . $a->attemptsummaryurl . '">' . $quizinvideoname . ' review</a>';
    // Student's info.
    $a->studentidnumber    = $submitter->idnumber;
    $a->studentname        = fullname($submitter);
    $a->studentusername    = $submitter->username;

    // Prepare the message.
    $eventdata = new stdClass();
    $eventdata->component         = 'mod_quizinvideo';
    $eventdata->name              = 'attempt_overdue';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $submitter;
    $eventdata->subject           = get_string('emailoverduesubject', 'quizinvideo', $a);
    $eventdata->fullmessage       = get_string('emailoverduebody', 'quizinvideo', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailoverduesmall', 'quizinvideo', $a);
    $eventdata->contexturl        = $a->quizinvideourl;
    $eventdata->contexturlname    = $a->quizinvideoname;

    // Send the message.
    return message_send($eventdata);
}

/**
 * Handle the quizinvideo_attempt_submitted event.
 *
 * This sends the confirmation and notification messages, if required.
 *
 * @param object $event the event object.
 */
function quizinvideo_attempt_submitted_handler($event) {
    global $DB;

    $course  = $DB->get_record('course', array('id' => $event->courseid));
    $attempt = $event->get_record_snapshot('quizinvideo_attempts', $event->objectid);
    $quizinvideo    = $event->get_record_snapshot('quizinvideo', $attempt->quizinvideo);
    $cm      = get_coursemodule_from_id('quizinvideo', $event->get_context()->instanceid, $event->courseid);

    if (!($course && $quizinvideo && $cm && $attempt)) {
        // Something has been deleted since the event was raised. Therefore, the
        // event is no longer relevant.
        return true;
    }

    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && ($quizinvideo->completionattemptsexhausted || $quizinvideo->completionpass)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $event->userid);
    }
    return quizinvideo_send_notification_messages($course, $quizinvideo, $attempt,
            context_module::instance($cm->id), $cm);
}

/**
 * Handle groups_member_added event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_quizinvideo\group_observers::group_member_added()}.
 */
function quizinvideo_groups_member_added_handler($event) {
    debugging('quizinvideo_groups_member_added_handler() is deprecated, please use ' .
        '\mod_quizinvideo\group_observers::group_member_added() instead.', DEBUG_DEVELOPER);
    quizinvideo_update_open_attempts(array('userid'=>$event->userid, 'groupid'=>$event->groupid));
}

/**
 * Handle groups_member_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_quizinvideo\group_observers::group_member_removed()}.
 */
function quizinvideo_groups_member_removed_handler($event) {
    debugging('quizinvideo_groups_member_removed_handler() is deprecated, please use ' .
        '\mod_quizinvideo\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    quizinvideo_update_open_attempts(array('userid'=>$event->userid, 'groupid'=>$event->groupid));
}

/**
 * Handle groups_group_deleted event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_quizinvideo\group_observers::group_deleted()}.
 */
function quizinvideo_groups_group_deleted_handler($event) {
    global $DB;
    debugging('quizinvideo_groups_group_deleted_handler() is deprecated, please use ' .
        '\mod_quizinvideo\group_observers::group_deleted() instead.', DEBUG_DEVELOPER);
    quizinvideo_process_group_deleted_in_course($event->courseid);
}

/**
 * Logic to happen when a/some group(s) has/have been deleted in a course.
 *
 * @param int $courseid The course ID.
 * @return void
 */
function quizinvideo_process_group_deleted_in_course($courseid) {
    global $DB;

    // It would be nice if we got the groupid that was deleted.
    // Instead, we just update all quizinvideos with orphaned group overrides.
    $sql = "SELECT o.id, o.quizinvideo
              FROM {quizinvideo_overrides} o
              JOIN {quizinvideo} quizinvideo ON quizinvideo.id = o.quizinvideo
         LEFT JOIN {groups} grp ON grp.id = o.groupid
             WHERE quizinvideo.course = :courseid
               AND o.groupid IS NOT NULL
               AND grp.id IS NULL";
    $params = array('courseid' => $courseid);
    $records = $DB->get_records_sql_menu($sql, $params);
    if (!$records) {
        return; // Nothing to do.
    }
    $DB->delete_records_list('quizinvideo_overrides', 'id', array_keys($records));
    quizinvideo_update_open_attempts(array('quizinvideoid' => array_unique(array_values($records))));
}

/**
 * Handle groups_members_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_quizinvideo\group_observers::group_member_removed()}.
 */
function quizinvideo_groups_members_removed_handler($event) {
    debugging('quizinvideo_groups_members_removed_handler() is deprecated, please use ' .
        '\mod_quizinvideo\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    if ($event->userid == 0) {
        quizinvideo_update_open_attempts(array('courseid'=>$event->courseid));
    } else {
        quizinvideo_update_open_attempts(array('courseid'=>$event->courseid, 'userid'=>$event->userid));
    }
}

/**
 * Get the information about the standard quizinvideo JavaScript module.
 * @return array a standard jsmodule structure.
 */
function quizinvideo_get_js_module() {
    global $PAGE;

    return array(
        'name' => 'mod_quizinvideo',
        'fullpath' => '/mod/quizinvideo/module.js',
        'requires' => array('base', 'dom', 'event-delegate', 'event-key',
                'core_question_engine', 'moodle-core-formchangechecker'),
        'strings' => array(
            array('cancel', 'moodle'),
            array('flagged', 'question'),
            array('functiondisabledbysecuremode', 'quizinvideo'),
            array('startattempt', 'quizinvideo'),
            array('timesup', 'quizinvideo'),
            array('changesmadereallygoaway', 'moodle'),
        ),
    );
}


/**
 * An extension of question_display_options that includes the extra options used
 * by the quizinvideo.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizinvideo_display_options extends question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * quizinvideo attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the quizinvideo settings, and a time constant.
     * @param object $quizinvideo the quizinvideo settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_quizinvideo_display_options set up appropriately.
     */
    public static function make_from_quizinvideo($quizinvideo, $when) {
        $options = new self();

        $options->attempt = self::extract($quizinvideo->reviewattempt, $when, true, false);
        $options->correctness = self::extract($quizinvideo->reviewcorrectness, $when);
        $options->marks = self::extract($quizinvideo->reviewmarks, $when,
                self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($quizinvideo->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($quizinvideo->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($quizinvideo->reviewrightanswer, $when);
        $options->overallfeedback = self::extract($quizinvideo->reviewoverallfeedback, $when);

        $options->numpartscorrect = $options->feedback;
        $options->manualcomment = $options->feedback;

        if ($quizinvideo->questiondecimalpoints != -1) {
            $options->markdp = $quizinvideo->questiondecimalpoints;
        } else {
            $options->markdp = $quizinvideo->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}


/**
 * A {@link qubaid_condition} for finding all the question usages belonging to
 * a particular quizinvideo.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_quizinvideo extends qubaid_join {
    public function __construct($quizinvideoid, $includepreviews = true, $onlyfinished = false) {
        $where = 'quizinvideoa.quizinvideo = :quizinvideoaquizinvideo';
        $params = array('quizinvideoaquizinvideo' => $quizinvideoid);

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state == :statefinished';
            $params['statefinished'] = quizinvideo_attempt::FINISHED;
        }

        parent::__construct('{quizinvideo_attempts} quizinvideoa', 'quizinvideoa.uniqueid', $where, $params);
    }
}

/**
 * Creates a textual representation of a question for display.
 *
 * @param object $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @return string
 */
function quizinvideo_question_tostring($question, $showicon = false, $showquestiontext = true) {
    $result = '';

    $name = shorten_text(format_string($question->name), 200);
    if ($showicon) {
        $name .= print_question_icon($question) . ' ' . $name;
    }
    $result .= html_writer::span($name, 'questionname');

    if ($showquestiontext) {
        $questiontext = question_utils::to_plain_text($question->questiontext,
                $question->questiontextformat, array('noclean' => true, 'para' => false));
        $questiontext = shorten_text($questiontext, 200);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * Does not return. Throws an exception if the question cannot be used.
 * @param int $questionid The id of the question.
 */
function quizinvideo_require_question_use($questionid) {
    global $DB;
    $question = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
    question_require_capability_on($question, 'use');
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * @param object $quizinvideo the quizinvideo settings.
 * @param int $slot which question in the quizinvideo to test.
 * @return bool whether the user can use this question.
 */
function quizinvideo_has_question_use($quizinvideo, $slot) {
    global $DB;
    $question = $DB->get_record_sql("
            SELECT q.*
              FROM {quizinvideo_slots} slot
              JOIN {question} q ON q.id = slot.questionid
             WHERE slot.quizinvideoid = ? AND slot.slot = ?", array($quizinvideo->id, $slot));
    if (!$question) {
        return false;
    }
    return question_has_capability_on($question, 'use');
}

/**
 * Add a question to a quizinvideo
 *
 * Adds a question to a quizinvideo by updating $quizinvideo as well as the
 * quizinvideo and quizinvideo_slots tables. It also adds a page break if required.
 * @param int $questionid The id of the question to be added
 * @param object $quizinvideo The extended quizinvideo object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in quizinvideo to add the question on. If 0 (default),
 *      add at the end
 * @param float $maxmark The maximum mark to set for this question. (Optional,
 *      defaults to question.defaultmark.
 * @return bool false if the question was already in the quizinvideo
 */
function quizinvideo_add_quizinvideo_question($questionid, $quizinvideo, $page = 0, $maxmark = null) {
    global $DB;
    $slots = $DB->get_records('quizinvideo_slots', array('quizinvideoid' => $quizinvideo->id),
            'slot', 'questionid, slot, page, id');
    if (array_key_exists($questionid, $slots)) {
        return false;
    }

    $trans = $DB->start_delegated_transaction();

    $maxpage = 1;
    $numonlastpage = 0;
    foreach ($slots as $slot) {
        if ($slot->page > $maxpage) {
            $maxpage = $slot->page;
            $numonlastpage = 1;
        } else {
            $numonlastpage += 1;
        }
    }

    // Add the new question instance.
    $slot = new stdClass();
    $slot->quizinvideoid = $quizinvideo->id;
    $slot->questionid = $questionid;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
    }

    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                $DB->set_field('quizinvideo_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($quizinvideo->questionsperpage && $numonlastpage >= $quizinvideo->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $DB->insert_record('quizinvideo_slots', $slot);
    quizinvideo_insert_timeofvideo($slot->quizinvideoid, $slot->page);
    $trans->allow_commit();
}

/**
 * Add a random question to the quizinvideo at a given point.
 * @param object $quizinvideo the quizinvideo settings.
 * @param int $addonpage the page on which to add the question.
 * @param int $categoryid the question category to add the question from.
 * @param int $number the number of random questions to add.
 * @param bool $includesubcategories whether to include questoins from subcategories.
 */
function quizinvideo_add_random_questions($quizinvideo, $addonpage, $categoryid, $number,
        $includesubcategories) {
    global $DB;

    $category = $DB->get_record('question_categories', array('id' => $categoryid));
    if (!$category) {
        print_error('invalidcategoryid', 'error');
    }

    $catcontext = context::instance_by_id($category->contextid);
    require_capability('moodle/question:useall', $catcontext);

    // Find existing random questions in this category that are
    // not used by any quizinvideo.
    if ($existingquestions = $DB->get_records_sql(
            "SELECT q.id, q.qtype FROM {question} q
            WHERE qtype = 'random'
                AND category = ?
                AND " . $DB->sql_compare_text('questiontext') . " = ?
                AND NOT EXISTS (
                        SELECT *
                          FROM {quizinvideo_slots}
                         WHERE questionid = q.id)
            ORDER BY id", array($category->id, ($includesubcategories ? '1' : '0')))) {
            // Take as many of these as needed.
        while (($existingquestion = array_shift($existingquestions)) && $number > 0) {
            quizinvideo_add_quizinvideo_question($existingquestion->id, $quizinvideo, $addonpage);
            $number -= 1;
        }
    }

    if ($number <= 0) {
        return;
    }

    // More random questions are needed, create them.
    for ($i = 0; $i < $number; $i += 1) {
        $form = new stdClass();
        $form->questiontext = array('text' => ($includesubcategories ? '1' : '0'), 'format' => 0);
        $form->category = $category->id . ',' . $category->contextid;
        $form->defaultmark = 1;
        $form->hidden = 1;
        $form->stamp = make_unique_id_code(); // Set the unique code (not to be changed).
        $question = new stdClass();
        $question->qtype = 'random';
        $question = question_bank::get_qtype('random')->save_question($question, $form);
        if (!isset($question->id)) {
            print_error('cannotinsertrandomquestion', 'quizinvideo');
        }
        quizinvideo_add_quizinvideo_question($question->id, $quizinvideo, $addonpage);
    }
}

/**
 * Get time of video for a page of a quizinvideo.
 * @param int $quizinvideoid the id of the quizinvideo.
 * @param int $page the page whose time of video needs to be returned.
 */
function quizinvideo_get_timeofvideo($quizinvideoid, $page){
    global $DB;
    $time = $DB->get_field('quizinvideo_page', 'time', array('quizinvideoid' => $quizinvideoid, 'page' => $page));
    if(!$time)
        return null;
    else
        return $time;
}

/**
 * Set time of video for a page of a quizinvideo.
 * @param int $quizinvideoid the id of the quizinvideo.
 * @param int $page the page whose time of video needs to be returned.
 * @param int $time the time to be set.
 * @return bool false if db operation fails, true if succeeds
 */
function quizinvideo_set_timeofvideo($quizinvideoid, $page, $time){
    global $DB;
    return $DB->set_field('quizinvideo_page', 'time', $time, array('quizinvideoid' => $quizinvideoid, 'page' => $page));
}

/**
 * Set time of video for a page of a quizinvideo.
 * @param int $quizinvideoid the id of the quizinvideo.
 * @param int $page the page whose time of video needs to be returned.
 * @param int $time the time to be set.
 */
function quizinvideo_insert_timeofvideo($quizinvideoid, $page, $time = null){
    global $DB;
    if(!$DB->record_exists('quizinvideo_page', array('quizinvideoid' => $quizinvideoid, 'page' => $page))){
        $page_to_insert = new stdClass();
        $page_to_insert->quizinvideoid = $quizinvideoid;
        $page_to_insert->page = $page;
        $page_to_insert->time = $time;
        $DB->insert_record('quizinvideo_page', $page_to_insert);
    }
}