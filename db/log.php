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
 * Definition of log events for the quizinvideo module.
 *
 * @package    mod_quizinvideo
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'quizinvideo', 'action'=>'add', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'update', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'view', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'report', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'attempt', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'submit', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'review', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'editquestions', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'preview', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'start attempt', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'close attempt', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'continue attempt', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'edit override', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'delete override', 'mtable'=>'quizinvideo', 'field'=>'name'),
    array('module'=>'quizinvideo', 'action'=>'view summary', 'mtable'=>'quizinvideo', 'field'=>'name'),
);