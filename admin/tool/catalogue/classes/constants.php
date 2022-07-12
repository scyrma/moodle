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

declare(strict_types=1);

namespace tool_catalogue;

/**
 * Constants class
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class constants {

    /** @var int Maximum timestamp for comparisons */
    public const MAX_DATE = 9999999999;

    /** @var string Filter by all (programs and courses) */
    public const FILTER_ALL = 'all';
    /** @var string Filter by courses */
    public const FILTER_COURSES = 'courses';
    /** @var string Filter by programs */
    public const FILTER_PROGRAMS = 'programs';
    /** @var string Filter by complete */
    public const FILTER_COMPLETE = 'complete';
    /** @var string Filter by incomplete */
    public const FILTER_INCOMPLETE = 'incomplete';

    /** @var string Sort by due date */
    public const SORT_DUEDATE = 'duedate';
    /** @var string Sort by name */
    public const SORT_NAME = 'name';
    /** @var string Sort by last access */
    public const SORT_LASTACCESS = 'lastaccess';

    /** @var string Start date */
    public const STARTDATE = 'startdate';
    /** @var string Due date */
    public const DUEDATE = 'duedate';
    /** @var string End date */
    public const ENDDATE = 'enddate';
}
