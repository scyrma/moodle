<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Hack to the settings tree
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

if (!defined('BEHAT_SITE_RUNNING')) {
    // Rename the "Courses" tab into "Learning".
    if ($coursestab = $ADMIN->locate('courses')) {
        $coursestab->visiblename = new lang_string('coursesadmintab', 'tool_wp');
    }
}

// Automatically hide the parent languages for workplace language packs.
if ($hassiteconfig && ($langsettings = $ADMIN->locate('langsettings'))) {
    $setting = new admin_setting_configcheckbox('wphideparentlang',
        new lang_string('confighideparentlang', 'tool_wp'),
        new lang_string('confighideparentlangdesc', 'tool_wp'), 1);
    $langsettings->add($setting);
}

$ADMIN->add('root', new admin_externalpage('tool_wp_exportimport', get_string('exportimport', 'tool_wp'),
    new moodle_url('/admin/tool/wp/exportimport.php'),
    'tool/wp:useexportimport'));

if ($hassiteconfig) {
    $temp = new admin_settingpage('wpexportimport', new lang_string('exportimportsettings', 'tool_wp'), 'moodle/site:config');
    $temp->add(new admin_setting_configcheckbox('tool_wp/coursecontentbackup',
        new lang_string('coursecontentbackup', 'tool_wp'),
        new lang_string('coursecontentbackupdesc', 'tool_wp'), 0));
    $ADMIN->add('root', $temp);

    // Presents options to site admins about the production state of the site.
    // Depending on the option chosen a message is displayed to intended users highlighting the site state.
    $options = [0 => new lang_string('nonproductionsite', 'tool_wp'),
        1 => new lang_string('productionsite', 'tool_wp')];
    $temp = new admin_settingpage('productionstate', new lang_string('productionstate', 'tool_wp'));
    $temp->add(new admin_setting_configselect('workplaceproductionstate',
        new lang_string('productionstate', 'tool_wp'),
        new lang_string('productionstatedesc', 'tool_wp'), 0, $options));
    $ADMIN->add('development', $temp);
}

if ($hassiteconfig && ($temp = $ADMIN->locate('gradessettings'))) {
    if (isset($temp->settings->recovergradesdefault)) {
        $temp->settings->recovergradesdefault->description = new lang_string('recovergradesdefault_help', 'grades') . ' ' .
            new lang_string('recovercoursegrades', 'tool_wp');
    }
    $temp->add(new admin_setting_configcheckbox('tool_wp_deletegradeshistory',
        new lang_string('deletegradeshistory', 'tool_wp'),
        new lang_string('deletegradeshistory_desc', 'tool_wp'),
        1));
}
