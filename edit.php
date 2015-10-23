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
 * Page to edit quizinvideos
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the quizinvideo does not already have student attempts
 * The left column lists all questions that have been added to the current quizinvideo.
 * The lecturer can add questions from the right hand list to the quizinvideo or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a quizinvideo:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the quizinvideo
 * add          Adds several selected questions to the quizinvideo
 * addrandom    Adds a certain number of random questions to the quizinvideo
 * repaginate   Re-paginates the quizinvideo
 * delete       Removes a question from the quizinvideo
 * savechanges  Saves the order and grades for questions in the quizinvideo
 *
 * @package    mod_quizinvideo
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
require_once($CFG->dirroot . '/mod/quizinvideo/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quizinvideo, $pagevars) =
        question_edit_setup('editq', '/mod/quizinvideo/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$quizinvideohasattempts = quizinvideo_has_attempts($quizinvideo->id);

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quizinvideo->course), '*', MUST_EXIST);
$quizinvideoobj = new quizinvideo($quizinvideo, $cm, $course);
$structure = $quizinvideoobj->get_structure();

// You need mod/quizinvideo:manage in addition to question capabilities to access this page.
require_capability('mod/quizinvideo:manage', $contexts->lowest());

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'quizinvideoid' => $quizinvideo->id
    )
);
$event = \mod_quizinvideo\event\edit_page_viewed::create($params);
$event->trigger();

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the quizinvideo.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $quizinvideo->questionsperpage, PARAM_INT);
    quizinvideo_repaginate_questions($quizinvideo->id, $questionsperpage );
    quizinvideo_delete_previews($quizinvideo);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current quizinvideo.
    $structure->check_can_be_edited();
    quizinvideo_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    quizinvideo_add_quizinvideo_question($addquestion, $quizinvideo, $addonpage);
    quizinvideo_delete_previews($quizinvideo);
    quizinvideo_update_sumgrades($quizinvideo);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current quizinvideo.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            quizinvideo_require_question_use($key);
            quizinvideo_add_quizinvideo_question($key, $quizinvideo, $addonpage);
        }
    }
    quizinvideo_delete_previews($quizinvideo);
    quizinvideo_update_sumgrades($quizinvideo);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the quizinvideo.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    quizinvideo_add_random_questions($quizinvideo, $addonpage, $categoryid, $randomcount, $recurse);

    quizinvideo_delete_previews($quizinvideo);
    quizinvideo_update_sumgrades($quizinvideo);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', -1, PARAM_RAW));
    if ($maxgrade >= 0) {
        quizinvideo_set_grade($maxgrade, $quizinvideo);
        quizinvideo_update_all_final_grades($quizinvideo);
        quizinvideo_update_grades($quizinvideo, 0, true);
    }

    redirect($afteractionurl);
}

// Get the question bank view.
$questionbank = new mod_quizinvideo\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $quizinvideo);
$questionbank->set_quizinvideo_has_attempts($quizinvideohasattempts);
$questionbank->process_actions($thispageurl, $cm);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-quizinvideo-edit');

$output = $PAGE->get_renderer('mod_quizinvideo', 'edit');

$PAGE->set_title(get_string('editingquizinvideox', 'quizinvideo', format_string($quizinvideo->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_quizinvideo_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

echo "<style>";
echo '@font-face {';
echo '    font-family: VideoJS;';
echo '    src: url(/mod/quizinvideo/videojs/font/VideoJS.eot);';
echo '    src: url(/mod/quizinvideo/videojs/font/VideoJS.eot?#iefix) format("embedded-opentype"), url(/mod/quizinvideo/videojs/font/VideoJS.woff) format("woff"), url(/mod/quizinvideo/videojs/font/VideoJS.ttf) format("truetype"), url(/mod/quizinvideo/videojs/font/VideoJS.svg#icomoon) format("svg");';
echo '    font-weight: 400;';
echo '    font-style: normal';
echo '}';
echo '</style>';

// Initialise the JavaScript.
$quizinvideoeditconfig = new stdClass();
$quizinvideoeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$quizinvideoeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {quizinvideo_slots}
     WHERE quizinvideoid = ?", array($quizinvideo->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $quizinvideoeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('quizinvideo_edit_config', $quizinvideoeditconfig);
$PAGE->requires->js('/question/qengine.js');

// Questions wrapper start.
echo html_writer::start_tag('div', array('class' => 'mod-quizinvideo-edit-content'));

echo $output->edit_page($quizinvideoobj, $structure, $contexts, $thispageurl, $pagevars);

// Questions wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
