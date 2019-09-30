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
 * Methods for tool_uploaduser
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis Araujo
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use uu_progress_tracker;

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis Araujo
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploaduser {

    /**
     * Callback for tool_uploaduser that process a line of user upload.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_new_user($user, $filecolumns, $upt) {
        foreach ($filecolumns as $column) {
            if (preg_match('/^certification\d+$/', $column)) {
                $i = substr($column, 13);

                if (empty($user->{'certification'.$i})) {
                    continue;
                }

                $tenantid = \tool_tenant\tenancy::get_tenant_id($user->id);
                if ($tenantid != \tool_tenant\tenancy::get_tenant_id()) {
                    // Only can allocate within the same tenant.
                    $upt->track('tool_wp', get_string('errorcantallocateusers', 'tool_certification'), 'error');
                    continue;
                }

                $certificationobj = api::get_certification_by_idnumber($user->{'certification'.$i}, $tenantid);
                if (!$certificationobj) {
                    $errorstr = get_string('errorinvalidcertification', 'tool_certification');
                    $upt->track('tool_wp', $errorstr, 'error');
                    continue;
                }

                $params = [
                    'userid' => $user->id,
                    'certificationid' => $certificationobj->get('id'),
                    'allocationtype' => constants::ALLOCATION_MANUAL
                ];

                /** @var certification_user $certuser */
                $certuser = certification_user::get_record($params);

                $canallocate = $certuser ? permission::can_edit_user_allocation($certuser)
                    : permission::can_allocate_user($certificationobj, $user->id);

                if (!$canallocate) {
                    $errorstr = get_string('cannotallocateuser', 'tool_certification');
                    $upt->track('tool_wp', $errorstr, 'error');
                    continue;
                }
                if (!self::validate_date_params($user, $i)) {
                    $upt->track('tool_wp', get_string('errorinvaliddate', 'tool_organisation'), 'error');
                    continue;
                }
                if ($certuser) {
                    $params = array_merge((array)$certuser->to_record(), self::date_params($user, $i));
                    api::update_certification_user_dates_and_status($certuser, (object)$params);
                } else {
                    $params['status'] = constants::STATUS_OVERRIDE_DEFAULT;
                    $params = array_merge($params, self::date_params($user, $i));
                    api::allocate_user($certificationobj, (object)$params);
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
        if (!empty($user->{'certificationstartdate'.$i})) {
            $params['startdate'] = strtotime($user->{'certificationstartdate'.$i});
            $params['startdatelocked'] = 1;
        }
        if (!empty($user->{'certificationenddate'.$i})) {
            $params['enddate'] = strtotime($user->{'certificationenddate'.$i});
            $params['enddatelocked'] = 1;
        }
        if (!empty($user->{'certificationduedate' . $i})) {
            $params['duedate'] = strtotime($user->{'certificationduedate'.$i});
            $params['duedatelocked'] = 1;
        }
        if (!empty($user->{'certificationexpirydate' . $i})) {
            $params['expirydate'] = strtotime($user->{'certificationexpirydate'.$i});
            $params['expirydatelocked'] = 1;
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
        foreach (['certificationstartdate', 'certificationenddate', 'certificationduedate', 'certificationexpirydate'] as $param) {
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
