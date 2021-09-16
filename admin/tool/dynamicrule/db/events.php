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
 * Event observers.
 *
 * @package     tool_dynamicrule
 * @category    event
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

$observers = [
    [
        'eventname' => '*',
        'callback' => 'tool_dynamicrule\event\observer::process_event'
    ],
    [
        'eventname' => \tool_tenant\event\tenant_deleted::class,
        'callback' => 'tool_dynamicrule\event\observer::tenant_deleted'
    ],
    [
        'eventname' => \tool_tenant\event\tenant_user_updated::class,
        'callback' => 'tool_dynamicrule\event\observer::tenant_user_updated'
    ],
    [
        'eventname' => \tool_tenant\event\tenant_updated::class,
        'callback' => 'tool_dynamicrule\event\observer::tenant_updated'
    ],
    [
        'eventname' => \core\event\user_created::class,
        'callback' => 'tool_dynamicrule\event\observer::user_created'
    ],
];
