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
 * Helper functions for the quizinvideo reports.
 *
 * @package   mod_quizinvideo
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/lib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Takes an array of objects and constructs a multidimensional array keyed by
 * the keys it finds on the object.
 * @param array $datum an array of objects with properties on the object
 * including the keys passed as the next param.
 * @param array $keys Array of strings with the names of the properties on the
 * objects in datum that you want to index the multidimensional array by.
 * @param bool $keysunique If there is not only one object for each
 * combination of keys you are using you should set $keysunique to true.
 * Otherwise all the object will be added to a zero based array. So the array
 * returned will have count($keys) + 1 indexs.
 * @return array multidimensional array properly indexed.
 */
function quizinvideo_report_index_by_keys($datum, $keys, $keysunique = true) {
    if (!$datum) {
        return array();
    }
    $key = array_shift($keys);
    $datumkeyed = array();
    foreach ($datum as $data) {
        if ($keys || !$keysunique) {
            $datumkeyed[$data->{$key}][]= $data;
        } else {
            $datumkeyed[$data->{$key}]= $data;
        }
    }
    if ($keys) {
        foreach ($datumkeyed as $datakey => $datakeyed) {
            $datumkeyed[$datakey] = quizinvideo_report_index_by_keys($datakeyed, $keys, $keysunique);
        }
    }
    return $datumkeyed;
}

function quizinvideo_report_unindex($datum) {
    if (!$datum) {
        return $datum;
    }
    $datumunkeyed = array();
    foreach ($datum as $value) {
        if (is_array($value)) {
            $datumunkeyed = array_merge($datumunkeyed, quizinvideo_report_unindex($value));
        } else {
            $datumunkeyed[] = $value;
        }
    }
    return $datumunkeyed;
}

/**
 * Are there any questions in this quizinvideo?
 * @param int $quizinvideoid the quizinvideo id.
 */
function quizinvideo_has_questions($quizinvideoid) {
    global $DB;
    return $DB->record_exists('quizinvideo_slots', array('quizinvideoid' => $quizinvideoid));
}

/**
 * Get the slots of real questions (not descriptions) in this quizinvideo, in order.
 * @param object $quizinvideo the quizinvideo.
 * @return array of slot => $question object with fields
 *      ->slot, ->id, ->maxmark, ->number, ->length.
 */
function quizinvideo_report_get_significant_questions($quizinvideo) {
    global $DB;

    $qsbyslot = $DB->get_records_sql("
            SELECT slot.slot,
                   q.id,
                   q.length,
                   slot.maxmark

              FROM {question} q
              JOIN {quizinvideo_slots} slot ON slot.questionid = q.id

             WHERE slot.quizinvideoid = ?
               AND q.length > 0

          ORDER BY slot.slot", array($quizinvideo->id));

    $number = 1;
    foreach ($qsbyslot as $question) {
        $question->number = $number;
        $number += $question->length;
    }

    return $qsbyslot;
}

/**
 * @param object $quizinvideo the quizinvideo settings.
 * @return bool whether, for this quizinvideo, it is possible to filter attempts to show
 *      only those that gave the final grade.
 */
function quizinvideo_report_can_filter_only_graded($quizinvideo) {
    return $quizinvideo->attempts != 1 && $quizinvideo->grademethod != quizinvideo_GRADEAVERAGE;
}

/**
 * This is a wrapper for {@link quizinvideo_report_grade_method_sql} that takes the whole quizinvideo object instead of just the grading method
 * as a param. See definition for {@link quizinvideo_report_grade_method_sql} below.
 *
 * @param object $quizinvideo
 * @param string $quizinvideoattemptsalias sql alias for 'quizinvideo_attempts' table
 * @return string sql to test if this is an attempt that will contribute towards the grade of the user
 */
function quizinvideo_report_qm_filter_select($quizinvideo, $quizinvideoattemptsalias = 'quizinvideoa') {
    if ($quizinvideo->attempts == 1) {
        // This quizinvideo only allows one attempt.
        return '';
    }
    return quizinvideo_report_grade_method_sql($quizinvideo->grademethod, $quizinvideoattemptsalias);
}

/**
 * Given a quizinvideo grading method return sql to test if this is an
 * attempt that will be contribute towards the grade of the user. Or return an
 * empty string if the grading method is quizinvideo_GRADEAVERAGE and thus all attempts
 * contribute to final grade.
 *
 * @param string $grademethod quizinvideo grading method.
 * @param string $quizinvideoattemptsalias sql alias for 'quizinvideo_attempts' table
 * @return string sql to test if this is an attempt that will contribute towards the graded of the user
 */
function quizinvideo_report_grade_method_sql($grademethod, $quizinvideoattemptsalias = 'quizinvideoa') {
    switch ($grademethod) {
        case quizinvideo_GRADEHIGHEST :
            return "($quizinvideoattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {quizinvideo_attempts} qa2
                            WHERE qa2.quizinvideo = $quizinvideoattemptsalias.quizinvideo AND
                                qa2.userid = $quizinvideoattemptsalias.userid AND
                                 qa2.state = 'finished' AND (
                COALESCE(qa2.sumgrades, 0) > COALESCE($quizinvideoattemptsalias.sumgrades, 0) OR
               (COALESCE(qa2.sumgrades, 0) = COALESCE($quizinvideoattemptsalias.sumgrades, 0) AND qa2.attempt < $quizinvideoattemptsalias.attempt)
                                )))";

        case quizinvideo_GRADEAVERAGE :
            return '';

        case quizinvideo_ATTEMPTFIRST :
            return "($quizinvideoattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {quizinvideo_attempts} qa2
                            WHERE qa2.quizinvideo = $quizinvideoattemptsalias.quizinvideo AND
                                qa2.userid = $quizinvideoattemptsalias.userid AND
                                 qa2.state = 'finished' AND
                               qa2.attempt < $quizinvideoattemptsalias.attempt))";

        case quizinvideo_ATTEMPTLAST :
            return "($quizinvideoattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {quizinvideo_attempts} qa2
                            WHERE qa2.quizinvideo = $quizinvideoattemptsalias.quizinvideo AND
                                qa2.userid = $quizinvideoattemptsalias.userid AND
                                 qa2.state = 'finished' AND
                               qa2.attempt > $quizinvideoattemptsalias.attempt))";
    }
}

/**
 * Get the number of students whose score was in a particular band for this quizinvideo.
 * @param number $bandwidth the width of each band.
 * @param int $bands the number of bands
 * @param int $quizinvideoid the quizinvideo id.
 * @param array $userids list of user ids.
 * @return array band number => number of users with scores in that band.
 */
function quizinvideo_report_grade_bands($bandwidth, $bands, $quizinvideoid, $userids = array()) {
    global $DB;
    if (!is_int($bands)) {
        debugging('$bands passed to quizinvideo_report_grade_bands must be an integer. (' .
                gettype($bands) . ' passed.)', DEBUG_DEVELOPER);
        $bands = (int) $bands;
    }

    if ($userids) {
        list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $usql = "qg.userid $usql AND";
    } else {
        $usql = '';
        $params = array();
    }
    $sql = "
SELECT band, COUNT(1)

FROM (
    SELECT FLOOR(qg.grade / :bandwidth) AS band
      FROM {quizinvideo_grades} qg
     WHERE $usql qg.quizinvideo = :quizinvideoid
) subquery

GROUP BY
    band

ORDER BY
    band";

    $params['quizinvideoid'] = $quizinvideoid;
    $params['bandwidth'] = $bandwidth;

    $data = $DB->get_records_sql_menu($sql, $params);

    // We need to create array elements with values 0 at indexes where there is no element.
    $data = $data + array_fill(0, $bands + 1, 0);
    ksort($data);

    // Place the maximum (perfect grade) into the last band i.e. make last
    // band for example 9 <= g <=10 (where 10 is the perfect grade) rather than
    // just 9 <= g <10.
    $data[$bands - 1] += $data[$bands];
    unset($data[$bands]);

    return $data;
}

function quizinvideo_report_highlighting_grading_method($quizinvideo, $qmsubselect, $qmfilter) {
    if ($quizinvideo->attempts == 1) {
        return '<p>' . get_string('onlyoneattemptallowed', 'quizinvideo_overview') . '</p>';

    } else if (!$qmsubselect) {
        return '<p>' . get_string('allattemptscontributetograde', 'quizinvideo_overview') . '</p>';

    } else if ($qmfilter) {
        return '<p>' . get_string('showinggraded', 'quizinvideo_overview') . '</p>';

    } else {
        return '<p>' . get_string('showinggradedandungraded', 'quizinvideo_overview',
                '<span class="gradedattempt">' . quizinvideo_get_grading_option_name($quizinvideo->grademethod) .
                '</span>') . '</p>';
    }
}

/**
 * Get the feedback text for a grade on this quizinvideo. The feedback is
 * processed ready for display.
 *
 * @param float $grade a grade on this quizinvideo.
 * @param int $quizinvideoid the id of the quizinvideo object.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function quizinvideo_report_feedback_for_grade($grade, $quizinvideoid, $context) {
    global $DB;

    static $feedbackcache = array();

    if (!isset($feedbackcache[$quizinvideoid])) {
        $feedbackcache[$quizinvideoid] = $DB->get_records('quizinvideo_feedback', array('quizinvideoid' => $quizinvideoid));
    }

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedbacks = $feedbackcache[$quizinvideoid];
    $feedbackid = 0;
    $feedbacktext = '';
    $feedbacktextformat = FORMAT_MOODLE;
    foreach ($feedbacks as $feedback) {
        if ($feedback->mingrade <= $grade && $grade < $feedback->maxgrade) {
            $feedbackid = $feedback->id;
            $feedbacktext = $feedback->feedbacktext;
            $feedbacktextformat = $feedback->feedbacktextformat;
            break;
        }
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedbacktext, 'pluginfile.php',
            $context->id, 'mod_quizinvideo', 'feedback', $feedbackid);
    $feedbacktext = format_text($feedbacktext, $feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * Format a number as a percentage out of $quizinvideo->sumgrades
 * @param number $rawgrade the mark to format.
 * @param object $quizinvideo the quizinvideo settings
 * @param bool $round whether to round the results ot $quizinvideo->decimalpoints.
 */
function quizinvideo_report_scale_summarks_as_percentage($rawmark, $quizinvideo, $round = true) {
    if ($quizinvideo->sumgrades == 0) {
        return '';
    }
    if (!is_numeric($rawmark)) {
        return $rawmark;
    }

    $mark = $rawmark * 100 / $quizinvideo->sumgrades;
    if ($round) {
        $mark = quizinvideo_format_grade($quizinvideo, $mark);
    }
    return $mark . '%';
}

/**
 * Returns an array of reports to which the current user has access to.
 * @return array reports are ordered as they should be for display in tabs.
 */
function quizinvideo_report_list($context) {
    global $DB;
    static $reportlist = null;
    if (!is_null($reportlist)) {
        return $reportlist;
    }

    $reports = $DB->get_records('quizinvideo_reports', null, 'displayorder DESC', 'name, capability');
    $reportdirs = core_component::get_plugin_list('quizinvideo');

    // Order the reports tab in descending order of displayorder.
    $reportcaps = array();
    foreach ($reports as $key => $report) {
        if (array_key_exists($report->name, $reportdirs)) {
            $reportcaps[$report->name] = $report->capability;
        }
    }

    // Add any other reports, which are on disc but not in the DB, on the end.
    foreach ($reportdirs as $reportname => $notused) {
        if (!isset($reportcaps[$reportname])) {
            $reportcaps[$reportname] = null;
        }
    }
    $reportlist = array();
    foreach ($reportcaps as $name => $capability) {
        if (empty($capability)) {
            $capability = 'mod/quizinvideo:viewreports';
        }
        if (has_capability($capability, $context)) {
            $reportlist[] = $name;
        }
    }
    return $reportlist;
}

/**
 * Create a filename for use when downloading data from a quizinvideo report. It is
 * expected that this will be passed to flexible_table::is_downloading, which
 * cleans the filename of bad characters and adds the file extension.
 * @param string $report the type of report.
 * @param string $courseshortname the course shortname.
 * @param string $quizinvideoname the quizinvideo name.
 * @return string the filename.
 */
function quizinvideo_report_download_filename($report, $courseshortname, $quizinvideoname) {
    return $courseshortname . '-' . format_string($quizinvideoname, true) . '-' . $report;
}

/**
 * Get the default report for the current user.
 * @param object $context the quizinvideo context.
 */
function quizinvideo_report_default_report($context) {
    $reports = quizinvideo_report_list($context);
    return reset($reports);
}

/**
 * Generate a message saying that this quizinvideo has no questions, with a button to
 * go to the edit page, if the user has the right capability.
 * @param object $quizinvideo the quizinvideo settings.
 * @param object $cm the course_module object.
 * @param object $context the quizinvideo context.
 * @return string HTML to output.
 */
function quizinvideo_no_questions_message($quizinvideo, $cm, $context) {
    global $OUTPUT;

    $output = '';
    $output .= $OUTPUT->notification(get_string('noquestions', 'quizinvideo'));
    if (has_capability('mod/quizinvideo:manage', $context)) {
        $output .= $OUTPUT->single_button(new moodle_url('/mod/quizinvideo/edit.php',
        array('cmid' => $cm->id)), get_string('editquizinvideo', 'quizinvideo'), 'get');
    }

    return $output;
}

/**
 * Should the grades be displayed in this report. That depends on the quizinvideo
 * display options, and whether the quizinvideo is graded.
 * @param object $quizinvideo the quizinvideo settings.
 * @param context $context the quizinvideo context.
 * @return bool
 */
function quizinvideo_report_should_show_grades($quizinvideo, context $context) {
    if ($quizinvideo->timeclose && time() > $quizinvideo->timeclose) {
        $when = mod_quizinvideo_display_options::AFTER_CLOSE;
    } else {
        $when = mod_quizinvideo_display_options::LATER_WHILE_OPEN;
    }
    $reviewoptions = mod_quizinvideo_display_options::make_from_quizinvideo($quizinvideo, $when);

    return quizinvideo_has_grades($quizinvideo) &&
            ($reviewoptions->marks >= question_display_options::MARK_AND_MAX ||
            has_capability('moodle/grade:viewhidden', $context));
}
