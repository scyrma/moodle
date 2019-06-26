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
 * Class aggregation_base
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for declare aggregations
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class aggregation_base {
    /**
     * Return a display name of the transformation option.
     *
     * @return string
     */
    public abstract static function get_displayname() : string;

    /**
     * Returns appropriate display function.
     * @return string
     */
    public abstract static function get_shortname() : string;

    /**
     * Get the field string.
     *
     * @param string $field
     * @param int|null $dbtype
     * @return string
     */
    public abstract static function get_field(string $field, ?int $dbtype = null) : string;

    /**
     * Is this aggregation compatible with given column type?
     *
     * @param int|null $dbtype
     * @return bool
     */
    public abstract static function is_compatible(?int $dbtype) : bool;
}
