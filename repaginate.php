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
 * Rest endpoint for ajax editing for paging operations on the quizinvideo structure.
 *
 * @package   mod_quizinvideo
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$quizinvideoid = required_param('quizinvideoid', PARAM_INT);
$slotnumber = required_param('slot', PARAM_INT);
$repagtype = required_param('repag', PARAM_INT);

require_sesskey();
$quizinvideoobj = quizinvideo::create($quizinvideoid);
require_login($quizinvideoobj->get_course(), false, $quizinvideoobj->get_cm());
require_capability('mod/quizinvideo:manage', $quizinvideoobj->get_context());
if (quizinvideo_has_attempts($quizinvideoid)) {
    $reportlink = quizinvideo_attempt_summary_link_to_reports($quizinvideoobj->get_quizinvideo(),
                    $quizinvideoobj->get_cm(), $quizinvideoobj->get_context());
    throw new \moodle_exception('cannoteditafterattempts', 'quizinvideo',
            new moodle_url('/mod/quizinvideo/edit.php', array('cmid' => $cmid)), $reportlink);
}

$slotnumber++;
$repage = new \mod_quizinvideo\repaginate($quizinvideoid);
$repage->repaginate_slots($slotnumber, $repagtype);

$structure = $quizinvideoobj->get_structure();
$slots = $structure->refresh_page_numbers_and_update_db($structure->get_quizinvideo());

redirect(new moodle_url('edit.php', array('cmid' => $quizinvideoobj->get_cmid())));
