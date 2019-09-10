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
 * Class containing helper methods
 *
 * @package    tool_datastore
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_datastore;

use core_tag_tag;
use badge;
use completion_completion;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Class containing helper methods
 *
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Returns whether datastore are enabled.
     *
     * @return boolean True when enabled.
     */
    public static function is_enabled() {
        return get_config('tool_datastore', 'enabled');
    }

    /**
     * Method for generating SQL query/params for getting field value from datastore
     *
     * Example:
     *     list($fieldsql, $params) = api::get_datasource_field_sql('course_completion', 'timecompleted', 'dsa',
     *         'originalcourseid');
     *     $sql = "SELECT {$fieldsql} AS value FROM {tool_datastore_action} dsa WHERE dsa.id = {$action->get('id')}";
     *
     * @param string $entitytype Type of the entity, for example, 'course', 'user'
     * @param string $fieldname name of the entity field stored in the datastore, for example 'fullname', 'firstname'
     * @param string $maintablealias alias for the table {tool_datastore_action} that is defined in the main SQL query
     * @param string|null $maintablefield if necessary the name of the field in the {tool_datastore_action} table,
     *      for example, 'relateduserid' or 'usermodified'
     * @return array array with two elements - SQL snippet and parameters ([$sql, $params])
     */
    public static function get_datasource_field_sql(string $entitytype, string $fieldname, string $maintablealias,
            ?string $maintablefield = null) : array {

        $paramentitytype = db::generate_param_name();
        $paramfieldname = db::generate_param_name();

        $fieldstable = db::generate_alias();
        $entitytable = db::generate_alias();

        $sql = "SELECT {$fieldstable}.value
                  FROM {tool_datastore_idx_fields} {$fieldstable}
                  JOIN {tool_datastore_entity} $entitytable ON
                      $entitytable.id = {$fieldstable}.entityid AND $entitytable.type = :{$paramentitytype}
                 WHERE {$fieldstable}.actionid = {$maintablealias}.id
                   AND {$fieldstable}.name = :{$paramfieldname}";

        // It is sometimes necessary to distinguish which field we want, in the instance there are two of the same type (i.e users).
        if ($maintablefield !== null) {
            $sql .= " AND $entitytable.originalid = {$maintablealias}.{$maintablefield}";
        }

        return ["({$sql})", [$paramentitytype => $entitytype, $paramfieldname => $fieldname]];
    }

    /**
     * Get the course entity.
     *
     * @param int $id
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_course(int $id) : array {
        $coursedata = (array)get_course($id);
        $coursedata['tags'] = self::get_course_tags($id);
        return $coursedata;
    }

    /**
     * Get the course tags.
     *
     * @param int $courseid
     *
     * @return array
     */
    public static function get_course_tags(int $courseid) : array {
        $tags = core_tag_tag::get_item_tags('core', 'course', $courseid);
        $coursetags = array();
        foreach ($tags as $tag) {
            $coursetags[] = $tag->get_display_name(false);
        }

        return $coursetags;
    }

    /**
     * Get the user entity.
     *
     * @param int $id
     *
     * @return array
     */
    public static function get_user(int $id) : array {
        $user = (array)get_complete_user_data('id', $id);
        $user['tags'] = self::get_user_tags($id);
        return $user;
    }

    /**
     * Get the user tags (interests).
     *
     * @param int $userid
     *
     * @return array
     */
    public static function get_user_tags(int $userid) : array {
        $tags = core_tag_tag::get_item_tags('core', 'user', $userid);
        $usertags = array();
        foreach ($tags as $tag) {
            $usertags[] = $tag->get_display_name(false);
        }

        return $usertags;
    }

    /**
     * Get the list of the fields to be stored of the entity passed a parameter.
     *
     * @param string $entity
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_entity_fields(string $entity) {
        $settingname = "fields$entity";
        return get_config('tool_datastore', $settingname);
    }

    /**
     * Get the badge entity and the related info if needed.
     *
     * @param int $id
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_badge(int $id) : array {
        $badge = new badge($id);
        return get_object_vars($badge);
    }

    /**
     * Return course completion record
     *
     * @param int $id
     * @return array
     */
    public static function get_course_completion(int $id) : array {
        global $DB;

        $record = $DB->get_record('course_completions', ['id' => $id], 'userid,course', MUST_EXIST);
        $completion = new completion_completion(['userid' => $record->userid, 'course' => $record->course]);

        return (array)($completion->get_record_data());
    }
}