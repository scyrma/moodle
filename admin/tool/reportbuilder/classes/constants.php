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
 * Class with all constants for the report builder.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

/**
 * Class constants
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class constants {
    /** @var int  */
    const TYPE_DATASOURCE = 0;
    /** @var int  */
    const TYPE_SYSTEM = 1;

    /** Integer, float, decimal, number ... */
    const DB_TYPE_NUMBER = 1;
    /** Text, char, varchar, binary ... */
    const DB_TYPE_TEXT = 2;
    /** Datetime */
    const DB_TYPE_DATETIME = 3;
    /** Timestamp */
    const DB_TYPE_TIMESTAMP = 4;
    /** Boolean */
    const DB_TYPE_BOOLEAN = 5;
    /** Decimal */
    const DB_TYPE_DECIMAL = 6;
    /** Decimal */
    const DB_TYPE_LONGTEXT = 7;

    /** @var int  */
    const RECURRENCE_NONE = 1;
    /** @var int  */
    const RECURRENCE_DAILY = 2;
    /** @var int  */
    const RECURRENCE_WEEKLY = 3;
    /** @var int  */
    const RECURRENCE_MONTHLY = 4;
    /** @var int  */
    const RECURRENCE_ANNUALLY = 5;
    /** @var int  */
    const RECURRENCE_DAILY_WEEKDAY = 6;
}
