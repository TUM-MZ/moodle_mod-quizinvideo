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
     * Create a course with an empty quizinvideo.
     * @return array with three elements quizinvideo, cm and course.
     */
    protected function prepare_quizinvideo_data() {

        $this->resetAfterTest(true);

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Make a quizinvideo.
        $quizinvideogenerator = $this->getDataGenerator()->get_plugin_generator('mod_quizinvideo');

        $quizinvideo = $quizinvideogenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));

        $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $course->id);

        return array($quizinvideo, $cm, $course);
    }

    /**
     * Creat a test quizinvideo.
     *
     * $layout looks like this:
     * $layout = array(
     *     'Heading 1'
     *     array('TF1', 1, 'truefalse'),
     *     'Heading 2*'
     *     array('TF2', 2, 'truefalse'),
     * );
     * That is, either a string, which represents a section heading,
     * or an array that represents a question.
     *
     * If the section heading ends with *, that section is shuffled.
     *
     * The elements in the question array are name, page number, and question type.
     *
     * @param array $layout as above.
     * @return quizinvideo the created quizinvideo.
     */
    protected function create_test_quizinvideo($layout) {
        list($quizinvideo, $cm, $course) = $this->prepare_quizinvideo_data();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $headings = array();
        $slot = 1;
        $lastpage = 0;
        foreach ($layout as $item) {
            if (is_string($item)) {
                if (isset($headings[$lastpage + 1])) {
                    throw new coding_exception('Sections cannot be empty.');
                }
                $headings[$lastpage + 1] = $item;

            } else {
                list($name, $page, $qtype) = $item;
                if ($page < 1 || !($page == $lastpage + 1 ||
                        (!isset($headings[$lastpage + 1]) && $page == $lastpage))) {
                    throw new coding_exception('Page numbers wrong.');
                }
                $q = $questiongenerator->create_question($qtype, null,
                        array('name' => $name, 'category' => $cat->id));

                quizinvideo_add_quizinvideo_question($q->id, $quizinvideo, $page);
                $lastpage = $page;
            }
        }

        $quizinvideoobj = new quizinvideo($quizinvideo, $cm, $course);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        if (isset($headings[1])) {
            list($heading, $shuffle) = $this->parse_section_name($headings[1]);
            $sections = $structure->get_sections();
            $firstsection = reset($sections);
            $structure->set_section_heading($firstsection->id, $heading);
            $structure->set_section_shuffle($firstsection->id, $shuffle);
            unset($headings[1]);
        }

        foreach ($headings as $startpage => $heading) {
            list($heading, $shuffle) = $this->parse_section_name($heading);
            $id = $structure->add_section_heading($startpage, $heading);
            $structure->set_section_shuffle($id, $shuffle);
        }

        return $quizinvideoobj;
    }

    /**
     * Verify that the given layout matches that expected.
     * @param array $expectedlayout as for $layout in {@link create_test_quizinvideo()}.
     * @param \mod_quizinvideo\structure $structure the structure to test.
     */
    protected function assert_quizinvideo_layout($expectedlayout, \mod_quizinvideo\structure $structure) {
        $sections = $structure->get_sections();

        $slot = 1;
        foreach ($expectedlayout as $item) {
            if (is_string($item)) {
                list($heading, $shuffle) = $this->parse_section_name($item);
                $section = array_shift($sections);

                if ($slot > 1 && $section->heading == '' && $section->firstslot == 1) {
                    // The array $expectedlayout did not contain default first quizinvideo section, so skip over it.
                    $section = array_shift($sections);
                }

                $this->assertEquals($slot, $section->firstslot);
                $this->assertEquals($heading, $section->heading);
                $this->assertEquals($shuffle, $section->shufflequestions);

            } else {
                list($name, $page, $qtype) = $item;
                $question = $structure->get_question_in_slot($slot);
                $this->assertEquals($slot,  $question->slot);
                $this->assertEquals($name,  $question->name);
                $this->assertEquals($qtype, $question->qtype);
                $this->assertEquals($page,  $question->page);

                $slot += 1;
            }
        }

        if ($slot - 1 != count($structure->get_slots())) {
            $this->fail('The quizinvideo contains more slots than expected.');
        }

        if (!empty($sections)) {
            $section = array_shift($sections);
            if ($section->heading != '' || $section->firstslot != 1) {
                $this->fail('Unexpected section (' . $section->heading .') found in the quizinvideo.');
            }
        }
    }

    /**
     * Parse the section name, optionally followed by a * to mean shuffle, as
     * used by create_test_quizinvideo as assert_quizinvideo_layout.
     * @param string $heading the heading.
     * @return array with two elements, the heading and the shuffle setting.
     */
    protected function parse_section_name($heading) {
        if (substr($heading, -1) == '*') {
            return array(substr($heading, 0, -1), 1);
        } else {
            return array($heading, 0);
        }
    }

    public function test_get_quizinvideo_slots() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(2, $structure->get_slots());
    }

    public function test_quizinvideo_has_one_section_by_default() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $sections = $structure->get_sections();
        $this->assertCount(1, $sections);

        $section = array_shift($sections);
        $this->assertEquals(1, $section->firstslot);
        $this->assertEquals('', $section->heading);
        $this->assertEquals(0, $section->shufflequestions);
    }

    public function test_get_sections() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1*',
                array('TF1', 1, 'truefalse'),
                'Heading 2*',
                array('TF2', 2, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $sections = $structure->get_sections();
        $this->assertCount(2, $sections);

        $section = array_shift($sections);
        $this->assertEquals(1, $section->firstslot);
        $this->assertEquals('Heading 1', $section->heading);
        $this->assertEquals(1, $section->shufflequestions);

        $section = array_shift($sections);
        $this->assertEquals(2, $section->firstslot);
        $this->assertEquals('Heading 2', $section->heading);
        $this->assertEquals(1, $section->shufflequestions);
    }

    public function test_remove_section_heading() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $sections = $structure->get_sections();
        $section = end($sections);
        $structure->remove_section_heading($section->id);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_cannot_remove_first_section() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $sections = $structure->get_sections();
        $section = reset($sections);

        $this->setExpectedException('coding_exception');
        $structure->remove_section_heading($section->id);
    }

    public function test_move_slot_to_the_same_place_does_nothing() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_end_of_one_page_to_start_of_next() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_end_of_one_section_to_start_of_next() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                'Heading',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_start_of_one_section_to_end_of_previous() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                'Heading',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading',
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }
    public function test_move_slot_on_same_page() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
        ), $structure);
    }

    public function test_move_slot_up_onto_previous_page() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_move_slot_emptying_a_page_renumbers_pages() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 3, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '3');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_move_slot_too_small_page_number_detected() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 3, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $this->setExpectedException('coding_exception');
        $structure->move_slot($idtomove, $idmoveafter, '1');
    }

    public function test_move_slot_too_large_page_number_detected() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 3, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $this->setExpectedException('coding_exception');
        $structure->move_slot($idtomove, $idmoveafter, '4');
    }

    public function test_move_slot_within_section() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                'Heading 1',
                array('TF2', 1, 'truefalse'),
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_to_new_section() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_to_start() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, 0, '1');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                'Heading 1',
                array('TF3', 1, 'truefalse'),
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_to_down_start_of_second_section() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_up_to_start_of_second_section() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF3', 3, 'truefalse'),
                array('TF4', 3, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF4', 3, 'truefalse'),
            ), $structure);
    }

    public function test_quizinvideo_remove_slot() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $structure->remove_slot(2);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_quizinvideo_removing_a_random_question_deletes_the_question() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
            ));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        quizinvideo_add_random_questions($quizinvideoobj->get_quizinvideo(), 1, $cat->id, 1, false);
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        $structure->remove_slot(2);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
            ), $structure);
        $this->assertFalse($DB->record_exists('question', array('id' => $randomq->id)));
    }

    public function test_cannot_remove_last_slot_in_a_section() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $this->setExpectedException('coding_exception');
        $structure->remove_slot(3);
    }

    public function test_add_question_updates_headings() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('truefalse', null,
                array('name' => 'TF3', 'category' => $cat->id));

        quizinvideo_add_quizinvideo_question($q->id, $quizinvideoobj->get_quizinvideo(), 1);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_add_question_and_end_does_not_update_headings() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('truefalse', null,
                array('name' => 'TF3', 'category' => $cat->id));

        quizinvideo_add_quizinvideo_question($q->id, $quizinvideoobj->get_quizinvideo(), 0);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
        ), $structure);
    }

    public function test_remove_page_break() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $slotid = $structure->get_question_in_slot(2)->slotid;
        $slots = $structure->update_page_break($slotid, \mod_quizinvideo\repaginate::LINK);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
            ), $structure);
    }

    public function test_add_page_break() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        $slotid = $structure->get_question_in_slot(2)->slotid;
        $slots = $structure->update_page_break($slotid, \mod_quizinvideo\repaginate::UNLINK);

        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assert_quizinvideo_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_update_question_dependency() {
        $quizinvideoobj = $this->create_test_quizinvideo(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
        ));
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);

        // Test adding a dependency.
        $slotid = $structure->get_slot_id_for_slot(2);
        $structure->update_question_dependency($slotid, true);

        // Having called update page break, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assertEquals(1, $structure->is_question_dependent_on_previous_slot(2));

        // Test removing a dependency.
        $structure->update_question_dependency($slotid, false);

        // Having called update page break, we need to reload $structure.
        $structure = \mod_quizinvideo\structure::create_for_quizinvideo($quizinvideoobj);
        $this->assertEquals(0, $structure->is_question_dependent_on_previous_slot(2));
    }
}
