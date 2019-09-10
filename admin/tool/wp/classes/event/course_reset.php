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
 * File for class tool_wp\event\course_reset
 *
 * @package   tool_wp
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp\event;

use core\event\base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_wp\event\course_reset
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int programid: id of program.
 *      - int certificationid: id of certification.
 * }
 *
 * @package    tool_wp
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reset extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param \stdClass $resetdata
     * @param int $recordid
     * @return self|base
     */
    public static function create_from_course_reset(\stdClass $resetdata, int $recordid): self {
        if (!isset($resetdata->courseid)) {
            throw new coding_exception('The course ID must be set.');
        }
        if (!isset($resetdata->userid)) {
            throw new coding_exception('The userid ID must be set.');
        }
        if (!isset($resetdata->programid)) {
            $resetdata->programid = 0;
        }
        if (!isset($resetdata->certificationid)) {
            $resetdata->certificationid = 0;
        }

        $params = [
            'contextid' => \context_course::instance($resetdata->courseid)->id,
            'objectid' => $recordid,
            'relateduserid' => $resetdata->userid,
            'other' => [
                'programid' => $resetdata->programid,
                'certificationid' => $resetdata->certificationid,
            ]
        ];

        return static::create($params);
    }

    /**
     * Initialise the event data.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'tool_wp_course_reset';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventcoursereset', 'tool_wp');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $userid = $this->userid;
        return "Course with id '$this->courseid' was reset for user with id '$userid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
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
