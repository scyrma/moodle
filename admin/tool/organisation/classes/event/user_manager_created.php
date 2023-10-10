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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_organisation\event;

use core\event\base;
use moodle_url;
use tool_organisation\local\persistent\user_manager;

/**
 * The user_manager_created event class.
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_manager_created extends base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = user_manager::TABLE;
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an event from user_manager object
     *
     * @param user_manager $usermanager
     * @return base
     */
    public static function create_from_object(user_manager $usermanager): base {
        $params = [
            'context' => \context_system::instance(),
            'objectid' => $usermanager->get('id'),
            'other' => [
                'employeeid' => $usermanager->get('userid'),
                'managerid' => $usermanager->get('managerid'),
            ],
        ];
        $event = static::create($params);
        $event->add_record_snapshot(user_manager::TABLE, $usermanager->to_record());
        return $event;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventusermgrcreated', 'tool_organisation');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user ".$this->other['managerid']." was assigned as a manager over user ".$this->other['employeeid'];
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/organisation/user.php', ['id' => $this->other['employeeid']]);
    }
}
