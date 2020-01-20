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
 * Class tool_certification\event\certification_deleted
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\event;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core\event\base;
use moodle_url;
use tool_certification\certification;

/**
 * Class certification_deleted
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int programid: id of program related to this certification.
 * }
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_deleted extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param certification $certification The new certification.
     * @return self
     */
    public static function create_from_certification_deleted(certification $certification) {
        if (!$certification->get('id')) {
            throw new \coding_exception('The certification ID must be set.');
        }

        $params = array(
            'contextid' => \context_system::instance()->id,
            'objectid' => $certification->get('id'),
            'other' => array(
                'programid' => $certification->get('program'),
            )
        );

        $event = static::create($params);
        $event->add_record_snapshot(certification::TABLE, $certification->to_record());
        return $event;
    }

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = certification::TABLE;
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventcertificationdeleted', 'tool_certification');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' deleted the certification with id '$this->objectid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/certification/index.php');
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
