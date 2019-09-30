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
 * Class job
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

use core\persistent;

defined('MOODLE_INTERNAL') || die();

/**
 * Class job
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job extends persistent {

    /** The table name. */
    const TABLE = 'tool_organisation_job';

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {@link self::from_record()}.
     */
    public function __construct(int $id = 0, \stdClass $record = null) {
        if ($record) {
            $record = (object)array_intersect_key((array)$record, self::properties_definition());
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
            'id' => array(
                'type' => PARAM_INT,
                'description' => 'The entity id.',
            ),
            'tenantid' => array(
                'type' => PARAM_INT,
                'description' => 'Tenant',
            ),
            'userid' => array(
                'type' => PARAM_INT,
                'description' => 'User',
            ),
            'positionid' => array(
                'type' => PARAM_INT,
                'description' => 'Position',
            ),
            'departmentid' => array(
                'type' => PARAM_INT,
                'description' => 'Department',
            ),
            'startdate' => array(
                'type' => PARAM_INT,
                'description' => 'Start date',
            ),
            'enddate' => array(
                'type' => PARAM_INT,
                'description' => 'End date',
                'default' => 0
            ),
        );
    }

    /**
     * Returns jobcounts on position.

     * @param int $positionid
     * @return mixed|null
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public static function get_postion_jobs_count(int $positionid) {
        // TODO SP-394 calculate for all displayed positions in one query, move the query to helper class.
        global $DB;
        $recordcount = null;

        if (is_int($positionid)) {
            $curtime = time();
            $params = ['curtime_a' => $curtime, 'curtime_e' => $curtime, 'positionid' => $positionid];
            $sql = "SELECT COUNT(id) AS total,
                      SUM(CASE WHEN (enddate = 0 OR enddate > :curtime_a) THEN 1 ELSE 0 END) AS active,
                      SUM(CASE WHEN (enddate <> 0 AND enddate < :curtime_e) THEN 1 ELSE 0 END) AS expired
                      FROM " . "{" . self::TABLE . "}" . " WHERE positionid = :positionid";
            $recordcount = $DB->get_record_sql($sql, $params);
        }
        return $recordcount;
    }

    /**
     * Returns jobcounts on department.
     *
     * @param int $departmentid
     * @return mixed|null
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public static function get_department_jobs_count(int $departmentid) {
        // TODO SP-394 calculate for all departments in one query, move the query to helper class.
        global $DB;
        $recordcount = null;
        if (is_int($departmentid)) {
            $curtime = time();
            $params = ['curtime_a' => $curtime, 'curtime_e' => $curtime, 'departmentid' => $departmentid];
            $sql = "SELECT COUNT(id) AS total,
                      SUM(CASE WHEN (enddate = 0 OR enddate > :curtime_a) THEN 1 ELSE 0 END) AS active,
                      SUM(CASE WHEN (enddate <> 0 AND enddate < :curtime_e) THEN 1 ELSE 0 END) AS expired
                      FROM " . "{" . self::TABLE . "}" . " WHERE departmentid = :departmentid";
            $recordcount = $DB->get_record_sql($sql, $params);
        }
        return $recordcount;
    }

}
