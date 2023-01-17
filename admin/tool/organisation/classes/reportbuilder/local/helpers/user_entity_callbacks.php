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

namespace tool_organisation\reportbuilder\local\helpers;

use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\report\filter;
use lang_string;
use tool_organisation\organisation;

/**
 * Callback to add user job filters/conditions to users entity
 *
 * @package   tool_organisation
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_entity_callbacks {

    /**
     * Filters/conditions for user jobs
     *
     * @param string $entityname
     * @param string $usertablealias
     * @param array $joins
     * @param bool $iscondition
     * @return array
     * @throws \moodle_exception
     */
    public static function get_filters(string $entityname, string $usertablealias, array $joins, bool $iscondition): array {
        $filters = [];

        // Add has current jobs condition and filter.
        $filters[] = (new filter(
            boolean_select::class,
            'hascurrentjobs',
            new lang_string('hascurrentjobs', 'tool_organisation'),
            $entityname,
            \tool_organisation\helper::get_has_current_jobs_sql($usertablealias)
        ))
            ->add_joins($joins);

        // Audience view condition (i.e. which users can current user view in the report).
        if ($iscondition) {
            $filters[] = (new filter(
                \tool_organisation\reportbuilder\local\filters\audience_select::class,
                'audience',
                new lang_string('audienceselect', 'tool_organisation'),
                $entityname,
                $usertablealias
            ))->add_joins($joins);
        }

        $filters[] = (new filter(
            \tool_organisation\reportbuilder\local\filters\user_position::class,
            'jobposition',
            new lang_string('hasjobposition', 'tool_organisation'),
            $entityname,
            $usertablealias
        ))
            ->add_joins($joins)
            ->set_options_callback(function() {
                return organisation::get_all_positions_menu(
                    ['' => get_string('anyposition', 'tool_organisation')]);
            });

        $filters[] = (new filter(
            \tool_organisation\reportbuilder\local\filters\user_department::class,
            'jobdepartment',
            new lang_string('hasjobdepartment', 'tool_organisation'),
            $entityname,
            $usertablealias
        ))
            ->add_joins($joins)
            ->set_options_callback(function() {
                return organisation::get_all_departments_menu(
                    ['' => get_string('anydepartment', 'tool_organisation')]);
            });

        return $filters;
    }
}
