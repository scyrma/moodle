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
 * File for class format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barberá <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\helpers;

use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Class format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barberá <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class format {
    /**
     * Formats a short string
     *
     * @param string $rawstring
     * @return string
     */
    public static function string(?string $rawstring): string {
        return format_string($rawstring, true, ['context' => context_system::instance(), 'escape' => false]);
    }

    /**
     * Formats a boolean
     *
     * @param bool $rawboolean
     * @param string|null $customyesstr
     * @param string|null $customnostr
     * @return string
     */
    public static function yesno(?bool $rawboolean, ?string $customyesstr = null, ?string $customnostr = null): string {
        if (!isset($rawboolean)) {
            return '';
        }
        if ($rawboolean) {
            return $customyesstr ?? get_string('yes');
        }
        return $customnostr ?? get_string('no');
    }

    /**
     * Formats a date
     *
     * @param int $rawtimestamp
     * @param string|null $customformat
     * @return string
     */
    public static function date(?int $rawtimestamp, ?string $customformat = null): string {
        if (!((int)$rawtimestamp > 0)) {
            return '';
        }
        $format = $customformat ?? get_string('strftimedatefullshort');
        return userdate($rawtimestamp, $format);
    }
}
