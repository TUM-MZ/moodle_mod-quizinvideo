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
 * @package    mod_quizinvideo
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Structure step to restore one quizinvideo activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizinvideo_activity_structure_step extends restore_questions_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $quizinvideo = new restore_path_element('quizinvideo', '/activity/quizinvideo');
        $paths[] = $quizinvideo;

        // A chance for access subplugings to set up their quizinvideo data.
        $this->add_subplugin_structure('quizinvideoaccess', $quizinvideo);

        $paths[] = new restore_path_element('quizinvideo_question_instance',
                '/activity/quizinvideo/question_instances/question_instance');
        $paths[] = new restore_path_element('quizinvideo_feedback', '/activity/quizinvideo/feedbacks/feedback');
        $paths[] = new restore_path_element('quizinvideo_override', '/activity/quizinvideo/overrides/override');

        if ($userinfo) {
            $paths[] = new restore_path_element('quizinvideo_grade', '/activity/quizinvideo/grades/grade');

            if ($this->task->get_old_moduleversion() > 2011010100) {
                // Restoring from a version 2.1 dev or later.
                // Process the new-style attempt data.
                $quizinvideoattempt = new restore_path_element('quizinvideo_attempt',
                        '/activity/quizinvideo/attempts/attempt');
                $paths[] = $quizinvideoattempt;

                // Add states and sessions.
                $this->add_question_usages($quizinvideoattempt, $paths);

                // A chance for access subplugings to set up their attempt data.
                $this->add_subplugin_structure('quizinvideoaccess', $quizinvideoattempt);

            } else {
                // Restoring from a version 2.0.x+ or earlier.
                // Upgrade the legacy attempt data.
                $quizinvideoattempt = new restore_path_element('quizinvideo_attempt_legacy',
                        '/activity/quizinvideo/attempts/attempt',
                        true);
                $paths[] = $quizinvideoattempt;
                $this->add_legacy_question_attempt_data($quizinvideoattempt, $paths);
            }
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_quizinvideo($data) {
        global $CFG, $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (property_exists($data, 'questions')) {
            // Needed by {@link process_quizinvideo_attempt_legacy}, in which case it will be present.
            $this->oldquizinvideolayout = $data->questions;
        }

        // The setting quizinvideo->attempts can come both in data->attempts and
        // data->attempts_number, handle both. MDL-26229.
        if (isset($data->attempts_number)) {
            $data->attempts = $data->attempts_number;
            unset($data->attempts_number);
        }

        // The old optionflags and penaltyscheme from 2.0 need to be mapped to
        // the new preferredbehaviour. See MDL-20636.
        if (!isset($data->preferredbehaviour)) {
            if (empty($data->optionflags)) {
                $data->preferredbehaviour = 'deferredfeedback';
            } else if (empty($data->penaltyscheme)) {
                $data->preferredbehaviour = 'adaptivenopenalty';
            } else {
                $data->preferredbehaviour = 'adaptive';
            }
            unset($data->optionflags);
            unset($data->penaltyscheme);
        }

        // The old review column from 2.0 need to be split into the seven new
        // review columns. See MDL-20636.
        if (isset($data->review)) {
            require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');

            if (!defined('quizinvideo_OLD_IMMEDIATELY')) {
                define('quizinvideo_OLD_IMMEDIATELY', 0x3c003f);
                define('quizinvideo_OLD_OPEN',        0x3c00fc0);
                define('quizinvideo_OLD_CLOSED',      0x3c03f000);

                define('quizinvideo_OLD_RESPONSES',        1*0x1041);
                define('quizinvideo_OLD_SCORES',           2*0x1041);
                define('quizinvideo_OLD_FEEDBACK',         4*0x1041);
                define('quizinvideo_OLD_ANSWERS',          8*0x1041);
                define('quizinvideo_OLD_SOLUTIONS',       16*0x1041);
                define('quizinvideo_OLD_GENERALFEEDBACK', 32*0x1041);
                define('quizinvideo_OLD_OVERALLFEEDBACK',  1*0x4440000);
            }

            $oldreview = $data->review;

            $data->reviewattempt =
                    mod_quizinvideo_display_options::DURING |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_RESPONSES ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_RESPONSES ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_RESPONSES ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);

            $data->reviewcorrectness =
                    mod_quizinvideo_display_options::DURING |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_SCORES ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_SCORES ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_SCORES ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);

            $data->reviewmarks =
                    mod_quizinvideo_display_options::DURING |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_SCORES ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_SCORES ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_SCORES ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);

            $data->reviewspecificfeedback =
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_FEEDBACK ?
                            mod_quizinvideo_display_options::DURING : 0) |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_FEEDBACK ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_FEEDBACK ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_FEEDBACK ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);

            $data->reviewgeneralfeedback =
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_GENERALFEEDBACK ?
                            mod_quizinvideo_display_options::DURING : 0) |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_GENERALFEEDBACK ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_GENERALFEEDBACK ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_GENERALFEEDBACK ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);

            $data->reviewrightanswer =
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_ANSWERS ?
                            mod_quizinvideo_display_options::DURING : 0) |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_ANSWERS ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_ANSWERS ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_ANSWERS ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);

            $data->reviewoverallfeedback =
                    0 |
                    ($oldreview & quizinvideo_OLD_IMMEDIATELY & quizinvideo_OLD_OVERALLFEEDBACK ?
                            mod_quizinvideo_display_options::IMMEDIATELY_AFTER : 0) |
                    ($oldreview & quizinvideo_OLD_OPEN & quizinvideo_OLD_OVERALLFEEDBACK ?
                            mod_quizinvideo_display_options::LATER_WHILE_OPEN : 0) |
                    ($oldreview & quizinvideo_OLD_CLOSED & quizinvideo_OLD_OVERALLFEEDBACK ?
                            mod_quizinvideo_display_options::AFTER_CLOSE : 0);
        }

        // The old popup column from from <= 2.1 need to be mapped to
        // the new browsersecurity. See MDL-29627.
        if (!isset($data->browsersecurity)) {
            if (empty($data->popup)) {
                $data->browsersecurity = '-';
            } else if ($data->popup == 1) {
                $data->browsersecurity = 'securewindow';
            } else if ($data->popup == 2) {
                $data->browsersecurity = 'safebrowser';
            } else {
                $data->preferredbehaviour = '-';
            }
            unset($data->popup);
        }

        if (!isset($data->overduehandling)) {
            $data->overduehandling = get_config('quizinvideo', 'overduehandling');
        }

        // Insert the quizinvideo record.
        $newitemid = $DB->insert_record('quizinvideo', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_quizinvideo_question_instance($data) {
        global $DB;

        $data = (object)$data;

        // Backwards compatibility for old field names (MDL-43670).
        if (!isset($data->questionid) && isset($data->question)) {
            $data->questionid = $data->question;
        }
        if (!isset($data->maxmark) && isset($data->grade)) {
            $data->maxmark = $data->grade;
        }

        if (!property_exists($data, 'slot')) {
            $page = 1;
            $slot = 1;
            foreach (explode(',', $this->oldquizinvideolayout) as $item) {
                if ($item == 0) {
                    $page += 1;
                    continue;
                }
                if ($item == $data->questionid) {
                    $data->slot = $slot;
                    $data->page = $page;
                    break;
                }
                $slot += 1;
            }
        }

        if (!property_exists($data, 'slot')) {
            // There was a question_instance in the backup file for a question
            // that was not acutally in the quizinvideo. Drop it.
            $this->log('question ' . $data->questionid . ' was associated with quizinvideo ' .
                    $this->get_new_parentid('quizinvideo') . ' but not actually used. ' .
                    'The instance has been ignored.', backup::LOG_INFO);
            return;
        }

        $data->quizinvideoid = $this->get_new_parentid('quizinvideo');
        $data->questionid = $this->get_mappingid('question', $data->questionid);

        $DB->insert_record('quizinvideo_slots', $data);
    }

    protected function process_quizinvideo_feedback($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->quizinvideoid = $this->get_new_parentid('quizinvideo');

        $newitemid = $DB->insert_record('quizinvideo_feedback', $data);
        $this->set_mapping('quizinvideo_feedback', $oldid, $newitemid, true); // Has related files.
    }

    protected function process_quizinvideo_override($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Based on userinfo, we'll restore user overides or no.
        $userinfo = $this->get_setting_value('userinfo');

        // Skip user overrides if we are not restoring userinfo.
        if (!$userinfo && !is_null($data->userid)) {
            return;
        }

        $data->quizinvideo = $this->get_new_parentid('quizinvideo');

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        $newitemid = $DB->insert_record('quizinvideo_overrides', $data);

        // Add mapping, restore of logs needs it.
        $this->set_mapping('quizinvideo_override', $oldid, $newitemid);
    }

    protected function process_quizinvideo_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->quizinvideo = $this->get_new_parentid('quizinvideo');

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->grade = $data->gradeval;

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('quizinvideo_grades', $data);
    }

    protected function process_quizinvideo_attempt($data) {
        $data = (object)$data;

        $data->quizinvideo = $this->get_new_parentid('quizinvideo');
        $data->attempt = $data->attemptnum;

        $data->userid = $this->get_mappingid('user', $data->userid);

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (!empty($data->timecheckstate)) {
            $data->timecheckstate = $this->apply_date_offset($data->timecheckstate);
        } else {
            $data->timecheckstate = 0;
        }

        // Deals with up-grading pre-2.3 back-ups to 2.3+.
        if (!isset($data->state)) {
            if ($data->timefinish > 0) {
                $data->state = 'finished';
            } else {
                $data->state = 'inprogress';
            }
        }

        // The data is actually inserted into the database later in inform_new_usage_id.
        $this->currentquizinvideoattempt = clone($data);
    }

    protected function process_quizinvideo_attempt_legacy($data) {
        global $DB;

        $this->process_quizinvideo_attempt($data);

        $quizinvideo = $DB->get_record('quizinvideo', array('id' => $this->get_new_parentid('quizinvideo')));
        $quizinvideo->oldquestions = $this->oldquizinvideolayout;
        $this->process_legacy_quizinvideo_attempt_data($data, $quizinvideo);
    }

    protected function inform_new_usage_id($newusageid) {
        global $DB;

        $data = $this->currentquizinvideoattempt;

        $oldid = $data->id;
        $data->uniqueid = $newusageid;

        $newitemid = $DB->insert_record('quizinvideo_attempts', $data);

        // Save quizinvideo_attempt->id mapping, because logs use it.
        $this->set_mapping('quizinvideo_attempt', $oldid, $newitemid, false);
    }

    protected function after_execute() {
        parent::after_execute();
        // Add quizinvideo related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_quizinvideo', 'intro', null);
        // Add feedback related files, matching by itemname = 'quizinvideo_feedback'.
        $this->add_related_files('mod_quizinvideo', 'feedback', 'quizinvideo_feedback');
    }
}
