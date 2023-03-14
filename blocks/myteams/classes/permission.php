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

declare(strict_types=1);

namespace block_myteams;

use core_user;
use moodle_exception;
use tool_organisation\permission as org_permission;
use tool_tenant\tenancy;

/**
 * Block myteams permission class.
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * Checks if current user can view course progress for given userid.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function can_view_course_progress(?int $userid = null): bool {
        global $CFG;
        require_once($CFG->dirroot.'/user/lib.php');

        // Check permissions for specific user course progress.
        if ($userid) {
            return user_can_view_profile(core_user::get_user($userid, '*', MUST_EXIST)) &&
                !tenancy::is_user_hidden_by_tenancy($userid);
        }
        return org_permission::user_is_manager();
    }

    /**
     * Require that current user can view course progress for given userid.
     *
     * @param int|null $userid
     * @throws moodle_exception
     */
    public static function require_can_view_course_progress(?int $userid = null): void {
        if (!self::can_view_course_progress($userid)) {
            throw new moodle_exception('errornopermissionviewreports', 'block_myteams');
        }
    }
}
