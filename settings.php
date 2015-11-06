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
 * Administration settings definitions for the quizinvideo module.
 *
 * @package   mod_quizinvideo
 * @copyright 2010 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizinvideo/lib.php');

// First get a list of quizinvideo reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$reports = core_component::get_plugin_list_with_file('quizinvideo', 'settings.php', false);
$reportsbyname = array();
foreach ($reports as $report => $reportdir) {
    $strreportname = get_string($report . 'report', 'quizinvideo_'.$report);
    $reportsbyname[$strreportname] = $report;
}
core_collator::ksort($reportsbyname);

// First get a list of quizinvideo reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$rules = core_component::get_plugin_list_with_file('quizinvideoaccess', 'settings.php', false);
$rulesbyname = array();
foreach ($rules as $rule => $ruledir) {
    $strrulename = get_string('pluginname', 'quizinvideoaccess_' . $rule);
    $rulesbyname[$strrulename] = $rule;
}
core_collator::ksort($rulesbyname);

// Create the quizinvideo settings page.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $pagetitle = get_string('modulename', 'quizinvideo');
} else {
    $pagetitle = get_string('generalsettings', 'admin');
}
$quizinvideosettings = new admin_settingpage('modsettingquizinvideo', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    // Introductory explanation that all the settings are defaults for the add quizinvideo form.
    $quizinvideosettings->add(new admin_setting_heading('quizinvideointro', '', get_string('configintro', 'quizinvideo')));

    // RTMP url list
    $quizinvideosettings->add(new admin_setting_configtextarea('quizinvideo/rtmpurls',
            get_string('rtmp_urls','quizinvideo'), get_string('rtmp_urls_desc','quizinvideo'), null));

    // Time limit.
    $quizinvideosettings->add(new admin_setting_configduration_with_advanced('quizinvideo/timelimit',
            get_string('timelimit', 'quizinvideo'), get_string('configtimelimitsec', 'quizinvideo'),
            array('value' => '0', 'adv' => false), 60));

    // What to do with overdue attempts.
    $quizinvideosettings->add(new mod_quizinvideo_admin_setting_overduehandling('quizinvideo/overduehandling',
            get_string('overduehandling', 'quizinvideo'), get_string('overduehandling_desc', 'quizinvideo'),
            array('value' => 'autosubmit', 'adv' => false), null));

    // Grace period time.
    $quizinvideosettings->add(new admin_setting_configduration_with_advanced('quizinvideo/graceperiod',
            get_string('graceperiod', 'quizinvideo'), get_string('graceperiod_desc', 'quizinvideo'),
            array('value' => '86400', 'adv' => false)));

    // Minimum grace period used behind the scenes.
    $quizinvideosettings->add(new admin_setting_configduration('quizinvideo/graceperiodmin',
            get_string('graceperiodmin', 'quizinvideo'), get_string('graceperiodmin_desc', 'quizinvideo'),
            60, 1));

    // Number of attempts.
    $options = array(get_string('unlimited'));
    for ($i = 1; $i <= quizinvideo_MAX_ATTEMPT_OPTION; $i++) {
        $options[$i] = $i;
    }
    $quizinvideosettings->add(new admin_setting_configselect_with_advanced('quizinvideo/attempts',
            get_string('attemptsallowed', 'quizinvideo'), get_string('configattemptsallowed', 'quizinvideo'),
            array('value' => 0, 'adv' => false), $options));

    // Grading method.
    $quizinvideosettings->add(new mod_quizinvideo_admin_setting_grademethod('quizinvideo/grademethod',
            get_string('grademethod', 'quizinvideo'), get_string('configgrademethod', 'quizinvideo'),
            array('value' => quizinvideo_GRADEHIGHEST, 'adv' => false), null));

    // Maximum grade.
    $quizinvideosettings->add(new admin_setting_configtext('quizinvideo/maximumgrade',
            get_string('maximumgrade'), get_string('configmaximumgrade', 'quizinvideo'), 10, PARAM_INT));

    // Questions per page.
    $perpage = array();
    $perpage[0] = get_string('never');
    $perpage[1] = get_string('aftereachquestion', 'quizinvideo');
    for ($i = 2; $i <= quizinvideo_MAX_QPP_OPTION; ++$i) {
        $perpage[$i] = get_string('afternquestions', 'quizinvideo', $i);
    }
    $quizinvideosettings->add(new admin_setting_configselect_with_advanced('quizinvideo/questionsperpage',
            get_string('newpageevery', 'quizinvideo'), get_string('confignewpageevery', 'quizinvideo'),
            array('value' => 1, 'adv' => false), $perpage));

    // Navigation method.
    $quizinvideosettings->add(new admin_setting_configselect_with_advanced('quizinvideo/navmethod',
            get_string('navmethod', 'quizinvideo'), get_string('confignavmethod', 'quizinvideo'),
            array('value' => quizinvideo_NAVMETHOD_FREE, 'adv' => true), quizinvideo_get_navigation_options()));

    // Shuffle within questions.
    $quizinvideosettings->add(new admin_setting_configcheckbox_with_advanced('quizinvideo/shuffleanswers',
            get_string('shufflewithin', 'quizinvideo'), get_string('configshufflewithin', 'quizinvideo'),
            array('value' => 1, 'adv' => false)));

    // Preferred behaviour.
    $quizinvideosettings->add(new admin_setting_question_behaviour('quizinvideo/preferredbehaviour',
            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'quizinvideo'),
            'deferredfeedback'));

    // Can redo completed questions.
    $quizinvideosettings->add(new admin_setting_configselect_with_advanced('quizinvideo/canredoquestions',
            get_string('canredoquestions', 'quizinvideo'), get_string('canredoquestions_desc', 'quizinvideo'),
            array('value' => 0, 'adv' => true),
            array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'quizinvideo'))));

    // Each attempt builds on last.
    $quizinvideosettings->add(new admin_setting_configcheckbox_with_advanced('quizinvideo/attemptonlast',
            get_string('eachattemptbuildsonthelast', 'quizinvideo'),
            get_string('configeachattemptbuildsonthelast', 'quizinvideo'),
            array('value' => 0, 'adv' => true)));

    // Review options.
    $quizinvideosettings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'quizinvideo'), ''));
    foreach (mod_quizinvideo_admin_review_setting::fields() as $field => $name) {
        $default = mod_quizinvideo_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_quizinvideo_admin_review_setting::DURING;
            $forceduring = false;
        }
        $quizinvideosettings->add(new mod_quizinvideo_admin_review_setting('quizinvideo/review' . $field,
                $name, '', $default, $forceduring));
    }

    // Show the user's picture.
    $quizinvideosettings->add(new mod_quizinvideo_admin_setting_user_image('quizinvideo/showuserpicture',
            get_string('showuserpicture', 'quizinvideo'), get_string('configshowuserpicture', 'quizinvideo'),
            array('value' => 0, 'adv' => false), null));

    // Decimal places for overall grades.
    $options = array();
    for ($i = 0; $i <= quizinvideo_MAX_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $quizinvideosettings->add(new admin_setting_configselect_with_advanced('quizinvideo/decimalpoints',
            get_string('decimalplaces', 'quizinvideo'), get_string('configdecimalplaces', 'quizinvideo'),
            array('value' => 2, 'adv' => false), $options));

    // Decimal places for question grades.
    $options = array(-1 => get_string('sameasoverall', 'quizinvideo'));
    for ($i = 0; $i <= quizinvideo_MAX_Q_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $quizinvideosettings->add(new admin_setting_configselect_with_advanced('quizinvideo/questiondecimalpoints',
            get_string('decimalplacesquestion', 'quizinvideo'),
            get_string('configdecimalplacesquestion', 'quizinvideo'),
            array('value' => -1, 'adv' => true), $options));

    // Show blocks during quizinvideo attempts.
    $quizinvideosettings->add(new admin_setting_configcheckbox_with_advanced('quizinvideo/showblocks',
            get_string('showblocks', 'quizinvideo'), get_string('configshowblocks', 'quizinvideo'),
            array('value' => 0, 'adv' => true)));

    // Password.
    $quizinvideosettings->add(new admin_setting_configtext_with_advanced('quizinvideo/password',
            get_string('requirepassword', 'quizinvideo'), get_string('configrequirepassword', 'quizinvideo'),
            array('value' => '', 'adv' => true), PARAM_TEXT));

    // IP restrictions.
    $quizinvideosettings->add(new admin_setting_configtext_with_advanced('quizinvideo/subnet',
            get_string('requiresubnet', 'quizinvideo'), get_string('configrequiresubnet', 'quizinvideo'),
            array('value' => '', 'adv' => true), PARAM_TEXT));

    // Enforced delay between attempts.
    $quizinvideosettings->add(new admin_setting_configduration_with_advanced('quizinvideo/delay1',
            get_string('delay1st2nd', 'quizinvideo'), get_string('configdelay1st2nd', 'quizinvideo'),
            array('value' => 0, 'adv' => true), 60));
    $quizinvideosettings->add(new admin_setting_configduration_with_advanced('quizinvideo/delay2',
            get_string('delaylater', 'quizinvideo'), get_string('configdelaylater', 'quizinvideo'),
            array('value' => 0, 'adv' => true), 60));

    // Browser security.
    $quizinvideosettings->add(new mod_quizinvideo_admin_setting_browsersecurity('quizinvideo/browsersecurity',
            get_string('showinsecurepopup', 'quizinvideo'), get_string('configpopup', 'quizinvideo'),
            array('value' => '-', 'adv' => true), null));

    // Allow user to specify if setting outcomes is an advanced setting.
    if (!empty($CFG->enableoutcomes)) {
        $quizinvideosettings->add(new admin_setting_configcheckbox('quizinvideo/outcomes_adv',
            get_string('outcomesadvanced', 'quizinvideo'), get_string('configoutcomesadvanced', 'quizinvideo'),
            '0'));
    }

    // Autosave frequency.
    $quizinvideosettings->add(new admin_setting_configduration('quizinvideo/autosaveperiod',
            get_string('autosaveperiod', 'quizinvideo'), get_string('autosaveperiod_desc', 'quizinvideo'), 60, 1));
}

// Now, depending on whether any reports have their own settings page, add
// the quizinvideo setting page to the appropriate place in the tree.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $ADMIN->add('modsettings', $quizinvideosettings);
} else {
    $ADMIN->add('modsettings', new admin_category('modsettingsquizinvideocat',
            get_string('modulename', 'quizinvideo'), $module->is_enabled() === false));
    $ADMIN->add('modsettingsquizinvideocat', $quizinvideosettings);

    // Add settings pages for the quizinvideo report subplugins.
    foreach ($reportsbyname as $strreportname => $report) {
        $reportname = $report;

        $settings = new admin_settingpage('modsettingsquizinvideocat'.$reportname,
                $strreportname, 'moodle/site:config', $module->is_enabled() === false);
        if ($ADMIN->fulltree) {
            include($CFG->dirroot . "/mod/quizinvideo/report/$reportname/settings.php");
        }
        if (!empty($settings)) {
            $ADMIN->add('modsettingsquizinvideocat', $settings);
        }
    }

    // Add settings pages for the quizinvideo access rule subplugins.
    foreach ($rulesbyname as $strrulename => $rule) {
        $settings = new admin_settingpage('modsettingsquizinvideocat' . $rule,
                $strrulename, 'moodle/site:config', $module->is_enabled() === false);
        if ($ADMIN->fulltree) {
            include($CFG->dirroot . "/mod/quizinvideo/accessrule/$rule/settings.php");
        }
        if (!empty($settings)) {
            $ADMIN->add('modsettingsquizinvideocat', $settings);
        }
    }
}

$settings = null; // We do not want standard settings link.
