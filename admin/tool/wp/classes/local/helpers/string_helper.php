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
 * Class string_helper
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Sumit Negi
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_wp\local\helpers;

/**
 * Class string_helper
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Sumit Negi
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class string_helper {

    /**
     * Translates a stored relative date (ex. 1 day/2 week/2 month/1 year) with correct language string.
     *
     * @param string $relativedate
     * @return string
     */
    public static function translate_relativedate_string(string $relativedate): string {
        $data = explode(' ', $relativedate);
        $stridentifier = $data[0] > 1 ? 'num' . $data[1] . 's' : 'num' . $data[1];
        // Avoid the lang string 'numhour' exception, as it is not exist in core 'moodle' component.
        if ($stridentifier === 'numhour') {
            return get_string($stridentifier, 'tool_wp', $data[0]);
        }
        return get_string($stridentifier, 'moodle', $data[0]);
    }
}
