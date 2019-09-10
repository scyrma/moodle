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
 *  * File for class tool_certification\event\user_allocation_updated
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\event;

defined('MOODLE_INTERNAL') || die();

use core\event\base;
use moodle_url;
use tool_certification\certification_user;

/**
 * tool_certification\event\user_allocation_updated
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int certificationid: id of certification.
 *      - int status: status of the user allocation.
 * }
 *
 * @package    tool_certification
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_allocation_updated extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param certification_user $certuser The user certification.
     * @return self
     */
    public static function create_from_user_allocation_updated(certification_user $certuser) {
        if (!$certuser->get('id')) {
            throw new \coding_exception('The certification user ID must be set.');
        }

        $params = array(
            'objectid' => $certuser->get('id'),
            'contextid' => \context_system::instance()->id,
            'relateduserid' => $certuser->get('userid'),
            'other' => array(
                'certificationid' => $certuser->get('certificationid'),
                'status' => $certuser->get('status')
            )
        );
        $event = static::create($params);
        $event->add_record_snapshot(certification_user::TABLE, $certuser->to_record());
        return $event;
    }

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['objecttable'] = certification_user::TABLE;
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventuserupdated', 'tool_certification');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The allocation for user id '$this->userid' on the certification with id '$this->objectid' got updated.";
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