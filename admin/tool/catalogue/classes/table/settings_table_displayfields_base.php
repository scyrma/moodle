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

use stdClass;
use tool_catalogue\configuration;

/**
 * Table with all available fields that can be displayed in the catalogue
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class settings_table_displayfields_base extends settings_table_base {

    /**
     * Get a list of the column titles
     * @return string[]
     */
    protected function get_column_list(): array {
        $columns = parent::get_column_list();
        $columns['html'] = get_string('allowhtmltags', 'tool_catalogue');
        return $columns;
    }

    /**
     * Display column 'Allow HTML tags'
     *
     * @param stdClass $row
     * @return string
     */
    protected function col_html(stdClass $row): string {
        $field = $row->field;
        // Standard custom field type 'textarea' can contain HTML tags.
        // We also assume that any non-standard custom field type can contain HTML tags.

        if (!in_array($field, $this->visiblefields)) {
            return '';
        }

        if (configuration::has_allow_html_tags_setting($field)) {
            // This field can potentially contain HTML tags and we need to know if
            // HTML tags are allowed there. By default only safe HTML tags are allowed.
            $settingname = configuration::get_setting_name_allow_html_tags($this->settingname, $field);
            $control = \html_writer::select([
                configuration::OPTION_HTML_TAGS_SAFE => get_string('htmltagssafe', 'tool_catalogue'),
                configuration::OPTION_HTML_TAGS_ALL => get_string('htmltagsall', 'tool_catalogue'),
                configuration::OPTION_HTML_TAGS_NONE => get_string('htmltagsnone', 'tool_catalogue'),
            ], 'tool_catalogue:' . $settingname,
            (int)get_config('tool_catalogue', $settingname),
            false,
            [
                'class' => 'ignoredirty',
                'data-action' => 'set',
                'data-setting' => $settingname,
                'data-field' => '',
                'data-id' => 'tool_catalogue:' . $settingname,
            ]);
            $control = \html_writer::tag('label', get_string('allowhtmltags', 'tool_catalogue'), [
                'for' => 'menutool_catalogue:' . $settingname,
                'class' => 'accesshide',
            ]) . $control;
            return $control;
        }
        return '';
    }
}
