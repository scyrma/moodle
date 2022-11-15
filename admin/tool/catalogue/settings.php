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
 * Plugin settings.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_catalogue\constants;

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

    $options = [
        constants::DISPLAY_FOR_EVERYBODY => new lang_string('displayforeverybody', 'tool_catalogue'),
        constants::DISPLAY_ONLY_FOR_STUDENTS_AND_GUESTS => new lang_string('displayforstudentsandguests', 'tool_catalogue'),
        constants::NEVER_DISPLAY => new lang_string('displaynever', 'tool_catalogue'),
    ];
    $default = defined('BEHAT_SITE_RUNNING') ? constants::NEVER_DISPLAY : constants::DISPLAY_FOR_EVERYBODY;
    $temp->add(new admin_setting_configselect('tool_catalogue/displaycoursecovermodals',
        new lang_string('displaycourseinfomodal', 'tool_catalogue'),
        '', $default, $options));

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
