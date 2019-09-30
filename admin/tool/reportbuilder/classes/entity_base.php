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
 * Class entity_base
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Entity is a typical collection of columns/filters/conditions that can be re-used in the reports
 *
 * For example, user fields, course fields, etc.
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class entity_base {

    /**
     * Name used for this entity, used only internally to build unique keys
     * @return string
     */
    public abstract function get_entity_name() : string;

    /**
     * Name for this entity as displayed to the user in the group names for columns, filters and conditions
     * @return \lang_string
     */
    public abstract function get_entity_title() : \lang_string;

    /**
     * Returns list of all columns that need to be added to the datasource
     *
     * @return report_column[]
     */
    public abstract function get_columns() : array;

    /**
     * Returns list of all conditions that need to be added to the datasource
     *
     * @return report_filter[]
     */
    public abstract function get_conditions() : array;

    /**
     * Returns list of all filters that need to be added to the datasource
     *
     * @return report_filter[]
     */
    public abstract function get_filters() : array;

}