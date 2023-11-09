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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * Plugin settings.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_catalogue\configuration;
use tool_catalogue\constants;
use tool_catalogue\local\setting_fields_list;
use tool_catalogue\table\settings_table_displayfields_list;
use tool_catalogue\table\settings_table_displayfields_tiles;
use tool_catalogue\table\settings_table_filterfields;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add('root', new admin_category('mycourses', new lang_string('mycourses', 'tool_catalogue')),
        'location');

    $temp = new admin_settingpage('tool_catalogue', new lang_string('cataloguesettings', 'tool_catalogue'));

    $temp->add(new admin_setting_configtext('tool_catalogue/programdisplayduelimit',
        new lang_string('programdisplayduelimit', 'tool_catalogue'),
        new lang_string('programdisplayduelimit_desc', 'tool_catalogue'),
        10, PARAM_INT));

    $temp->add(new admin_setting_configtext('tool_catalogue/coursedisplayduelimit',
        new lang_string('coursedisplayduelimit', 'tool_catalogue'),
        new lang_string('coursedisplayduelimit_desc', 'tool_catalogue'),
        10, PARAM_INT));

    $temp->add(new admin_setting_configcheckbox('tool_catalogue/showcataloguecoursecategory',
        new lang_string('showcataloguecoursecategory', 'tool_catalogue'),
        new lang_string('showcataloguecoursecategory_desc', 'tool_catalogue'),
        false));

    $temp->add(new admin_setting_configcheckbox('tool_catalogue/showcoursedates',
        new lang_string('showcoursedates', 'tool_catalogue'),
        new lang_string('showcoursedates_desc', 'tool_catalogue'),
        true));

    $options = [
        constants::DISPLAY_FOR_EVERYBODY => new lang_string('displayforeverybody', 'tool_catalogue'),
        constants::DISPLAY_ONLY_FOR_STUDENTS_AND_GUESTS => new lang_string('displayforstudentsandguests', 'tool_catalogue'),
        constants::NEVER_DISPLAY => new lang_string('displaynever', 'tool_catalogue'),
    ];
    $default = defined('BEHAT_SITE_RUNNING') ? constants::NEVER_DISPLAY : constants::DISPLAY_FOR_EVERYBODY;
    $temp->add(new admin_setting_configselect('tool_catalogue/displaycoursecovermodals',
        new lang_string('displaycourseinfomodal', 'tool_catalogue'),
        '', $default, $options));

    // Setting to handle program cover page view.
    $options = [
        constants::DISPLAY_FOR_EVERYBODY => new lang_string('displayforeverybody', 'tool_catalogue'),
        constants::DISPLAY_ONLY_FOR_NOT_ADMIN => new lang_string('displayfornotadmin', 'tool_catalogue'),
        constants::NEVER_DISPLAY => new lang_string('displaynever', 'tool_catalogue'),
    ];
    $temp->add(new admin_setting_configselect('tool_catalogue/displayprogramcoverpage',
        new lang_string('displayprogramcoverpage', 'tool_catalogue'),
        '', constants::DISPLAY_FOR_EVERYBODY, $options));

    // Default sort order for the My courses page.
    $options = [
        constants::SORT_NAME => new lang_string('name', 'tool_catalogue'),
        constants::SORT_DUEDATE => new lang_string('duedate', 'tool_catalogue'),
        constants::SORT_LASTACCESS => new lang_string('lastaccess', 'tool_catalogue'),
    ];
    $temp->add(new admin_setting_configselect('tool_catalogue/defaultsortorder',
        new lang_string('defaultsortorder', 'tool_catalogue'),
        '', constants::SORT_DUEDATE, $options));

    $ADMIN->add('mycourses', $temp);
}

// Learning catalogue settings.
if (configuration::is_catalogue_enabled()) {
    $settings = new admin_settingpage('tool_catalogue_catalogue',
        new lang_string('learningcataloguesettings', 'tool_catalogue'),
        'tool/catalogue:config');

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_COURSES_PER_PAGE_FRONTPAGE,
        new lang_string('coursesperpage_frontpage', 'tool_catalogue'),
        new lang_string('coursesperpage_frontpage_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_COURSES_PER_PAGE_FRONTPAGE,
        PARAM_INT));

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_COURSES_PER_PAGE_MAIN,
        new lang_string('coursesperpage_main', 'tool_catalogue'),
        new lang_string('coursesperpage_main_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_COURSES_PER_PAGE_MAIN,
        PARAM_INT));

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_COURSES_PER_PAGE_SEARCH,
        new lang_string('coursesperpage_search', 'tool_catalogue'),
        new lang_string('coursesperpage_search_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_COURSES_PER_PAGE_SEARCH,
        PARAM_INT));

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_CATEGORIES_LIMIT,
        new lang_string('categorieslimit', 'tool_catalogue'),
        new lang_string('categorieslimit_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_CATEGORIES_LIMIT,
        PARAM_INT));

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_CATEGORIES_DEPTH_LIMIT,
        new lang_string('categoriesdepthlimit', 'tool_catalogue'),
        new lang_string('categoriesdepthlimit_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_CATEGORIES_DEPTH_LIMIT,
        PARAM_INT));

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_SAFE_HTML_TAGS,
        new lang_string('safehtmltags', 'tool_catalogue'),
        new lang_string('safehtmltags_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_SAFE_HTML_TAGS,
        PARAM_TEXT));

    $settings->add(new admin_setting_configtext(
        'tool_catalogue/'.configuration::SETTING_TRUNCATE_SUMMARY,
        new lang_string('truncatesummary', 'tool_catalogue'),
        new lang_string('truncatesummary_desc', 'tool_catalogue'),
        configuration::DEFAULT_TRUNCATE_SUMMARY, PARAM_INT));

    // Tiles view settings.

    $settings->add(new setting_fields_list(
        settings_table_displayfields_tiles::class,
        'tool_catalogue/'.configuration::SETTING_DISPLAYFIELDS_TILES,
        new lang_string('displayfields_tiles', 'tool_catalogue'),
        new lang_string('displayfields_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_DISPLAYFIELDS_TILES));

    // List view settings.

    $settings->add(new setting_fields_list(
        settings_table_displayfields_list::class,
        'tool_catalogue/'.configuration::SETTING_DISPLAYFIELDS_LIST,
        new lang_string('displayfields_list', 'tool_catalogue'),
        new lang_string('displayfields_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_DISPLAYFIELDS_LIST));

    // Filters settings.

    $settings->add(new setting_fields_list(
        settings_table_filterfields::class,
        'tool_catalogue/'.configuration::SETTING_FILTERFIELDS,
        new lang_string('filterfields', 'tool_catalogue'),
        new lang_string('displayfields_desc', 'tool_catalogue'),
        configuration::DEFAULT_SETTING_FILTERFIELDS));

    $ADMIN->add('courses', $settings);
}
