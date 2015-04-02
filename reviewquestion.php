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
 * This page prints a review of a particular question attempt.
 * This page is expected to only be used in a popup window.
 *
 * @package   mod_quizinvideo
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once('locallib.php');

$attemptid = required_param('attempt', PARAM_INT);
$slot = required_param('slot', PARAM_INT);
$seq = optional_param('step', null, PARAM_INT);

$baseurl = new moodle_url('/mod/quizinvideo/reviewquestion.php',
        array('attempt' => $attemptid, 'slot' => $slot));
$currenturl = new moodle_url($baseurl);
if (!is_null($seq)) {
    $currenturl->param('step', $seq);
}
$PAGE->set_url($currenturl);

$attemptobj = quizinvideo_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();

$accessmanager = $attemptobj->get_access_manager(time());
$options = $attemptobj->get_display_options(true);

$PAGE->set_pagelayout('popup');
$PAGE->set_heading($attemptobj->get_course()->fullname);
$output = $PAGE->get_renderer('mod_quizinvideo');

// Check permissions.
if ($attemptobj->is_own_attempt()) {
    if (!$attemptobj->is_finished()) {
        echo $output->review_question_not_allowed(get_string('cannotreviewopen', 'quizinvideo'));
        die();
    } else if (!$options->attempt) {
        echo $output->review_question_not_allowed(
                $attemptobj->cannot_review_message());
        die();
    }

} else if (!$attemptobj->is_review_allowed()) {
    throw new moodle_quizinvideo_exception($attemptobj->get_quizinvideoobj(), 'noreviewattempt');
}

// Prepare summary informat about this question attempt.
$summarydata = array();

// quizinvideo name.
$summarydata['quizinvideoname'] = array(
    'title'   => get_string('modulename', 'quizinvideo'),
    'content' => format_string($attemptobj->get_quizinvideo_name()),
);

// Question name.
$summarydata['questionname'] = array(
    'title'   => get_string('question', 'quizinvideo'),
    'content' => $attemptobj->get_question_name($slot),
);

// Other attempts at the quizinvideo.
if ($attemptobj->has_capability('mod/quizinvideo:viewreports')) {
    $attemptlist = $attemptobj->links_to_other_attempts($baseurl);
    if ($attemptlist) {
        $summarydata['attemptlist'] = array(
            'title'   => get_string('attempts', 'quizinvideo'),
            'content' => $attemptlist,
        );
    }
}

// Timestamp of this action.
$timestamp = $attemptobj->get_question_action_time($slot);
if ($timestamp) {
    $summarydata['timestamp'] = array(
        'title'   => get_string('completedon', 'quizinvideo'),
        'content' => userdate($timestamp),
    );
}

echo $output->review_question_page($attemptobj, $slot, $seq,
        $attemptobj->get_display_options(true), $summarydata);
