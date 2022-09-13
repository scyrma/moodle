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
 * Plugin version and other meta-data are defined here.
 *
 * @package     theme_workplace
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Workplace team
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // Raw SCSS to include before the content.
    $setting = new admin_setting_scsscode('theme_workplace/scsspre',
        get_string('rawscsspre', 'theme_workplace'), get_string('rawscsspre_desc', 'theme_workplace'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Raw SCSS to include after the content.
    $setting = new admin_setting_scsscode('theme_workplace/scss',
        get_string('rawscss', 'theme_workplace'), get_string('rawscss_desc', 'theme_workplace'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Dashboard settings header.
    $heading = new admin_setting_heading('theme_workplace/dashboard', get_string('wpdashboard', 'theme_workplace'), '');
    $settings->add($heading);

    // Display hide learning tab setting.
    $setting = new admin_setting_configcheckbox('theme_workplace/dashboardlearning',
        get_string('wpdashboardlearning', 'theme_workplace'),
        get_string('wpdashboardlearningdesc', 'theme_workplace'), 1);
    $settings->add($setting);

    // Display hide teams tab setting.
    $setting = new admin_setting_configcheckbox('theme_workplace/dashboardteams',
        get_string('wpdashboardteams', 'theme_workplace'),
        get_string('wpdashboardteamsdesc', 'theme_workplace'), 1);
    $settings->add($setting);

    // Display myoverview block only on mobile setting.
    $setting = new admin_setting_configcheckbox(
            'tool_wp/myoverviewdisplayonmobileonly',
        get_string('blockmyoverviewmobileonly', 'tool_wp'),
        get_string('blockmyoverviewmobileonly_help', 'tool_wp'), 1);
    $settings->add($setting);

    // Hide program courses from myoverview block.
    $setting = new admin_setting_configcheckbox('theme_workplace/hideprogramcourses',
        get_string('blockmyoverviewhideprogramcourses', 'theme_workplace'),
        get_string('blockmyoverviewhideprogramcoursesdesc', 'theme_workplace'), 1);
    $settings->add($setting);

}
