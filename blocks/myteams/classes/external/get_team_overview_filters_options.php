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

namespace block_myteams\external;

use context_system;
use core_reportbuilder\local\filters\text;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\filters\org_structure;
use tool_tenant\hierarchy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function get_team_overview_filters_options for block_myteams.
 *
 * @package   block_myteams
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_team_overview_filters_options extends external_api {

    /**
     * Describes the parameters for get_users_courses.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * External function to get the myteams filters options to populate the form.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);

        // Check if user has permission to retrieve all the information.
        $manager = organisation::get_user_with_jobs();
        if (!$manager || !$manager->is_manager()) {
            throw new \moodle_exception('nopermissions', 'error', '', 'Get team overview filters options');
        }

        [$select, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid');
        $fields = 'id,name,parentid,tenantid';

        $departments = [];
        $alldepartments = $DB->get_records_select('tool_organisation_department', $select, $params, '', $fields);
        foreach ($alldepartments as $department) {
            $departments[] = [
                'id' => $department->id,
                'name' => format_string($department->name, true, ['context' => context_system::instance()]),
                'parentid' => $department->parentid ?? 0,
            ];
        }

        $positions = [];
        $allpositions = $DB->get_records_select('tool_organisation_position', $select, $params, '', $fields);
        foreach ($allpositions as $position) {
            $positions[] = [
                'id' => $position->id,
                'name' => format_string($position->name, true, ['context' => context_system::instance()]),
                'parentid' => $position->parentid ?? 0,
            ];
        }

        // Use the text filter from core reportbuilder to get the SQL query and parameters.
        $fullnameoperators = [
            ['id' => text::ANY_VALUE, 'identifier' => 'filterisanyvalue', 'component' => 'core_reportbuilder'],
            ['id' => text::CONTAINS, 'identifier' => 'filtercontains', 'component' => 'core_reportbuilder'],
            ['id' => text::DOES_NOT_CONTAIN, 'identifier' => 'filterdoesnotcontain', 'component' => 'core_reportbuilder'],
            ['id' => text::IS_EQUAL_TO, 'identifier' => 'filterisequalto', 'component' => 'core_reportbuilder'],
            ['id' => text::IS_NOT_EQUAL_TO, 'identifier' => 'filterisnotequalto', 'component' => 'core_reportbuilder'],
            ['id' => text::STARTS_WITH, 'identifier' => 'filterstartswith', 'component' => 'core_reportbuilder'],
            ['id' => text::ENDS_WITH, 'identifier' => 'filterendswith', 'component' => 'core_reportbuilder'],
            ['id' => text::IS_EMPTY, 'identifier' => 'filterisempty', 'component' => 'core_reportbuilder'],
            ['id' => text::IS_NOT_EMPTY, 'identifier' => 'filterisnotempty', 'component' => 'core_reportbuilder'],
        ];

        $orgstructure = [
            ['id' => org_structure::OPERATOR_DIRECT_ONLY, 'identifier' => 'orgfilterdirectreports',
                'component' => 'tool_organisation'],
            ['id' => org_structure::OPERATOR_EVERYONE, 'identifier' => 'orgfiltereverybody', 'component' => 'tool_organisation'],
            ['id' => org_structure::OPERATOR_CUSTOM, 'identifier' => 'orgfiltercustomise', 'component' => 'tool_organisation'],
        ];

        return [
            'departments' => $departments,
            'positions' => $positions,
            'fullname_filter' => $fullnameoperators,
            'orgstructuretype_filter' => $orgstructure,
            'warnings' => [],
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'departments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'parentid' => new external_value(PARAM_INT, ''),
                    'name' => new external_value(PARAM_TEXT, ''),
                ])
            ),
            'positions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'parentid' => new external_value(PARAM_INT, ''),
                    'name' => new external_value(PARAM_TEXT, ''),
                ])
            ),
            'fullname_filter' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'identifier' => new external_value(PARAM_TEXT, ''),
                    'component' => new external_value(PARAM_TEXT, ''),
                ])
            ),
            'orgstructuretype_filter' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'identifier' => new external_value(PARAM_TEXT, ''),
                    'component' => new external_value(PARAM_TEXT, ''),
                ])
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
