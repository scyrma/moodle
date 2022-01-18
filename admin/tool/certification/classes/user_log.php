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
 * File for class user_log
 *
 * @package   tool_certification
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use tool_program\persistent\program;

/**
 * Class user_log
 *
 * This class generates a log with all user events on this certification (allocation, certified, revoked, ..).
 *
 * @package   tool_certification
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_log {

    /** @var int $userid */
    private $userid;
    /** @var int $certificationid */
    private $certificationid;
    /** @var array $logdata Report generated ready to export. */
    private $logdata = [];
    /** @var certification_user $certificationuser */
    private $certificationuser;
    /** @var array $users Used to store all users that appear in this log. */
    private $users = [];

    /**
     * Class user_log constructor.
     *
     * @param int $userid
     * @param int $certificationid
     */
    public function __construct(int $userid, int $certificationid) {
        $this->userid = $userid;
        $this->certificationid = $certificationid;
    }

    /**
     * Returns user certification log.
     *
     * @return array
     */
    public function get_user_log(): array {
        $params = ['userid' => $this->userid, 'certificationid' => $this->certificationid];
        $this->certificationuser = certification_user::get_record($params);
        $this->generate_log();
        return [
            'log' => $this->logdata,
            'lastallocationdate' => $this->certificationuser->get('timecreated'),
        ];
    }

    /**
     * Get user by user id
     *
     * @param int $userid
     * @return \stdClass
     */
    private function get_user_by_id(int $userid): \stdClass {
        if (isset($this->users[$userid])) {
            $user = $this->users[$userid];
        } else {
            $this->users[$userid] = $user = \core_user::get_user($userid);
        }

        return $user;
    }

    /**
     * Builds user profile field that will show on the log with the name and picture.
     *
     * @param int|null $userid
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function get_user_field(?int $userid): array {
        global $OUTPUT;

        // If user is the system.
        if (!$userid) {
            return [
                'id' => 0,
                'picture' => $OUTPUT->pix_icon('info-circle', '', 'tool_wp', ['class' => 'systemusericon']),
                'fullname' => get_string('system', 'tool_certification'),
                'profileurl' => ''
            ];
        }

        $user = $this->get_user_by_id($userid);

        return [
            'id' => $userid,
            'picture' => $OUTPUT->user_picture($user, ['class' => 'rounded-circle', 'link' => false]),
            'fullname' => fullname($user),
            'profileurl' => (new \moodle_url('/user/profile.php', array('id' => $userid)))->out(false)
        ];
    }

    /**
     * Adds a user event to the log.
     *
     * @param int|null $userid
     * @param string $name
     * @param int $date
     */
    private function add_event(?int $userid, string $name, int $date): void {
        $this->logdata[] = [
            'user' => $this->get_user_field($userid),
            'event' => $name,
            'date' => $date
        ];
    }

    /**
     * Generates log from all completion records and certification user data.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function generate_log(): void {
        global $DB;

        $this->logdata = [];
        $report = [];
        $eventsrevoked = [];

        $params = ['userid' => $this->userid, 'certificationid' => $this->certificationid];
        $completionrecords = $DB->get_records('tool_certification_compltion', $params, 'id');

        foreach ($completionrecords as $record) {
            $inarray = in_array($record->timecertified, array_column($report, 'date'), true);

            if ($inarray) {
                // This is a change of expiry date, remove previous entry from revoked array items because it was duplicated.
                $key = array_search($record->timecertified, array_column($eventsrevoked, 'timecertified'), true);
                if ((int)$record->expirydate !== (int)$eventsrevoked[$key]['expirydate']
                && (int)$record->timecreated === (int)$eventsrevoked[$key]['date']) {
                    unset($eventsrevoked[$key]);
                    $eventsrevoked = array_values($eventsrevoked);
                }
            } else {

                if ((int)$record->expirydate === constants::DATE_NONE) {
                    $expirydate = get_string('neverexpires', 'tool_certification');
                } else {
                    $expirydate = userdate($record->expirydate, get_string('strftimedatefullshort', 'langconfig'));
                    $expirydate = get_string('expireson', 'tool_certification', $expirydate);
                }

                // Check if user completed the program or got manually certified.
                if (!$record->certifiedby) {
                    $programname = format_string((new program($record->programid))->get('fullname'));
                    // If user completed the program we need to show an extra message.
                    $report[] = [
                        'user' => $this->userid,
                        'event' => get_string('completedtheprogram', 'tool_certification', $programname),
                        'date' => $record->timecertified
                    ];
                    // Add event for user certified.
                    $report[] = [
                        'user' => $this->userid,
                        'event' => get_string('becamecertified', 'tool_certification', $expirydate),
                        'date' => $record->timecertified
                    ];
                } else {
                    // Add event for user manually certified.
                    $report[] = [
                        'user' => $record->certifiedby,
                        'event' => get_string(
                            'manuallycertifieduser',
                            'tool_certification',
                            ['expirydate' => $expirydate, 'usertarget' => fullname($this->get_user_by_id($this->userid))]
                        ),
                        'date' => $record->timecertified
                    ];
                }

                // Add event for certification expired.
                if ((int)$record->expirydate > 0 && time() > (int)$record->expirydate) {
                    $report[] = [
                        'user' => null,
                        'event' => get_string('conditioncertificationexpired', 'tool_certification'),
                        'date' => $record->expirydate
                    ];
                }
            }

            // If record has been revoked we add it to revoked events array to reorder them later.
            if (!in_array($record->timerevoked, array_column($eventsrevoked, 'date'), true)
                && (int)$record->timerevoked > 0) {
                $eventsrevoked[] = [
                    'user' => $record->revokedby,
                    'event' => get_string(
                        'revokedthisuser',
                        'tool_certification',
                        fullname($this->get_user_by_id($this->userid))
                    ),
                    'date' => $record->timerevoked,
                    'expirydate' => $record->expirydate,
                    'timecertified' => $record->timecertified
                ];
            }
        }

        // Add suspended event in case user is currently suspended.
        if ((int)$this->certificationuser->get('timesuspended') > 0) {
            $report[] = [
                'user' => null,
                'event' => get_string('usergotsuspended', 'tool_certification'),
                'date' => (int)$this->certificationuser->get('timesuspended')
            ];
        }

        $report = array_merge($report, $eventsrevoked);
        usort($report, static function($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        foreach ($report as $event) {
            $this->add_event($event['user'], $event['event'], $event['date']);
        }
    }
}
