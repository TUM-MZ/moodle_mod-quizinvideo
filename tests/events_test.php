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
 * @package    mod_quizinvideo
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quizinvideo/attemptlib.php');

/**
 * Unit tests for quizinvideo events.
 *
 * @package    mod_quizinvideo
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizinvideo_events_testcase extends advanced_testcase {

    protected function prepare_quizinvideo_data() {

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Make a quizinvideo.
        $quizinvideogenerator = $this->getDataGenerator()->get_plugin_generator('mod_quizinvideo');

        $quizinvideo = $quizinvideogenerator->create_instance(array('course'=>$course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('quizinvideo', $quizinvideo->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the quizinvideo.
        quizinvideo_add_quizinvideo_question($saq->id, $quizinvideo);
        quizinvideo_add_quizinvideo_question($numq->id, $quizinvideo);

        // Make a user to do the quizinvideo.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $quizinvideoobj = quizinvideo::create($quizinvideo->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quizinvideo', $quizinvideoobj->get_context());
        $quba->set_preferred_behaviour($quizinvideoobj->get_quizinvideo()->preferredbehaviour);

        $timenow = time();
        $attempt = quizinvideo_create_attempt($quizinvideoobj, 1, false, $timenow);
        quizinvideo_start_new_attempt($quizinvideoobj, $quba, $attempt, 1, $timenow);
        quizinvideo_attempt_save_started($quizinvideoobj, $quba, $attempt);

        return array($quizinvideoobj, $quba, $attempt);
    }

    public function test_attempt_submitted() {

        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();
        $attemptobj = quizinvideo_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();

        $timefinish = time();
        $attemptobj->process_finish($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_submitted', $event);
        $this->assertEquals('quizinvideo_attempts', $event->objecttable);
        $this->assertEquals($quizinvideoobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals(null, $event->other['submitterid']); // Should be the user, but PHP Unit complains...
        $this->assertEquals('quizinvideo_attempt_submitted', $event->get_legacy_eventname());
        $legacydata = new stdClass();
        $legacydata->component = 'mod_quizinvideo';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $quizinvideoobj->get_cmid();
        $legacydata->courseid = $quizinvideoobj->get_courseid();
        $legacydata->quizinvideoid = $quizinvideoobj->get_quizinvideoid();
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $legacydata->submitterid = null;
        $legacydata->timefinish = $timefinish;
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_becameoverdue() {

        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();
        $attemptobj = quizinvideo_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_going_overdue($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_becameoverdue', $event);
        $this->assertEquals('quizinvideo_attempts', $event->objecttable);
        $this->assertEquals($quizinvideoobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('quizinvideo_attempt_overdue', $event->get_legacy_eventname());
        $legacydata = new stdClass();
        $legacydata->component = 'mod_quizinvideo';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $quizinvideoobj->get_cmid();
        $legacydata->courseid = $quizinvideoobj->get_courseid();
        $legacydata->quizinvideoid = $quizinvideoobj->get_quizinvideoid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_abandoned() {

        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();
        $attemptobj = quizinvideo_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_abandon($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_abandoned', $event);
        $this->assertEquals('quizinvideo_attempts', $event->objecttable);
        $this->assertEquals($quizinvideoobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('quizinvideo_attempt_abandoned', $event->get_legacy_eventname());
        $legacydata = new stdClass();
        $legacydata->component = 'mod_quizinvideo';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $quizinvideoobj->get_cmid();
        $legacydata->courseid = $quizinvideoobj->get_courseid();
        $legacydata->quizinvideoid = $quizinvideoobj->get_quizinvideoid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_started() {
        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();

        // Create another attempt.
        $attempt = quizinvideo_create_attempt($quizinvideoobj, 1, false, time(), false, 2);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        quizinvideo_attempt_save_started($quizinvideoobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_started', $event);
        $this->assertEquals('quizinvideo_attempts', $event->objecttable);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals($quizinvideoobj->get_context(), $event->get_context());
        $this->assertEquals('quizinvideo_attempt_started', $event->get_legacy_eventname());
        $this->assertEquals(context_module::instance($quizinvideoobj->get_cmid()), $event->get_context());
        // Check legacy log data.
        $expected = array($quizinvideoobj->get_courseid(), 'quizinvideo', 'attempt', 'review.php?attempt=' . $attempt->id,
            $quizinvideoobj->get_quizinvideoid(), $quizinvideoobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        // Check legacy event data.
        $legacydata = new stdClass();
        $legacydata->component = 'mod_quizinvideo';
        $legacydata->attemptid = $attempt->id;
        $legacydata->timestart = $attempt->timestart;
        $legacydata->timestamp = $attempt->timestart;
        $legacydata->userid = $attempt->userid;
        $legacydata->quizinvideoid = $quizinvideoobj->get_quizinvideoid();
        $legacydata->cmid = $quizinvideoobj->get_cmid();
        $legacydata->courseid = $quizinvideoobj->get_courseid();
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the edit page viewed event.
     *
     * There is no external API for updating a quizinvideo, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_edit_page_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'courseid' => $course->id,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id
            )
        );
        $event = \mod_quizinvideo\event\edit_page_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\edit_page_viewed', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'editquestions', 'view.php?id=' . $quizinvideo->cmid, $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt deleted event.
     */
    public function test_attempt_deleted() {
        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        quizinvideo_delete_attempt($attempt, $quizinvideoobj->get_quizinvideo());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_deleted', $event);
        $this->assertEquals(context_module::instance($quizinvideoobj->get_cmid()), $event->get_context());
        $expected = array($quizinvideoobj->get_courseid(), 'quizinvideo', 'delete attempt', 'report.php?id=' . $quizinvideoobj->get_cmid(),
            $attempt->id, $quizinvideoobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the report viewed event.
     *
     * There is no external API for viewing reports, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_report_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'context' => $context = context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id,
                'reportname' => 'overview'
            )
        );
        $event = \mod_quizinvideo\event\report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\report_viewed', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'report', 'report.php?id=' . $quizinvideo->cmid . '&mode=overview',
            $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt reviewed event.
     *
     * There is no external API for reviewing attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_reviewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id
            )
        );
        $event = \mod_quizinvideo\event\attempt_reviewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_reviewed', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'review', 'review.php?attempt=1', $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt summary viewed event.
     *
     * There is no external API for viewing the attempt summary, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_summary_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id
            )
        );
        $event = \mod_quizinvideo\event\attempt_summary_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_summary_viewed', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'view summary', 'summary.php?attempt=1', $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id
            )
        );
        $event = \mod_quizinvideo\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\user_override_created', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id,
                'groupid' => 2
            )
        );
        $event = \mod_quizinvideo\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\group_override_created', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id
            )
        );
        $event = \mod_quizinvideo\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\user_override_updated', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'edit override', 'overrideedit.php?id=1', $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id,
                'groupid' => 2
            )
        );
        $event = \mod_quizinvideo\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\group_override_updated', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'edit override', 'overrideedit.php?id=1', $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        // Create an override.
        $override = new stdClass();
        $override->quizinvideo = $quizinvideo->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('quizinvideo_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        quizinvideo_delete_override($quizinvideo, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\user_override_deleted', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'delete override', 'overrides.php?cmid=' . $quizinvideo->cmid, $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        // Create an override.
        $override = new stdClass();
        $override->quizinvideo = $quizinvideo->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('quizinvideo_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        quizinvideo_delete_override($quizinvideo, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\group_override_deleted', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'delete override', 'overrides.php?cmid=' . $quizinvideo->cmid, $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt viewed event.
     *
     * There is no external API for continuing an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quizinvideo = $this->getDataGenerator()->create_module('quizinvideo', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => context_module::instance($quizinvideo->cmid),
            'other' => array(
                'quizinvideoid' => $quizinvideo->id
            )
        );
        $event = \mod_quizinvideo\event\attempt_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_viewed', $event);
        $this->assertEquals(context_module::instance($quizinvideo->cmid), $event->get_context());
        $expected = array($course->id, 'quizinvideo', 'continue attempt', 'review.php?attempt=1', $quizinvideo->id, $quizinvideo->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt previewed event.
     */
    public function test_attempt_preview_started() {
        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();

        // We want to preview this attempt.
        $attempt = quizinvideo_create_attempt($quizinvideoobj, 1, false, time(), false, 2);
        $attempt->preview = 1;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        quizinvideo_attempt_save_started($quizinvideoobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\attempt_preview_started', $event);
        $this->assertEquals(context_module::instance($quizinvideoobj->get_cmid()), $event->get_context());
        $expected = array($quizinvideoobj->get_courseid(), 'quizinvideo', 'preview', 'view.php?id=' . $quizinvideoobj->get_cmid(),
            $quizinvideoobj->get_quizinvideoid(), $quizinvideoobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the question manually graded event.
     *
     * There is no external API for manually grading a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_question_manually_graded() {
        list($quizinvideoobj, $quba, $attempt) = $this->prepare_quizinvideo_data();

        $params = array(
            'objectid' => 1,
            'courseid' => $quizinvideoobj->get_courseid(),
            'context' => context_module::instance($quizinvideoobj->get_cmid()),
            'other' => array(
                'quizinvideoid' => $quizinvideoobj->get_quizinvideoid(),
                'attemptid' => 2,
                'slot' => 3
            )
        );
        $event = \mod_quizinvideo\event\question_manually_graded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_quizinvideo\event\question_manually_graded', $event);
        $this->assertEquals(context_module::instance($quizinvideoobj->get_cmid()), $event->get_context());
        $expected = array($quizinvideoobj->get_courseid(), 'quizinvideo', 'manualgrade', 'comment.php?attempt=2&slot=3',
            $quizinvideoobj->get_quizinvideoid(), $quizinvideoobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }
}
