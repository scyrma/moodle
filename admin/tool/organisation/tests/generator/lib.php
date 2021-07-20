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
 * tool_organisation data generator.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tool_organisation data generator class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_generator extends component_generator_base {

    /**
     * @var int
     */
    protected $departmentcount = 0;

    /**
     * @var int
     */
    protected $positioncount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->departmentcount = 0;
        $this->positioncount = 0;
    }

    /**
     * Creates new department
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    public function create_department($record = null) : stdClass {
        $manager = new \tool_organisation\department_manager();
        $record = (object)(($record ? (array)$record : []) + ['parentid' => 0]);
        if (empty($record->name)) {
            $record->name = 'New department ' . (++$this->departmentcount);
        }
        return $manager->create_department($record, false)->to_record();
    }

    /**
     * Creates new position
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    public function create_position($record = null) : stdClass {
        $manager = new \tool_organisation\position_manager();
        $record = (object)(($record ? (array)$record : []) + ['parentid' => 0]);
        if (empty($record->name)) {
            $record->name = 'New position ' . (++$this->positioncount);
        }
        return $manager->create_position($record, false)->to_record();
    }

    /**
     * Create a new position and department in one go
     *
     * @param array|stdClass $position
     * @param array|stdClass $department
     * @return stdClass[]
     */
    public function create_position_and_department($position = null, $department = null) : array {
        return [
            $this->create_position($position),
            $this->create_department($department),
        ];
    }

    /**
     * Looks up a department by name or idnumber
     * @param string $nameoridnumber
     * @return int
     */
    public function lookup_department(string $nameoridnumber): int {
        global $DB;
        if (empty($nameoridnumber)) {
            return 0;
        }
        return $DB->get_field_select(\tool_organisation\department::TABLE, 'id',
            'name = ? OR idnumber = ?', [$nameoridnumber, $nameoridnumber], MUST_EXIST);
    }

    /**
     * Looks up a position by name or idnumber
     * @param string $nameoridnumber
     * @return int
     */
    public function lookup_position(string $nameoridnumber): int {
        global $DB;
        if (empty($nameoridnumber)) {
            return 0;
        }
        return $DB->get_field_select(\tool_organisation\position::TABLE, 'id',
            'name = ? OR idnumber = ?', [$nameoridnumber, $nameoridnumber], MUST_EXIST);
    }

    /**
     * Assign a job
     *
     * @param array|stdClass $data must have: userid, departmentid, positionid
     * @return stdClass
     */
    public function assign_job($data): stdClass {
        $data = (object)(array)$data;
        if (empty($data->tenantid)) {
            $data->tenantid = \tool_tenant\tenancy::get_tenant_id($data->userid);
        }
        if (empty($data->startdate)) {
            $data->startdate = \tool_organisation\helper::round_time(time() - DAYSECS);
        }
        return (new \tool_organisation\job_manager())->create_job($data, false)->to_record();
    }

    /**
     * Assigns capability to user
     *
     * @param string $capability
     * @param int $userid
     * @param context $context
     * @throws coding_exception
     */
    public function assign_capability(string $capability, int $userid, context $context): void {
        static $cnt = 0;
        $cnt++;
        $roleid = create_role('Dummy role ' . $cnt, 'dummyrole' . $cnt, 'dummy role description');
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }
}
