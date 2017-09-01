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
 * This page is the entry page into the quizinvideo UI. Displays information about the
 * quizinvideo to students and teachers, and lets students see their previous attempts.
 *
 * @package   mod_quizinvideo
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/mod/quizinvideo/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or ...
$q = optional_param('q',  0, PARAM_INT);  // quizinvideo ID.

if ($id) {
    if (!$cm = get_coursemodule_from_id('quizinvideo', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
} else {
    if (!$quizinvideo = $DB->get_record('quizinvideo', array('id' => $q))) {
        print_error('invalidquizinvideoid', 'quizinvideo');
    }
    if (!$course = $DB->get_record('course', array('id' => $quizinvideo->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quizinvideo", $quizinvideo->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

// Check login and get context.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quizinvideo:view', $context);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/quizinvideo:attempt', $context);
$canreviewmine = has_capability('mod/quizinvideo:reviewmyattempts', $context);
$canpreview = has_capability('mod/quizinvideo:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$quizinvideoobj = quizinvideo::create($cm->instance, $USER->id);
$accessmanager = new quizinvideo_access_manager($quizinvideoobj, $timenow,
    has_capability('mod/quizinvideo:ignoretimelimits', $context, null, false));
$quizinvideo = $quizinvideoobj->get_quizinvideo();

// Log this request.
$params = array(
    'objectid' => $quizinvideo->id,
    'context' => $context
);
$event = \mod_quizinvideo\event\course_module_viewed::create($params);
$event->add_record_snapshot('quizinvideo', $quizinvideo);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Initialize $PAGE, compute blocks.
$PAGE->set_url('/mod/quizinvideo/view.php', array('id' => $cm->id));

// Create view object which collects all the information the renderer will need.
$viewobj = new mod_quizinvideo_view_object();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine;

// Get this user's attempts.
$attempts = quizinvideo_get_user_attempts($quizinvideo->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
if ($unfinishedattempt = quizinvideo_get_user_attempt_unfinished($quizinvideo->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $quizinvideoobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == quizinvideo_attempt::IN_PROGRESS ||
        $unfinishedattempt->state == quizinvideo_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);

$viewobj->attempts = $attempts;
$viewobj->attemptobjs = array();
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new quizinvideo_attempt($attempt, $quizinvideo, $cm, $course, false);
}

// Work out the final grade, checking whether it was overridden in the gradebook.
if (!$canpreview) {
    $mygrade = quizinvideo_get_best_grade($quizinvideo, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the quizinvideo don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = quizinvideo_rescale_grade($lastfinishedattempt->sumgrades, $quizinvideo, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$grading_info = grade_get_grades($course->id, 'mod', 'quizinvideo', $quizinvideo->id, $USER->id);
if (!empty($grading_info->items)) {
    $item = $grading_info->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}

$title = $course->shortname . ': ' . format_string($quizinvideo->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$output = $PAGE->get_renderer('mod_quizinvideo');

// Print table with existing attempts.
if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = quizinvideo_get_combined_reviewoptions($quizinvideo, $attempts, $context);

    $viewobj->attemptcolumn  = $quizinvideo->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
        quizinvideo_has_grades($quizinvideo);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($quizinvideo->grade != $quizinvideo->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = quizinvideo_has_feedback($quizinvideo) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
    !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/quizinvideo:manage', $context);
$viewobj->editurl = new moodle_url('/mod/quizinvideo/edit.php', array('cmid' => $cm->id));
$viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$viewobj->startattempturl = $quizinvideoobj->start_attempt_url();
$viewobj->startattemptwarning = $quizinvideoobj->confirm_start_attempt_message($unfinished);
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this quizinvideo.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($quizinvideo->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'quizinvideo',
        quizinvideo_get_grading_option_name($quizinvideo->grademethod));
}

// Determine wheter a start attempt button should be displayed.
$viewobj->quizinvideohasquestions = $quizinvideoobj->has_questions();
$viewobj->preventmessages = array();
if (!$viewobj->quizinvideohasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptquizinvideo', 'quizinvideo');
        } else if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'quizinvideo');
        }

    } else {
        if ($canattempt) {
            $viewobj->buttontext = get_string('attemptquizinvideonow', 'quizinvideo');

        } else if ($canpreview) {
            $viewobj->buttontext = get_string('previewquizinvideonow', 'quizinvideo');
        }
    }

    // If, so far, we think a button should be printed, so check if they will be
    // allowed to access it.
    if ($viewobj->buttontext) {
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt
            && $viewobj->preventmessages = $viewobj->accessmanager->prevent_access()) {
            $viewobj->buttontext = '';
        }
    }
}

$viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
    course_get_format($course)->has_view_page());

echo $OUTPUT->header();

if (isguestuser()) {
    // Guests can't do a quizinvideo, so offer them a choice of logging in or going back.
    echo $output->view_page_guest($course, $quizinvideo, $cm, $context, $viewobj->infomessages);
} else if (!isguestuser() && !($canattempt || $canpreview
        || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    echo $output->view_page_notenrolled($course, $quizinvideo, $cm, $context, $viewobj->infomessages);
} else {
//    echo $output->view_page($course, $quizinvideo, $cm, $context, $viewobj);
    $accessmanager = $quizinvideoobj->get_access_manager($timenow);
    $messages = $accessmanager->prevent_access();
    if (!$quizinvideoobj->is_preview_user() && $messages) {
        if ($lastattempt = end($viewobj->attemptobjs)) {
            redirect($quizinvideoobj->review_url($lastattempt->get_attempt()->id, true));
        } else {
            echo $OUTPUT->header();
            print_error('attempterror', 'quizinvideo', $quizinvideoobj->view_url(),
                $output->access_messages($messages));
        }
    } else {
        if ($lastattempt = end($viewobj->attemptobjs)) {
            $lastattempt->set_state();
        }
        // redirect($quizinvideoobj->start_attempt_url());
        echo $output->view_page($course, $quizinvideo, $cm, $context, $viewobj);
    }
}

echo $OUTPUT->footer();
