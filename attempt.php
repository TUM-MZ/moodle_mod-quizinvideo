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
 * This script displays a particular page of a quizinvideo attempt that is in progress.
 *
 * @package   mod_quizinvideo
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
    redirect($CFG->wwwroot . '/mod/quizinvideo/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey());
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
    if (!$cm = get_coursemodule_from_instance('quizinvideo', $qid)) {
        print_error('invalidquizinvideoid', 'quizinvideo');
    }
    redirect(new moodle_url('/mod/quizinvideo/startattempt.php',
            array('cmid' => $cm->id, 'sesskey' => sesskey())));
}

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$attemptobj = quizinvideo_attempt::create($attemptid);
//$page = $attemptobj->force_page_number_into_range($page);
$PAGE->set_url($attemptobj->attempt_url(null));

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/quizinvideo:viewreports')) {
        redirect($attemptobj->review_url(null, -1, true));
    } else {
        throw new moodle_quizinvideo_exception($attemptobj->get_quizinvideoobj(), 'notyourattempt');
    }
}

// render course navigation block.
navigation_node::override_active_url($attemptobj->start_attempt_url());


// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_quizinvideo');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'quizinvideo', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null));
}


// Log this page view.
$params = array(
    'objectid' => $attemptid,
    'relateduserid' => $attemptobj->get_userid(),
    'courseid' => $attemptobj->get_courseid(),
    'context' => context_module::instance($attemptobj->get_cmid()),
    'other' => array(
        'quizinvideoid' => $attemptobj->get_quizinvideoid()
    )
);
$event = \mod_quizinvideo\event\attempt_viewed::create($params);
$event->add_record_snapshot('quizinvideo_attempts', $attemptobj->get_attempt());
$event->trigger();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots();

// Check.
if (empty($slots)) {
    throw new moodle_quizinvideo_exception($attemptobj->get_quizinvideoobj(), 'noquestionsfound');
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions();
$PAGE->requires->js_init_call('M.mod_quizinvideo.init_video', null, false, quizinvideo_get_js_module());

// Arrange for the navigation to be displayed in the first region on the page.
if($attemptobj->is_preview_user()){
    $navbc = $attemptobj->get_navigation_panel($output, 'quizinvideo_attempt_nav_panel', $page);
    $regions = $PAGE->blocks->get_regions();
    $PAGE->blocks->add_fake_block($navbc, reset($regions));
}


$title = get_string('attempt', 'quizinvideo', $attemptobj->get_attempt_number());
$headtags = $attemptobj->get_html_head_contributions();
$PAGE->set_title($attemptobj->get_quizinvideo_name());
$PAGE->set_heading($attemptobj->get_course()->fullname);


echo $output->attempt_page($attemptobj, $accessmanager, $messages, $id);
