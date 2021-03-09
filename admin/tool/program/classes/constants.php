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
 * Class with all constants.
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

defined('MOODLE_INTERNAL') || die;

/**
 * Class constants
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class constants {
    /** @var int Date is not set */
    public const DATE_NONE = 0;
    /** @var int Date is an specific date (timestamp) */
    public const DATE_ABSOLUTE = 1;
    /** @var int Date is relative to after user was allocated to the program */
    public const DATE_AFTER_USER_ALLOCATION = 2;
    /** @var int Date is relative to after start date */
    public const DATE_AFTER_START = 3;
    /** @var int Date is relative to before end date */
    public const DATE_BEFORE_END = 4;
    /** @var int Date is relative to after due date */
    public const DATE_AFTER_DUE = 5;
    /** @var int Date is relative to after program allocation window starts */
    public const DATE_AFTER_ALLOCATION_STARTS = 6;
    /** @var int The user was allocated manually */
    public const ALLOCATION_MANUAL = 0;
    /** @var int The user was allocated due to some dynamic rule */
    public const ALLOCATION_DYNAMIC = 1;
    /** @var int The user was allocated manually from a certification */
    public const ALLOCATION_CERTIFICATION = 2;
    /** @var int The program visibility is set to hidden/not visible */
    public const VISIBILITY_HIDDEN = 0;
    /** @var int The program visibility is set to available/visible/not hidden */
    public const VISIBILITY_AVAILABLE = 1;
    /** @var int User allocation to a program is suspended */
    public const STATUS_OVERRIDE_SUSPENDED = 0;
    /** @var int User allocation to a program is not suspended */
    public const STATUS_OVERRIDE_DEFAULT = 1;
    /** @var int Date is unlocked and can be recalculated as usual */
    public const DATE_UNLOCKED = 0;
    /** @var int Date is locked to the current value and should not be recalculated */
    public const DATE_LOCKED = 1;
    /** @var int Calendar event is related to a program allocation due date */
    public const CALENDAR_EVENT_DUE_DATE = 1;
    /** @var int Calendar event is related to a program allocation end date */
    public const CALENDAR_EVENT_END_DATE = 2;
    /** @var int User is suspended from the program */
    public const STATUS_SUSPENDED = 0;
    /** @var int User has completed the program */
    public const STATUS_COMPLETED = 1;
    /** @var int The program has not started yet for this user */
    public const STATUS_FUTUREALLOCATION = 2;
    /** @var int User allocation is overdue */
    public const STATUS_OVERDUE = 3;
    /** @var int User allocation is open/active */
    public const STATUS_OPEN = 4;
    /** @var int User allocation status is a non-specified one */
    public const STATUS_UNKNOWN = -1;
    /** @var int Maximum timestamp for comparisons */
    public const MAX_DATE = 9999999999;
}
