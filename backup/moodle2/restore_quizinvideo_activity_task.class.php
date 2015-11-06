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

require_once($CFG->dirroot . '/mod/quizinvideo/backup/moodle2/restore_quizinvideo_stepslib.php');


/**
 * quizinvideo restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizinvideo_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // quizinvideo only has one structure step.
        $this->add_step(new restore_quizinvideo_activity_structure_step('quizinvideo_structure', 'quizinvideo.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('quizinvideo', array('intro'), 'quizinvideo');
        $contents[] = new restore_decode_content('quizinvideo_feedback',
                array('feedbacktext'), 'quizinvideo_feedback');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('quizinvideoVIEWBYID',
                '/mod/quizinvideo/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('quizinvideoVIEWBYQ',
                '/mod/quizinvideo/view.php?q=$1', 'quizinvideo');
        $rules[] = new restore_decode_rule('quizinvideoINDEX',
                '/mod/quizinvideo/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * quizinvideo logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('quizinvideo', 'add',
                'view.php?id={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'update',
                'view.php?id={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'view',
                'view.php?id={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'preview',
                'view.php?id={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'report',
                'report.php?id={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'editquestions',
                'view.php?id={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'delete attempt',
                'report.php?id={course_module}', '[oldattempt]');
        $rules[] = new restore_log_rule('quizinvideo', 'edit override',
                'overrideedit.php?id={quizinvideo_override}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'delete override',
                'overrides.php.php?cmid={course_module}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('quizinvideo', 'view summary',
                'summary.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'manualgrade',
                'comment.php?attempt={quizinvideo_attempt_id}&question={question}', '{quizinvideo}');
        $rules[] = new restore_log_rule('quizinvideo', 'manualgrading',
                'report.php?mode=grading&q={quizinvideo}', '{quizinvideo}');
        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'quizinvideo_attempt_id' mapping because that is the
        // one containing the quizinvideo_attempt->ids old an new for quizinvideo-attempt.
        $rules[] = new restore_log_rule('quizinvideo', 'attempt',
                'review.php?id={course_module}&attempt={quizinvideo_attempt}', '{quizinvideo}',
                null, null, 'review.php?attempt={quizinvideo_attempt}');
        // Old an new for quizinvideo-submit.
        $rules[] = new restore_log_rule('quizinvideo', 'submit',
                'review.php?id={course_module}&attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, null, 'review.php?attempt={quizinvideo_attempt_id}');
        $rules[] = new restore_log_rule('quizinvideo', 'submit',
                'review.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}');
        // Old an new for quizinvideo-review.
        $rules[] = new restore_log_rule('quizinvideo', 'review',
                'review.php?id={course_module}&attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, null, 'review.php?attempt={quizinvideo_attempt_id}');
        $rules[] = new restore_log_rule('quizinvideo', 'review',
                'review.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}');
        // Old an new for quizinvideo-start attemp.
        $rules[] = new restore_log_rule('quizinvideo', 'start attempt',
                'review.php?id={course_module}&attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, null, 'review.php?attempt={quizinvideo_attempt_id}');
        $rules[] = new restore_log_rule('quizinvideo', 'start attempt',
                'review.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}');
        // Old an new for quizinvideo-close attemp.
        $rules[] = new restore_log_rule('quizinvideo', 'close attempt',
                'review.php?id={course_module}&attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, null, 'review.php?attempt={quizinvideo_attempt_id}');
        $rules[] = new restore_log_rule('quizinvideo', 'close attempt',
                'review.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}');
        // Old an new for quizinvideo-continue attempt.
        $rules[] = new restore_log_rule('quizinvideo', 'continue attempt',
                'review.php?id={course_module}&attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, null, 'review.php?attempt={quizinvideo_attempt_id}');
        $rules[] = new restore_log_rule('quizinvideo', 'continue attempt',
                'review.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}');
        // Old an new for quizinvideo-continue attemp.
        $rules[] = new restore_log_rule('quizinvideo', 'continue attemp',
                'review.php?id={course_module}&attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, 'continue attempt', 'review.php?attempt={quizinvideo_attempt_id}');
        $rules[] = new restore_log_rule('quizinvideo', 'continue attemp',
                'review.php?attempt={quizinvideo_attempt_id}', '{quizinvideo}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('quizinvideo', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
