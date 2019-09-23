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
 * Class observer for tool_tenant
 *
 * @package   tool_tenant
 * @copyright 2019 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Class tool_tenant_observer
 *
 * @copyright 2019 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_tenant_observer {

    /**
     * User created observer
     *
     * @param \core\event\user_created $event
     */
    public static function on_user_created($event) {
        $userid = $event->objectid;
        $tenantid = \tool_tenant\tenancy::get_tenant_id($userid);
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_user_role($userid, $tenantid);
    }

    /**
     * Course deleted observer
     *
     * @param \core\event\course_deleted $event
     */
    public static function on_course_deleted(\core\event\course_deleted $event) {
        \tool_tenant\tenant_group::delete_for_course($event->courseid);
    }
}