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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class hierarchy
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use core\output\inplace_editable;
use tool_tenant\sharedspace;

/**
 * Class hierarchy
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class hierarchy extends \core\persistent {

    /**
     * @var hierarchy[]
     */
    protected $children = [];

    /**
     * @var hierarchy
     */
    protected $parent = null;

    /** @var array caches jobs count for {@see get_jobs_count()} */
    protected $jobscount = null;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {@see self::from_record()}.
     */
    public function __construct(int $id = 0, \stdClass $record = null) {
        if ($record) {
            $record = (object)array_intersect_key((array)$record, static::properties_definition());
        }
        if ($id && $record) {
            debugging('Either id or record need to be specified in the persistent constructor but not both',
                DEBUG_DEVELOPER);
        }
        parent::__construct($id, $record);
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'name' => array(
                'type' => PARAM_TEXT,
                'description' => 'The entity name.',
            ),
            'archived' => array(
                'type' => PARAM_INT,
                'description' => 'Is archived.',
                'default' => 0,
            ),
            'sortorder' => array(
                'type' => PARAM_INT,
                'description' => 'Sort order',
                'default' => 0,
            ),
            'tenantid' => array(
                'type' => PARAM_INT,
                'description' => 'Tenant',
            ),
            'parentid' => array(
                'type' => PARAM_INT,
                'description' => 'Parent',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'pathlevel' => array(
                'type' => PARAM_INT,
                'description' => 'Level in hierarchy',
            ),
            'path' => array(
                'type' => PARAM_PATH,
                'description' => 'Path',
            ),
            'idnumber' => array(
                'type' => PARAM_RAW,
                'description' => 'ID number',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'description' => array(
                'type' => PARAM_RAW,
                'description' => 'Description',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'descriptionformat' => array(
                'type' => PARAM_INT,
                'description' => 'Description format',
                'default' => 0
            ),
            'timearchived' => array(
                'type' => PARAM_INT,
                'description' => 'When it was archived',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'shared' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],

        );
    }

    /**
     * Adds a child hierarchy
     *
     * @param hierarchy $hierarchy
     * @return hierarchy
     */
    public function add_child(hierarchy $hierarchy) : hierarchy {
        $this->children[$hierarchy->get('id')] = $hierarchy;
        $hierarchy->set_parent($this);
        return $hierarchy;
    }

    /**
     * Sets the parent hierarchy
     *
     * @param hierarchy $hierarchy
     * @return hierarchy
     */
    protected function set_parent(hierarchy $hierarchy) : hierarchy {
        $this->parent = $hierarchy;
        return $hierarchy;
    }

    /**
     * Returns formatted name
     *
     * @return string
     */
    public function get_formatted_name() : string {
        return format_string($this->get('name'), true, ['context' => \context_system::instance()]);
    }

    /**
     * Generates inplace_editable object for the name
     *
     * @return inplace_editable
     */
    abstract public function get_editable_name() : inplace_editable;

    /**
     * Returns the top-most parent id or 0 if it is a framework
     *
     * @return int
     */
    public function get_framework_id() : int {
        $path = preg_split('|/|', $this->get('path'), -1, PREG_SPLIT_NO_EMPTY);
        $frameworkid = (int)reset($path);
        return $frameworkid;
    }

    /**
     * Checks if this is a top-most element in the hierarchy
     *
     * @return bool
     */
    public function is_framework() : bool {
        return !$this->get('parentid');
    }

    /**
     * Returns the children
     *
     * @return hierarchy[]
     */
    public function get_children() : array {
        return $this->children;
    }

    /**
     * Archive an instance (recursively for all children)
     *
     * @param bool $main
     */
    public function archive(bool $main = true) {
        if ($this->get('archived')) {
            return;
        }
        $this->set('archived', true);
        if ($main) {
            $this->set('timearchived', time());
        }
        $this->save();
        foreach ($this->children as $child) {
            $child->archive(false);
        }
    }

    /**
     * Restore archived instance (recursively for all children)
     *
     * @param bool $main
     */
    public function unarchive(bool $main = true) {
        if (!$this->get('archived')) {
            return;
        }
        if (!$main && $this->get('timearchived') !== null) {
            // This was already archived in another action.
            return;
        }

        $this->set('archived', false);
        $this->set('timearchived', null);
        $this->save();
        foreach ($this->children as $child) {
            $child->unarchive(false);
        }
    }

    /**
     * Calculate the number of existing jobs in this department/position
     *
     * @param int $id id of department/position
     * @return \stdClass contains fields: total, active, expired, totalwithchildren
     */
    public function get_jobs_count(int $id): \stdClass {
        global $DB;
        if ($this->jobscount === null) {
            $frameworkid = $this->get_framework_id();
            $table = static::TABLE;
            $field = ($table === department::TABLE) ? 'departmentid' : 'positionid';
            $curtime = time();
            $sqllike = $DB->sql_like('t.path', ':path');
            $params = ['curtime_a' => $curtime, 'curtime_e' => $curtime,
                'path' => '/' . $frameworkid . '/%', 'frameworkid' => $frameworkid];
            // If is not shared space it needs to count only those position and departments inside the job tenant.
            $tenantwhere = '';
            if (!sharedspace::is_shared_space()) {
                [$tenantsql, $tenantparams] = \tool_tenant\hierarchy::get_subtenants_sql();
                $tenantwhere = 'AND j.tenantid '.$tenantsql;
                $params += $tenantparams;
            }
            $sql = "SELECT t.id, t.path, COUNT(j.id) AS total,
                           SUM(CASE WHEN (j.enddate = 0 OR j.enddate > :curtime_a) THEN 1 ELSE 0 END) AS active,
                           SUM(CASE WHEN (j.enddate <> 0 AND j.enddate < :curtime_e) THEN 1 ELSE 0 END) AS expired,
                           0 AS totalwithchildren
                      FROM {{$table}} t
                      LEFT JOIN {tool_organisation_job} j ON j.{$field} = t.id $tenantwhere
                      WHERE ($sqllike OR t.id = :frameworkid)
                      GROUP BY t.id, t.path";
            $records = $DB->get_records_sql($sql, $params);
            foreach ($records as $info) {
                $parents = preg_split('|/|', $info->path, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($parents as $pid) {
                    $records[(int)$pid]->totalwithchildren += (int)$info->total;
                }
            }
            $this->jobscount = $records;
        }
        return array_key_exists($id, $this->jobscount) ?
            $this->jobscount[$id] :
            (object)['totalwithchildren' => 0, 'total' => 0, 'active' => 0, 'expired' => 0];
    }
}
