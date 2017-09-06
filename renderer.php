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
 * Defines the renderer for the quizinvideo module.
 *
 * @package   mod_quizinvideo
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * The renderer for the quizinvideo module.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizinvideo_renderer extends plugin_renderer_base {
    /**
     * Builds the review page
     *
     * @param quizinvideo_attempt $attemptobj an instance of quizinvideo_attempt.
     * @param array $slots an array of intgers relating to questions.
     * @param int $page the current page number
     * @param bool $showall whether to show entire attempt on one page.
     * @param bool $lastpage if true the current page is the last page.
     * @param mod_quizinvideo_display_options $displayoptions instance of mod_quizinvideo_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_page(quizinvideo_attempt $attemptobj, $slots, $page, $showall,
                                $lastpage, mod_quizinvideo_display_options $displayoptions,
                                $summarydata) {

        $output = '';
//        $output .= $this->header();
//        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, false, $displayoptions,
            $this->questions($attemptobj, true, $slots, $page, false, $displayoptions),
            $attemptobj);

        $output .= $this->review_next_navigation();
//        $output .= $this->footer();
        return $output;
    }

    /**
     * Builds the review page
     *
     * @param quizinvideo_attempt $attemptobj an instance of quizinvideo_attempt.
     * @param array $slots an array of intgers relating to questions.
     * @param int $page the current page number
     * @param bool $showall whether to show entire attempt on one page.
     * @param bool $lastpage if true the current page is the last page.
     * @param mod_quizinvideo_display_options $displayoptions instance of mod_quizinvideo_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_page_full(quizinvideo_attempt $attemptobj, $slots, $page, $showall,
                                     $lastpage, mod_quizinvideo_display_options $displayoptions,
                                     $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
            $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
            $attemptobj);

        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param quizinvideo_attempt $attemptobj an instance of quizinvideo_attempt.
     * @param int $slot which question to display.
     * @param int $seq which step of the question attempt to show. null = latest.
     * @param mod_quizinvideo_display_options $displayoptions instance of mod_quizinvideo_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_question_page(quizinvideo_attempt $attemptobj, $slot, $seq,
            mod_quizinvideo_display_options $displayoptions, $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, 0);

        if (!is_null($seq)) {
            $output .= $attemptobj->render_question_at_step($slot, $seq, true, $this);
        } else {
            $output .= $attemptobj->render_question($slot, true, $this);
        }

        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param string $message Why the review is not allowed.
     * @return string html to output.
     */
    public function review_question_not_allowed($message) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_quizinvideo_name(), true,
                                  array("context" => $attemptobj->get_quizinvideoobj()->get_context())));
        $output .= $this->notification($message);
        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Filters the summarydata array.
     *
     * @param array $summarydata contains row data for table
     * @param int $page the current page number
     * @return $summarydata containing filtered row data
     */
    protected function filter_review_summary_table($summarydata, $page) {
        if ($page == 0) {
            return $summarydata;
        }

        // Only show some of summary table on subsequent pages.
        foreach ($summarydata as $key => $rowdata) {
            if (!in_array($key, array('user', 'attemptlist'))) {
                unset($summarydata[$key]);
            }
        }

        return $summarydata;
    }

    /**
     * Outputs the table containing data from summary data array
     *
     * @param array $summarydata contains row data for table
     * @param int $page contains the current page number
     */
    public function review_summary_table($summarydata, $page) {
        $summarydata = $this->filter_review_summary_table($summarydata, $page);
        if (empty($summarydata)) {
            return '';
        }

        $output = '';
        $output .= html_writer::start_tag('table', array(
                'class' => 'generaltable generalbox quizinvideoreviewsummary'));
        $output .= html_writer::start_tag('tbody');
        foreach ($summarydata as $rowdata) {
            if ($rowdata['title'] instanceof renderable) {
                $title = $this->render($rowdata['title']);
            } else {
                $title = $rowdata['title'];
            }

            if ($rowdata['content'] instanceof renderable) {
                $content = $this->render($rowdata['content']);
            } else {
                $content = $rowdata['content'];
            }

            $output .= html_writer::tag('tr',
                html_writer::tag('th', $title, array('class' => 'cell', 'scope' => 'row')) .
                        html_writer::tag('td', $content, array('class' => 'cell'))
            );
        }

        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        return $output;
    }

    /**
     * Renders each question
     *
     * @param quizinvideo_attempt $attemptobj instance of quizinvideo_attempt
     * @param bool $reviewing
     * @param array $slots array of intgers relating to questions
     * @param int $page current page number
     * @param bool $showall if true shows attempt on single page
     * @param mod_quizinvideo_display_options $displayoptions instance of mod_quizinvideo_display_options
     */
    public function questions(quizinvideo_attempt $attemptobj, $reviewing, $slots, $page, $showall,
                              mod_quizinvideo_display_options $displayoptions) {
        $output = '';
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, $reviewing,
                    $attemptobj->review_url($slot, $page, $showall));
        }
        return $output;
    }

    /**
     * Renders the main bit of the review page.
     *
     * @param array $summarydata contain row data for table
     * @param int $page current page number
     * @param mod_quizinvideo_display_options $displayoptions instance of mod_quizinvideo_display_options
     * @param $content contains each question
     * @param quizinvideo_attempt $attemptobj instance of quizinvideo_attempt
     * @param bool $showall if true display attempt on one page
     */
    public function review_form($page, $showall, $displayoptions, $content, $attemptobj) {
//        if ($displayoptions->flags != question_display_options::EDITABLE) {
//            return $content;
//        }

        $this->page->requires->js_init_call('M.mod_quizinvideo.init_review_form', null, false,
                quizinvideo_get_js_module());

        $output = '';
        $output .= html_writer::start_tag('form', array('action' => $attemptobj->review_url(null,
                $page, $showall), 'method' => 'post', 'class' => 'questionflagsaveform'));
        $output .= html_writer::start_tag('div');
        $output .= $content;
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Returns either a liink or button
     *
     * @param quizinvideo_attempt $attemptobj instance of quizinvideo_attempt
     */
    public function finish_review_link(quizinvideo_attempt $attemptobj) {
        return html_writer::empty_tag('input', array('type' => 'button',
            'value' => get_string('finishreview', 'quizinvideo'),
            'id' => 'secureclosebutton'));
    }

    /**
     * Creates a next page arrow or the finishing link
     *
     * @param quizinvideo_attempt $attemptobj instance of quizinvideo_attempt
     * @param int $page the current page
     * @param bool $lastpage if true current page is the last page
     */
    public function review_next_navigation() {
        return html_writer::empty_tag('input', array('type' => 'button', 'id' => 'btn_continuevideo', 'value' => get_string('next')));
    }

    /**
     * Return the HTML of the quizinvideo timer.
     * @return string HTML content.
     */
    public function countdown_timer(quizinvideo_attempt $attemptobj, $timenow) {

        $timeleft = $attemptobj->get_time_left_display($timenow);
        if ($timeleft !== false) {
            $ispreview = $attemptobj->is_preview();
            $timerstartvalue = $timeleft;
            if (!$ispreview) {
                // Make sure the timer starts just above zero. If $timeleft was <= 0, then
                // this will just have the effect of causing the quizinvideo to be submitted immediately.
                $timerstartvalue = max($timerstartvalue, 1);
            }
            $this->initialise_timer($timerstartvalue, $ispreview);
        }

        return html_writer::tag('div', get_string('timeleft', 'quizinvideo') . ' ' .
                html_writer::tag('span', '', array('id' => 'quizinvideo-time-left')),
                array('id' => 'quizinvideo-timer', 'role' => 'timer',
                    'aria-atomic' => 'true', 'aria-relevant' => 'text'));
    }

    /**
     * Create a preview link
     *
     * @param $url contains a url to the given page
     */
    public function restart_preview_button($url) {
        return $this->single_button($url, get_string('startnewpreview', 'quizinvideo'));
    }

    /**
     * Outputs the navigation block panel
     *
     * @param quizinvideo_nav_panel_base $panel instance of quizinvideo_nav_panel_base
     */
    public function navigation_panel(quizinvideo_nav_panel_base $panel) {

        $output = '';
        $userpicture = $panel->user_picture();
        if ($userpicture) {
            $fullname = fullname($userpicture->user);
            if ($userpicture->size === true) {
                $fullname = html_writer::div($fullname);
            }
            $output .= html_writer::tag('div', $this->render($userpicture) . $fullname,
                    array('id' => 'user-picture', 'class' => 'clearfix'));
        }
        $output .= $panel->render_before_button_bits($this);

        $output .= html_writer::tag('div', $panel->render_end_bits($this),
                array('class' => 'othernav'));

        $this->page->requires->js_init_call('M.mod_quizinvideo.nav.init', null, false,
                quizinvideo_get_js_module());

        return $output;
    }

    /**
     * Display a quizinvideo navigation button.
     *
     * @param quizinvideo_nav_question_button $button
     * @return string HTML fragment.
     */
    protected function render_quizinvideo_nav_question_button(quizinvideo_nav_question_button $button) {
        $classes = array('qnbutton', $button->stateclass, $button->navmethod);
        $extrainfo = array();

        if ($button->currentpage) {
            $classes[] = 'thispage';
            $extrainfo[] = get_string('onthispage', 'quizinvideo');
        }

        // Flagged?
        if ($button->flagged) {
            $classes[] = 'flagged';
            $flaglabel = get_string('flagged', 'question');
        } else {
            $flaglabel = '';
        }
        $extrainfo[] = html_writer::tag('span', $flaglabel, array('class' => 'flagstate'));

        if (is_numeric($button->number)) {
            $qnostring = 'questionnonav';
        } else {
            $qnostring = 'questionnonavinfo';
        }

        $a = new stdClass();
        $a->number = $button->number;
        $a->attributes = implode(' ', $extrainfo);
        $tagcontents = html_writer::tag('span', '', array('class' => 'thispageholder')) .
                        html_writer::tag('span', '', array('class' => 'trafficlight')) .
                        get_string($qnostring, 'quizinvideo', $a);
        $tagattributes = array('class' => implode(' ', $classes), 'id' => $button->id,
                                  'title' => $button->statestring, 'data-quizinvideo-page' => $button->page);

        if ($button->url) {
            return html_writer::link($button->url, $tagcontents, $tagattributes);
        } else {
            return html_writer::tag('span', $tagcontents, $tagattributes);
        }
    }

    /**
     * Display a quizinvideo navigation heading.
     *
     * @param quizinvideo_nav_section_heading $heading the heading.
     * @return string HTML fragment.
     */
    protected function render_quizinvideo_nav_section_heading(quizinvideo_nav_section_heading $heading) {
        return $this->heading($heading->heading, 3, 'mod_quizinvideo-section-heading');
    }

    /**
     * outputs the link the other attempts.
     *
     * @param mod_quizinvideo_links_to_other_attempts $links
     */
    protected function render_mod_quizinvideo_links_to_other_attempts(
            mod_quizinvideo_links_to_other_attempts $links) {
        $attemptlinks = array();
        foreach ($links->links as $attempt => $url) {
            if (!$url) {
                $attemptlinks[] = html_writer::tag('strong', $attempt);
            } else if ($url instanceof renderable) {
                $attemptlinks[] = $this->render($url);
            } else {
                $attemptlinks[] = html_writer::link($url, $attempt);
            }
        }
        return implode(', ', $attemptlinks);
    }

    public function start_attempt_page(quizinvideo $quizinvideoobj, mod_quizinvideo_preflight_check_form $mform) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($quizinvideoobj->get_quizinvideo_name(), true,
                                  array("context" => $quizinvideoobj->get_context())));
        $output .= $this->quizinvideo_intro($quizinvideoobj->get_quizinvideo(), $quizinvideoobj->get_cm());
        ob_start();
        $mform->display();
        $output .= ob_get_clean();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Attempt Page
     *
     * @param quizinvideo_attempt $attemptobj Instance of quizinvideo_attempt
     * @param int $page Current page number
     * @param quizinvideo_access_manager $accessmanager Instance of quizinvideo_access_manager
     * @param array $messages An array of messages
     * @param array $slots Contains an array of integers that relate to questions
     * @param int $id The ID of an attempt
     */
    public function attempt_page($attemptobj, $accessmanager, $messages, $id) {
        $output = '';
        $output .= $this->header();
        $output .= "<style>";
        $output .= '@font-face {';
        $output .= '    font-family: VideoJS;';
        $output .= '    src: url(/mod/quizinvideo/videojs/font/VideoJS.eot);';
        $output .= '    src: url(/mod/quizinvideo/videojs/font/VideoJS.eot?#iefix) format("embedded-opentype"), url(/mod/quizinvideo/videojs/font/VideoJS.woff) format("woff"), url(/mod/quizinvideo/videojs/font/VideoJS.ttf) format("truetype"), url(/mod/quizinvideo/videojs/font/VideoJS.svg#icomoon) format("svg");';
        $output .= '    font-weight: 400;';
        $output .= '    font-style: normal';
        $output .= '}';
        $output .= '</style>';

        $output .= $this->quizinvideo_notices($messages);
        $output .= $this->show_video($attemptobj);
        $output .= $this->print_hidden_DOMs($attemptobj);
        $output .= $this->print_review_button($attemptobj);

        $output .= $this->footer();
        return $output;
    }

    /**
     * Returns any notices.
     *
     * @param array $messages
     */
    public function quizinvideo_notices($messages) {
        if (!$messages) {
            return '';
        }
        return $this->box($this->heading(get_string('accessnoticesheader', 'quizinvideo'), 3) .
                $this->access_messages($messages), 'quizinvideoaccessnotices');
    }

    /**
     * Ouputs the form for making an attempt
     *
     * @param quizinvideo_attempt $attemptobj
     * @param int $id ID of the attempt
     */
    public function attempt_form($attemptobj, $id) {
        $output = '';

        // Start the form.
        $output .= html_writer::start_tag('form',
            array('method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'responseform'));
        $output .= html_writer::start_tag('div');
        $num_pages = $attemptobj->get_num_pages();

        for($i = 0; $i < $num_pages; $i++)
        {
            $page = $i + 1;
            $output .= html_writer::start_tag('div', array('id' => 'page' . $page, 'class' => 'page', 'style' => 'display:none'));
            $time = quizinvideo_get_timeofvideo($attemptobj->get_quizinvideo()->id, $page);
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'class' => 'timestamp',
                'value' => $time, 'id' => 'timestamp'. $page));
            $slots = $attemptobj->get_slots($i);    //$i is offset here, which will be page-1.
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slotsinpage',
                'value' => implode(',', $slots)));
            // Print all the questions.
            foreach ($slots as $slot) {
                $output .= $attemptobj->render_question($slot, false, $this,
                        $attemptobj->attempt_url($slot), $this);
            }
            $output .= html_writer::end_tag('div');
        }

        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'button', 'id' => 'btn_checkForm', 'value' => 'Check'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'next',
            'value' => get_string('next')));
        $output .= html_writer::end_tag('div');

        // Some hidden fields to trach what is going on.
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
                'value' => $attemptobj->get_attemptid()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'timeup',
                'value' => '0', 'id' => 'timeup'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos',
                'value' => '', 'id' => 'scrollpos'));

        // Add a hidden field with questionids. Do this at the end of the form, so
        // if you navigate before the form has finished loading, it does not wipe all
        // the student's answers.
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
                'value' => implode(',', $attemptobj->get_active_slots($page))));

        // Finish the form.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        $output .= $this->connection_warning();

        return $output;
    }

    /**
     * Render a button which allows students to redo a question in the attempt.
     *
     * @param int $slot the number of the slot to generate the button for.
     * @param bool $disabled if true, output the button disabled.
     * @return string HTML fragment.
     */
    public function redo_question_button($slot, $disabled) {
        $attributes = array('type' => 'submit',  'name' => 'redoslot' . $slot,
                'value' => get_string('redoquestion', 'quizinvideo'), 'class' => 'mod_quizinvideo-redo_question_button');
        if ($disabled) {
            $attributes['disabled'] = 'disabled';
        }
        return html_writer::div(html_writer::empty_tag('input', $attributes));
    }

    /**
     * Output the JavaScript required to initialise the countdown timer.
     * @param int $timerstartvalue time remaining, in seconds.
     */
    public function initialise_timer($timerstartvalue, $ispreview) {
        $options = array($timerstartvalue, (bool)$ispreview);
        $this->page->requires->js_init_call('M.mod_quizinvideo.timer.init', $options, false, quizinvideo_get_js_module());
    }

    /**
     * Output a page with an optional message, and JavaScript code to close the
     * current window and redirect the parent window to a new URL.
     * @param moodle_url $url the URL to redirect the parent window to.
     * @param string $message message to display before closing the window. (optional)
     * @return string HTML to output.
     */
    public function close_attempt_popup($url, $message = '') {
        $output = '';
        $output .= $this->header();
        $output .= $this->box_start();

        if ($message) {
            $output .= html_writer::tag('p', $message);
            $output .= html_writer::tag('p', get_string('windowclosing', 'quizinvideo'));
            $delay = 5;
        } else {
            $output .= html_writer::tag('p', get_string('pleaseclose', 'quizinvideo'));
            $delay = 0;
        }
        $this->page->requires->js_init_call('M.mod_quizinvideo.secure_window.close',
                array($url, $delay), false, quizinvideo_get_js_module());

        $output .= $this->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Print each message in an array, surrounded by &lt;p>, &lt;/p> tags.
     *
     * @param array $messages the array of message strings.
     * @param bool $return if true, return a string, instead of outputting.
     *
     * @return string HTML to output.
     */
    public function access_messages($messages) {
        $output = '';
        foreach ($messages as $message) {
            $output .= html_writer::tag('p', $message) . "\n";
        }
        return $output;
    }

    /*
     * Summary Page
     */
    /**
     * Create the summary page
     *
     * @param quizinvideo_attempt $attemptobj
     * @param mod_quizinvideo_display_options $displayoptions
     */
    public function summary_page($attemptobj, $displayoptions) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_quizinvideo_name()));
        $output .= $this->heading(get_string('summaryofattempt', 'quizinvideo'), 3);
        $output .= $this->summary_table($attemptobj, $displayoptions);
        $output .= $this->summary_page_controls($attemptobj);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Generates the table of summarydata
     *
     * @param quizinvideo_attempt $attemptobj
     * @param mod_quizinvideo_display_options $displayoptions
     */
    public function summary_table($attemptobj, $displayoptions) {
        // Prepare the summary table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizinvideosummaryofattempt boxaligncenter';
        $table->head = array(get_string('question', 'quizinvideo'), get_string('status', 'quizinvideo'));
        $table->align = array('left', 'left');
        $table->size = array('', '');
        $markscolumn = $displayoptions->marks >= question_display_options::MARK_AND_MAX;
        if ($markscolumn) {
            $table->head[] = get_string('marks', 'quizinvideo');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        $tablewidth = count($table->align);
        $table->data = array();

        // Get the summary info for each question.
        $slots = $attemptobj->get_slots();
        foreach ($slots as $slot) {
            // Add a section headings if we need one here.
            $heading = $attemptobj->get_heading_before_slot($slot);
            if ($heading) {
                $cell = new html_table_cell(format_string($heading));
                $cell->header = true;
                $cell->colspan = $tablewidth;
                $table->data[] = array($cell);
            }

            // Don't display information items.
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }

            // Real question, show it.
            $flag = '';
            if ($attemptobj->is_question_flagged($slot)) {
                $flag = html_writer::empty_tag('img', array('src' => $this->pix_url('i/flagged'),
                        'alt' => get_string('flagged', 'question'), 'class' => 'questionflag icon-post'));
            }
            if ($attemptobj->can_navigate_to($slot)) {
                $row = array(html_writer::link($attemptobj->attempt_url($slot),
                        $attemptobj->get_question_number($slot) . $flag),
                        $attemptobj->get_question_status($slot, $displayoptions->correctness));
            } else {
                $row = array($attemptobj->get_question_number($slot) . $flag,
                                $attemptobj->get_question_status($slot, $displayoptions->correctness));
            }
            if ($markscolumn) {
                $row[] = $attemptobj->get_question_mark($slot);
            }
            $table->data[] = $row;
            $table->rowclasses[] = 'quizinvideosummary' . $slot . ' ' . $attemptobj->get_question_state_class(
                    $slot, $displayoptions->correctness);
        }

        // Print the summary table.
        $output = html_writer::table($table);

        return $output;
    }

    /**
     * Creates any controls a the page should have.
     *
     * @param quizinvideo_attempt $attemptobj
     */
    public function summary_page_controls($attemptobj) {
        $output = '';

        // Return to place button.
        if ($attemptobj->get_state() == quizinvideo_attempt::IN_PROGRESS) {
            $button = new single_button(
                    new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage())),
                    get_string('returnattempt', 'quizinvideo'));
            $output .= $this->container($this->container($this->render($button),
                    'controls'), 'submitbtns mdl-align');
        }

        // Finish attempt button.
        $options = array(
            'attempt' => $attemptobj->get_attemptid(),
            'finishattempt' => 1,
            'timeup' => 0,
            'slots' => '',
            'sesskey' => sesskey(),
        );

        $button = new single_button(
                new moodle_url($attemptobj->processattempt_url(), $options),
                get_string('submitallandfinish', 'quizinvideo'));
        $button->id = 'responseform';
        if ($attemptobj->get_state() == quizinvideo_attempt::IN_PROGRESS) {
            $button->add_action(new confirm_action(get_string('confirmclose', 'quizinvideo'), null,
                    get_string('submitallandfinish', 'quizinvideo')));
        }

        $duedate = $attemptobj->get_due_date();
        $message = '';
        if ($attemptobj->get_state() == quizinvideo_attempt::OVERDUE) {
            $message = get_string('overduemustbesubmittedby', 'quizinvideo', userdate($duedate));

        } else if ($duedate) {
            $message = get_string('mustbesubmittedby', 'quizinvideo', userdate($duedate));
        }

        $output .= $this->countdown_timer($attemptobj, time());
        $output .= $this->container($message . $this->container(
                $this->render($button), 'controls'), 'submitbtns mdl-align');

        return $output;
    }

    /*
     * View Page
     */
    /**
     * Generates the view page
     *
     * @param int $course The id of the course
     * @param array $quizinvideo Array conting quizinvideo data
     * @param int $cm Course Module ID
     * @param int $context The page context ID
     * @param array $infomessages information about this quizinvideo
     * @param mod_quizinvideo_view_object $viewobj
     * @param string $buttontext text for the start/continue attempt button, if
     *      it should be shown.
     * @param array $infomessages further information about why the student cannot
     *      attempt this quizinvideo now, if appicable this quizinvideo
     */
    public function view_page($course, $quizinvideo, $cm, $context, $viewobj) {
        $output = '';
        $output .= $this->view_information($quizinvideo, $cm, $context, $viewobj->infomessages);
        $output .= $this->view_table($quizinvideo, $context, $viewobj);
        $output .= $this->view_result_info($quizinvideo, $context, $cm, $viewobj);
        $output .= $this->box($this->view_page_buttons($viewobj), 'quizinvideoattempt');
        return $output;
    }

    /**
     * Work out, and render, whatever buttons, and surrounding info, should appear
     * at the end of the review page.
     * @param mod_quizinvideo_view_object $viewobj the information required to display
     * the view page.
     * @return string HTML to output.
     */
    public function view_page_buttons(mod_quizinvideo_view_object $viewobj) {
        global $CFG;
        $output = '';

        if (!$viewobj->quizinvideohasquestions) {
            $output .= $this->no_questions_message($viewobj->canedit, $viewobj->editurl);
        }

        $output .= $this->access_messages($viewobj->preventmessages);

        if ($viewobj->buttontext) {
            $output .= $this->start_attempt_button($viewobj->buttontext,
                    $viewobj->startattempturl, $viewobj->startattemptwarning,
                    $viewobj->popuprequired, $viewobj->popupoptions);

        }

        if ($viewobj->showbacktocourse) {
            $output .= $this->single_button($viewobj->backtocourseurl,
                    get_string('backtocourse', 'quizinvideo'), 'get',
                    array('class' => 'continuebutton'));
        }

        return $output;
    }

    /**
     * Generates the view attempt button
     *
     * @param int $course The course ID
     * @param array $quizinvideo Array containging quizinvideo date
     * @param int $cm The Course Module ID
     * @param int $context The page Context ID
     * @param mod_quizinvideo_view_object $viewobj
     * @param string $buttontext
     */
    public function start_attempt_button($buttontext, moodle_url $url,
            $startattemptwarning, $popuprequired, $popupoptions) {

        $button = new single_button($url, $buttontext);
        $button->class .= ' quizinvideostartbuttondiv';

        $warning = '';
        if ($popuprequired) {
            $this->page->requires->js_module(quizinvideo_get_js_module());
            $this->page->requires->js('/mod/quizinvideo/module.js');
            $popupaction = new popup_action('click', $url, 'quizinvideopopup', $popupoptions);

            $button->class .= ' quizinvideosecuremoderequired';
            $button->add_action(new component_action('click',
                    'M.mod_quizinvideo.secure_window.start_attempt_action', array(
                        'url' => $url->out(false),
                        'windowname' => 'quizinvideopopup',
                        'options' => $popupaction->get_js_options(),
                        'fullscreen' => true,
                        'startattemptwarning' => $startattemptwarning,
                    )));

            $warning = html_writer::tag('noscript', $this->heading(get_string('noscript', 'quizinvideo')));

        } else if ($startattemptwarning) {
            $button->add_action(new confirm_action($startattemptwarning, null,
                    get_string('startattempt', 'quizinvideo')));
        }

        return $this->render($button) . $warning;
    }

    /**
     * Generate a message saying that this quizinvideo has no questions, with a button to
     * go to the edit page, if the user has the right capability.
     * @param object $quizinvideo the quizinvideo settings.
     * @param object $cm the course_module object.
     * @param object $context the quizinvideo context.
     * @return string HTML to output.
     */
    public function no_questions_message($canedit, $editurl) {
        $output = '';
        $output .= $this->notification(get_string('noquestions', 'quizinvideo'));
        if ($canedit) {
            $output .= $this->single_button($editurl, get_string('editquizinvideo', 'quizinvideo'), 'get');
        }

        return $output;
    }

    /**
     * Outputs an error message for any guests accessing the quizinvideo
     *
     * @param int $course The course ID
     * @param array $quizinvideo Array contingin quizinvideo data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_guest($course, $quizinvideo, $cm, $context, $messages) {
        $output = '';
        $output .= $this->view_information($quizinvideo, $cm, $context, $messages);
        $guestno = html_writer::tag('p', get_string('guestsno', 'quizinvideo'));
        $liketologin = html_writer::tag('p', get_string('liketologin'));
        $referer = get_local_referer(false);
        $output .= $this->confirm($guestno."\n\n".$liketologin."\n", get_login_url(), $referer);
        return $output;
    }

    /**
     * Outputs and error message for anyone who is not enrolle don the course
     *
     * @param int $course The course ID
     * @param array $quizinvideo Array contingin quizinvideo data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_notenrolled($course, $quizinvideo, $cm, $context, $messages) {
        global $CFG;
        $output = '';
        $output .= $this->view_information($quizinvideo, $cm, $context, $messages);
        $youneedtoenrol = html_writer::tag('p', get_string('youneedtoenrol', 'quizinvideo'));
        $button = html_writer::tag('p',
                $this->continue_button($CFG->wwwroot . '/course/view.php?id=' . $course->id));
        $output .= $this->box($youneedtoenrol."\n\n".$button."\n", 'generalbox', 'notice');
        return $output;
    }

    /**
     * Output the page information
     *
     * @param object $quizinvideo the quizinvideo settings.
     * @param object $cm the course_module object.
     * @param object $context the quizinvideo context.
     * @param array $messages any access messages that should be described.
     * @return string HTML to output.
     */
    public function view_information($quizinvideo, $cm, $context, $messages) {
        global $CFG;

        $output = '';

        // Print quizinvideo name and description.
        $output .= $this->heading(format_string($quizinvideo->name));
        $output .= $this->quizinvideo_intro($quizinvideo, $cm);

        // Output any access messages.
        if ($messages) {
            $output .= $this->box($this->access_messages($messages), 'quizinvideoinfo');
        }

        // Show number of attempts summary to those who can view reports.
        if (has_capability('mod/quizinvideo:viewreports', $context)) {
            if ($strattemptnum = $this->quizinvideo_attempt_summary_link_to_reports($quizinvideo, $cm,
                    $context)) {
                $output .= html_writer::tag('div', $strattemptnum,
                        array('class' => 'quizinvideoattemptcounts'));
            }
        }
        return $output;
    }

    /**
     * Output the quizinvideo intro.
     * @param object $quizinvideo the quizinvideo settings.
     * @param object $cm the course_module object.
     * @return string HTML to output.
     */
    public function quizinvideo_intro($quizinvideo, $cm) {
        if (html_is_blank($quizinvideo->intro)) {
            return '';
        }

        return $this->box(format_module_intro('quizinvideo', $quizinvideo, $cm->id), 'generalbox', 'intro');
    }

    /**
     * Generates the table heading.
     */
    public function view_table_heading() {
        return $this->heading(get_string('summaryofattempts', 'quizinvideo'), 3);
    }

    /**
     * Generates the table of data
     *
     * @param array $quizinvideo Array contining quizinvideo data
     * @param int $context The page context ID
     * @param mod_quizinvideo_view_object $viewobj
     */
    public function view_table($quizinvideo, $context, $viewobj) {
        if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizinvideoattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'quizinvideo');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('attemptstate', 'quizinvideo');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'quizinvideo') . ' / ' .
                    quizinvideo_format_grade($quizinvideo, $quizinvideo->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('grade') . ' / ' .
                    quizinvideo_format_grade($quizinvideo, $quizinvideo->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'quizinvideo');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'quizinvideo');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = array();

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'quizinvideo');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {
                    $row[] = quizinvideo_format_grade($quizinvideo, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = quizinvideo_rescale_grade($attemptobj->get_sum_marks(), $quizinvideo, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview()
                            && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                            && $attemptobj->get_state() == quizinvideo_attempt::FINISHED
                            && $attemptgrade == $viewobj->mygrade
                            && $quizinvideo->grademethod == quizinvideo_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = quizinvideo_format_grade($quizinvideo, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attemptobj->get_attempt(),
                        $attemptoptions, $this);
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = quizinvideo_feedback_for_grade($attemptgrade, $quizinvideo, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

    /**
     * Generate a brief textual desciption of the current state of an attempt.
     * @param quizinvideo_attempt $attemptobj the attempt
     * @param int $timenow the time to use as 'now'.
     * @return string the appropriate lang string to describe the state.
     */
    public function attempt_state($attemptobj) {
        switch ($attemptobj->get_state()) {
            case quizinvideo_attempt::IN_PROGRESS:
                return get_string('stateinprogress', 'quizinvideo');

            case quizinvideo_attempt::OVERDUE:
                return get_string('stateoverdue', 'quizinvideo') . html_writer::tag('span',
                        get_string('stateoverduedetails', 'quizinvideo',
                                userdate($attemptobj->get_due_date())),
                        array('class' => 'statedetails'));

            case quizinvideo_attempt::FINISHED:
                return get_string('statefinished', 'quizinvideo') . html_writer::tag('span',
                        get_string('statefinisheddetails', 'quizinvideo',
                                userdate($attemptobj->get_submitted_date())),
                        array('class' => 'statedetails'));

            case quizinvideo_attempt::ABANDONED:
                return get_string('stateabandoned', 'quizinvideo');
        }
    }

    /**
     * Generates data pertaining to quizinvideo results
     *
     * @param array $quizinvideo Array containing quizinvideo data
     * @param int $context The page context ID
     * @param int $cm The Course Module Id
     * @param mod_quizinvideo_view_object $viewobj
     */
    public function view_result_info($quizinvideo, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = quizinvideo_get_grading_option_name($quizinvideo->grademethod);
                $a->mygrade = quizinvideo_format_grade($quizinvideo, $viewobj->mygrade);
                $a->quizinvideograde = quizinvideo_format_grade($quizinvideo, $quizinvideo->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'quizinvideo', $a), 3);
            } else {
                $a = new stdClass();
                $a->grade = quizinvideo_format_grade($quizinvideo, $viewobj->mygrade);
                $a->maxgrade = quizinvideo_format_grade($quizinvideo, $quizinvideo->grade);
                $a = get_string('outofshort', 'quizinvideo', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'quizinvideo', $a), 3);
            }
        }

        if ($viewobj->mygradeoverridden) {

            $resultinfo .= html_writer::tag('p', get_string('overriddennotice', 'grades'),
                    array('class' => 'overriddennotice'))."\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'quizinvideo'), 3);
            $resultinfo .= html_writer::div($viewobj->gradebookfeedback, 'quizinvideoteacherfeedback') . "\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'quizinvideo'), 3);
            $resultinfo .= html_writer::div(
                    quizinvideo_feedback_for_grade($viewobj->mygrade, $quizinvideo, $context),
                    'quizinvideogradefeedback') . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }

    /**
     * Output either a link to the review page for an attempt, or a button to
     * open the review in a popup window.
     *
     * @param moodle_url $url of the target page.
     * @param bool $reviewinpopup whether a pop-up is required.
     * @param array $popupoptions options to pass to the popup_action constructor.
     * @return string HTML to output.
     */
    public function review_link($url, $reviewinpopup, $popupoptions) {
        if ($reviewinpopup) {
            $button = new single_button($url, get_string('review', 'quizinvideo'));
            $button->add_action(new popup_action('click', $url, 'quizinvideopopup', $popupoptions));
            return $this->render($button);

        } else {
            return html_writer::link($url, get_string('review', 'quizinvideo'),
                    array('title' => get_string('reviewthisattempt', 'quizinvideo')));
        }
    }

    /**
     * Displayed where there might normally be a review link, to explain why the
     * review is not available at this time.
     * @param string $message optional message explaining why the review is not possible.
     * @return string HTML to output.
     */
    public function no_review_message($message) {
        return html_writer::nonempty_tag('span', $message,
                array('class' => 'noreviewmessage'));
    }

    /**
     * Returns the same as {@link quizinvideo_num_attempt_summary()} but wrapped in a link
     * to the quizinvideo reports.
     *
     * @param object $quizinvideo the quizinvideo object. Only $quizinvideo->id is used at the moment.
     * @param object $cm the cm object. Only $cm->course, $cm->groupmode and $cm->groupingid
     * fields are used at the moment.
     * @param object $context the quizinvideo context.
     * @param bool $returnzero if false (default), when no attempts have been made '' is returned
     * instead of 'Attempts: 0'.
     * @param int $currentgroup if there is a concept of current group where this method is being
     * called
     *         (e.g. a report) pass it in here. Default 0 which means no current group.
     * @return string HTML fragment for the link.
     */
    public function quizinvideo_attempt_summary_link_to_reports($quizinvideo, $cm, $context,
                                                          $returnzero = false, $currentgroup = 0) {
        global $CFG;
        $summary = quizinvideo_num_attempt_summary($quizinvideo, $cm, $returnzero, $currentgroup);
        if (!$summary) {
            return '';
        }

        require_once($CFG->dirroot . '/mod/quizinvideo/report/reportlib.php');
        $url = new moodle_url('/mod/quizinvideo/report.php', array(
                'id' => $cm->id, 'mode' => quizinvideo_report_default_report($context)));
        return html_writer::link($url, $summary);
    }

    /**
     * Output a graph, or a message saying that GD is required.
     * @param moodle_url $url the URL of the graph.
     * @param string $title the title to display above the graph.
     * @return string HTML fragment for the graph.
     */
    public function graph(moodle_url $url, $title) {
        global $CFG;

        $graph = html_writer::empty_tag('img', array('src' => $url, 'alt' => $title));

        return $this->heading($title, 3) . html_writer::tag('div', $graph, array('class' => 'graph'));
    }

    /**
     * Output the connection warning messages, which are initially hidden, and
     * only revealed by JavaScript if necessary.
     */
    public function connection_warning() {
        $options = array('filter' => false, 'newlines' => false);
        $warning = format_text(get_string('connectionerror', 'quizinvideo'), FORMAT_MARKDOWN, $options);
        $ok = format_text(get_string('connectionok', 'quizinvideo'), FORMAT_MARKDOWN, $options);
        return html_writer::tag('div', $warning,
                    array('id' => 'connection-error', 'style' => 'display: none;', 'role' => 'alert')) .
                    html_writer::tag('div', $ok, array('id' => 'connection-ok', 'style' => 'display: none;', 'role' => 'alert'));
    }

    /**
     * Return the video element to be displayed in the attempt page.
     *
     * @param $url the url of the video.
     * @return string HTML.
     */
    private function show_video($attemptobj)
    {
        $this->page->requires->js('/mod/quizinvideo/videojs/jquery.js');
        $this->page->requires->js('/mod/quizinvideo/videojs/video.js');
        $this->page->requires->js('/mod/quizinvideo/videojs/videojs-markers.js');
        $this->page->requires->js('/mod/quizinvideo/videojs/youtube.js');
        $url = $attemptobj->get_quizinvideoobj()->get_quizinvideo_videourl();
        $output = '';
        if (preg_match('/^(https?\:\/\/)?(www\.youtube\.com|youtu\.?be)\/.+$/', $url)) {
            $youtube = true;
        } else {
            $youtube = false;
        }
        $output .= html_writer::start_tag('div', array('id'=>'video_div'));
        $output .= html_writer::start_tag('video', array( 'id'=>'video_content','data-setup' => $youtube ? '{"techOrder": ["youtube"], "sources": [{ "type": "video/youtube", "src": "'.$url.'"}]}' : '{ "playbackRates": [0.5, 1, 1.5, 2] }', 'preload'=>'auto', 'controls'=>'', 'autoplay' => 'autoplay', 'class' => 'video-js  vjs-default-skin'));
        if (!$youtube)
        {
            if (substr($url, 0, 4) === "rtmp")
                $output .= html_writer::start_tag('source', array('src' => $url, 'type' => 'rtmp/mp4'));
            else
                $output .= html_writer::start_tag('source', array('src' => $url));
        }
        $output .= html_writer::end_tag('source');
        $output .= html_writer::end_tag('video');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    private function print_review_button($attemptobj)
    {
        return $this->render(new single_button($attemptobj->review_url(null, -1, true), get_string('review', 'quizinvideo')));
    }

    private function print_hidden_DOMs($attemptobj){
        $num_pages = $attemptobj->get_num_pages();
        $output = '';
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
            'value' => $attemptobj->get_attemptid()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
            'value' => sesskey()));
        for($i = 0; $i < $num_pages; $i++){
            $page = $i + 1;
            $time = quizinvideo_get_timeofvideo($attemptobj->get_quizinvideo()->id, $page);
            $section_name = quizinvideo_get_sectionofvideo($attemptobj->get_quizinvideo()->id, $page);
            if($time) {
                $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'class' => 'timestamp',
                    'value' => $time, 'id' => 'timestamp' . $page));
            }
            if($section_name) {
                $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'class' => 'timestamp_title',
                    'value' => $section_name, 'id' => 'timestamp_title' . $page));
            }
            else{
                $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'class' => 'timestamp_title',
                    'value' => null, 'id' => 'timestamp_title' . $page));
            }
        }
        return $output;
    }
}


class mod_quizinvideo_links_to_other_attempts implements renderable {
    /**
     * @var array string attempt number => url, or null for the current attempt.
     * url may be either a moodle_url, or a renderable.
     */
    public $links = array();
}


class mod_quizinvideo_view_object {
    /** @var array $infomessages of messages with information to display about the quizinvideo. */
    public $infomessages;
    /** @var array $attempts contains all the user's attempts at this quizinvideo. */
    public $attempts;
    /** @var array $attemptobjs quizinvideo_attempt objects corresponding to $attempts. */
    public $attemptobjs;
    /** @var quizinvideo_access_manager $accessmanager contains various access rules. */
    public $accessmanager;
    /** @var bool $canreviewmine whether the current user has the capability to
     *       review their own attempts. */
    public $canreviewmine;
    /** @var bool $canedit whether the current user has the capability to edit the quizinvideo. */
    public $canedit;
    /** @var moodle_url $editurl the URL for editing this quizinvideo. */
    public $editurl;
    /** @var int $attemptcolumn contains the number of attempts done. */
    public $attemptcolumn;
    /** @var int $gradecolumn contains the grades of any attempts. */
    public $gradecolumn;
    /** @var int $markcolumn contains the marks of any attempt. */
    public $markcolumn;
    /** @var int $overallstats contains all marks for any attempt. */
    public $overallstats;
    /** @var string $feedbackcolumn contains any feedback for and attempt. */
    public $feedbackcolumn;
    /** @var string $timenow contains a timestamp in string format. */
    public $timenow;
    /** @var int $numattempts contains the total number of attempts. */
    public $numattempts;
    /** @var float $mygrade contains the user's final grade for a quizinvideo. */
    public $mygrade;
    /** @var bool $moreattempts whether this user is allowed more attempts. */
    public $moreattempts;
    /** @var int $mygradeoverridden contains an overriden grade. */
    public $mygradeoverridden;
    /** @var string $gradebookfeedback contains any feedback for a gradebook. */
    public $gradebookfeedback;
    /** @var bool $unfinished contains 1 if an attempt is unfinished. */
    public $unfinished;
    /** @var object $lastfinishedattempt the last attempt from the attempts array. */
    public $lastfinishedattempt;
    /** @var array $preventmessages of messages telling the user why they can't
     *       attempt the quizinvideo now. */
    public $preventmessages;
    /** @var string $buttontext caption for the start attempt button. If this is null, show no
     *      button, or if it is '' show a back to the course button. */
    public $buttontext;
    /** @var string $startattemptwarning alert to show the user before starting an attempt. */
    public $startattemptwarning;
    /** @var moodle_url $startattempturl URL to start an attempt. */
    public $startattempturl;
    /** @var moodle_url $startattempturl URL for any Back to the course button. */
    public $backtocourseurl;
    /** @var bool $showbacktocourse should we show a back to the course button? */
    public $showbacktocourse;
    /** @var bool whether the attempt must take place in a popup window. */
    public $popuprequired;
    /** @var array options to use for the popup window, if required. */
    public $popupoptions;
    /** @var bool $quizinvideohasquestions whether the quizinvideo has any questions. */
    public $quizinvideohasquestions;
}
