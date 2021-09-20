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
 * Plugin event classes are defined here.
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\event;

use core\event\base;
use tool_organisation\department;
use tool_organisation\department_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * The department_updated event class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class department_updated extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'tool_organisation_department';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an event from department object
     *
     * @param department $department
     * @param \stdClass $oldrecord
     * @return department_updated
     */
    public static function create_from_object(department $department, \stdClass $oldrecord) : base {
        $params = [
            'context' => \context_system::instance(),
            'objectid' => $department->get('id'),
            'other' => [
                'isframework' => $department->is_framework(),
                'frameworkid' => $department->get_framework_id(),
            ]
        ];
        if ($department->get('archived') && !$oldrecord->archived) {
            $params['other']['isarchived'] = true;
        } else if (!$department->get('archived') && $oldrecord->archived) {
            $params['other']['isrestored'] = true;
        }
        $event = static::create($params);
        $event->add_record_snapshot(department::TABLE, $department->to_record());
        return $event;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdepartmentupdated', 'tool_organisation');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if (!empty($this->other['isarchived'])) {
            return "The user with id '$this->userid' archived the department with id '$this->objectid'";
        } else if (!empty($this->other['isrestored'])) {
            return "The user with id '$this->userid' restored the archived department with id '$this->objectid'";
        } else {
            return "The user with id '$this->userid' updated the department with id '$this->objectid'";
        }
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(department_manager::get_department_url($this->other['frameworkid']));
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return int|string
     */
    public static function get_objectid_mapping() {
        return base::NOT_MAPPED;
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the information in 'other' to it's new value in the new course.
     *
     * @return array|bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
