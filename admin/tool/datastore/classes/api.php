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
 * Class containing helper methods
 *
 * @package    tool_datastore
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_datastore;

use core_tag_tag;
use badge;
use coding_exception;
use completion_completion;
use tool_reportbuilder\constants;
use tool_wp\db;
use tool_datastore\local\models\entity;
use tool_datastore\local\models\field;

defined('MOODLE_INTERNAL') || die();

/**
 * Class containing helper methods
 *
 * @package    tool_datastore
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api {

    /** @var array fields to index for default entities */
    const DEFAULT_ENTITY_FIELDS = [
        'course' => ['category', 'fullname', 'shortname', 'idnumber', 'summary', 'summaryformat', 'format'],
        'user' => ['username', 'idnumber', 'firstname', 'lastname', 'email'],
    ];

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
     * @param int|null $fieldtype type of the field, by default constants::DB_TYPE_LONGTEXT
     * @return array array with two elements - SQL snippet and parameters ([$sql, $params])
     */
    public static function get_datasource_field_sql(string $entitytype, string $fieldname, string $maintablealias,
            ?string $maintablefield = null, ?int $fieldtype = null) : array {

        global $DB;

        $paramentitytype = db::generate_param_name();
        $paramfieldname = db::generate_param_name();

        $fieldtable = db::generate_alias();
        $entitytable = db::generate_alias();

        $sql = "SELECT {$fieldtable}.value
                  FROM {" . field::TABLE . "} {$fieldtable}
                  JOIN {" . entity::TABLE . "} {$entitytable}
                    ON {$entitytable}.id = {$fieldtable}.entityid
                 WHERE {$entitytable}.type = :{$paramentitytype}
                   AND {$entitytable}.actionid = {$maintablealias}.id
                   AND {$fieldtable}.name = :{$paramfieldname}";

        // It is sometimes necessary to distinguish which field we want, in the instance there are two of the same type (i.e users).
        if ($maintablefield !== null) {
            $sql .= " AND $entitytable.originalid = {$maintablealias}.{$maintablefield}";
        }

        // Cast the returned field value for cross-DB compatibility.
        $fieldtype = $fieldtype ?? constants::DB_TYPE_LONGTEXT;
        switch ($fieldtype) {
            case constants::DB_TYPE_NUMBER :
            case constants::DB_TYPE_DATETIME :
            case constants::DB_TYPE_TIMESTAMP :
                $sql = $DB->sql_cast_char2int("({$sql})", true);
                break;
            case constants::DB_TYPE_TEXT :
                $sql = $DB->sql_compare_text("({$sql})", 255);
                break;
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
     * Get the list of the fields to be stored for the entity
     *
     * @param string $entity
     * @return array
     * @throws coding_exception
     */
    public static function get_entity_fields(string $entity): array {
        if (!array_key_exists($entity, self::DEFAULT_ENTITY_FIELDS)) {
            throw new coding_exception('Unknown entity type', $entity);
        }

        return self::DEFAULT_ENTITY_FIELDS[$entity];
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

        $record = $DB->get_record('course_completions', ['id' => $id], '*', MUST_EXIST);
        $completion = new completion_completion((array) $record);

        return (array)($completion->get_record_data());
    }
}
