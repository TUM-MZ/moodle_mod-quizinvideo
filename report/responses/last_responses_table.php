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
 * This file defines the quizinvideo responses table for showing last try at question.
 *
 * @package   quizinvideo_responses
 * @copyright 2008 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/report/attemptsreport_table.php');


/**
 * This is a table subclass for displaying the quizinvideo responses report.
 *
 * @copyright 2008 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizinvideo_last_responses_table extends quizinvideo_attempts_report_table {

    /**
     * Constructor
     * @param object $quizinvideo
     * @param context $context
     * @param string $qmsubselect
     * @param quizinvideo_responses_options $options
     * @param array $groupstudents
     * @param array $students
     * @param array $questions
     * @param moodle_url $reporturl
     */
    public function __construct($quizinvideo, $context, $qmsubselect, quizinvideo_responses_options $options,
            $groupstudents, $students, $questions, $reporturl) {
        parent::__construct('mod-quizinvideo-report-responses-report', $quizinvideo, $context,
                $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
    }

    public function build_table() {
        if (!$this->rawdata) {
            return;
        }

        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    public function col_sumgrades($attempt) {
        if ($attempt->state != quizinvideo_attempt::FINISHED) {
            return '-';
        }

        $grade = quizinvideo_rescale_grade($attempt->sumgrades, $this->quizinvideo);
        if ($this->is_downloading()) {
            return $grade;
        }

        $gradehtml = '<a href="review.php?q=' . $this->quizinvideo->id . '&amp;attempt=' .
                $attempt->attempt . '">' . $grade . '</a>';
        return $gradehtml;
    }

    public function data_col($slot, $field, $attempt) {
        if ($attempt->usageid == 0) {
            return '-';
        }

        $value = $this->field_from_extra_data($attempt, $slot, $field);

        if (is_null($value)) {
            $summary = '-';
        } else {
            $summary = trim($value);
        }

        if ($this->is_downloading() && $this->is_downloading() != 'xhtml') {
            return $summary;
        }
        $summary = s($summary);

        if ($this->is_downloading() || $field != 'responsesummary') {
            return $summary;
        }

        return $this->make_review_link($summary, $attempt, $slot);
    }

    /**
     * Column text from the extra data loaded in load_extra_data(), before html formatting etc.
     *
     * @param object $attempt
     * @param int $slot
     * @param string $field
     * @return string
     */
    protected function field_from_extra_data($attempt, $slot, $field) {
        if (!isset($this->lateststeps[$attempt->usageid][$slot])) {
            return '-';
        }
        $stepdata = $this->lateststeps[$attempt->usageid][$slot];

        if (property_exists($stepdata, $field . 'full')) {
            $value = $stepdata->{$field . 'full'};
        } else {
            $value = $stepdata->$field;
        }
        return $value;
    }

    public function other_cols($colname, $attempt) {
        if (preg_match('/^question(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionsummary', $attempt);

        } else if (preg_match('/^response(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'responsesummary', $attempt);

        } else if (preg_match('/^right(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'rightanswer', $attempt);

        } else {
            return null;
        }
    }

    protected function requires_extra_data() {
        return true;
    }

    protected function is_latest_step_column($column) {
        if (preg_match('/^(?:question|response|right)([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Get any fields that might be needed when sorting on date for a particular slot.
     * @param int    $slot  the slot for the column we want.
     * @param string $alias the table alias for latest state information relating to that slot.
     * @return string sql fragment to alias fields.
     */
    protected function get_required_latest_state_fields($slot, $alias) {
        global $DB;
        $sortableresponse = $DB->sql_order_by_text("{$alias}.questionsummary");
        if ($sortableresponse === "{$alias}.questionsummary") {
            // Can just order by text columns. No complexity needed.
            return "{$alias}.questionsummary AS question{$slot},
                    {$alias}.rightanswer AS right{$slot},
                    {$alias}.responsesummary AS response{$slot}";
        } else {
            // Work-around required.
            return $DB->sql_order_by_text("{$alias}.questionsummary") . " AS question{$slot},
                    {$alias}.questionsummary AS question{$slot}full,
                    " . $DB->sql_order_by_text("{$alias}.rightanswer") . " AS right{$slot},
                    {$alias}.rightanswer AS right{$slot}full,
                    " . $DB->sql_order_by_text("{$alias}.responsesummary") . " AS response{$slot},
                    {$alias}.responsesummary AS response{$slot}full";
        }
    }
}
