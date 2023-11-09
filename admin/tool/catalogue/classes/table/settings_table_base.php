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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_catalogue\table;

use context_system;
use core_table\dynamic;
use flexible_table;
use html_writer;
use stdClass;
use tool_catalogue\configuration;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/tablelib.php");

/**
 * Table to display a setting representing a list of fields (display fields, filter fields, search fields).
 *
 * Each of these fields can be set to visible or hidden and the visible ones can be reordered
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class settings_table_base extends flexible_table implements dynamic {
    /** @var string Name of the setting - must be overridden in the child classes */
    protected $settingname;
    /** @var string[] The list of all available fields */
    protected array $fields = [];

    /** @var int List of all fields that are visible in the correct order */
    protected array $visiblefields = [];

    /**
     * Constructor
     */
    public function __construct() {
        global $CFG;

        parent::__construct('tool_catalogue_admin_setting_table-' . $this->settingname);
        require_once($CFG->libdir . '/adminlib.php');

        $this->guess_base_url();

        $this->setup_fields();

        $columnlist = $this->get_column_list();
        $this->define_columns(array_keys($columnlist));
        $this->define_headers(array_values($columnlist));
        $this->set_filterset(new settings_table_filterset());
        $this->setup();
        $this->attributes['class'] .= ' tool_catalogue-'.$this->settingname;
    }

    /**
     * Get the class used as a filterset.
     *
     * @return string
     */
    public static function get_filterset_class(): string {
        return settings_table_filterset::class;
    }

    /**
     * Get the context for this table.
     *
     * @return context_system
     */
    public function get_context(): context_system {
        return context_system::instance();
    }

    /**
     * Provide a default implementation for guessing the base URL from the action URL.
     */
    public function guess_base_url(): void {
        $this->define_baseurl($this->get_action_url());
    }

    /**
     * Get the action URL for this table.
     *
     * The action URL is used to perform all actions when JS is not available.
     *
     * @param array $params
     * @return \moodle_url
     */
    protected function get_action_url(array $params = []): \moodle_url {
        return new \moodle_url('/admin/settings.php?section=tool_catalogue_catalogue', $params);
    }

    /**
     * Prepares list of fields displayed in this table
     *
     * Must fill $this->visiblefields and $this->fields
     */
    abstract protected function setup_fields();

    /**
     * Are the fields in this table related to the search query
     *
     * @param string $query
     * @return bool
     */
    public function is_related(string $query): bool {
        foreach ($this->fields as $field) {
            $str = $field . ' ' . $this->col_name((object)['field' => $field]);
            if (str_contains($str, $query)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a list of the column titles
     *
     * @return string[]
     */
    protected function get_column_list(): array {
        $columns = [
            'name' => get_string('name', 'core'),
            'visible' => get_string('display', 'tool_catalogue'),
            'order' => get_string('order', 'core'),
        ];

        return $columns;
    }

    /**
     * Print the table.
     */
    public function out(): void {
        foreach ($this->fields as $field) {
            $row = (object) [
                'field' => $field,
            ];
            $this->add_data_keyed(
                $this->format_row($row),
                $this->get_row_class($row)
            );
        }

        $this->finish_output(false);
    }

    /**
     * CSS class for the table row
     *
     * @param stdClass $row
     * @return string
     */
    protected function get_row_class(stdClass $row): string {
        $field = $row->field;
        return in_array($field, $this->visiblefields) ? '' : 'dimmed_text';
    }

    /**
     * Add JS specific to this implementation.
     *
     * @return string
     */
    protected function get_dynamic_table_html_end(): string {
        global $PAGE;

        $PAGE->requires->js_call_amd('tool_catalogue/settingstable', 'init');
        return parent::get_dynamic_table_html_end();
    }

    /**
     * Display column 'Name' (magic method, it is called if there is a 'name' column in the table)
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_name(stdClass $row): string {
        $field = $row->field;

        if ($field === configuration::FIELD_TAGS) {
            return get_string('tags');
        } else if ($field === configuration::FIELD_CATEGORY) {
            return get_string('coursecategory', 'moodle');
        } else if ($field === configuration::FIELD_CONTACTS) {
            return get_string('coursecontact', 'admin');
        } else if ($field === configuration::FIELD_SUBCATEGORY) {
            return get_string('subcategory', 'moodle');
        } else if ($field === configuration::FIELD_SUMMARY) {
            return get_string('coursesummary', 'moodle');
        } else if (($customfields = configuration::get_all_course_custom_fields()) &&
                array_key_exists($field, $customfields)) {
            return $customfields[$field]->get_formatted_name();
        }
        return $field;
    }

    /**
     * Can this field be enabled/disalbed?
     * @param string $field
     * @return bool
     */
    protected function can_toggle_visibility(string $field): bool {
        return true;
    }

    /**
     * Can this field be moved down?
     * @param string $field
     * @return bool
     */
    protected function can_move_down(string $field): bool {
        $index = array_search($field, $this->visiblefields);
        return $index !== false && $index < count($this->visiblefields) - 1;
    }

    /**
     * Can this field be moved up?
     * @param string $field
     * @return bool
     */
    protected function can_move_up(string $field): bool {
        $index = array_search($field, $this->visiblefields);
        return $index !== false && $index > 0;
    }

    /**
     * Display column 'Display' (magic method, it is called if there is a 'visible' column in the table)
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_visible(stdClass $row): string {
        global $OUTPUT;
        $field = $row->field;

        if (!$this->can_toggle_visibility($field)) {
            return '';
        }

        $enabled = in_array($field, $this->visiblefields);
        if ($enabled) {
            $title = get_string('hide');
            return $this->icon_link('t/hide', $title, 'toggle', $field, '0');
        } else {
            $title = get_string('show');
            return $this->icon_link('t/show', $title, 'toggle', $field, '1');
        }
    }

    /**
     * Display column 'Order' (magic method, it is called if there is an 'order' column in the table)
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_order(stdClass $row): string {
        global $OUTPUT;
        $field = $row->field;

        $hasup = $this->can_move_up($field);
        $hasdown = $this->can_move_down($field);

        if ($hasup) {
            $upicon = $this->icon_link('t/up', get_string('moveup'), 'move', $field, '-1', true);
        } else {
            $upicon = $OUTPUT->spacer();
        }

        if ($hasdown) {
            $downicon = $this->icon_link('t/down', get_string('movedown'), 'move', $field, '1', true);
        } else {
            $downicon = $OUTPUT->spacer();
        }

        return html_writer::span($upicon . $downicon);
    }

    /**
     * Helper method to display an icon with a link
     *
     * @param string $icon icon name
     * @param string $title link title
     * @param string $action to use as data-action attribute
     * @param string $field to use as data-field attribute
     * @param string $newstate to use as data-newstate attribute
     * @param bool $includestateinid attribute 'data-id' will indicate to JS which DOM element
     *    in the reloaded table should be focused. This argument tells to also include
     *    the 'newstate' value when forming the 'data-id'. For example, for show/hide
     *    controls it should not be included but for 'up/down' it should.
     * @return string
     */
    protected function icon_link(string $icon, string $title, string $action, string $field,
            string $newstate, bool $includestateinid = false): string {
        global $OUTPUT;
        $dataattributes = [
            'data-action' => $action,
            'data-setting' => $this->settingname,
            'data-field' => $field,
            'data-newstate' => $newstate,
            'data-id' => "id-{$this->settingname}-{$field}-{$action}",
        ];
        if ($includestateinid) {
            $dataattributes['data-id'] .= '-' . $newstate;
        }
        return html_writer::link(
            '#',
            $OUTPUT->pix_icon($icon, $title),
            $dataattributes,
        );
    }

    /**
     * Get the table content.
     *
     * @return string
     */
    public function get_content(): string {
        ob_start();
        $this->out();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
