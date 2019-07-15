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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     tool_tenant
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_tool_tenant_install() {
    global $CFG, $DB;

    // Create tenant-related roles.
    update_capabilities('tool_tenant'); // TODO MDL-65668 remove.
    \tool_tenant\manager::create_tenant_roles();

    if (during_initial_install() &&
            !defined('BEHAT_SITE_RUNNING') && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
        // When Moodle Workplace is installed remove capability to view list of courses from regular users.
        // Do not do it in the behat/unittests because it will break all core tests.
        $userroleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        $guestroleid = $DB->get_field('role', 'id', ['shortname' => 'guest']);
        unassign_capability('moodle/category:viewcourselist', $userroleid);
        unassign_capability('moodle/category:viewcourselist', $guestroleid);

        \tool_tenant\manager::change_core_roles();

        // Rename default category and associate it to default tenant.
        $category = \core_course_category::get_default();
        $category->update(['name' => get_string('defaultname', 'tool_tenant')]);
        $manager = new \tool_tenant\manager();
        $manager->update_tenant(\tool_tenant\tenancy::get_tenant_id(), (object)['categoryid' => $category->id]);
    }

    // Login background file object.
    $filerecord = new stdClass;
    $filerecord->component = 'tool_tenant';
    $filerecord->contextid = context_system::instance()->id;
    $filerecord->userid    = get_admin()->id;
    $filerecord->filearea  = 'loginbackground';
    $filerecord->filepath  = '/';
    $filerecord->filename  = 'login-image.png';
    $filerecord->itemid    = \tool_tenant\tenancy::get_tenant_id();

    $fs = get_file_storage();
    $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/admin/tool/tenant/pix/' . $filerecord->filename);

    return true;
}
