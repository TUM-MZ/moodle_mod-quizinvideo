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
 * quizinvideo events tests.
 *
 * @package   mod_quizinvideo
 * @category  test
 * @copyright 2013 Adrian Greeve
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quizinvideo/attemptlib.php');

/**
 * Unit tests for quizinvideo events.
 *
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizinvideo_structure_testcase extends advanced_testcase {

    /**
     * Prepare the quizinvideo object with standard data. Ready for testing.
     */
    protected function prepare_quizinvideo_data() {

        $this->resetAfterTest(true);

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Make a quizinvideo.
        $quizinvideogenerator = $this->getDataGenerator()->get_plugin_generator('mod_quizinvideo');

        $quizinvideo = $quizinvideogenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $course->id);

        return array($quizinvideo, $cm, $course);
    }

    /**
     * Test getting the quizinvideo slots.
     */
    public function test_get_quizinvideo_slots() {
        // Get basic quizinvideo.
        list($quizinvideo, $cm, $course) = $this->prepare_quizinvideo_data();
        $quizinvideoobj = new quizinvideo($quizinvideo, $cm, $course);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // When no slots exist or slots propery is not set.
        $slots = $structure->get_slots();
        $this->assertInternalType('array', $slots);
        $this->assertCount(0, $slots);

        // Append slots to the quizinvideo.
        $this->add_eight_questions_to_the_quizinvideo($quizinvideo);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(8, $slots);
    }

    /**
     * Test getting the quizinvideo sections.
     */
    public function test_get_quizinvideo_sections() {
        // Get basic quizinvideo.
        list($quizinvideo, $cm, $course) = $this->prepare_quizinvideo_data();
        $quizinvideoobj = new quizinvideo($quizinvideo, $cm, $course);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Are the correct sections returned?
        $sections = $structure->get_quizinvideo_sections();
        $this->assertCount(1, $sections);
    }

    /**
     * Verify that the given layout matches that expected.
     * @param array $expectedlayout
     * @param \mod_quizinvideo\structure $structure
     */
    protected function assert_quizinvideo_layout($expectedlayout, \mod_quizinvideo\structure $structure) {
        $slotnumber = 0;
        foreach ($expectedlayout as $slotid => $page) {
            $slotnumber += 1;
            $this->assertEquals($slotid, $structure->get_question_in_slot($slotnumber)->slotid,
                    'Wrong question in slot ' . $slotnumber);
            $this->assertEquals($page, $structure->get_question_in_slot($slotnumber)->page,
                    'Wrong page number for slot ' . $slotnumber);
        }
    }

    /**
     * Test moving slots in the quizinvideo.
     */
    public function test_move_slot() {
        // Create a test quizinvideo with 8 questions.
        list($quizinvideo, $cm, $course) = $this->prepare_quizinvideo_data();
        $this->add_eight_questions_to_the_quizinvideo($quizinvideo);
        $quizinvideoobj = new quizinvideo($quizinvideo, $cm, $course);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Store the original order of slots, so we can assert what has changed.
        $originalslotids = array();
        foreach ($structure->get_slots() as $slot) {
            $originalslotids[$slot->slot] = $slot->id;
        }

        // Don't actually move anything. Check the layout is unchanged.
        $idmove = $structure->get_question_in_slot(2)->slotid;
        $idbefore = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idmove, $idbefore, 2);

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                    $originalslotids[1] => 1,
                    $originalslotids[2] => 2,
                    $originalslotids[3] => 2,
                    $originalslotids[4] => 2,
                    $originalslotids[5] => 2,
                    $originalslotids[6] => 2,
                    $originalslotids[7] => 3,
                    $originalslotids[8] => 4,
                ), $structure);

        // Slots don't move. Page changed.
        $idmove = $structure->get_question_in_slot(2)->slotid;
        $idbefore = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idmove, $idbefore, 1);

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                    $originalslotids[1] => 1,
                    $originalslotids[2] => 1,
                    $originalslotids[3] => 2,
                    $originalslotids[4] => 2,
                    $originalslotids[5] => 2,
                    $originalslotids[6] => 2,
                    $originalslotids[7] => 3,
                    $originalslotids[8] => 4,
                ), $structure);

        // Slots move 2 > 3. Page unchanged. Pages not reordered.
        $idmove = $structure->get_question_in_slot(2)->slotid;
        $idbefore = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idmove, $idbefore, '2');

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                $originalslotids[1] => 1,
                $originalslotids[3] => 2,
                $originalslotids[2] => 2,
                $originalslotids[4] => 2,
                $originalslotids[5] => 2,
                $originalslotids[6] => 2,
                $originalslotids[7] => 3,
                $originalslotids[8] => 4,
        ), $structure);

        // Slots move 6 > 7. Page changed. Pages not reordered.
        $idmove = $structure->get_question_in_slot(6)->slotid;
        $idbefore = $structure->get_question_in_slot(7)->slotid;
        $structure->move_slot($idmove, $idbefore, '3');

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                $originalslotids[1] => 1,
                $originalslotids[3] => 2,
                $originalslotids[2] => 2,
                $originalslotids[4] => 2,
                $originalslotids[5] => 2,
                $originalslotids[7] => 3,
                $originalslotids[6] => 3,
                $originalslotids[8] => 4,
        ), $structure);

        // Page changed slot 6 . Pages not reordered.
        $idmove = $structure->get_question_in_slot(6)->slotid;
        $idbefore = $structure->get_question_in_slot(5)->slotid;
        $structure->move_slot($idmove, $idbefore, 2);

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                $originalslotids[1] => 1,
                $originalslotids[3] => 2,
                $originalslotids[2] => 2,
                $originalslotids[4] => 2,
                $originalslotids[5] => 2,
                $originalslotids[7] => 2,
                $originalslotids[6] => 3,
                $originalslotids[8] => 4,
        ), $structure);

        // Slots move 1 > 2. Page changed. Page 2 becomes page 1. Pages reordered.
        $idmove = $structure->get_question_in_slot(1)->slotid;
        $idbefore = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idmove, $idbefore, 2);

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                $originalslotids[3] => 1,
                $originalslotids[1] => 1,
                $originalslotids[2] => 1,
                $originalslotids[4] => 1,
                $originalslotids[5] => 1,
                $originalslotids[7] => 1,
                $originalslotids[6] => 2,
                $originalslotids[8] => 3,
        ), $structure);

        // Slots move 7 > 3. Page changed. Page 3 becomes page 2. Pages reordered.
        $idmove = $structure->get_question_in_slot(7)->slotid;
        $idbefore = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idmove, $idbefore, 1);

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                $originalslotids[3] => 1,
                $originalslotids[1] => 1,
                $originalslotids[6] => 1,
                $originalslotids[2] => 1,
                $originalslotids[4] => 1,
                $originalslotids[5] => 1,
                $originalslotids[7] => 1,
                $originalslotids[8] => 2,
        ), $structure);

        // Slots move 2 > top. No page changes.
        $idmove = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idmove, 0, 1);

        // Having called move, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                $originalslotids[1] => 1,
                $originalslotids[3] => 1,
                $originalslotids[6] => 1,
                $originalslotids[2] => 1,
                $originalslotids[4] => 1,
                $originalslotids[5] => 1,
                $originalslotids[7] => 1,
                $originalslotids[8] => 2,
        ), $structure);
    }

    /**
     * Test removing slots from a quizinvideo.
     */
    public function test_quizinvideo_remove_slot() {
        global $SITE, $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a quizinvideo with 1 standard and 1 random question.
        $quizinvideogenerator = $this->getDataGenerator()->get_plugin_generator('mod_quizinvideo');
        $quizinvideo = $quizinvideogenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));
        $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $SITE->id);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        quizinvideo_add_quizinvideo_question($standardq->id, $quizinvideo);
        quizinvideo_add_random_questions($quizinvideo, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        $quizinvideoobj = new quizinvideo($quizinvideo, $cm, $SITE);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Check that the setup looks right.
        $this->assertEquals(2, $structure->get_question_count());
        $this->assertEquals($standardq->id, $structure->get_question_in_slot(1)->questionid);
        $this->assertEquals($randomq->id, $structure->get_question_in_slot(2)->questionid);

        // Remove the standard question.
        $structure->remove_slot($quizinvideo, 1);

        $alteredstructure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Check the new ordering, and that the slot number was updated.
        $this->assertEquals(1, $alteredstructure->get_question_count());
        $this->assertEquals($randomq->id, $alteredstructure->get_question_in_slot(1)->questionid);

        // Check that the ordinary question was not deleted.
        $this->assertTrue($DB->record_exists('question', array('id' => $standardq->id)));

        // Remove the random question.
        $structure->remove_slot($quizinvideo, 1);
        $alteredstructure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Check that new ordering.
        $this->assertEquals(0, $alteredstructure->get_question_count());

        // Check that the random question was deleted.
        $this->assertFalse($DB->record_exists('question', array('id' => $randomq->id)));
    }

    /**
     * Test updating pagebreaks in the quizinvideo.
     */
    public function test_update_page_break() {
        // Create a test quizinvideo with 8 questions.
        list($quizinvideo, $cm, $course) = $this->prepare_quizinvideo_data();
        $this->add_eight_questions_to_the_quizinvideo($quizinvideo);
        $quizinvideoobj = new quizinvideo($quizinvideo, $cm, $course);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Store the original order of slots, so we can assert what has changed.
        $originalslotids = array();
        foreach ($structure->get_slots() as $slot) {
            $originalslotids[$slot->slot] = $slot->id;
        }

        // Test removing a page break.
        $slotid = $structure->get_question_in_slot(2)->slotid;
        $type = \mod_quizinvideo\repaginate::LINK;
        $slots = $structure->update_page_break($quizinvideo, $slotid, $type);

        // Having called update page break, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                    $originalslotids[1] => 1,
                    $originalslotids[2] => 1,
                    $originalslotids[3] => 1,
                    $originalslotids[4] => 1,
                    $originalslotids[5] => 1,
                    $originalslotids[6] => 1,
                    $originalslotids[7] => 2,
                    $originalslotids[8] => 3,
                ), $structure);

        // Test adding a page break.
        $slotid = $structure->get_question_in_slot(2)->slotid;
        $type = \mod_quizinvideo\repaginate::UNLINK;
        $slots = $structure->update_page_break($quizinvideo, $slotid, $type);

        // Having called update page break, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                    $originalslotids[1] => 1,
                    $originalslotids[2] => 2,
                    $originalslotids[3] => 2,
                    $originalslotids[4] => 2,
                    $originalslotids[5] => 2,
                    $originalslotids[6] => 2,
                    $originalslotids[7] => 3,
                    $originalslotids[8] => 4,
                ), $structure);
    }

    /**
     * Populate quizinvideo with eight questions.
     * @param stdClass $quizinvideo the quizinvideo to add to.
     */
    public function add_eight_questions_to_the_quizinvideo($quizinvideo) {
        // We add 8 numerical questions with this layout:
        // Slot 1 2 3 4 5 6 7 8
        // Page 1 2 2 2 2 2 3 4.

        // Create slots.
        $pagenumber = 1;
        $pagenumberdefaults = array(2, 7, 8);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        for ($i = 0; $i < 8; $i ++) {
            $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

            if (in_array($i + 1, $pagenumberdefaults)) {
                $pagenumber++;
            }
            // Add them to the quizinvideo.
            quizinvideo_add_quizinvideo_question($numq->id, $quizinvideo, $pagenumber);
        }
    }
}
