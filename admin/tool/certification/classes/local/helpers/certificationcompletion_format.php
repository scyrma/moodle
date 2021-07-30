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
 * File for class certificationcompletion_format
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\helpers;

use coding_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certificationcompletion_format
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificationcompletion_format {
    /**
     * Displays column expirydate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function expirydate(?string $value, stdClass $row): string {
        if ($row->expirydate === null) {
            return '';
        }
        if (0 === (int)$row->expirydate) {
            return get_string('never', 'tool_certification');
        }
        return userdate($row->expirydate, get_string('strftimedatefullshort'));
    }

    /**
     * Returns formatted expired
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function expired(?string $value, stdClass $row): string {
        if ($value === null) {
            return '';
        }
        return $value ? get_string('yes') : get_string('no');
    }

    /**
     * Displays certifiedtype (Certified as: Manually/Upon completion).
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function certifiedtype(?string $value, stdClass $row): string {
        switch ((int)$row->certifiedtype) {
            case 1:
                return get_string('uponcompletion', 'tool_certification');
                break;
            case 2:
                return get_string('manual', 'tool_certification');
                break;
            case 0:
            default:
                return '';
        }
    }
}
