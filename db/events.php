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
 * Add event handlers for the quizinvideo
 *
 * @package    mod_quizinvideo
 * @category   event
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$observers = array(

    // Handle group events, so that open quizinvideo attempts with group overrides get updated check times.
    array(
        'eventname' => '\core\event\course_reset_started',
        'callback' => '\mod_quizinvideo\group_observers::course_reset_started',
    ),
    array(
        'eventname' => '\core\event\course_reset_ended',
        'callback' => '\mod_quizinvideo\group_observers::course_reset_ended',
    ),
    array(
        'eventname' => '\core\event\group_deleted',
        'callback' => '\mod_quizinvideo\group_observers::group_deleted'
    ),
    array(
        'eventname' => '\core\event\group_member_added',
        'callback' => '\mod_quizinvideo\group_observers::group_member_added',
    ),
    array(
        'eventname' => '\core\event\group_member_removed',
        'callback' => '\mod_quizinvideo\group_observers::group_member_removed',
    ),

    // Handle our own \mod_quizinvideo\event\attempt_submitted event, as a way to
    // send confirmation messages asynchronously.
    array(
        'eventname' => '\mod_quizinvideo\event\attempt_submitted',
        'includefile'     => '/mod/quizinvideo/locallib.php',
        'callback' => 'quizinvideo_attempt_submitted_handler',
        'internal' => false
    ),
);
