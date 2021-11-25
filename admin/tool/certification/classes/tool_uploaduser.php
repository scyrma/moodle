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
 * Methods for tool_uploaduser
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis Araujo
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        global $USER;

        foreach ($filecolumns as $column) {
            if (preg_match('/^certification\d+$/', $column)) {
                $i = substr($column, 13);

                if (empty($user->{'certification'.$i})) {
                    continue;
                }

                $tenantid = \tool_tenant\tenancy::get_tenant_id($user->id);

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
                    $errorstr = get_string('errorcantallocateusers', 'tool_program');
                    $upt->track('tool_wp', $errorstr, 'error');
                    continue;
                }
                if (!self::validate_date_params($user, $i)) {
                    $upt->track('tool_wp', get_string('errorinvaliddate', 'tool_certification'), 'error');
                    continue;
                }
                if ($certuser) {
                    $dateparams = self::date_params($user, $i);
                    $params = $params + $dateparams +
                        edit_certification_users_edit_form_modal::prepare_data_for_dynamic_submission($certuser);
                    api::update_certification_user_dates_and_status($certuser, (object)$params);
                } else {
                    $params['status'] = constants::STATUS_OVERRIDE_DEFAULT;
                    $params = array_merge($params, self::date_params($user, $i));
                    $certuser = api::allocate_user($certificationobj, (object)$params);
                }
                // If certificationcertify is set to 1 it will certify this user.
                if (!empty($user->{'certificationcertify' . $i})) {

                    $timecertified = time();
                    if (!empty($user->{'certificationcertifytimecertified' . $i})) {
                        $timecertified = strtotime($user->{'certificationcertifytimecertified' . $i});
                    }
                    $iscertified = api::is_user_certified($params['userid'], $params['certificationid']);

                    if ($iscertified) {
                        $upt->track('tool_wp', get_string('erroralreadycertified', 'tool_certification'), 'error');
                        continue;
                    }
                    if (permission::can_certify_user_deep($certuser, $iscertified)) {
                        if (empty($user->{'certificationcertifyexpires' . $i})) {
                            $expirydate = null;
                        } else {
                            $expirydate = strtotime($user->{'certificationcertifyexpires' . $i});
                        }
                        api::set_user_as_certified(
                            $params['userid'],
                            $params['certificationid'],
                            $expirydate,
                            $timecertified,
                            $USER->id
                        );
                        // Effectively run recertification task but for this user allocation only.
                        // Do not reset the program when a past certification has been manually imported.
                        api::allocate_recertification_users($certuser, false);
                        api::deallocate_users_after_grace_period_end($certuser, false);
                    } else {
                        $upt->track('tool_wp', get_string('errornopermissioncertifyuser', 'tool_certification'), 'error');
                        continue;
                    }
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
        $datefields = [
            'certificationstartdate',
            'certificationduedate',
            'certificationexpirydate',
            'certificationcertifytimecertified',
            'certificationcertifyexpires',
        ];
        foreach ($datefields as $param) {
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
