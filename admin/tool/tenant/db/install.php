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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_tool_tenant_install() {
    global $CFG, $DB;

    // Create tenant-related roles.
    update_capabilities('tool_tenant'); // See MDL-65668 about why this is needed.
    \tool_tenant\manager::create_tenant_roles();

    if ((during_initial_install() || (isset($CFG->forcewpsetup) && $CFG->forcewpsetup == true)) &&
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
    if (!defined('BEHAT_SITE_RUNNING')) {
        // Create Login background and logos file objects for default tenant.
        // Do not do it in the behat because it will break all tests looking for specific SITENAME.
        $defaulttenantfiles = [
            'loginbackground' => 'login-image.png',
            'headerlogo' => 'workplacelogo-small.png',
            'loginlogo' => 'workplacelogo.png',
            'tenantselectorlogo' => 'workplacelogo.png',
        ];
        $fs = get_file_storage();
        foreach ($defaulttenantfiles as $filearea => $filename) {
            $filerecord = [
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_tenant',
                'userid' => get_admin()->id,
                'filearea' => $filearea,
                'itemid' => \tool_tenant\tenancy::get_tenant_id(),
                'filepath' => '/',
                'filename' => $filename,
            ];
            $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/pix/' . $filename);
        }
    }

    return true;
}
