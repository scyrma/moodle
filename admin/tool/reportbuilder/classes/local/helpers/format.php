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
 * Class containing helper methods for format columns data as callbacks.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use core_tag_tag;
use html_writer;
use stdClass;
use tool_reportbuilder\aggregation_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class format
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format {

    /**
     * Returns a display value for a customfield
     *
     * @param mixed $value
     * @param \stdClass $row
     * @param \core_customfield\field_controller $field
     * @return string
     */
    public static function customfield_value($value, \stdClass $row, $field) {
        // TODO implement using a callback from a field.
        return $value;
    }

    /**
     * Returns yes/no string
     *
     * @param mixed $value
     * @return string
     */
    public static function checkbox_as_text($value) {
        return $value ? get_string('yes') : get_string('no');
    }

    /**
     * Formats a string
     *
     * @param mixed $value
     * @return string
     */
    public static function format_string($value) {
        return format_string($value);
    }

    /**
     * Returns user name. The SQL must include all user fields, use get_all_user_name_fields()
     *
     * @param mixed $value Current row value
     * @param \stdClass $row Full row
     * @return string
     */
    public static function fullname($value, \stdClass $row) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        return fullname($row, $viewfullnames);
    }

    /**
     * Returns formatted date
     *
     * @param string $value
     * @param \stdClass $row
     * @param string $format
     * @return string
     */
    public static function userdate($value, \stdClass $row, $format = null) {
        if (!$format) {
            $format = get_string('strftimedatefullshort');
        }
        return $value ? userdate($value, $format) : '';
    }

    /**
     * Returns formatted country field
     *
     * @param string $value
     * @param \stdClass $row
     * @param string $format
     * @return string
     */
    public static function country($value, \stdClass $row, $format = null) {
        $countries = get_string_manager()->get_list_of_countries();
        return array_key_exists($value, $countries) ? $countries[$value] : $value;
    }

    /**
     * Returns formatted countries list
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public static function countries_list($value, \stdClass $row) {
        $namedcountries = [];
        $separator = aggregation_base::get_list_separator();
        $countries = explode ($separator, $value);
        foreach ($countries as $country) {
            $namedcountries[] = self::country($country, $row);
        }
        return implode($separator, array_filter($namedcountries));
    }

    /**
     * Render a cell with the report link.
     *
     * The params array must contains be the url without and optionally a set of attributes.
     * The ID for the URL is set automatically from the row.
     *
     * @param string $value The current value
     * @param \stdClass $row The full row
     * @param array $params Custom params for the callback.
     *
     * @return string
     */
    public static function report_link(string $value, \stdClass $row, array $params = array()) : string {
        $attributes = array_key_exists('attributes', $params) ? $params['attributes'] : array();
        /** @var \moodle_url $link */
        $link = $params['url'];
        $link->param('id', $row->reportid);
        return \html_writer::link($link, format_string($value), $attributes);
    }

    /**
     * Format the last sent on date for schedules.
     *
     * @param string $value
     * @param \stdClass $row
     *
     * @return string
     * TODO: implement last sent on logic for schedules.
     */
    public static function last_sent_on(string $value, \stdClass $row) : string {
        if ($value == -1) {
            return get_string('never', 'tool_reportbuilder');
        }

        return userdate($value, get_string('strftimedatetimeshort'));
    }

    /**
     * Format full departament path.
     *
     * @param string $value
     * @param \stdClass $row
     *
     * @return string
     */
    public static function full_departament_path(string $value, \stdClass $row) : string {
        if (empty($value)) {
            return "";
        }

        global $DB;
        // TODO: Move this to tool_organization.
        $departments = $DB->get_records('tool_organisation_department', null, '', $fields = 'id, name');
        // TODO: Add cache here to reduce database calls.
        $departstring = "";
        foreach (explode('/', $value) as $id) {
            if (!empty($departments[$id]->name)) {
                $departstring .= format_string($departments[$id]->name) . ' / ';
            }
        }
        return substr($departstring, 0, -3);
    }

    /**
     * Format source to show plugin name.
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     * @throws \coding_exception
     */
    public static function source_plugin(string $value, \stdClass $row): string {
        $component = substr($value, 0, strpos($value, '\\'));
        return get_string('pluginname', $component);
    }

    /**
     * Formats as number and adds a '%' in the end
     *
     * @param mixed $value
     * @param \stdClass $row
     * @return null|string
     */
    public static function percent($value, \stdClass $row): string {
        if (is_numeric($value)) {
            return sprintf("%.2f", $value) . '%';
        }
        return '';
    }

    /**
     * Formats a tags column (which is itself a concatenated list of tag names)
     *
     * @param string|null $value
     * @param stdClass $row
     * @param bool $distinct
     * @return string|string[]|null
     */
    public static function tags_replace_all(?string $value, stdClass $row, $distinct = false) {
        if (empty($value)) {
            return '';
        }

        // Remove comma-separator from value.
        $value = str_replace(', ', '', $value);

        $regex = '#<span data-rawname="(?<rawname>[^"]*?)">(?<name>[^<]*?)</span>#';

        // For distinct values, remove duplicates.
        if ($distinct) {
            preg_match_all($regex, $value, $matches);
            $value = implode('', array_unique($matches[0]));
        }

        // In the callback, we construct a tag object so that we can use the tag API to display it.
        return preg_replace_callback($regex, function($matches) {
            $tag = (object) [
                'name' => $matches['name'],
                'rawname' => $matches['rawname'],
            ];

            return html_writer::span(core_tag_tag::make_display_name($tag), 'border p-1 text-uppercase font-small my-2 mr-2');
        }, $value);
    }
}