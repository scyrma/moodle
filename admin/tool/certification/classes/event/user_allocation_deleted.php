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
 * Class tool_certification\event\user_deallocated
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\event;

use coding_exception;
use core\event\base;
use moodle_url;
use tool_certification\certification_user;

/**
 * Class user_allocation_deleted
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int certificationid: id of the certification.
 * }
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_allocation_deleted extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param certification_user $certuser The user certification.
     * @return user_allocation_deleted|base
     */
    public static function create_from_user_allocation_deleted(certification_user $certuser) {
        if (!$certuser->get('id')) {
            throw new \coding_exception('The user certification ID must be set.');
        }

        $params = [
            'contextid' => \context_system::instance()->id,
            'objectid' => $certuser->get('id'),
            'relateduserid' => $certuser->get('userid'),
            'other' => [
                'certificationid' => $certuser->get('certificationid'),
                'programid' => $certuser->get('currentprogramid'),
            ]
        ];

        $event = static::create($params);
        $event->add_record_snapshot(certification_user::TABLE, $certuser->to_record());
        return $event;
    }

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = certification_user::TABLE;
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventuserdeallocated', 'tool_certification');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' deallocated " .
            "the user with id '$this->relateduserid' " .
            "from certification with id '" . $this->other['certificationid'] . "'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/certification/edit.php', ['id' => $this->other['certificationid']]);
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (CONTEXT_SYSTEM !== (int) $this->contextlevel) {
            throw new coding_exception('Context level must be CONTEXT_SYSTEM.');
        }
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return string the name of the restore mapping the objectid links to
     */
    public static function get_objectid_mapping(): string {
        return base::NOT_MAPPED;
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the information in 'other' to it's new value in the new course.
     *
     * @return bool an array of other values and their corresponding mapping
     */
    public static function get_other_mapping(): bool {
        // Nothing to map.
        return false;
    }
}
