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
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

use tool_organisation\event\department_created;
use tool_organisation\event\department_updated;
use tool_organisation\event\position_created;
use tool_organisation\event\position_updated;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class department_manager extends hierarchy_manager {

    /**
     * Class name
     *
     * @return string
     */
    protected function get_class() : string {
        return department::class;
    }

    /**
     * Get individual department by id
     *
     * @param int $departmentid
     * @return department
     * @throws \moodle_exception
     */
    public function get_department(int $departmentid) : department {
        $conditions = ['id' => $departmentid, 'archived' => 0];
        return $this->get_hierarchy_entity($conditions);
    }

    /**
     * Get full tree of departments
     *
     * @param int $frameworkid
     * @param bool $includearchived
     * @return department
     */
    public function get_department_structure(int $frameworkid, bool $includearchived = false) : department {
        return $this->get_hierarchy_structure($frameworkid, $includearchived);
    }

    /**
     * Creates a new department
     *
     * @param \stdClass $data
     */
    public function create_department(\stdClass $data) : department {
        $parent = !empty($data->parentid) ? $this->get_department($data->parentid) : new department();
        $department = $this->create_hierarchy($parent, $data);
        return $department;
    }

    /**
     * Updates a department
     *
     * @param int $departmentid
     * @param \stdClass $newdata
     * @return department
     */
    public function update_department(int $departmentid, \stdClass $newdata) : department {
        $entity = $this->get_hierarchy_entity(['id' => $departmentid, 'archived' => 0]);
        return $this->update_hierarchy_entity($entity, $newdata);
    }

    /**
     * Get list of department frameworks
     *
     * @return department[]
     */
    public function get_department_frameworks() : array {
        return $this->get_hierarchy_entities(['archived' => 0, 'parentid' => null]);
    }

    /** Returns boolean true if Department hierarchy has any department where job can be assigned.
     * These should be at least one department framework with a department within it
     * to proceed with job assignments.
     *
     * @return bool
     */
    public function has_any_department_for_jobcreate() : bool {
        foreach ($frameworks = $this->get_department_frameworks() as $framework) {
            if ($this->get_all_children($framework)) {
                return true;
            }
        }
        return false;
    }

    /**
     * URL to view department framework
     *
     * @param int $frameworkid
     * @return \moodle_url
     */
    public static function get_department_url(int $frameworkid) : \moodle_url {
        $params = $frameworkid ? ['departmentframeworkid' => $frameworkid] : [];
        return new \moodle_url('/admin/tool/organisation/index.php', $params, 'departments');
    }

    /**
     * Name of filearea for department description
     * @return string
     */
    public static function get_description_filearea() : string {
        return 'departmentdescription';
    }

    /**
     * Deletes a department
     *
     * @param int $depatmentid
     */
    public function delete_department(int $depatmentid) {
        global $DB;
        $entity = $this->get_hierarchy_entity(['id' => $depatmentid]);
        $sql = $DB->sql_like('d.path', ':path');
        $sql = "SELECT 1 FROM {tool_organisation_job} j
            JOIN {tool_organisation_department} d ON d.id = j.departmentid
            WHERE d.id = :id OR ($sql)";
        $params = ['id' => $depatmentid, 'path' => $entity->get('path') . '/%'];
        if ($DB->record_exists_sql($sql, $params)) {
            throw new \moodle_exception('departmenthasjobs', 'tool_organisation');
        }
        $this->delete_hierarchy_entity($entity);
    }
}