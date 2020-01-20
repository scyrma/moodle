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
 * Admin settings
 *
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/appointment/lib.php');

$settings->add(new admin_setting_configtext(
    'appointment_fromaddress',
    get_string('setting:fromaddress_caption', 'appointment'),
    get_string('setting:fromaddress', 'appointment'),
    get_string('setting:fromaddressdefault', 'appointment'),
    PARAM_EMAIL,
    30
));

// Load roles.
$choices = array();
if ($roles = role_fix_names(get_all_roles(), context_system::instance())) {
    foreach ($roles as $role) {
        $choices[$role->id] = format_string($role->localname);
    }
}

$settings->add(new admin_setting_configmultiselect(
    'appointment_session_roles',
    get_string('setting:sessionroles_caption', 'appointment'),
    get_string('setting:sessionroles', 'appointment'),
    array(),
    $choices
));


$settings->add(new admin_setting_heading(
    'appointment_manageremail_header',
    get_string('manageremailheading', 'appointment'),
    ''
));

$settings->add(new admin_setting_configcheckbox(
    'appointment_addchangemanageremail',
    get_string('setting:addchangemanageremail_caption', 'appointment'),
    get_string('setting:addchangemanageremail', 'appointment'),
    0
));

$settings->add(new admin_setting_configtext(
    'appointment_manageraddressformat',
    get_string('setting:manageraddressformat_caption', 'appointment'),
    get_string('setting:manageraddressformat', 'appointment'),
    get_string('setting:manageraddressformatdefault', 'appointment'),
    PARAM_TEXT
));

$settings->add(new admin_setting_configtext(
    'appointment_manageraddressformatreadable',
    get_string('setting:manageraddressformatreadable_caption', 'appointment'),
    get_string('setting:manageraddressformatreadable', 'appointment'),
    get_string('setting:manageraddressformatreadabledefault', 'appointment'),
    PARAM_NOTAGS
));

$settings->add(new admin_setting_heading('appointment_cost_header', get_string('costheading', 'appointment'), ''));

$settings->add(new admin_setting_configcheckbox(
    'appointment_hidecost',
    get_string('setting:hidecost_caption', 'appointment'),
    get_string('setting:hidecost', 'appointment'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'appointment_hidediscount',
    get_string('setting:hidediscount_caption', 'appointment'),
    get_string('setting:hidediscount', 'appointment'),
    0
));

$settings->add(new admin_setting_heading('appointment_icalendar_header', get_string('icalendarheading', 'appointment'), ''));

$settings->add(new admin_setting_configcheckbox(
    'appointment_oneemailperday',
    get_string('setting:oneemailperday_caption', 'appointment'),
    get_string('setting:oneemailperday', 'appointment'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'appointment_disableicalcancel',
    get_string('setting:disableicalcancel_caption', 'appointment'),
    get_string('setting:disableicalcancel', 'appointment'),
    0
));

$modappointmentfolder = new admin_category('modappointmentfolder',
    new lang_string('pluginname', 'mod_appointment'), $module->is_enabled() === false);
$ADMIN->add('modsettings', $modappointmentfolder);

$ADMIN->add('modappointmentfolder', new admin_externalpage('modappointmentcustomfield',
        get_string('appointmentcustomfields', 'mod_appointment'),
        new moodle_url('/mod/appointment/customfield.php'),
        'mod/appointment:managecustomfields'));
