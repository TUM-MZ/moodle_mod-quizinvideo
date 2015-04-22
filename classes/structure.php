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
 * Defines the \mod_quizinvideo\structure class.
 *
 * @package   mod_quizinvideo
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quizinvideo;
defined('MOODLE_INTERNAL') || die();

/**
 * quizinvideo structure class.
 *
 * The structure of the quizinvideo. That is, which questions it is built up
 * from. This is used on the Edit quizinvideo page (edit.php) and also when
 * starting an attempt at the quizinvideo (startattempt.php). Once an attempt
 * has been started, then the attempt holds the specific set of questions
 * that that student should answer, and we no longer use this class.
 *
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structure {
    /** @var \quizinvideo the quizinvideo this is the structure of. */
    protected $quizinvideoobj = null;

    /**
     * @var \stdClass[] the questions in this quizinvideo. Contains the row from the questions
     * table, with the data from the quizinvideo_slots table added, and also question_categories.contextid.
     */
    protected $questions = array();

    /** @var \stdClass[] quizinvideo_slots.id => the quizinvideo_slots rows for this quizinvideo, agumented by sectionid. */
    protected $slots = array();

    /** @var \stdClass[] quizinvideo_slots.slot => the quizinvideo_slots rows for this quizinvideo, agumented by sectionid. */
    protected $slotsinorder = array();

    /**
     * @var \stdClass[] currently a dummy. Holds data that will match the
     * quizinvideo_sections, once it exists.
     */
    protected $sections = array();

    /** @var bool caches the results of can_be_edited. */
    protected $canbeedited = null;

    /**
     * Create an instance of this class representing an empty quizinvideo.
     * @return structure
     */
    public static function create() {
        return new self();
    }

    /**
     * Create an instance of this class representing the structure of a given quizinvideo.
     * @param \quizinvideo $quizinvideoobj the quizinvideo.
     * @return structure
     */
    public static function create_for_quizinvideo($quizinvideoobj) {
        $structure = self::create();
        $structure->quizinvideoobj = $quizinvideoobj;
        $structure->populate_structure($quizinvideoobj->get_quizinvideo());
        return $structure;
    }

    /**
     * Whether there are any questions in the quizinvideo.
     * @return bool true if there is at least one question in the quizinvideo.
     */
    public function has_questions() {
        return !empty($this->questions);
    }

    /**
     * Get the number of questions in the quizinvideo.
     * @return int the number of questions in the quizinvideo.
     */
    public function get_question_count() {
        return count($this->questions);
    }

    /**
     * Get the information about the question with this id.
     * @param int $questionid The question id.
     * @return \stdClass the data from the questions table, augmented with
     * question_category.contextid, and the quizinvideo_slots data for the question in this quizinvideo.
     */
    public function get_question_by_id($questionid) {
        return $this->questions[$questionid];
    }

    /**
     * Get the information about the question in a given slot.
     * @param int $slotnumber the index of the slot in question.
     * @return \stdClass the data from the questions table, augmented with
     * question_category.contextid, and the quizinvideo_slots data for the question in this quizinvideo.
     */
    public function get_question_in_slot($slotnumber) {
        return $this->questions[$this->slotsinorder[$slotnumber]->questionid];
    }

    /**
     * Get the course module id of the quizinvideo.
     * @return int the course_modules.id for the quizinvideo.
     */
    public function get_cmid() {
        return $this->quizinvideoobj->get_cmid();
    }

    /**
     * Get id of the quizinvideo.
     * @return int the quizinvideo.id for the quizinvideo.
     */
    public function get_quizinvideoid() {
        return $this->quizinvideoobj->get_quizinvideoid();
    }

    /**
     * Get the quizinvideo object.
     * @return \stdClass the quizinvideo settings row from the database.
     */
    public function get_quizinvideo() {
        return $this->quizinvideoobj->get_quizinvideo();
    }

    /**
     * Whether the question in the quizinvideo are shuffled for each attempt.
     * @return bool true if the questions are shuffled.
     */
    public function is_shuffled() {
        return $this->quizinvideoobj->get_quizinvideo()->shufflequestions;
    }

    /**
     * quizinvideos can only be repaginated if they have not been attempted, the
     * questions are not shuffled, and there are two or more questions.
     * @return bool whether this quizinvideo can be repaginated.
     */
    public function can_be_repaginated() {
        return !$this->is_shuffled() && $this->can_be_edited()
                && $this->get_question_count() >= 2;
    }

    /**
     * quizinvideos can only be edited if they have not been attempted.
     * @return bool whether the quizinvideo can be edited.
     */
    public function can_be_edited() {
        if ($this->canbeedited === null) {
            $this->canbeedited = !quizinvideo_has_attempts($this->quizinvideoobj->get_quizinvideoid());
        }
        return $this->canbeedited;
    }

    /**
     * This quizinvideo can only be edited if they have not been attempted.
     * Throw an exception if this is not the case.
     */
    public function check_can_be_edited() {
        if (!$this->can_be_edited()) {
            $reportlink = quizinvideo_attempt_summary_link_to_reports($this->get_quizinvideo(),
                    $this->quizinvideoobj->get_cm(), $this->quizinvideoobj->get_context());
            throw new \moodle_exception('cannoteditafterattempts', 'quizinvideo',
                    new \moodle_url('/mod/quizinvideo/edit.php', array('cmid' => $this->get_cmid())), $reportlink);
        }
    }

    /**
     * How many questions are allowed per page in the quizinvideo.
     * This setting controls how frequently extra page-breaks should be inserted
     * automatically when questions are added to the quizinvideo.
     * @return int the number of questions that should be on each page of the
     * quizinvideo by default.
     */
    public function get_questions_per_page() {
        return $this->quizinvideoobj->get_quizinvideo()->questionsperpage;
    }

    /**
     * Get quizinvideo slots.
     * @return \stdClass[] the slots in this quizinvideo.
     */
    public function get_slots() {
        return $this->slots;
    }

    /**
     * Is this slot the first one on its page?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the first one on its page.
     */
    public function is_first_slot_on_page($slotnumber) {
        if ($slotnumber == 1) {
            return true;
        }
        return $this->slotsinorder[$slotnumber]->page != $this->slotsinorder[$slotnumber - 1]->page;
    }

    /**
     * Is this slot the last one on its page?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the last one on its page.
     */
    public function is_last_slot_on_page($slotnumber) {
        if (!isset($this->slotsinorder[$slotnumber + 1])) {
            return true;
        }
        return $this->slotsinorder[$slotnumber]->page != $this->slotsinorder[$slotnumber + 1]->page;
    }

    /**
     * Is this slot the last one in the quizinvideo?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the last one in the quizinvideo.
     */
    public function is_last_slot_in_quizinvideo($slotnumber) {
        end($this->slotsinorder);
        return $slotnumber == key($this->slotsinorder);
    }

    /**
     * Get the final slot in the quizinvideo.
     * @return \stdClass the quizinvideo_slots for for the final slot in the quizinvideo.
     */
    public function get_last_slot() {
        return end($this->slotsinorder);
    }

    /**
     * Get a slot by it's id. Throws an exception if it is missing.
     * @param int $slotid the slot id.
     * @return \stdClass the requested quizinvideo_slots row.
     */
    public function get_slot_by_id($slotid) {
        if (!array_key_exists($slotid, $this->slots)) {
            throw new \coding_exception('The \'slotid\' could not be found.');
        }
        return $this->slots[$slotid];
    }

    /**
     * Get all the questions in a section of the quizinvideo.
     * @param int $sectionid the section id.
     * @return \stdClass[] of question/slot objects.
     */
    public function get_questions_in_section($sectionid) {
        $questions = array();
        foreach ($this->slotsinorder as $slot) {
            if ($slot->sectionid == $sectionid) {
                $questions[] = $this->questions[$slot->questionid];
            }
        }
        return $questions;
    }

    /**
     * Get all the sections of the quizinvideo.
     * @return \stdClass[] the sections in this quizinvideo.
     */
    public function get_quizinvideo_sections() {
        return $this->sections;
    }

    /**
     * Get any warnings to show at the top of the edit page.
     * @return string[] array of strings.
     */
    public function get_edit_page_warnings() {
        $warnings = array();

        if (quizinvideo_has_attempts($this->quizinvideoobj->get_quizinvideoid())) {
            $reviewlink = quizinvideo_attempt_summary_link_to_reports($this->quizinvideoobj->get_quizinvideo(),
                    $this->quizinvideoobj->get_cm(), $this->quizinvideoobj->get_context());
            $warnings[] = get_string('cannoteditafterattempts', 'quizinvideo', $reviewlink);
        }

        if ($this->is_shuffled()) {
            $updateurl = new \moodle_url('/course/mod.php',
                    array('return' => 'true', 'update' => $this->quizinvideoobj->get_cmid(), 'sesskey' => sesskey()));
            $updatelink = '<a href="'.$updateurl->out().'">' . get_string('updatethis', '',
                    get_string('modulename', 'quizinvideo')) . '</a>';
            $warnings[] = get_string('shufflequestionsselected', 'quizinvideo', $updatelink);
        }

        return $warnings;
    }

    /**
     * Get the date information about the current state of the quizinvideo.
     * @return string[] array of two strings. First a short summary, then a longer
     * explanation of the current state, e.g. for a tool-tip.
     */
    public function get_dates_summary() {
        $timenow = time();
        $quizinvideo = $this->quizinvideoobj->get_quizinvideo();

        // Exact open and close dates for the tool-tip.
        $dates = array();
        if ($quizinvideo->timeopen > 0) {
            if ($timenow > $quizinvideo->timeopen) {
                $dates[] = get_string('quizinvideoopenedon', 'quizinvideo', userdate($quizinvideo->timeopen));
            } else {
                $dates[] = get_string('quizinvideowillopen', 'quizinvideo', userdate($quizinvideo->timeopen));
            }
        }
        if ($quizinvideo->timeclose > 0) {
            if ($timenow > $quizinvideo->timeclose) {
                $dates[] = get_string('quizinvideoclosed', 'quizinvideo', userdate($quizinvideo->timeclose));
            } else {
                $dates[] = get_string('quizinvideocloseson', 'quizinvideo', userdate($quizinvideo->timeclose));
            }
        }
        if (empty($dates)) {
            $dates[] = get_string('alwaysavailable', 'quizinvideo');
        }
        $explanation = implode(', ', $dates);

        // Brief summary on the page.
        if ($timenow < $quizinvideo->timeopen) {
            $currentstatus = get_string('quizinvideoisclosedwillopen', 'quizinvideo',
                    userdate($quizinvideo->timeopen, get_string('strftimedatetimeshort', 'langconfig')));
        } else if ($quizinvideo->timeclose && $timenow <= $quizinvideo->timeclose) {
            $currentstatus = get_string('quizinvideoisopenwillclose', 'quizinvideo',
                    userdate($quizinvideo->timeclose, get_string('strftimedatetimeshort', 'langconfig')));
        } else if ($quizinvideo->timeclose && $timenow > $quizinvideo->timeclose) {
            $currentstatus = get_string('quizinvideoisclosed', 'quizinvideo');
        } else {
            $currentstatus = get_string('quizinvideoisopen', 'quizinvideo');
        }

        return array($currentstatus, $explanation);
    }

    /**
     * Set up this class with the structure for a given quizinvideo.
     * @param \stdClass $quizinvideo the quizinvideo settings.
     */
    public function populate_structure($quizinvideo) {
        global $DB;

        $slots = $DB->get_records_sql("
                SELECT slot.id AS slotid, slot.slot, slot.questionid, slot.page, slot.maxmark,
                       q.*, qc.contextid
                  FROM {quizinvideo_slots} slot
                  LEFT JOIN {question} q ON q.id = slot.questionid
                  LEFT JOIN {question_categories} qc ON qc.id = q.category
                 WHERE slot.quizinvideoid = ?
              ORDER BY slot.slot", array($quizinvideo->id));

        $slots = $this->populate_missing_questions($slots);

        $this->questions = array();
        $this->slots = array();
        $this->slotsinorder = array();
        foreach ($slots as $slotdata) {
            $this->questions[$slotdata->questionid] = $slotdata;

            $slot = new \stdClass();
            $slot->id = $slotdata->slotid;
            $slot->slot = $slotdata->slot;
            $slot->quizinvideoid = $quizinvideo->id;
            $slot->page = $slotdata->page;
            $slot->questionid = $slotdata->questionid;
            $slot->maxmark = $slotdata->maxmark;

            $this->slots[$slot->id] = $slot;
            $this->slotsinorder[$slot->slot] = $slot;
        }

        $section = new \stdClass();
        $section->id = 1;
        $section->quizinvideoid = $quizinvideo->id;
        $section->heading = '';
        $section->firstslot = 1;
        $section->shuffle = false;
        $this->sections = array(1 => $section);

        $this->populate_slots_with_sectionids();
        $this->populate_question_numbers();
    }

    /**
     * Used by populate. Make up fake data for any missing questions.
     * @param \stdClass[] $slots the data about the slots and questions in the quizinvideo.
     * @return \stdClass[] updated $slots array.
     */
    protected function populate_missing_questions($slots) {
        // Address missing question types.
        foreach ($slots as $slot) {
            if ($slot->qtype === null) {
                // If the questiontype is missing change the question type.
                $slot->id = $slot->questionid;
                $slot->category = 0;
                $slot->qtype = 'missingtype';
                $slot->name = get_string('missingquestion', 'quizinvideo');
                $slot->slot = $slot->slot;
                $slot->maxmark = 0;
                $slot->questiontext = ' ';
                $slot->questiontextformat = FORMAT_HTML;
                $slot->length = 1;

            } else if (!\question_bank::qtype_exists($slot->qtype)) {
                $slot->qtype = 'missingtype';
            }
        }

        return $slots;
    }

    /**
     * Fill in the section ids for each slot.
     */
    public function populate_slots_with_sectionids() {
        $nextsection = reset($this->sections);
        foreach ($this->slotsinorder as $slot) {
            if ($slot->slot == $nextsection->firstslot) {
                $currentsectionid = $nextsection->id;
                $nextsection = next($this->sections);
                if (!$nextsection) {
                    $nextsection = new \stdClass();
                    $nextsection->firstslot = -1;
                }
            }

            $slot->sectionid = $currentsectionid;
        }
    }

    /**
     * Number the questions.
     */
    protected function populate_question_numbers() {
        $number = 1;
        foreach ($this->slots as $slot) {
            $question = $this->questions[$slot->questionid];
            if ($question->length == 0) {
                $question->displayednumber = get_string('infoshort', 'quizinvideo');
            } else {
                $question->displayednumber = $number;
                $number += 1;
            }
        }
    }

    /**
     * Move a slot from its current location to a new location.
     *
     * After callig this method, this class will be in an invalid state, and
     * should be discarded if you want to manipulate the structure further.
     *
     * @param int $idmove id of slot to be moved
     * @param int $idbefore id of slot to come before slot being moved
     * @param int $page new page number of slot being moved
     * @return void
     */
    public function move_slot($idmove, $idbefore, $page) {
        global $DB;

        $this->check_can_be_edited();

        $movingslot = $this->slots[$idmove];
        if (empty($movingslot)) {
            throw new moodle_exception('Bad slot ID ' . $idmove);
        }
        $movingslotnumber = (int) $movingslot->slot;

        // Empty target slot means move slot to first.
        if (empty($idbefore)) {
            $targetslotnumber = 0;
        } else {
            $targetslotnumber = (int) $this->slots[$idbefore]->slot;
        }

        // Work out how things are being moved.
        $slotreorder = array();
        if ($targetslotnumber > $movingslotnumber) {
            $slotreorder[$movingslotnumber] = $targetslotnumber;
            for ($i = $movingslotnumber; $i < $targetslotnumber; $i++) {
                $slotreorder[$i + 1] = $i;
            }
        } else if ($targetslotnumber < $movingslotnumber - 1) {
            $slotreorder[$movingslotnumber] = $targetslotnumber + 1;
            for ($i = $targetslotnumber + 1; $i < $movingslotnumber; $i++) {
                $slotreorder[$i] = $i + 1;
            }
        }

        $trans = $DB->start_delegated_transaction();

        // Slot has moved record new order.
        if ($slotreorder) {
            update_field_with_unique_index('quizinvideo_slots', 'slot', $slotreorder,
                    array('quizinvideoid' => $this->get_quizinvideoid()));
        }

        // Page has changed. Record it.
        if (!$page) {
            $page = 1;
        }
        if ($movingslot->page != $page) {
            $DB->set_field('quizinvideo_slots', 'page', $page,
                    array('id' => $movingslot->id));
        }

        $emptypages = $DB->get_fieldset_sql("
                SELECT DISTINCT page - 1
                  FROM {quizinvideo_slots} slot
                 WHERE quizinvideoid = ?
                   AND page > 1
                   AND NOT EXISTS (SELECT 1 FROM {quizinvideo_slots} WHERE quizinvideoid = ? AND page = slot.page - 1)
              ORDER BY page - 1 DESC
                ", array($this->get_quizinvideoid(), $this->get_quizinvideoid()));

        foreach ($emptypages as $page) {
            $DB->execute("
                    UPDATE {quizinvideo_slots}
                       SET page = page - 1
                     WHERE quizinvideoid = ?
                       AND page > ?
                    ", array($this->get_quizinvideoid(), $page));
        }

        $trans->allow_commit();
    }

    /**
     * Refresh page numbering of quizinvideo slots.
     * @param \stdClass $quizinvideo the quizinvideo object.
     * @param \stdClass[] $slots (optional) array of slot objects.
     * @return \stdClass[] array of slot objects.
     */
    public function refresh_page_numbers($quizinvideo, $slots=array()) {
        global $DB;
        // Get slots ordered by page then slot.
        if (!count($slots)) {
            $slots = $DB->get_records('quizinvideo_slots', array('quizinvideoid' => $quizinvideo->id), 'slot, page');
        }

        // Loop slots. Start Page number at 1 and increment as required.
        $pagenumbers = array('new' => 0, 'old' => 0);

        foreach ($slots as $slot) {
            if ($slot->page !== $pagenumbers['old']) {
                $pagenumbers['old'] = $slot->page;
                ++$pagenumbers['new'];
            }

            if ($pagenumbers['new'] == $slot->page) {
                continue;
            }
            $slot->page = $pagenumbers['new'];
        }
        //removing page from quizinvideo_page if it does not exist in quizinvideo_slots
        $DB->execute("delete from {quizinvideo_page}
                        where not exists (
                                select * from {quizinvideo_slots}
                                    where {quizinvideo_page}.quizinvideoid = {quizinvideo_slots}.quizinvideoid and
                                          {quizinvideo_page}.page = {quizinvideo_slots}.page)"
            );

        //adding new page in quizinvideo_page if it exists in quizinvideo_slots
        $DB->execute("insert into {quizinvideo_page}(quizinvideoid, page)
                        select {quizinvideo_slots}.quizinvideoid, {quizinvideo_slots}.page
                            from {quizinvideo_slots}
                            where not exists (
                                    select * from {quizinvideo_page}
                                            where {quizinvideo_slots}.quizinvideoid={quizinvideo_page}.quizinvideoid and
                                                  {quizinvideo_slots}.page={quizinvideo_page}.page)"
            );

        return $slots;
    }

    /**
     * Refresh page numbering of quizinvideo slots and save to the database.
     * @param \stdClass $quizinvideo the quizinvideo object.
     * @return \stdClass[] array of slot objects.
     */
    public function refresh_page_numbers_and_update_db($quizinvideo) {
        global $DB;
        $this->check_can_be_edited();

        $slots = $this->refresh_page_numbers($quizinvideo);

        // Record new page order.
        foreach ($slots as $slot) {
            $DB->set_field('quizinvideo_slots', 'page', $slot->page,
                    array('id' => $slot->id));
        }

        return $slots;
    }

    /**
     * Remove a slot from a quizinvideo
     * @param \stdClass $quizinvideo the quizinvideo object.
     * @param int $slotnumber The number of the slot to be deleted.
     */
    public function remove_slot($quizinvideo, $slotnumber) {
        global $DB;

        $this->check_can_be_edited();

        $slot = $DB->get_record('quizinvideo_slots', array('quizinvideoid' => $quizinvideo->id, 'slot' => $slotnumber));
        $maxslot = $DB->get_field_sql('SELECT MAX(slot) FROM {quizinvideo_slots} WHERE quizinvideoid = ?', array($quizinvideo->id));
        if (!$slot) {
            return;
        }

        $trans = $DB->start_delegated_transaction();
        $DB->delete_records('quizinvideo_slots', array('id' => $slot->id));
        $DB->delete_records('quizinvideo_page', array('quizinvideoid' => $quizinvideo->id, 'page' => $slot->page));
        for ($i = $slot->slot + 1; $i <= $maxslot; $i++) {
            $DB->set_field('quizinvideo_slots', 'slot', $i - 1,
                    array('quizinvideoid' => $quizinvideo->id, 'slot' => $i));
        }

        $qtype = $DB->get_field('question', 'qtype', array('id' => $slot->questionid));
        if ($qtype === 'random') {
            // This function automatically checks if the question is in use, and won't delete if it is.
            question_delete_question($slot->questionid);
        }

        unset($this->questions[$slot->questionid]);

        $this->refresh_page_numbers_and_update_db($quizinvideo);

        $trans->allow_commit();
    }

    /**
     * Change the max mark for a slot.
     *
     * Saves changes to the question grades in the quizinvideo_slots table and any
     * corresponding question_attempts.
     * It does not update 'sumgrades' in the quizinvideo table.
     *
     * @param \stdClass $slot row from the quizinvideo_slots table.
     * @param float $maxmark the new maxmark.
     * @return bool true if the new grade is different from the old one.
     */
    public function update_slot_maxmark($slot, $maxmark) {
        global $DB;

        if (abs($maxmark - $slot->maxmark) < 1e-7) {
            // Grade has not changed. Nothing to do.
            return false;
        }

        $trans = $DB->start_delegated_transaction();
        $slot->maxmark = $maxmark;
        $DB->update_record('quizinvideo_slots', $slot);
        \question_engine::set_max_mark_in_attempts(new \qubaids_for_quizinvideo($slot->quizinvideoid),
                $slot->slot, $maxmark);
        $trans->allow_commit();

        return true;
    }

    /**
     * Add/Remove a pagebreak.
     *
     * Saves changes to the slot page relationship in the quizinvideo_slots table and reorders the paging
     * for subsequent slots.
     *
     * @param \stdClass $quizinvideo the quizinvideo object.
     * @param int $slotid id of slot.
     * @param int $type repaginate::LINK or repaginate::UNLINK.
     * @return \stdClass[] array of slot objects.
     */
    public function update_page_break($quizinvideo, $slotid, $type) {
        global $DB;

        $this->check_can_be_edited();

        $quizinvideoslots = $DB->get_records('quizinvideo_slots', array('quizinvideoid' => $quizinvideo->id), 'slot');
        $repaginate = new \mod_quizinvideo\repaginate($quizinvideo->id, $quizinvideoslots);
        $repaginate->repaginate_slots($quizinvideoslots[$slotid]->slot, $type);
        $slots = $this->refresh_page_numbers_and_update_db($quizinvideo);

        return $slots;
    }
}
