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
 * Class report_tool_certificate_templates datasource
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Daniel Neis Araujo <danielneis@gmail.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_reportbuilder\datasource;
use tool_reportbuilder\report_column;
use tool_reportbuilder\tool_reportbuilder\entities\tool_certificate_template;

/**
 * Class report_tool_certificate_templates
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_tool_certificate_templates extends datasource {

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     */
    public function convert_get_datasource_class(): string {
        return \tool_certificate\reportbuilder\datasource\templates::class;
    }

    /**
     * Get the entity name that corresponds to the given entity in the converted datasource
     *
     * @param string $oldentityname entity name in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string entity name in the converted datasource
     */
    public function convert_get_entity_name(string $oldentityname, \core_reportbuilder\datasource $newsource): string {
        if ($oldentityname === 'tool_certificate_template') {
            return 'template';
        }
        return parent::convert_get_entity_name($oldentityname, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which column corresponds to the given column
     *
     * @param report_column $oldcolumn the column in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding column in the converted datasource
     */
    public function convert_get_column_unique_identifier(report_column $oldcolumn,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldcolumn->get_unique_identifier() === 'tool_certificate_template:coursecatnamewithlink') {
            // If column 'course_category:namewithlink' exists - use it, otherwise use course_category:name.
            return 'course_category:name';
        } else if ($oldcolumn->get_unique_identifier() === 'tool_certificate_template:coursecatname') {
            return 'course_category:name';
        }
        return parent::convert_get_column_unique_identifier($oldcolumn, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param \tool_reportbuilder\report_filter $oldfilter the filter in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     */
    public function convert_get_filter_unique_identifier(\tool_reportbuilder\report_filter $oldfilter,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldfilter->get_unique_identifier() === 'tool_certificate_template:coursecategory') {
            return 'course_category:name';
        }
        return parent::convert_get_filter_unique_identifier($oldfilter, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which condition corresponds to the given condition
     *
     * @param \tool_reportbuilder\report_filter $oldcondition the condition in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding condition in the converted datasource
     */
    public function convert_get_condition_unique_identifier(\tool_reportbuilder\report_filter $oldcondition,
                                                            \core_reportbuilder\datasource $newsource): string {
        if ($oldcondition->get_unique_identifier() === 'tool_certificate_template:coursecategory') {
            return 'course_category:name';
        }
        return parent::convert_get_condition_unique_identifier($oldcondition, $newsource);
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        // Set main table. For certificates we want a custom tenant filter, so disable automatic one.
        $this->set_main_table('tool_certificate_templates', 'tct', false);
        list($sql, $params) = $this->get_visible_categories_contexts_sql();
        $this->add_base_join("JOIN {context} ctx
            ON ctx.id = tct.contextid AND " . $sql, $params);
        $p3 = \tool_wp\db::generate_param_name();
        $this->add_base_join("LEFT JOIN {course_categories} coursecat
            ON coursecat.id = ctx.instanceid AND ctx.contextlevel = :{$p3}",
            [$p3 => CONTEXT_COURSECAT]);

        $this->set_downloadable(true);
        $this->set_columns();
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new tool_certificate_template());

        if ($column = $this->get_column('tool_certificate_template:name')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_certificate_template:coursecatnamewithlink')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_certificate_template:timecreated')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true);
        }

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_certificate_template:name']->set_is_default(true, [1]);
        $filters['tool_certificate_template:coursecategory']->set_is_default(true);
        $filters['tool_certificate_template:timecreated']->set_is_default(true);
    }

    /**
     * Get the report availability.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('\tool_certificate\permission');
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('certificatetemplates', 'tool_reportbuilder');
    }

    /**
     * Subquery for visible contexts for a category/system
     *
     * @return array
     */
    protected function get_visible_categories_contexts_sql() {
        global $DB;
        $contextids = \tool_certificate\permission::get_visible_categories_contexts(false);
        if ($contextids) {
            list($sql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, \tool_wp\db::generate_param_name());
            return ['ctx.id '.$sql, $params];
        } else {
            return ['1=0', []];
        }
    }
}
