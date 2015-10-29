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
 * This script lists all the instances of quizinvideo in a particular course
 *
 * @package    mod_quizinvideo
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/quizinvideo/index.php', array('id'=>$id));
if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => $coursecontext
);
$event = \mod_quizinvideo\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strquizinvideozes = get_string("modulenameplural", "quizinvideo");
$streditquestions = '';
$editqcontexts = new question_edit_contexts($coursecontext);
if ($editqcontexts->have_one_edit_tab_cap('questions')) {
    $streditquestions =
            "<form target=\"_parent\" method=\"get\" action=\"$CFG->wwwroot/question/edit.php\">
               <div>
               <input type=\"hidden\" name=\"courseid\" value=\"$course->id\" />
               <input type=\"submit\" value=\"".get_string("editquestions", "quizinvideo")."\" />
               </div>
             </form>";
}
$PAGE->navbar->add($strquizinvideozes);
$PAGE->set_title($strquizinvideozes);
$PAGE->set_button($streditquestions);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strquizinvideozes, 2);

// Get all the appropriate data.
if (!$quizinvideozes = get_all_instances_in_course("quizinvideo", $course)) {
    notice(get_string('thereareno', 'moodle', $strquizinvideozes), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the closing date header.
$showclosingheader = false;
$showfeedback = false;
foreach ($quizinvideozes as $quizinvideo) {
    if ($quizinvideo->timeclose!=0) {
        $showclosingheader=true;
    }
    if (quizinvideo_has_feedback($quizinvideo)) {
        $showfeedback=true;
    }
    if ($showclosingheader && $showfeedback) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

if ($showclosingheader) {
    array_push($headings, get_string('quizinvideocloses', 'quizinvideo'));
    array_push($align, 'left');
}

if (course_format_uses_sections($course->format)) {
    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
} else {
    array_unshift($headings, '');
}
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/quizinvideo:viewreports', $coursecontext)) {
    array_push($headings, get_string('attempts', 'quizinvideo'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_any_capability(array('mod/quizinvideo:reviewmyattempts', 'mod/quizinvideo:attempt'),
        $coursecontext)) {
    array_push($headings, get_string('grade', 'quizinvideo'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'quizinvideo'));
        array_push($align, 'left');
    }
    $showing = 'grades';

    $grades = $DB->get_records_sql_menu('
            SELECT qg.quizinvideo, qg.grade
            FROM {quizinvideo_grades} qg
            JOIN {quizinvideo} q ON q.id = qg.quizinvideo
            WHERE q.course = ? AND qg.userid = ?',
            array($course->id, $USER->id));
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
foreach ($quizinvideozes as $quizinvideo) {
    $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id);
    $context = context_module::instance($cm->id);
    $data = array();

    // Section number if necessary.
    $strsection = '';
    if ($quizinvideo->section != $currentsection) {
        if ($quizinvideo->section) {
            $strsection = $quizinvideo->section;
            $strsection = get_section_name($course, $quizinvideo->section);
        }
        if ($currentsection) {
            $learningtable->data[] = 'hr';
        }
        $currentsection = $quizinvideo->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$quizinvideo->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$quizinvideo->coursemodule\">" .
            format_string($quizinvideo->name, true) . '</a>';

    // Close date.
    if ($quizinvideo->timeclose) {
        $data[] = userdate($quizinvideo->timeclose);
    } else if ($showclosingheader) {
        $data[] = '';
    }

    if ($showing == 'stats') {
        // The $quizinvideo objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = quizinvideo_attempt_summary_link_to_reports($quizinvideo, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        $attempts = quizinvideo_get_user_attempts($quizinvideo->id, $USER->id, 'all');
        list($someoptions, $alloptions) = quizinvideo_get_combined_reviewoptions(
                $quizinvideo, $attempts, $context);

        $grade = '';
        $feedback = '';
        if ($quizinvideo->grade && array_key_exists($quizinvideo->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = quizinvideo_format_grade($quizinvideo, $grades[$quizinvideo->id]);
                $a->maxgrade = quizinvideo_format_grade($quizinvideo, $quizinvideo->grade);
                $grade = get_string('outofshort', 'quizinvideo', $a);
            }
            if ($alloptions->overallfeedback) {
                $feedback = quizinvideo_feedback_for_grade($grades[$quizinvideo->id], $quizinvideo, $context);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over quizinvideo instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
