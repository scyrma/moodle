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
 * tool_organisation data generator.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tool_organisation data generator class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $parent = new \tool_organisation\department($record->parentid);
        if (!$record->parentid && !empty($record->tenantid)) {
            $parent->set('tenantid', $record->tenantid);
        }

        // We need to avoid tenant check and call protected method.
        $reflector = new ReflectionClass(get_class($manager));
        $method = $reflector->getMethod('create_hierarchy');
        $method->setAccessible(true);
        $entity = $method->invokeArgs($manager, [$parent, $record]);
        return $entity->to_record();
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
        $parent = new \tool_organisation\position($record->parentid);
        if (!$record->parentid && !empty($record->tenantid)) {
            $parent->set('tenantid', $record->tenantid);
        }

        // We need to avoid tenant check and call protected method.
        $reflector = new ReflectionClass(get_class($manager));
        $method = $reflector->getMethod('create_hierarchy');
        $method->setAccessible(true);
        $entity = $method->invokeArgs($manager, [$parent, $record]);
        return $entity->to_record();
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
        $job = new \tool_organisation\job(0, $data);
        $job->save();
        return $job->to_record();
    }
}
