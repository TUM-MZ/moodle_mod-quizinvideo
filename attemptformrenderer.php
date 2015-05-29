<?php
//defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quizinvideo/locallib.php');
//
$attemptid = required_param('attempt', PARAM_INT);
$page      = optional_param('page', 0, PARAM_INT);

$attemptobj = quizinvideo_attempt::create($attemptid);


require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$PAGE->set_url($attemptobj->attempt_url(null));


$output = '';

// Start the form.
$output .= html_writer::start_tag('form',
    array('method' => 'post',
        'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
        'id' => 'responseform'));
$output .= html_writer::start_tag('div');

//for($i = 0; $i < $num_pages; $i++)
//{
//    $page = $i + 1;
    $output .= html_writer::start_tag('div', array('id' => 'page' . $page, 'class' => 'page'));
//    $time = quizinvideo_get_timeofvideo($attemptobj->get_quizinvideo()->id, $page);
//    $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'class' => 'timestamp',
//        'value' => $time, 'id' => 'timestamp'. $page));
    $slots = $attemptobj->get_slots($page - 1);    //parameter is offset here, which will be page-1.
    $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slotsinpage',
        'value' => implode(',', $slots)));
    // Print all the questions.
    foreach ($slots as $slot) {
        $output .= $attemptobj->render_question($slot, false);
    }
    $output .= html_writer::end_tag('div');
//}


$output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
$output .= html_writer::empty_tag('input', array('type' => 'button', 'id' => 'btn_checkForm', 'value' => get_string('next')));
$output .= html_writer::end_tag('div');



// Add a hidden field with questionids. Do this at the end of the form, so
// if you navigate before the form has finished loading, it does not wipe all
// the student's answers.


// Finish the form.
$output .= html_writer::end_tag('div');
$output .= html_writer::end_tag('form');


echo $output;