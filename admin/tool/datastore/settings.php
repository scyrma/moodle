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
 * Datastore related settings.
 *
 * @package   tool_datastore
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if (!$ADMIN->locate('reportbuilder')) {
    $ADMIN->add('reports', new admin_category('reportbuilder', new lang_string('pluginname', 'tool_datastore')));
}

$settings = new admin_settingpage('datastoresettings', new lang_string('datastoresettings', 'tool_datastore'));
$ADMIN->add('reportbuilder', $settings);
$settings->add(new admin_setting_configcheckbox('tool_datastore/enabled',
        new lang_string('datastoreenabled', 'tool_datastore'),
        new lang_string('datastoreenabled_desc', 'tool_datastore'), 0)
);

$settings->add(new admin_setting_configtext('tool_datastore/fieldsuser',
        new lang_string('datastorefieldsuser', 'tool_datastore'),
        new lang_string('datastorefieldsuser_desc', 'tool_datastore'),
        'username,idnumber,firstname,lastname,email')
);

$settings->add(new admin_setting_configtext('tool_datastore/fieldscourse',
        new lang_string('datastorefieldscourse', 'tool_datastore'),
        new lang_string('datastorefieldscourser_desc', 'tool_datastore'),
        'category,fullname,shortname,idnumber,summary,summaryformat,format')
);