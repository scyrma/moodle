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
 * File for class tool_program\event\program_completed
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\event;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use context_system;
use core\event\base;
use moodle_url;
use tool_program\persistent\program_set_completion;

/**
 * Class tool_program\event\program_completed
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int programid: id of program.
 * }
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_completed extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param program_set_completion $setcompletion The set completion.
     * @param int $programid The program id.
     * @return self|base
     */
    public static function create_from_program_completed(program_set_completion $setcompletion, int $programid): self {
        if (!$setcompletion->get('id')) {
            throw new coding_exception('The set completion ID must be set.');
        }

        $params = [
            'contextid' => context_system::instance()->id,
            'objectid' => $setcompletion->get('id'),
            'relateduserid' => $setcompletion->get('userid'),
            'other' => [
                'programid' => $programid,
            ]
        ];

        $event = static::create($params);
        $event->add_record_snapshot(program_set_completion::TABLE, $setcompletion->to_record());
        return $event;
    }

    /**
     * Initialise the event data.
     */
    protected function init(): void {
        $this->data['objecttable'] = program_set_completion::TABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventprogramcompleted', 'tool_program');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $programid = $this->other['programid'];
        return "The user with id '$this->relateduserid' completed the program with id '$programid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/program/index.php');
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data(): void {
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
