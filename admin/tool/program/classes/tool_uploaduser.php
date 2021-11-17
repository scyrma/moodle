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
 * Methods for tool_uploaduser
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_program\persistent\program;
use uu_progress_tracker;

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser {

    /**
     * Callback for tool_uploaduser that process a created user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_new_user($user, $filecolumns, $upt) {
        foreach ($filecolumns as $column) {
            if (preg_match('/^program(\d+)$/', $column, $matches)) {
                $i = $matches[1];

                if (empty($user->{'program'.$i})) {
                    continue;
                }

                $tenantid = \tool_tenant\tenancy::get_tenant_id($user->id);
                $programobj = api::get_program_by_idnumber($user->{'program'.$i}, $tenantid);
                if (!$programobj) {
                    $upt->track('tool_wp', get_string('errorinvalidprogram', 'tool_program'), 'error');
                    continue;
                }

                // We must check if allocation in program already exists.
                $params = [
                    'userid' => $user->id,
                    'programid' => $programobj->get('id'),
                    'certificationid' => 0,
                    'allocationtype' => constants::ALLOCATION_MANUAL
                ];

                /** @var persistent\program_user $programuser */
                $programuser = persistent\program_user::get_record($params);
                $canmanage = $programuser ? permission::can_edit_user_allocation($programuser) :
                    permission::can_allocate_user($programobj, $user->id);

                if (!$canmanage) {
                    $upt->track('tool_wp', get_string('errorcantallocateusers', 'tool_program'), 'error');
                    continue;
                }
                if (!self::validate_date_params($user, $i)) {
                    $upt->track('tool_wp', get_string('errorinvaliddate', 'tool_program'), 'error');
                    continue;
                }
                if ($programuser) {
                    $params = array_merge((array)$programuser->to_record(), self::date_params($user, $i));
                    api::update_program_user_dates_and_status($programuser, (object)$params);
                } else {
                    $params = array_merge($params, self::date_params($user, $i));
                    api::allocate_user($programobj, (object)$params);
                }
            }
        }
    }

    /**
     * Callback for tool_uploaduser that process updated user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_updated_user($user, $filecolumns, $upt) {
        self::process_new_user($user, $filecolumns, $upt);
    }

    /**
     * Fill date parameters if present.
     *
     * @param stdClass $user
     * @param int $i
     * @return array
     */
    private static function date_params($user, $i) {
        $params = [];
        if (!empty($user->{'programstartdate'.$i})) {
            $params['startdate'] = strtotime($user->{'programstartdate'.$i});
            $params['startdatelocked'] = 1;
        }
        if (!empty($user->{'programenddate'.$i})) {
            $params['enddate'] = strtotime($user->{'programenddate'.$i});
            $params['enddatelocked'] = 1;
        }
        if (!empty($user->{'programduedate' . $i})) {
            $params['duedate'] = strtotime($user->{'programduedate'.$i});
            $params['duedatelocked'] = 1;
        }
        return $params;
    }

    /**
     * Check all date params for user.
     *
     * @param stdclass $user
     * @param int $i
     * @return bool
     */
    private static function validate_date_params($user, $i) : bool {
        foreach (['programstartdate', 'programenddate', 'programduedate'] as $param) {
            if (!empty($user->{$param.$i}) && !self::validate_date($user->{$param.$i})) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if date is in the expected 'Y-m-d' format.
     *
     * @param string $date
     * @return bool
     */
    private static function validate_date(string $date) : bool {
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
