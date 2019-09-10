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
 * Class with all constants.
 *
 * @package   tool_certification
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die;

/**
 * Class constants
 *
 * @package   tool_certification
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** @var int Date is not set */
    public const DATE_NONE = 0;
    /** @var int Date is an specific date (timestamp) */
    public const DATE_ABSOLUTE = 1;
    /** @var int Date is the user allocation date to the certification */
    public const DATE_USER_ALLOCATION_DATE = 2;
    /** @var int Date is relative to after user was allocated to the certification */
    public const DATE_RELATIVE_TO_ALLOCATION_DATE = 3;
    /** @var int Date is set to never (there is no ending date) */
    public const DATE_NEVER = 4;
    /** @var int Date is relative to after user completion date */
    public const DATE_AFTER_COMPLETION = 5;
    /** @var int Date is relative to after user is allocated to certification */
    public const DATE_AFTER_ALLOCATION_DATE = 6;
    /** @var int Date is relative to after due date */
    public const DATE_AFTER_DUE_DATE = 7;
    /** @var int Date is relative to after start date */
    public const DATE_RELATIVE_TO_START_DATE = 8;
    /** @var int Date is relative to after start date */
    public const DATE_AFTER_START_DATE = 9;

    /** @var int The user was allocated manually */
    public const ALLOCATION_MANUAL = 0;
    /** @var int The user was allocated due to some dynamic rule */
    public const ALLOCATION_DYNAMIC = 1;
    /** @var int The user was allocated manually from a certification */
    public const ALLOCATION_CERTIFICATION = 2;

    /** @var int User allocation to a certification is suspended */
    public const STATUS_OVERRIDE_SUSPENDED = 0;
    /** @var int User allocation to a certification is not suspended */
    public const STATUS_OVERRIDE_DEFAULT = 1;

    /** @var int Date is calculated as usual */
    public const DATE_UNLOCKED = 0;
    /** @var int Date has been overriden and should not be recalculated */
    public const DATE_LOCKED = 1;

    /** @var int Allocation date is not set */
    public const ALLOCATION_NOT_SET = 0;
    /** @var int Allocation date is set */
    public const ALLOCATION_SET = 1;

    /** @var int Due date event type */
    public const CALENDAR_EVENT_DUE_DATE = 1;
    /** @var int Expiry date event type */
    public const CALENDAR_EVENT_EXPIRY_DATE = 2;

    /** @var int User is suspended from the certification */
    public const STATUS_SUSPENDED = 0;
    /** @var int The certification has not started yet for this user */
    public const STATUS_FUTUREALLOCATION = 1;
    /** @var int The certification has expired for this user */
    public const STATUS_EXPIRED = 2;
    /** @var int User allocation is certified */
    public const STATUS_CERTIFIED = 3;
    /** @var int User allocation is open/active */
    public const STATUS_OPEN = 4;
    /** @var int User allocation is overdue */
    public const STATUS_OVERDUE = 5;
    /** @var int User allocation is certified and suspended */
    public const STATUS_CERTIFIED_AND_SUSPENDED = 6;
    /** @var int User allocation status is a non-specified one */
    public const STATUS_UNKNOWN = -1;
}
