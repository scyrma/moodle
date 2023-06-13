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
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

/**
 * Class manager
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class position_manager extends hierarchy_manager {

    /**
     * Class name
     * @return string
     */
    protected function get_class() : string {
        return position::class;
    }

    /**
     * Get full tree of positions
     *
     * @param int $frameworkid
     * @param bool $includearchived
     * @return position
     */
    public function get_position_structure(int $frameworkid, bool $includearchived = false) : position {
        return $this->get_hierarchy_structure($frameworkid, $includearchived);
    }

    /**
     * Updates a position
     *
     * @param int $positionid
     * @param \stdClass $newdata
     * @return position
     */
    public function update_position(int $positionid, \stdClass $newdata) : position {
        $entity = $this->get_hierarchy_entity(['id' => $positionid, 'archived' => 0]);
        return $this->update_hierarchy_entity($entity, $newdata);
    }

    /**
     * URL to view position framework
     *
     * @param int $frameworkid
     * @return \moodle_url
     */
    public static function get_position_url(int $frameworkid) : \moodle_url {
        $params = $frameworkid ? ['positionframeworkid' => $frameworkid] : [];
        return new \moodle_url('/admin/tool/organisation/index.php', $params, 'positions');
    }

    /**
     * Creates a new position
     *
     * @param \stdClass $data
     * @param bool $currenttenantonly only allow to create in the current tenant (default: true). Can be set to false
     *     from unittests and imports
     * @return position
     */
    public function create_position(\stdClass $data, bool $currenttenantonly = true) : position {
        $parent = !empty($data->parentid) ? $this->get_position($data->parentid, $currenttenantonly) : new position();
        $position = $this->create_hierarchy($parent, $data, $currenttenantonly);
        return $position;
    }

    /**
     * Get list of position frameworks
     *
     * @return position[]
     */
    public function get_position_frameworks() : array {
        return $this->get_hierarchy_entities(['archived' => 0, 'parentid' => null]);
    }

    /**
     * Get individual position by id
     *
     * @param int $positionid
     * @param bool $currenttenantonly only allow to create in the current tenant (default: true). Can be set to false
     *     from unittests and imports
     * @return position
     * @throws \moodle_exception
     */
    public function get_position(int $positionid, bool $currenttenantonly = true) : position {
        $conditions = ['id' => $positionid, 'archived' => 0];
        return $this->get_hierarchy_entity($conditions, $currenttenantonly);
    }

    /** Returns boolean true if Position hierarchy has any position where job can be assigned
     * These should be at least one position framework with a position within it
     * to proceed with job assignments.
     *
     * @return bool
     */
    public function has_any_position_for_jobcreate() : bool {
        foreach ($frameworks = $this->get_position_frameworks() as $framework) {
            if ($this->get_all_children($framework)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Name of filearea for position description
     * @return string
     */
    public static function get_description_filearea() : string {
        return 'positiondescription';
    }

    /**
     * Deletes a position
     *
     * @param int $positionid
     */
    public function delete_position(int $positionid) {
        global $DB;
        $entity = $this->get_hierarchy_entity(['id' => $positionid]);
        $sql = $DB->sql_like('p.path', ':path');
        $sql = "SELECT 1 FROM {tool_organisation_job} j
            JOIN {tool_organisation_position} p ON p.id = j.positionid
            WHERE p.id = :id OR ($sql)";
        $params = ['id' => $positionid, 'path' => $entity->get('path') . '/%'];
        if ($DB->record_exists_sql($sql, $params)) {
            throw new \moodle_exception('positionhasjobs', 'tool_organisation');
        }
        $this->delete_hierarchy_entity($entity);
    }

    /**
     * Delete all positions inside one tenant. Used when tenant is deleted.
     *
     * @param int $tenantid
     */
    public function delete_all_positions_for_tenant(int $tenantid) {
        $this->delete_all_entities_for_tenant($tenantid);
    }
}
