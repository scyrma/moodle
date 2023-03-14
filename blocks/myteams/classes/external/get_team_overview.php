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

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\filter;
use core_user\external\user_summary_exporter;
use core_user\fields;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\filters\org_structure;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function get_team_overview for block_myteams.
 *
 * @package   block_myteams
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_team_overview extends external_api {

    /**
     * Describes the parameters for get_users_courses.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fullnametype'  => new external_value(PARAM_INT, 'Filter type for fullname', VALUE_DEFAULT, text::ANY_VALUE),
            'fullname'  => new external_value(PARAM_TEXT, 'Fullname to search for', VALUE_DEFAULT, ''),
            'orgstructuretype'  => new external_value(PARAM_INT, 'Organisation structure filter type', VALUE_DEFAULT,
                org_structure::OPERATOR_CUSTOM),
            'departmentid'  => new external_value(PARAM_INT, 'Departmend ID', VALUE_DEFAULT, 0),
            'includesubdepts'  => new external_value(PARAM_BOOL, 'Include subdepartments', VALUE_DEFAULT, false),
            'positionid'  => new external_value(PARAM_INT, 'Position ID', VALUE_DEFAULT, 0),
            'includesubpos'  => new external_value(PARAM_BOOL, 'Include subpositions', VALUE_DEFAULT, false),
            'limitfrom'  => new external_value(PARAM_INT, 'limitfrom (integer) sql limit from', VALUE_DEFAULT, 0),
            'limitnumber'  => new external_value(PARAM_INT, 'limitnumber (integer) maximum number of returned users',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * External function to get the managed users and their learning statuses (sections).
     *
     * @param int $fullnametype
     * @param string $fullname
     * @param int $orgstructuretype
     * @param int $departmentid
     * @param bool $includesubdepts
     * @param int $positionid
     * @param bool $includesubpos
     * @param int $limitfrom
     * @param int $limitnumber
     * @return array of managed users with their learning statuses (sections).
     */
    public static function execute(int $fullnametype = text::ANY_VALUE,
                                   string $fullname = '',
                                   int $orgstructuretype = org_structure::OPERATOR_CUSTOM,
                                   int $departmentid = 0, bool $includesubdepts = false, int $positionid = 0,
                                   bool $includesubpos = false, int $limitfrom = 0, int $limitnumber = 0): array {
        global $DB, $PAGE;

        // Parameter validation.
        [
            'fullnametype' => $fullnametype,
            'fullname' => $fullname,
            'orgstructuretype' => $orgstructuretype,
            'departmentid' => $departmentid,
            'includesubdepts' => $includesubdepts,
            'positionid' => $positionid,
            'includesubpos' => $includesubpos,
            'limitfrom' => $limitfrom,
            'limitnumber' => $limitnumber,
        ] = self::validate_parameters(self::execute_parameters(), [
            'fullnametype' => $fullnametype,
            'fullname' => $fullname,
            'orgstructuretype' => $orgstructuretype,
            'departmentid' => $departmentid,
            'includesubdepts' => $includesubdepts,
            'positionid' => $positionid,
            'includesubpos' => $includesubpos,
            'limitfrom' => $limitfrom,
            'limitnumber' => $limitnumber,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $output = $PAGE->get_renderer('block_myteams');

        $manager = organisation::get_user_with_jobs();
        // Check if user has permission to retrieve all the information.
        if (!$manager || !$manager->is_manager()) {
            throw new \moodle_exception('nopermissions', 'error', '', 'Get team overview');
        }

        [$wheres[], $params] = helper::get_managed_users_select($manager);

        // Get the condition SQL where clause and parameters from org_structure filter.
        [$select, $orgstructureparams] =
            self::get_orgstructure_filter_sql($orgstructuretype, $departmentid, $includesubdepts, $positionid, $includesubpos);

        if (!empty($select)) {
            $wheres[] = $select;
            $params = array_merge($params, $orgstructureparams);
        }

        // Get the condition SQL where clause and parameters from fullname text filter.
        [$select, $paramsfullname] = self::get_fullname_filter_sql($fullnametype, $fullname);

        if (!empty($select)) {
            $wheres[] = $select;
            $params = array_merge($params, $paramsfullname);
        }

        if (empty($wheres)) {
            $wheresql = '1=1';
        } else {
            $wheresql = '(' . implode(' AND ', $wheres) . ')';
        }

        // It will be ordered by user's fullname, like default order in the managed users UI.
        $fullnamesort = user::get_name_fields_select('u');
        $ordersql = " ORDER BY {$fullnamesort}";

        $users = $DB->get_records_sql("SELECT u.* FROM {user} u WHERE " . $wheresql . $ordersql, $params, $limitfrom, $limitnumber);
        $totalcount = $DB->count_records_sql("SELECT COUNT(u.id) FROM {user} u WHERE " . $wheresql, $params);

        $managedusers = [];
        foreach ($users as $user) {
            $record = [];
            $exporter = new user_summary_exporter($user);
            $record['user'] = (array) $exporter->export($output);
            $record['lastaccess'] = (int) $user->lastaccess;

            // Look for plugins adding a section to user information.
            [$record['sections'], $record['isoverdue']] = \block_myteams\api::get_all_user_sections((int) $user->id);

            $managedusers[] = $record;
        }

        $result['managedusers'] = $managedusers;
        $result['totalcount'] = $totalcount;
        $result['warnings'] = [];

        return $result;
    }

    /**
     * Returns the condition SQL where clause and parameters from org_structure filter
     *
     * @param int $orgstructuretype
     * @param int $departmentid
     * @param bool $includesubdepts
     * @param int $positionid
     * @param bool $includesubpos
     * @return array [$select, $orgstructureparams]
     */
    private static function get_orgstructure_filter_sql(int $orgstructuretype, int $departmentid, bool $includesubdepts,
                                                      int $positionid, bool $includesubpos): array {
        // Use the org_structure filter from core reportbuilder to get the SQL query and parameters.
        $filter = new filter(
            org_structure::class,
            'orgstructure',
            new \lang_string('orgstructure', 'tool_organisation'),
            'entity'
        );

        // Create instance of our filter, passing the operator and the value.
        return org_structure::create($filter)->get_sql_filter([
            'entity:orgstructure_op' => $orgstructuretype,
            'entity:orgstructure_department' => $departmentid,
            'entity:orgstructure_department_op' => $includesubdepts,
            'entity:orgstructure_position' => $positionid,
            'entity:orgstructure_position_op' => $includesubpos,
        ]);
    }

    /**
     * Returns the condition SQL where clause and parameters from fullname text filter
     *
     * @param int $fullnametype
     * @param string $fullname
     * @return array
     */
    private static function get_fullname_filter_sql(int $fullnametype, string $fullname): array {
        $canviewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        [$field, $paramsfullname1] = fields::get_sql_fullname('u', $canviewfullnames);

        // Use the text filter from core reportbuilder to get the SQL query and parameters.
        $filter = new filter(
            text::class,
            'fullname',
            new \lang_string('fullnameuser'),
            'entity',
            $field
        );

        // Create instance of our filter, passing the operator and the value.
        [$select, $paramsfullname2] = text::create($filter)->get_sql_filter([
            'entity:fullname_operator' => $fullnametype,
            'entity:fullname_value' => $fullname,
        ]);

        return [$select, array_merge($paramsfullname1, $paramsfullname2)];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'managedusers' => new external_multiple_structure(
                new external_single_structure([
                    'user' => user_summary_exporter::get_read_structure(),
                    'lastaccess' => new external_value(PARAM_INT, ''),
                    'isoverdue' => new external_value(PARAM_BOOL, ''),
                    'sections' => new external_multiple_structure(
                        userinfo_section_exporter::get_read_structure(),
                    ),
                ])
            ),
            'totalcount' => new external_value(PARAM_INT, ''),
            'warnings' => new external_warnings(),
        ]);
    }
}
