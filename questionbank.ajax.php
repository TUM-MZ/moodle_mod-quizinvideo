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
 * Ajax script to update the contents of the question bank dialogue.
 *
 * @package    mod_quizinvideo
 * @copyright  2014 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

list($thispageurl, $contexts, $cmid, $cm, $quizinvideo, $pagevars) =
        question_edit_setup('editq', '/mod/quizinvideo/edit.php', true);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quizinvideo->course), '*', MUST_EXIST);
require_capability('mod/quizinvideo:manage', $contexts->lowest());

// Create quizinvideo question bank view.
$questionbank = new mod_quizinvideo\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $quizinvideo);
$questionbank->set_quizinvideo_has_attempts(quizinvideo_has_attempts($quizinvideo->id));

// Output.
$output = $PAGE->get_renderer('mod_quizinvideo', 'edit');
$contents = $output->question_bank_contents($questionbank, $pagevars);
echo json_encode(array(
    'status'   => 'OK',
    'contents' => $contents,
));
