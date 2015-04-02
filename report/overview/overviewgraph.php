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
 * This file renders the quizinvideo overview graph.
 *
 * @package   quizinvideo_overview
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->libdir . '/graphlib.php');
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
require_once($CFG->dirroot . '/mod/quizinvideo/report/reportlib.php');

$quizinvideoid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$quizinvideo = $DB->get_record('quizinvideo', array('id' => $quizinvideoid));
$course = $DB->get_record('course', array('id' => $quizinvideo->course));
$cm = get_coursemodule_from_instance('quizinvideo', $quizinvideoid);

require_login($course, false, $cm);
$modcontext = context_module::instance($cm->id);
require_capability('mod/quizinvideo:viewreports', $modcontext);

if ($groupid && $groupmode = groups_get_activity_groupmode($cm)) {
    // Groups are being used.
    $groups = groups_get_activity_allowed_groups($cm);
    if (!array_key_exists($groupid, $groups)) {
        print_error('errorinvalidgroup', 'group', null, $groupid);
    }
    $group = $groups[$groupid];
    $groupusers = get_users_by_capability($modcontext,
            array('mod/quizinvideo:reviewmyattempts', 'mod/quizinvideo:attempt'),
            '', '', '', '', $group->id, '', false);
    if (!$groupusers) {
        print_error('nostudentsingroup');
    }
    $groupusers = array_keys($groupusers);
} else {
    $groupusers = array();
}

$line = new graph(800, 600);
$line->parameter['title'] = '';
$line->parameter['y_label_left'] = get_string('participants');
$line->parameter['x_label'] = get_string('grade');
$line->parameter['y_label_angle'] = 90;
$line->parameter['x_label_angle'] = 0;
$line->parameter['x_axis_angle'] = 60;

// The following two lines seem to silence notice warnings from graphlib.php.
$line->y_tick_labels = null;
$line->offset_relation = null;

// We will make size > 1 to get an overlap effect when showing groups.
$line->parameter['bar_size'] = 1;
// Don't forget to increase spacing so that graph doesn't become one big block of colour.
$line->parameter['bar_spacing'] = 10;

// Pick a sensible number of bands depending on quizinvideo maximum grade.
$bands = $quizinvideo->grade;
while ($bands > 20 || $bands <= 10) {
    if ($bands > 50) {
        $bands /= 5;
    } else if ($bands > 20) {
        $bands /= 2;
    }
    if ($bands < 4) {
        $bands *= 5;
    } else if ($bands <= 10) {
        $bands *= 2;
    }
}

// See MDL-34589. Using doubles as array keys causes problems in PHP 5.4,
// hence the explicit cast to int.
$bands = (int) ceil($bands);
$bandwidth = $quizinvideo->grade / $bands;
$bandlabels = array();
for ($i = 1; $i <= $bands; $i++) {
    $bandlabels[] = quizinvideo_format_grade($quizinvideo, ($i - 1) * $bandwidth) . ' - ' .
            quizinvideo_format_grade($quizinvideo, $i * $bandwidth);
}
$line->x_data = $bandlabels;

$line->y_format['allusers'] = array(
    'colour' => 'red',
    'bar' => 'fill',
    'shadow_offset' => 1,
    'legend' => get_string('allparticipants')
);
$line->y_data['allusers'] = quizinvideo_report_grade_bands($bandwidth, $bands, $quizinvideoid, $groupusers);

$line->y_order = array('allusers');

$ymax = max($line->y_data['allusers']);
$line->parameter['y_min_left'] = 0;
$line->parameter['y_max_left'] = $ymax;
$line->parameter['y_decimal_left'] = 0;

// Pick a sensible number of gridlines depending on max value on graph.
$gridlines = $ymax;
while ($gridlines >= 10) {
    if ($gridlines >= 50) {
        $gridlines /= 5;
    } else {
        $gridlines /= 2;
    }
}

$line->parameter['y_axis_gridlines'] = $gridlines + 1;
$line->draw();
