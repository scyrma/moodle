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

namespace tool_program\reportbuilder\local\formatters;

use stdClass;
use tool_program\persistent\program_set;

/**
 * Formatters for the program content entity
 *
 * @package   tool_program
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content {

    /**
     * Returns program set completion criteria
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function completion_criteria(?string $value, stdClass $row): string {
        switch ($row->completioncriteria) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
                return get_string('completeallinanyorder', 'tool_program');
            case program_set::COMPLETION_ALL_IN_ORDER:
                return get_string('completeallinorder', 'tool_program');
            case program_set::COMPLETION_AT_LEAST:
                return get_string('completeatleast', 'tool_program') . ' ' . $row->completionatleast;
            default:
                return '-';
        }
    }
}
