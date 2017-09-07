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
 * Renderer outputting the quizinvideo editing UI.
 *
 * @package mod_quizinvideo
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quizinvideo\output;
defined('MOODLE_INTERNAL') || die();

use \mod_quizinvideo\structure;
use \html_writer;

/**
 * Renderer outputting the quizinvideo editing UI.
 *
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */
class edit_renderer extends \plugin_renderer_base {

    /**
     * Render the edit page
     *
     * @param \quizinvideo $quizinvideoobj object containing all the quizinvideo settings information.
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link question_edit_setup()}.
     * @return string HTML to output.
     */
    public function edit_page(\quizinvideo $quizinvideoobj, structure $structure,
            \question_edit_contexts $contexts, \moodle_url $pageurl, array $pagevars) {
        $output = '';

        // Page title.
        $output .= $this->heading_with_help(get_string('editingquizinvideox', 'quizinvideo',
                format_string($quizinvideoobj->get_quizinvideo_name())), 'editingquizinvideo', 'quizinvideo', '',
                get_string('basicideasofquizinvideo', 'quizinvideo'), 2);

        //video at the top
        $output .= $this->show_video($quizinvideoobj->get_quizinvideo_videourl());

        // Information at the top.
        $output .= $this->quizinvideo_state_warnings($structure);
        $output .= $this->quizinvideo_information($structure);
        $output .= $this->maximum_grade_input($structure, $pageurl);
        $output .= $this->repaginate_button($structure, $pageurl);
        $output .= $this->total_marks($quizinvideoobj->get_quizinvideo());

        // Show the questions organised into sections and pages.
        $output .= $this->start_section_list();

        foreach ($structure->get_sections() as $section) {
            $output .= $this->start_section($structure, $section);
            $output .= $this->questions_in_section($structure, $section, $contexts, $pagevars, $pageurl);

            if ($structure->is_last_section($section)) {
                $output .= \html_writer::start_div('last-add-menu');
                $output .= html_writer::tag('span', $this->add_menu_actions($structure, 0,
                        $pageurl, $contexts, $pagevars), array('class' => 'add-menu-outer'));
                $output .= \html_writer::end_div();
            }

            $output .= $this->end_section();
        }

        $output .= $this->end_section_list();

        // Initialise the JavaScript.
        $this->initialise_editing_javascript($structure, $contexts, $pagevars, $pageurl);

        // Include the contents of any other popups required.
        if ($structure->can_be_edited()) {
            $popups = '';

            $popups .= $this->question_bank_loading();
            $this->page->requires->yui_module('moodle-mod_quizinvideo-quizinvideoquestionbank',
                    'M.mod_quizinvideo.quizinvideoquestionbank.init',
                    array('class' => 'questionbank', 'cmid' => $structure->get_cmid()));

            $popups .= $this->random_question_form($pageurl, $contexts, $pagevars);
            $this->page->requires->yui_module('moodle-mod_quizinvideo-randomquestion',
                    'M.mod_quizinvideo.randomquestion.init');

            $output .= html_writer::div($popups, 'mod_quizinvideo_edit_forms');

            // Include the question chooser.
            $output .= $this->question_chooser();
            $this->page->requires->yui_module('moodle-mod_quizinvideo-questionchooser', 'M.mod_quizinvideo.init_questionchooser');
        }

        return $output;
    }

    /**
     * Render any warnings that might be required about the state of the quizinvideo,
     * e.g. if it has been attempted, or if the shuffle questions option is
     * turned on.
     *
     * @param structure $structure the quizinvideo structure.
     * @return string HTML to output.
     */
    public function quizinvideo_state_warnings(structure $structure) {
        $warnings = $structure->get_edit_page_warnings();

        if (empty($warnings)) {
            return '';
        }

        $output = array();
        foreach ($warnings as $warning) {
            $output[] = \html_writer::tag('p', $warning);
        }
        return $this->box(implode("\n", $output), 'statusdisplay');
    }

    /**
     * Render the status bar.
     *
     * @param structure $structure the quizinvideo structure.
     * @return string HTML to output.
     */
    public function quizinvideo_information(structure $structure) {
        list($currentstatus, $explanation) = $structure->get_dates_summary();

        $output = html_writer::span(
                    get_string('numquestionsx', 'quizinvideo', $structure->get_question_count()),
                    'numberofquestions') . ' | ' .
                html_writer::span($currentstatus, 'quizinvideoopeningstatus',
                    array('title' => $explanation));

        return html_writer::div($output, 'statusbar');
    }

    /**
     * Render the form for setting a quizinvideo' overall grade
     *
     * @param structure $structure the quizinvideo structure.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function maximum_grade_input($structure, \moodle_url $pageurl) {
        $output = '';
        $output .= html_writer::start_div('maxgrade');
        $output .= html_writer::start_tag('form', array('method' => 'post', 'action' => 'edit.php',
                'class' => 'quizinvideosavegradesform'));
        $output .= html_writer::start_tag('fieldset', array('class' => 'invisiblefieldset'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $output .= html_writer::input_hidden_params($pageurl);
        $a = html_writer::empty_tag('input', array('type' => 'text', 'id' => 'inputmaxgrade',
                'name' => 'maxgrade', 'size' => ($structure->get_decimal_places_for_grades() + 2),
                'value' => $structure->formatted_quizinvideo_grade()));
        $output .= html_writer::tag('label', get_string('maximumgradex', '', $a),
                array('for' => 'inputmaxgrade'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit',
                'name' => 'savechanges', 'value' => get_string('save', 'quizinvideo')));
        $output .= html_writer::end_tag('fieldset');
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Return the repaginate button
     * @param structure $structure the structure of the quizinvideo being edited.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    protected function repaginate_button(structure $structure, \moodle_url $pageurl) {

        $header = html_writer::tag('span', get_string('repaginatecommand', 'quizinvideo'), array('class' => 'repaginatecommand'));
        $form = $this->repaginate_form($structure, $pageurl);
        $containeroptions = array(
                'class'  => 'rpcontainerclass',
                'cmid'   => $structure->get_cmid(),
                'header' => $header,
                'form'   => $form,
        );

        $buttonoptions = array(
            'type'  => 'submit',
            'name'  => 'repaginate',
            'id'    => 'repaginatecommand',
            'value' => get_string('repaginatecommand', 'quizinvideo'),
        );
        if (!$structure->can_be_repaginated()) {
            $buttonoptions['disabled'] = 'disabled';
        } else {
            $this->page->requires->yui_module('moodle-mod_quizinvideo-repaginate', 'M.mod_quizinvideo.repaginate.init');
        }

        return html_writer::tag('div',
                html_writer::empty_tag('input', $buttonoptions), $containeroptions);
    }

    /**
     * Return the repaginate form
     * @param structure $structure the structure of the quizinvideo being edited.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    protected function repaginate_form(structure $structure, \moodle_url $pageurl) {
        $perpage = array();
        $perpage[0] = get_string('allinone', 'quizinvideo');
        for ($i = 1; $i <= 50; ++$i) {
            $perpage[$i] = $i;
        }

        $hiddenurl = clone($pageurl);
        $hiddenurl->param('sesskey', sesskey());

        $select = html_writer::select($perpage, 'questionsperpage',
                $structure->get_questions_per_page(), false);

        $buttonattributes = array('type' => 'submit', 'name' => 'repaginate', 'value' => get_string('go'));

        $formcontent = html_writer::tag('form', html_writer::div(
                    html_writer::input_hidden_params($hiddenurl) .
                    get_string('repaginate', 'quizinvideo', $select) .
                    html_writer::empty_tag('input', $buttonattributes)
                ), array('action' => 'edit.php', 'method' => 'post'));

        return html_writer::div($formcontent, '', array('id' => 'repaginatedialog'));
    }

    /**
     * Render the total marks available for the quizinvideo.
     *
     * @param \stdClass $quizinvideo the quizinvideo settings from the database.
     * @return string HTML to output.
     */
    public function total_marks($quizinvideo) {
        $totalmark = html_writer::span(quizinvideo_format_grade($quizinvideo, $quizinvideo->sumgrades), 'mod_quizinvideo_summarks');
        return html_writer::tag('span',
                get_string('totalmarksx', 'quizinvideo', $totalmark),
                array('class' => 'totalpoints'));
    }

    /**
     * Generate the starting container html for the start of a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'slots'));
    }

    /**
     * Generate the closing container html for the end of a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Display the start of a section, before the questions.
     *
     * @param structure $structure the structure of the quizinvideo being edited.
     * @param \stdClass $section The quizinvideo_section entry from DB
     * @return string HTML to output.
     */
    protected function start_section($structure, $section) {

        $output = '';

        $sectionstyle = '';
        if ($structure->is_only_one_slot_in_section($section)) {
            $sectionstyle = ' only-has-one-slot';
        }

        $output .= html_writer::start_tag('li', array('id' => 'section-'.$section->id,
            'class' => 'section main clearfix'.$sectionstyle, 'role' => 'region',
            'aria-label' => $section->heading));

        $output .= html_writer::start_div('content');

        $output .= html_writer::start_div('section-heading');

        $headingtext = $this->heading(html_writer::span(
                html_writer::span($section->heading, 'instancesection'), 'sectioninstance'), 3);

        if (!$structure->can_be_edited()) {
            $editsectionheadingicon = '';
        } else {
            $editsectionheadingicon = html_writer::link(new \moodle_url('#'),
                $this->pix_icon('t/editstring', get_string('sectionheadingedit', 'quizinvideo', $section->heading),
                        'moodle', array('class' => 'editicon visibleifjs')),
                        array('class' => 'editing_section', 'data-action' => 'edit_section_title'));
        }
        $output .= html_writer::div($headingtext . $editsectionheadingicon, 'instancesectioncontainer');

        if (!$structure->is_first_section($section) && $structure->can_be_edited()) {
            $output .= $this->section_remove_icon($section);
        }
        $output .= $this->section_shuffle_questions($structure, $section);

        $output .= html_writer::end_div($output, 'section-heading');

        return $output;
    }

    /**
     * Display a checkbox for shuffling question within a section.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param \stdClass $section data from the quizinvideo_section table.
     * @return string HTML to output.
     */
    public function section_shuffle_questions(structure $structure, $section) {
        $checkboxattributes = array(
            'type' => 'checkbox',
            'id' => 'shuffle-' . $section->id,
            'value' => 1,
            'data-action' => 'shuffle_questions',
            'class' => 'cm-edit-action',
        );

        if (!$structure->can_be_edited()) {
            $checkboxattributes['disabled'] = 'disabled';
        }
        if ($section->shufflequestions) {
            $checkboxattributes['checked'] = 'checked';
        }

        if ($structure->is_first_section($section)) {
            $help = $this->help_icon('shufflequestions', 'quizinvideo');
        } else {
            $help = '';
        }

        $progressspan = html_writer::span('', 'shuffle-progress');
        $checkbox = html_writer::empty_tag('input', $checkboxattributes);
        $label = html_writer::label(get_string('shufflequestions', 'quizinvideo') . ' ' . $help,
                $checkboxattributes['id'], false);
        return html_writer::span($progressspan . $checkbox . $label,
                'instanceshufflequestions', array('data-action' => 'shuffle_questions'));
    }

    /**
     * Display the end of a section, after the questions.
     *
     * @return string HTML to output.
     */
    protected function end_section() {
        $output = html_writer::end_tag('div');
        $output .= html_writer::end_tag('li');

        return $output;
    }

    /**
     * Render an icon to remove a section from the quizinvideo.
     *
     * @param object $section the section to be removed.
     * @return string HTML to output.
     */
    public function section_remove_icon($section) {
        $title = get_string('sectionheadingremove', 'quizinvideo', $section->heading);
        $url = new \moodle_url('/mod/quizinvideo/edit.php',
                array('sesskey' => sesskey(), 'removesection' => '1', 'sectionid' => $section->id));
        $image = $this->pix_icon('t/delete', $title);
        return $this->action_link($url, $image, null, array(
                'class' => 'cm-edit-action editing_delete', 'data-action' => 'deletesection'));
    }

    /**
     * Renders HTML to display the questions in a section of the quizinvideo.
     *
     * This function calls {@link core_course_renderer::quizinvideo_section_question()}
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param \stdClass $section information about the section.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function questions_in_section(structure $structure, $section,
            $contexts, $pagevars, $pageurl) {

        $output = '';
        foreach ($structure->get_slots_in_section($section->id) as $slot) {
            $output .= $this->question_row($structure, $slot, $contexts, $pagevars, $pageurl);
        }
        return html_writer::tag('ul', $output, array('class' => 'section img-text'));
    }

    /**
     * Displays one question with the surrounding controls.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot which slot we are outputting.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function question_row(structure $structure, $slot, $contexts, $pagevars, $pageurl) {
        $output = '';

        $output .= $this->page_row($structure, $slot, $contexts, $pagevars, $pageurl);

        // Page split/join icon.
        $joinhtml = '';
        if ($structure->can_be_edited() && !$structure->is_last_slot_in_quizinvideo($slot) &&
                                            !$structure->is_last_slot_in_section($slot)) {
            $joinhtml = $this->page_split_join_button($structure, $slot);
        }
        // Question HTML.
        $questionhtml = $this->question($structure, $slot, $pageurl);
        $qtype = $structure->get_question_type_for_slot($slot);
        $questionclasses = 'activity ' . $qtype . ' qtype_' . $qtype . ' slot';

        $output .= html_writer::tag('li', $questionhtml . $joinhtml,
                array('class' => $questionclasses, 'id' => 'slot-' . $structure->get_slot_id_for_slot($slot),
                        'data-canfinish' => $structure->can_finish_during_the_attempt($slot)));

        return $output;
    }

    /**
     * Displays one question with the surrounding controls.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot the first slot on the page we are outputting.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function page_row(structure $structure, $slot, $contexts, $pagevars, $pageurl) {
        $output = '';

        $pagenumber = $structure->get_page_number_for_slot($slot);

        // Put page in a heading for accessibility and styling.
        $page = $this->heading(get_string('page') . ' ' . $pagenumber, 4);

        if ($structure->is_first_slot_on_page($slot)) {
            // Add the add-menu at the page level.
            $addmenu = html_writer::tag('span', $this->add_menu_actions($structure,
                    $pagenumber, $pageurl, $contexts, $pagevars),
                    array('class' => 'add-menu-outer'));

            $addquestionform = $this->add_question_form($structure,
                    $pagenumber, $pageurl, $pagevars);

            $output .= html_writer::tag('li', $page . $addmenu . $this->time_of_video_div($structure->get_quizinvideo(), $pagenumber) . $addquestionform,
                    array('class' => 'pagenumber activity yui3-dd-drop page', 'id' => 'page-' . $pagenumber));
        }

        return $output;
    }

    /**
     * Display the 'time in video' information for a question.
     * Along with the regrade action.
     * @param \stdClass $quizinvideo the quizinvideo settings from the database.
     * @param \stdClass $question data from the question and quizinvideo_slots tables.
     * @return string HTML to output.
     */
    public function time_of_video_div($quizinvideo, $page) {
        $time = quizinvideo_get_timeofvideo($quizinvideo->id, $page);
        $time_minutes = floor($time / 60);
        $time_seconds = str_pad($time % 60, 2, "0", STR_PAD_LEFT);
        $time_string = "". $time_minutes .":". $time_seconds;
        $output = html_writer::span(get_string('timeofvideo', 'quizinvideo').":", 'timeofvideolabel');
        if($time == null) {
            $output .= html_writer::span('',
                'instance_timeofvideo',
                array('title' => get_string('timeofvideo_tooltip', 'quizinvideo')));
        }
        else {
            $output .= html_writer::span($time_string,
                'instance_timeofvideo',
                array('title' => get_string('timeofvideo_tooltip', 'quizinvideo')));
        }



        $output .= html_writer::span(
            html_writer::link(
                new \moodle_url('#'),
                $this->pix_icon('t/editstring', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => '')),
                array(
                    'class' => 'editing_timeofvideo',
                    'data-action' => 'edittimeofvideo',
                    'title' => get_string('edit_timeofvideo', 'quizinvideo'),
                )
            )
        );

        $output .= "<a class='copying_timeofvideo' data-action='copytimeofvideo'><button class='btn btn-secondary m-b-1' type='submit'>";
        $output .= $this->pix_icon('t/copy', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => ''));
        $output .= get_string('copy_timeofvideo', 'quizinvideo')."</button></a>";

        $output .= "<a class='seek_totimestamp' data-action='seektotimestamp'><button class='btn btn-secondary m-b-1' type='submit'>";
        $output .= $this->pix_icon('t/collapsed', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => ''));
        $output .= get_string('seek_totimestamp', 'quizinvideo')."</button></a>";
        return html_writer::span($output, 'instancetimeofvideocontainer');
    }



    /**
     * Returns the add menu that is output once per page.
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $page the page number that this menu will add to.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output.
     */
    public function add_menu_actions(structure $structure, $page, \moodle_url $pageurl,
            \question_edit_contexts $contexts, array $pagevars) {

        $actions = $this->edit_menu_actions($structure, $page, $pageurl, $pagevars);
        if (empty($actions)) {
            return '';
        }
        $menu = new \action_menu();
        $menu->set_alignment(\action_menu::TR, \action_menu::TR);
        $menu->set_constraint('.mod-quizinvideo-edit-content');
        $trigger = html_writer::tag('span', get_string('add', 'quizinvideo'), array('class' => 'add-menu'));
        $menu->set_menu_trigger($trigger);
        // The menu appears within an absolutely positioned element causing width problems.
        // Make sure no-wrap is set so that we don't get a squashed menu.
        $menu->set_nowrap_on_items(true);

        // Disable the link if quizinvideo has attempts.
        if (!$structure->can_be_edited()) {
            return $this->render($menu);
        }

        foreach ($actions as $action) {
            if ($action instanceof \action_menu_link) {
                $action->add_class('add-menu');
            }
            $menu->add($action);
        }
        $menu->attributes['class'] .= ' page-add-actions commands';

        // Prioritise the menu ahead of all other actions.
        $menu->prioritise = true;

        return $this->render($menu);
    }

    /**
     * Returns the list of actions to go in the add menu.
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $page the page number that this menu will add to.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return array the actions.
     */
    public function edit_menu_actions(structure $structure, $page,
            \moodle_url $pageurl, array $pagevars) {
        $questioncategoryid = question_get_category_id_from_pagevars($pagevars);
        static $str;
        if (!isset($str)) {
            $str = get_strings(array('addasection', 'addaquestion', 'addarandomquestion',
                    'addarandomselectedquestion', 'questionbank'), 'quizinvideo');
        }

        // Get section, page, slotnumber and maxmark.
        $actions = array();

        // Add a new section to the add_menu if possible. This is always added to the HTML
        // then hidden with CSS when no needed, so that as things are re-ordered, etc. with
        // Ajax it can be relevaled again when necessary.
        $params = array('cmid' => $structure->get_cmid(), 'addsectionatpage' => $page);

        $actions['addasection'] = new \action_menu_link_secondary(
                new \moodle_url($pageurl, $params),
                new \pix_icon('t/add', $str->addasection, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str->addasection, array('class' => 'cm-edit-action addasection', 'data-action' => 'addasection')
        );

        // Add a new question to the quizinvideo.
        $returnurl = new \moodle_url($pageurl, array('addonpage' => $page));
        $params = array('returnurl' => $returnurl->out_as_local_url(false),
                'cmid' => $structure->get_cmid(), 'category' => $questioncategoryid,
                'addonpage' => $page, 'appendqnumstring' => 'addquestion');

        $actions['addaquestion'] = new \action_menu_link_secondary(
            new \moodle_url('/question/addquestion.php', $params),
            new \pix_icon('t/add', $str->addaquestion, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addaquestion, array('class' => 'cm-edit-action addquestion', 'data-action' => 'addquestion')
        );

        // Call question bank.
        $icon = new \pix_icon('t/add', $str->questionbank, 'moodle', array('class' => 'iconsmall', 'title' => ''));
        if ($page) {
            $title = get_string('addquestionfrombanktopage', 'quizinvideo', $page);
        } else {
            $title = get_string('addquestionfrombankatend', 'quizinvideo');
        }
        $attributes = array('class' => 'cm-edit-action questionbank',
                'data-header' => $title, 'data-action' => 'questionbank', 'data-addonpage' => $page);
        $actions['questionbank'] = new \action_menu_link_secondary($pageurl, $icon, $str->questionbank, $attributes);

        // Add a random question.
        $returnurl = new \moodle_url('/mod/quizinvideo/edit.php', array('cmid' => $structure->get_cmid(), 'data-addonpage' => $page));
        $params = array('returnurl' => $returnurl, 'cmid' => $structure->get_cmid(), 'appendqnumstring' => 'addarandomquestion');
        $url = new \moodle_url('/mod/quizinvideo/addrandom.php', $params);
        $icon = new \pix_icon('t/add', $str->addarandomquestion, 'moodle', array('class' => 'iconsmall', 'title' => ''));
        $attributes = array('class' => 'cm-edit-action addarandomquestion', 'data-action' => 'addarandomquestion');
        if ($page) {
            $title = get_string('addrandomquestiontopage', 'quizinvideo', $page);
        } else {
            $title = get_string('addrandomquestionatend', 'quizinvideo');
        }
        $attributes = array_merge(array('data-header' => $title, 'data-addonpage' => $page), $attributes);
        $actions['addarandomquestion'] = new \action_menu_link_secondary($url, $icon, $str->addarandomquestion, $attributes);

        return $actions;
    }

    /**
     * Render the form that contains the data for adding a new question to the quizinvideo.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $page the page number that this menu will add to.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output.
     */
    protected function add_question_form(structure $structure, $page, \moodle_url $pageurl, array $pagevars) {

        $questioncategoryid = question_get_category_id_from_pagevars($pagevars);

        $output = html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'returnurl',
                        'value' => $pageurl->out_as_local_url(false, array('addonpage' => $page))));
        $output .= html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'cmid', 'value' => $structure->get_cmid()));
        $output .= html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'appendqnumstring', 'value' => 'addquestion'));
        $output .= html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'category', 'value' => $questioncategoryid));

        return html_writer::tag('form', html_writer::div($output),
                array('class' => 'addnewquestion', 'method' => 'post',
                        'action' => new \moodle_url('/question/addquestion.php')));
    }

    /**
     * Display a question.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot the first slot on the page we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function question(structure $structure, $slot, \moodle_url $pageurl) {
        $output = '';
        $output .= html_writer::start_tag('div');

        if ($structure->can_be_edited()) {
            $output .= $this->question_move_icon($structure, $slot);
        }

        $output .= html_writer::start_div('mod-indent-outer');
        $output .= $this->question_number($structure->get_displayed_number_for_slot($slot));

        // This div is used to indent the content.
        $output .= html_writer::div('', 'mod-indent');

        // Display the link to the question (or do nothing if question has no url).
        if ($structure->get_question_type_for_slot($slot) == 'random') {
            $questionname = $this->random_question($structure, $slot, $pageurl);
        } else {
            $questionname = $this->question_name($structure, $slot, $pageurl);
        }

        // Start the div for the activity title, excluding the edit icons.
        $output .= html_writer::start_div('activityinstance');
        $output .= $questionname;

        // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
        $output .= html_writer::end_tag('div'); // .activityinstance.

        // Action icons.
        $questionicons = '';
        $questionicons .= $this->question_preview_icon($structure->get_quizinvideo(), $structure->get_question_in_slot($slot));
        if ($structure->can_be_edited()) {
            $questionicons .= $this->question_remove_icon($structure, $slot, $pageurl);
        }
        $questionicons .= $this->marked_out_of_field($structure, $slot);
        $output .= html_writer::span($questionicons, 'actions'); // Required to add js spinner icon.
        if ($structure->can_be_edited()) {
            $output .= $this->question_dependency_icon($structure, $slot);
        }

        // End of indentation div.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Render the move icon.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot the first slot on the page we are outputting.
     * @return string The markup for the move action.
     */
    public function question_move_icon(structure $structure, $slot) {
        return html_writer::link(new \moodle_url('#'),
            $this->pix_icon('i/dragdrop', get_string('move'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
            array('class' => 'editing_move', 'data-action' => 'move')
        );
    }

    /**
     * Output the question number.
     * @param string $number The number, or 'i'.
     * @return string HTML to output.
     */
    public function question_number($number) {
        if (is_numeric($number)) {
            $number = html_writer::span(get_string('question'), 'accesshide') . ' ' . $number;
        }
        return html_writer::tag('span', $number, array('class' => 'slotnumber'));
    }

    /**
     * Render the preview icon.
     *
     * @param \stdClass $quizinvideo the quizinvideo settings from the database.
     * @param \stdClass $question data from the question and quizinvideo_slots tables.
     * @param bool $label if true, show the preview question label after the icon
     * @return string HTML to output.
     */
    public function question_preview_icon($quizinvideo, $question, $label = null) {
        $url = quizinvideo_question_preview_url($quizinvideo, $question);

        // Do we want a label?
        $strpreviewlabel = '';
        if ($label) {
            $strpreviewlabel = ' ' . get_string('preview', 'quizinvideo');
        }

        // Build the icon.
        $strpreviewquestion = get_string('previewquestion', 'quizinvideo');
        $image = $this->pix_icon('t/preview', $strpreviewquestion);

        $action = new \popup_action('click', $url, 'questionpreview',
                                        question_preview_popup_params());

        return $this->action_link($url, $image . $strpreviewlabel, $action,
                array('title' => $strpreviewquestion, 'class' => 'preview'));
    }

    /**
     * Render an icon to remove a question from the quizinvideo.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot the first slot on the page we are outputting.
     * @param \moodle_url $pageurl the canonical URL of the edit page.
     * @return string HTML to output.
     */
    public function question_remove_icon(structure $structure, $slot, $pageurl) {
        $url = new \moodle_url($pageurl, array('sesskey' => sesskey(), 'remove' => $slot));
        $strdelete = get_string('delete');

        $image = $this->pix_icon('t/delete', $strdelete);

        return $this->action_link($url, $image, null, array('title' => $strdelete,
                    'class' => 'cm-edit-action editing_delete', 'data-action' => 'delete'));
    }

    /**
     * Display an icon to split or join two pages of the quizinvideo.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot the first slot on the page we are outputting.
     * @return string HTML to output.
     */
    public function page_split_join_button($structure, $slot) {
        $insertpagebreak = !$structure->is_last_slot_on_page($slot);
        $url = new \moodle_url('repaginate.php', array('quizinvideoid' => $structure->get_quizinvideoid(),
                'slot' => $slot, 'repag' => $insertpagebreak ? 2 : 1, 'sesskey' => sesskey()));

        if ($insertpagebreak) {
            $title = get_string('addpagebreak', 'quizinvideo');
            $image = $this->pix_icon('e/insert_page_break', $title);
            $action = 'addpagebreak';
        } else {
            $title = get_string('removepagebreak', 'quizinvideo');
            $image = $this->pix_icon('e/remove_page_break', $title);
            $action = 'removepagebreak';
        }

        // Disable the link if quizinvideo has attempts.
        $disabled = null;
        if (!$structure->can_be_edited()) {
            $disabled = 'disabled';
        }
        return html_writer::span($this->action_link($url, $image, null, array('title' => $title,
                    'class' => 'page_split_join cm-edit-action', 'disabled' => $disabled, 'data-action' => $action)),
                'page_split_join_wrapper');
    }

    /**
     * Display the icon for whether this question can only be seen if the previous
     * one has been answered.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot the first slot on the page we are outputting.
     * @return string HTML to output.
     */
    public function question_dependency_icon($structure, $slot) {
        $a = array(
            'thisq' => $structure->get_displayed_number_for_slot($slot),
            'previousq' => $structure->get_displayed_number_for_slot(max($slot - 1, 1)),
        );
        if ($structure->is_question_dependent_on_previous_slot($slot)) {
            $title = get_string('questiondependencyremove', 'quizinvideo', $a);
            $image = $this->pix_icon('t/locked', get_string('questiondependsonprevious', 'quizinvideo'),
                    'moodle', array('title' => ''));
            $action = 'removedependency';
        } else {
            $title = get_string('questiondependencyadd', 'quizinvideo', $a);
            $image = $this->pix_icon('t/unlocked', get_string('questiondependencyfree', 'quizinvideo'),
                    'moodle', array('title' => ''));
            $action = 'adddependency';
        }

        // Disable the link if quizinvideo has attempts.
        $disabled = null;
        if (!$structure->can_be_edited()) {
            $disabled = 'disabled';
        }
        $extraclass = '';
        if (!$structure->can_question_depend_on_previous_slot($slot)) {
            $extraclass = ' question_dependency_cannot_depend';
        }
        return html_writer::span($this->action_link('#', $image, null, array('title' => $title,
                'class' => 'cm-edit-action', 'disabled' => $disabled, 'data-action' => $action)),
                'question_dependency_wrapper' . $extraclass);
    }

    /**
     * Renders html to display a name with the link to the question on a quizinvideo edit page
     *
     * If the user does not have permission to edi the question, it is rendered
     * without a link
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot which slot we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function question_name(structure $structure, $slot, $pageurl) {
        $output = '';

        $question = $structure->get_question_in_slot($slot);
        $editurl = new \moodle_url('/question/question.php', array(
                'returnurl' => $pageurl->out_as_local_url(),
                'cmid' => $structure->get_cmid(), 'id' => $question->id));

        $instancename = quizinvideo_question_tostring($question);

        $qtype = \question_bank::get_qtype($question->qtype, false);
        $namestr = $qtype->local_name();

        $icon = $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr,
                'class' => 'icon activityicon', 'alt' => ' ', 'role' => 'presentation'));

        $editicon = $this->pix_icon('t/edit', '', 'moodle', array('title' => ''));

        // Need plain question name without html tags for link title.
        $title = shorten_text(format_string($question->name), 100);

        // Display the link itself.
        $activitylink = $icon . html_writer::tag('span', $editicon . $instancename, array('class' => 'instancename'));
        $output .= html_writer::link($editurl, $activitylink,
                array('title' => get_string('editquestion', 'quizinvideo').' '.$title));

        return $output;
    }

    /**
     * Renders html to display a random question the link to edit the configuration
     * and also to see that category in the question bank.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot which slot we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function random_question(structure $structure, $slot, $pageurl) {

        $question = $structure->get_question_in_slot($slot);
        $editurl = new \moodle_url('/question/question.php', array(
                'returnurl' => $pageurl->out_as_local_url(),
                'cmid' => $structure->get_cmid(), 'id' => $question->id));

        $temp = clone($question);
        $temp->questiontext = '';
        $instancename = quizinvideo_question_tostring($temp);

        $configuretitle = get_string('configurerandomquestion', 'quizinvideo');
        $qtype = \question_bank::get_qtype($question->qtype, false);
        $namestr = $qtype->local_name();
        $icon = $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr,
                'class' => 'icon activityicon', 'alt' => ' ', 'role' => 'presentation'));

        $editicon = $this->pix_icon('t/edit', $configuretitle, 'moodle', array('title' => ''));

        // If this is a random question, display a link to show the questions
        // selected from in the question bank.
        $qbankurl = new \moodle_url('/question/edit.php', array(
                'cmid' => $structure->get_cmid(),
                'cat' => $question->category . ',' . $question->contextid,
                'recurse' => !empty($question->questiontext)));
        $qbanklink = ' ' . \html_writer::link($qbankurl,
                get_string('seequestions', 'quizinvideo'), array('class' => 'mod_quizinvideo_random_qbank_link'));

        return html_writer::link($editurl, $icon . $editicon, array('title' => $configuretitle)) .
                ' ' . $instancename . ' ' . $qbanklink;
    }

    /**
     * Display the 'marked out of' information for a question.
     * Along with the regrade action.
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param int $slot which slot we are outputting.
     * @return string HTML to output.
     */
    public function marked_out_of_field(structure $structure, $slot) {
        if (!$structure->is_real_question($slot)) {
            $output = html_writer::span('',
                    'instancemaxmark decimalplaces_' . $structure->get_decimal_places_for_question_marks());

            $output .= html_writer::span(
                    $this->pix_icon('spacer', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => '')),
                    'editing_maxmark');
            return html_writer::span($output, 'instancemaxmarkcontainer infoitem');
        }

        $output = html_writer::span($structure->formatted_question_grade($slot),
                'instancemaxmark decimalplaces_' . $structure->get_decimal_places_for_question_marks(),
                array('title' => get_string('maxmark', 'quizinvideo')));

        $output .= html_writer::span(
            html_writer::link(
                new \moodle_url('#'),
                $this->pix_icon('t/editstring', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => '')),
                array(
                    'class' => 'editing_maxmark',
                    'data-action' => 'editmaxmark',
                    'title' => get_string('editmaxmark', 'quizinvideo'),
                )
            )
        );
        return html_writer::span($output, 'instancemaxmarkcontainer');
    }

    /**
     * Render the question type chooser dialogue.
     * @return string HTML to output.
     */
    public function question_chooser() {
        $container = html_writer::div(print_choose_qtype_to_add_form(array(), null, false), '',
                array('id' => 'qtypechoicecontainer'));
        return html_writer::div($container, 'createnewquestion');
    }

    /**
     * Render the contents of the question bank pop-up in its initial state,
     * when it just contains a loading progress indicator.
     * @return string HTML to output.
     */
    public function question_bank_loading() {
        return html_writer::div(html_writer::empty_tag('img',
                array('alt' => 'loading', 'class' => 'loading-icon', 'src' => $this->pix_url('i/loading'))),
                'questionbankloading');
    }

    /**
     * Return random question form.
     * @param \moodle_url $thispageurl the canonical URL of this page.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output.
     */
    protected function random_question_form(\moodle_url $thispageurl, \question_edit_contexts $contexts, array $pagevars) {

        if (!$contexts->have_cap('moodle/question:useall')) {
            return '';
        }
        $randomform = new \quizinvideo_add_random_form(new \moodle_url('/mod/quizinvideo/addrandom.php'),
                                 array('contexts' => $contexts, 'cat' => $pagevars['cat']));
        $randomform->set_data(array(
                'category' => $pagevars['cat'],
                'returnurl' => $thispageurl->out_as_local_url(true),
                'randomnumber' => 1,
                'cmid' => $thispageurl->param('cmid'),
        ));
        return html_writer::div($randomform->render(), 'randomquestionformforpopup');
    }

    /**
     * Initialise the JavaScript for the general editing. (JavaScript for popups
     * is handled with the specific code for those.)
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return bool Always returns true
     */
    protected function initialise_editing_javascript(structure $structure,
            \question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl) {

        $config = new \stdClass();
        $config->resourceurl = '/mod/quizinvideo/edit_rest.php';
        $config->sectionurl = '/mod/quizinvideo/edit_rest.php';
        $config->pageparams = array();
        $config->questiondecimalpoints = $structure->get_decimal_places_for_question_marks();
        $config->pagehtml = $this->new_page_template($structure, $contexts, $pagevars, $pageurl);
        $config->addpageiconhtml = $this->add_page_icon_template($structure);

        $this->page->requires->yui_module('moodle-mod_quizinvideo-toolboxes',
                'M.mod_quizinvideo.init_resource_toolbox',
                array(array(
                        'courseid' => $structure->get_courseid(),
                        'quizinvideoid' => $structure->get_quizinvideoid(),
                        'ajaxurl' => $config->resourceurl,
                        'config' => $config,
                ))
        );
        unset($config->pagehtml);
        unset($config->addpageiconhtml);

        $this->page->requires->yui_module('moodle-mod_quizinvideo-toolboxes',
                'M.mod_quizinvideo.init_section_toolbox',
                array(array(
                        'courseid' => $structure,
                        'quizinvideoid' => $structure->get_quizinvideoid(),
                        'ajaxurl' => $config->sectionurl,
                        'config' => $config,
                ))
        );

        $this->page->requires->yui_module('moodle-mod_quizinvideo-dragdrop', 'M.mod_quizinvideo.init_section_dragdrop',
                array(array(
                        'courseid' => $structure,
                        'quizinvideoid' => $structure->get_quizinvideoid(),
                        'ajaxurl' => $config->sectionurl,
                        'config' => $config,
                )), null, true);

        $this->page->requires->yui_module('moodle-mod_quizinvideo-dragdrop', 'M.mod_quizinvideo.init_resource_dragdrop',
                array(array(
                        'courseid' => $structure,
                        'quizinvideoid' => $structure->get_quizinvideoid(),
                        'ajaxurl' => $config->resourceurl,
                        'config' => $config,
                )), null, true);

        // Require various strings for the command toolbox.
        $this->page->requires->strings_for_js(array(
                'clicktohideshow',
                'deletechecktype',
                'deletechecktypename',
                'edittitle',
                'edittitleinstructions',
                'emptydragdropregion',
                'hide',
                'markedthistopic',
                'markthistopic',
                'move',
                'movecontent',
                'moveleft',
                'movesection',
                'page',
                'question',
                'selectall',
                'show',
                'tocontent',
        ), 'moodle');

        $this->page->requires->strings_for_js(array(
                'addpagebreak',
                'confirmremovesectionheading',
                'confirmremovequestion',
                'dragtoafter',
                'dragtostart',
                'numquestionsx',
                'sectionheadingedit',
                'sectionheadingremove',
                'removepagebreak',
                'questiondependencyadd',
                'questiondependencyfree',
                'questiondependencyremove',
                'questiondependsonprevious',
        ), 'quizinvideo');

        foreach (\question_bank::get_all_qtypes() as $qtype => $notused) {
            $this->page->requires->string_for_js('pluginname', 'qtype_' . $qtype);
        }

        return true;
    }

    /**
     * HTML for a page, with ids stripped, so it can be used as a javascript template.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @param \question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML for a new page.
     */
    protected function new_page_template(structure $structure,
            \question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl) {
        if (!$structure->has_questions()) {
            return '';
        }

        $pagehtml = $this->page_row($structure, 1, $contexts, $pagevars, $pageurl);

        // Normalise the page number.
        $pagenumber = $structure->get_page_number_for_slot(1);
        $strcontexts = array();
        $strcontexts[] = 'page-';
        $strcontexts[] = get_string('page') . ' ';
        $strcontexts[] = 'addonpage%3D';
        $strcontexts[] = 'addonpage=';
        $strcontexts[] = 'addonpage="';
        $strcontexts[] = get_string('addquestionfrombanktopage', 'quizinvideo', '');
        $strcontexts[] = 'data-addonpage%3D';
        $strcontexts[] = 'action-menu-';

        foreach ($strcontexts as $strcontext) {
            $pagehtml = str_replace($strcontext . $pagenumber, $strcontext . '%%PAGENUMBER%%', $pagehtml);
        }

        return $pagehtml;
    }

    /**
     * HTML for a page, with ids stripped, so it can be used as a javascript template.
     *
     * @param structure $structure object containing the structure of the quizinvideo.
     * @return string HTML for a new icon
     */
    protected function add_page_icon_template(structure $structure) {

        if (!$structure->has_questions()) {
            return '';
        }

        $html = $this->page_split_join_button($structure, 1);
        return str_replace('&amp;slot=1&amp;', '&amp;slot=%%SLOT%%&amp;', $html);
    }

    /**
     * Return the contents of the question bank, to be displayed in the question-bank pop-up.
     *
     * @param \mod_quizinvideo\question\bank\custom_view $questionbank the question bank view object.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output / send back in response to an AJAX request.
     */
    public function question_bank_contents(\mod_quizinvideo\question\bank\custom_view $questionbank, array $pagevars) {

        $qbank = $questionbank->render('editq', $pagevars['qpage'], $pagevars['qperpage'],
                $pagevars['cat'], $pagevars['recurse'], $pagevars['showhidden'], $pagevars['qbshowtext']);
        return html_writer::div(html_writer::div($qbank, 'bd'), 'questionbankformforpopup');
    }

    /**
     * Return the video element to be displayed in the edit page.
     *
     * @param $url the url of the video.
     * @return string HTML.
     */
    public function show_video($url)
    {
        $this->page->requires->js('/mod/quizinvideo/videojs/video.js');
        $this->page->requires->js('/mod/quizinvideo/videojs/youtube.js');
        $output = '';
        if (preg_match('/^(https?\:\/\/)?(www\.youtube\.com|youtu\.?be)\/.+$/', $url)) {
            $youtube = true;
        } else {
            $youtube = false;
        }
        $output .= html_writer::start_tag('div', array('id'=>'video_div'));
        $output .= html_writer::start_tag('video', array( 'id'=>'video_content','data-setup' => $youtube ? '{"techOrder": ["youtube"], "sources": [{ "type": "video/youtube", "src": "'.$url.'"}]}' : '{}', 'preload'=>'auto', 'controls'=>'', 'autoplay' => 'autoplay', 'class' => 'video-js  vjs-default-skin'));
        if (!$youtube) {
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
}
