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
 * Class containing helper methods for format columns data as callbacks.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use completion_info;
use core_tag_tag;
use core_user\output\status_field;
use html_writer;
use stdClass;
use tool_reportbuilder\datasource;
use tool_reportbuilder\helper;
use tool_reportbuilder\manager;

/**
 * Class format
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
     * Return toggle element for setting schedule enabled flag
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function toggle_schedule_enabled($value, \stdClass $row): string {
        global $OUTPUT;

        // There is nothing to toggle for an invalid report source, it will always be considered disabled.
        if (!manager::report_source_valid($row->source, datasource::class)) {
            return '';
        }

        if ($row->enabled) {
            $title = get_string('disable');
            $pix = 'toggle-on';
        } else {
            $title = get_string('enable');
            $pix = 'toggle-off';
        }

        $toggle = new \action_link(
            new \moodle_url('#'), '', null,
            ['title' => $title, 'data-action' => 'toggle', 'data-id' => $row->id, 'data-enabled' => $row->enabled],
            new \pix_icon($pix, $title, 'tool_wp')
        );

        return $OUTPUT->render_from_template('core/action_menu_link', $toggle->export_for_template($OUTPUT));
    }

    /**
     * Round day value down to nearest whole number, if zero then return appropriate lang string
     *
     * @param mixed $value
     * @param stdClass $row
     * @return string
     */
    public static function days($value, stdClass $row) : string {
        $days = floor($value);

        return ($days > 0) ? (string)$days : get_string('lessthanaday', 'tool_reportbuilder');
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
        $separator = helper::get_list_separator();
        $countries = explode ($separator, $value);
        foreach ($countries as $country) {
            $namedcountries[] = self::country($country, $row);
        }
        return implode($separator, array_filter($namedcountries));
    }

    /**
     * Render a cell with the report link.
     *
     * @param string $value The current value
     * @param \stdClass $row The full row
     * @return string
     */
    public static function report_link(string $value, \stdClass $row): string {
         $label = self::format_string($row->name);

        // Add a warning for missing or unavailable report source.
        if (!manager::report_source_exists($row->source, datasource::class)) {
            $label .= \html_writer::span(get_string('error'), 'badge badge-warning broken ml-3',
                ['title' => get_string('errormissingreportsource', 'tool_reportbuilder')]);

            return $label;
        } else if (!manager::report_source_available($row->source)) {
            $label .= \html_writer::span(get_string('error'), 'badge badge-warning broken ml-3',
                ['title' => get_string('errorunavailablereportsource', 'tool_reportbuilder')]);

            return $label;
        }

        $link = new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $row->reportid]);

        return \html_writer::link($link, $label);
    }

    /**
     * Format the last sent on date for schedules.
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public static function last_sent_on(string $value, \stdClass $row) : string {
        if ($value == -1) {
            return get_string('never', 'tool_reportbuilder');
        }

        return userdate($value, get_string('strftimedatetimeshort', 'langconfig'));
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
     * Return enrolment plugin instance name
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function enrolment_name(string $value, stdClass $row) : string {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $row->enrolid, 'enrol' => $row->enrol], '*', MUST_EXIST);
        $plugin = enrol_get_plugin($row->enrol);

        return $plugin ? $plugin->get_instance_name($instance) : '-';
    }

    /**
     * Return enrolment status for user
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function enrolment_status(string $value, stdClass $row) : string {
        $statusvalues = [
            status_field::STATUS_ACTIVE => get_string('participationactive', 'enrol'),
            status_field::STATUS_SUSPENDED => get_string('participationsuspended', 'enrol'),
            status_field::STATUS_NOT_CURRENT => get_string('participationnotcurrent', 'enrol'),
        ];

        return $statusvalues[(int) $value] ?? '';
    }

    /**
     * Return completion progress in the form 'X / Y' or as a percentage
     *
     * @param string|null $value
     * @param stdClass $row
     * @param bool $percent
     * @return string
     */
    public static function completion_progress(?string $value, stdClass $row, ?bool $percent = false) : string {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new completion_info((object)['id' => $row->courseid, 'enablecompletion' => $row->enablecompletion]);

        // Bail out early if completion not enabled, or not tracking user.
        if (!$completion->is_enabled() || !$completion->is_tracked_user($row->userid)) {
            return '';
        }

        // Check we have some course modules that support completion.
        $modules = $completion->get_activities();
        if (0 == ($totalcount = count($modules))) {
            return '';
        }

        // Filter modules to those completed by user.
        $userid = $row->userid;
        $completed = array_filter($modules, function(\cm_info $module) use ($completion, $userid) {
            return ($completion->get_data($module, true, $userid)->completionstate != COMPLETION_INCOMPLETE);
        });

        $completedcount = count($completed);

        return $percent ? self::percent(100 * $completedcount / $totalcount) : sprintf('%d / %d', $completedcount, $totalcount);
    }

    /**
     * Formats as number and adds a '%' in the end
     *
     * @param mixed $value
     * @param \stdClass $row
     * @return null|string
     */
    public static function percent($value, stdClass $row = null): string {
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
