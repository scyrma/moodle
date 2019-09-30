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
 * Class report_course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_wp\tool_reportbuilder\datasources;

use moodle_exception;
use tool_reportbuilder\datasource;
use tool_wp\local\helpers\course_reset_entity;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_course_reset extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_wp_course_reset', 'twpcr');
        $this->add_base_join('INNER JOIN {user} u ON twpcr.userid = u.id');

        $this->set_downloadable(true);
        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        if ($column = $this->get_column('tool_wp_course_reset:course')) {
            $column->set_is_default(true);
            $column->set_is_sortable(true, true, 1);
        }

        if ($column = $this->get_column('tool_wp_course_reset:user')) {
            $column->set_is_default(true);
            $column->set_is_sortable(true, true, 2);
        }
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('coursereset', 'tool_wp');
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new course_reset_entity('', 'twpcr', [], 'u'));

    }

    /**
     * Set the filters of the report
     */
    protected function set_filters(): void {
        $this->add_filters_conditions_helper('add_filter');
    }

    /**
     * Available conditions to be selected in the report.
     */
    protected function set_conditions(): void {
        $this->add_filters_conditions_helper('add_condition');
    }

    /**
     * Helper that add filters or conditions depend how is called
     *
     * @param string $method
     */
    protected function add_filters_conditions_helper(string $method): void {
        if (!in_array($method, ['add_filter', 'add_condition'])) {
            throw new moodle_exception('Helper action not allowed.');
        }
    }
}
