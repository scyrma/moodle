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
 * Class observer for tool_tenant
 *
 * @package   tool_tenant
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Adrian Greeve <adrian@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Class tool_tenant_observer
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Adrian Greeve <adrian@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_observer {

    /**
     * User created observer
     *
     * @param \core\event\user_created $event
     */
    public static function on_user_created($event) {
        $manager = new \tool_tenant\manager();
        $manager->allocate_user_on_creation($event);
    }

    /**
     * Course deleted observer
     *
     * @param \core\event\course_deleted $event
     */
    public static function on_course_deleted(\core\event\course_deleted $event) {
        \tool_tenant\tenant_group::delete_for_course($event->courseid);
    }

    /**
     * Category deleted observer
     *
     * @param \core\event\course_category_deleted $event
     */
    public static function on_category_deleted(\core\event\course_category_deleted $event) {
        $categoryid = $event->objectid;

        if ($tenant = \tool_tenant\tenant::get_record(['categoryid' => $categoryid])) {
            $tenantid = $tenant->get('id');
            $manager = new \tool_tenant\manager();
            $manager->update_tenant($tenantid, (object)['categoryid' => 0]);
        }
    }

    /**
     * User profile fields category deleted observer
     *
     * @param \core\event\user_info_category_deleted $event
     */
    public static function on_user_info_category_deleted(\core\event\user_info_category_deleted $event) {
        \tool_tenant\profile_manager::save_category_config((object)['id' => $event->objectid]);
    }
}
