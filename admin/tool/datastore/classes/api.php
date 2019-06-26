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
}