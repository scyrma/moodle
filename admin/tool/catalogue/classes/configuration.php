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

namespace tool_catalogue;

use core_course\customfield\course_handler;

/**
 * Learning catalogue configuration
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class configuration {

    /** @var string prefix for the course custom fields used in the fields array */
    const FIELD_PREFIX_CUSTOM_FIELDS = 'customfield_';
    /** @var string name for the 'Tags' field in the fields array */
    const FIELD_TAGS = 'tags';
    /** @var string name for the 'Course category' field in the fields array */
    const FIELD_CATEGORY = 'category';
    /** @var string name for the 'Subcategory' field in the fields array (used in available filters) */
    const FIELD_SUBCATEGORY = 'subcategory';
    /** @var string name for the 'Course contacts' field in the fields array */
    const FIELD_CONTACTS = 'contacts';
    /** @var string name for the 'Summary' field in the fields array */
    const FIELD_SUMMARY = 'summary';

    /** @var string */
    const SETTING_DISPLAYFIELDS_LIST = 'displayfields_list';
    /** @var string */
    const SETTING_DISPLAYFIELDS_TILES = 'displayfields_tiles';
    /** @var string */
    const SETTING_FILTERFIELDS = 'filterfields';
    /** @var string */
    const SETTING_COURSES_PER_PAGE_FRONTPAGE = 'coursesperpage_frontpage';
    /** @var string */
    const SETTING_COURSES_PER_PAGE_MAIN = 'coursesperpage_main';
    /** @var string */
    const SETTING_COURSES_PER_PAGE_SEARCH = 'coursesperpage_search';
    /** @var string */
    const SETTING_CATEGORIES_LIMIT = 'categorieslimit';
    /** @var string */
    const SETTING_CATEGORIES_DEPTH_LIMIT = 'categoriesdepthlimit';
    /** @var string Name of the setting: Number of course summary characters to display */
    const SETTING_TRUNCATE_SUMMARY = 'truncatesummary';
    /** @var string */
    const SETTING_SAFE_HTML_TAGS = 'safehtmltags';

    /** @var array list of settings that can be modified via AJAX */
    const VALID_SETTINGS_FIELDS_LIST = [
        self::SETTING_DISPLAYFIELDS_LIST,
        self::SETTING_DISPLAYFIELDS_TILES,
        self::SETTING_FILTERFIELDS,
    ];

    /** @var int */
    const DEFAULT_SETTING_COURSES_PER_PAGE_FRONTPAGE = 10;
    /** @var int */
    const DEFAULT_SETTING_COURSES_PER_PAGE_MAIN = 10;
    /** @var int */
    const DEFAULT_SETTING_COURSES_PER_PAGE_SEARCH = 10;
    /** @var int */
    const DEFAULT_SETTING_CATEGORIES_LIMIT = 10;
    /** @var int */
    const DEFAULT_SETTING_CATEGORIES_DEPTH_LIMIT = 3;
    /** @var string */
    const DEFAULT_SETTING_FILTERFIELDS = self::FIELD_SUBCATEGORY . ',' . self::FIELD_TAGS;
    /** @var string */
    const DEFAULT_SETTING_DISPLAYFIELDS_LIST = self::FIELD_SUMMARY;
    /** @var string */
    const DEFAULT_SETTING_DISPLAYFIELDS_TILES = '';
    /** @var int */
    const DEFAULT_TRUNCATE_SUMMARY = 250;
    /** @var string */
    const DEFAULT_SETTING_SAFE_HTML_TAGS = 'strong,b,em';

    /** @var string dropdown option for a setting value */
    const OPTION_HTML_TAGS_SAFE = 0;
    /** @var string dropdown option for a setting value */
    const OPTION_HTML_TAGS_ALL = 1;
    /** @var string dropdown option for a setting value */
    const OPTION_HTML_TAGS_NONE = 2;

    /**
     * Is catalogue enabled?
     *
     * @return bool
     */
    public static function is_catalogue_enabled(): bool {
        return (bool)get_config('tool_catalogue', 'enabled');
    }

    /**
     * Getter method for 'Number of courses per page, site home page' setting
     * @return int
     */
    public static function get_courses_per_page_frontpage(): int {
        return (int)self::get_setting_value(
            self::SETTING_COURSES_PER_PAGE_FRONTPAGE,
            self::DEFAULT_SETTING_COURSES_PER_PAGE_FRONTPAGE,
            PARAM_INT
        );
    }

    /**
     * Getter method for 'Number of courses per page, main catalogue page' setting
     * @return int
     */
    public static function get_courses_per_page_main(): int {
        return (int)self::get_setting_value(
            self::SETTING_COURSES_PER_PAGE_MAIN,
            self::DEFAULT_SETTING_COURSES_PER_PAGE_MAIN,
            PARAM_INT
        );
    }

    /**
     * Getter method for 'Number of courses per page, search results' setting
     * @return int
     */
    public static function get_courses_per_page_search(): int {
        return (int)self::get_setting_value(
            self::SETTING_COURSES_PER_PAGE_SEARCH,
            self::DEFAULT_SETTING_COURSES_PER_PAGE_SEARCH,
            PARAM_INT
        );
    }

    /**
     * Getter method for 'Maximum number of same-level categories' setting
     * @return int
     */
    public static function get_categories_limit(): int {
        return (int)self::get_setting_value(
            self::SETTING_CATEGORIES_LIMIT,
            self::DEFAULT_SETTING_CATEGORIES_LIMIT,
            PARAM_INT
        );
    }

    /**
     * Getter method for 'Maximum number of nested categories levels' setting
     * @return int
     */
    public static function get_categories_depth_limit(): int {
        return (int)self::get_setting_value(
            self::SETTING_CATEGORIES_DEPTH_LIMIT,
            self::DEFAULT_SETTING_CATEGORIES_DEPTH_LIMIT,
            PARAM_INT
        );
    }

    /**
     * Getter method for 'Truncate course summary' setting
     * @return int
     */
    public static function get_truncate_summary(): int {
        return (int)self::get_setting_value(
            self::SETTING_TRUNCATE_SUMMARY,
            self::DEFAULT_TRUNCATE_SUMMARY,
            PARAM_INT
        );
    }

    /**
     * Getter method for 'Safe HTML tags in summary and text fields' setting
     * @return string[]
     */
    public static function get_safe_html_tags(): array {
        $tagslist = self::get_setting_value(
            self::SETTING_SAFE_HTML_TAGS,
            self::DEFAULT_SETTING_SAFE_HTML_TAGS,
            PARAM_RAW_TRIMMED
        );
        return preg_split('/\s*,\s*/', $tagslist, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * List of fields to display for each course in the 'tiles' view
     * @return string[]
     */
    public static function get_display_fields_tiles(): array {
        $fieldslist = self::get_setting_value(
            self::SETTING_DISPLAYFIELDS_TILES,
            self::DEFAULT_SETTING_DISPLAYFIELDS_TILES,
            PARAM_RAW_TRIMMED
        );
        $fields = preg_split('/\s*,\s*/', $fieldslist, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_intersect($fields,
            self::get_all_available_fields_for_setting(self::SETTING_DISPLAYFIELDS_TILES))));
    }

    /**
     * List of fields to display for each course in the 'list' view
     * @return string[]
     */
    public static function get_display_fields_list(): array {
        $fieldslist = self::get_setting_value(
            self::SETTING_DISPLAYFIELDS_LIST,
            self::DEFAULT_SETTING_DISPLAYFIELDS_LIST,
            PARAM_RAW_TRIMMED
        );
        $fields = preg_split('/\s*,\s*/', $fieldslist, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_intersect($fields,
            self::get_all_available_fields_for_setting(self::SETTING_DISPLAYFIELDS_LIST))));
    }

    /**
     * List of fields to use for filtering courses
     * @return string[]
     */
    public static function get_filter_fields(): array {
        $fieldslist = self::get_setting_value(
            self::SETTING_FILTERFIELDS,
            self::DEFAULT_SETTING_FILTERFIELDS,
            PARAM_RAW_TRIMMED
        );
        $fields = preg_split('/\s*,\s*/', $fieldslist, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_intersect($fields,
            self::get_all_available_fields_for_setting(self::SETTING_FILTERFIELDS))));
    }

    /**
     * Gets a config setting value, applies default and cleans to the specified parameter type
     * @param string $name
     * @param mixed $defaultvalue
     * @param string $paramtype
     * @return mixed
     */
    protected static function get_setting_value(string $name, $defaultvalue, string $paramtype) {
        $value = get_config('tool_catalogue', $name);
        if ($value === false) {
            $value = $defaultvalue;
        }
        return clean_param($value, $paramtype);
    }

    /**
     * Get value of the settings that are stored as comma separated lists
     *
     * This method is used in the settings management interface where we access setting
     * by its name. In other cases use the respective get_...() methods.
     *
     * @param string $settingname
     * @return array
     */
    protected static function get_list_setting_value_by_name(string $settingname): array {
        if ($settingname === self::SETTING_DISPLAYFIELDS_LIST) {
            return self::get_display_fields_list();
        } else if ($settingname === self::SETTING_DISPLAYFIELDS_TILES) {
            return self::get_display_fields_tiles();
        } else if ($settingname === self::SETTING_FILTERFIELDS) {
            return self::get_filter_fields();
        } else {
            throw new \coding_exception('Invalid setting name');
        }
    }

    /**
     * All course custom fields that can potentially be used in the catalogue (must be visible to all)
     *
     * Returns array indexed by course custom field shortname with the prefix
     *
     * @return \core_customfield\field_controller[]
     */
    public static function get_all_course_custom_fields(): array {
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'tool_catalogue', 'coursecustomfields');
        $fields = $cache->get('all');

        if ($fields === false) {
            $fields = [];
            $customfields = course_handler::create()->get_fields();
            foreach ($customfields as $field) {
                if ($field->get_configdata_property('visibility') != course_handler::VISIBLETOALL) {
                    continue;
                }
                $fields[self::FIELD_PREFIX_CUSTOM_FIELDS . $field->get('shortname')] = $field;
            }
            $cache->set('all', $fields);
        }

        return $fields;
    }

    /**
     * Get all available course fields that can be displayed in the catalogue
     *
     * @return string[]
     */
    protected static function get_all_available_display_fields(): array {
        return array_merge([
                self::FIELD_SUMMARY,
                self::FIELD_CATEGORY,
                self::FIELD_TAGS,
                self::FIELD_CONTACTS
            ],
            array_keys(self::get_all_course_custom_fields())
        );
    }

    /**
     * Get all available course fields that can be used as filters in the catalogue
     *
     * @return string[]
     */
    protected static function get_all_available_filter_fields(): array {
        return [
            self::FIELD_SUBCATEGORY,
            self::FIELD_TAGS,
        ];
    }

    /**
     * Get all available fields that can be used for a given setting
     *
     * This method is used in the settings management interface where we access setting
     * by its name. In other cases use the respective get_...() methods.
     *
     * @param string $settingname
     * @return string[]
     */
    public static function get_all_available_fields_for_setting(string $settingname): array {
        if (!in_array($settingname, self::VALID_SETTINGS_FIELDS_LIST)) {
            throw new \coding_exception('Invalid setting name');
        }
        if ($settingname === self::SETTING_FILTERFIELDS) {
            return self::get_all_available_filter_fields();
        } else {
            return self::get_all_available_display_fields();
        }
    }

    /**
     * Adds or removes a field to the setting that represents a list of fields
     *
     * @param string $settingname
     * @param string $field
     * @param int $state
     */
    protected static function change_admin_setting_field_visibility(string $settingname, string $field, int $state): void {
        if ($state != 0 && $state != 1) {
            throw new \coding_exception('Invalid state');
        }
        $settingvalue = self::get_list_setting_value_by_name($settingname);
        if ($state && !in_array($field, $settingvalue)) {
            if (!in_array($field, self::get_all_available_fields_for_setting($settingname))) {
                return;
            }
            $settingvalue[] = $field;
        } else if (!$state && in_array($field, $settingvalue)) {
            $settingvalue = array_diff($settingvalue, [$field]);
        } else {
            // Probably double click, the field is already in this state.
            return;
        }
        set_config($settingname, join(',', $settingvalue), 'tool_catalogue');
    }

    /**
     * Changes the order of a field in the setting that represents a list of fields
     *
     * @param string $settingname
     * @param string $field
     * @param int $direction
     */
    protected static function change_admin_setting_field_order(string $settingname, string $field, int $direction): void {
        if ($direction != -1 && $direction != 1) {
            throw new \coding_exception('Invalid direction');
        }
        $settingvalue = self::get_list_setting_value_by_name($settingname);
        $index = array_search($field, $settingvalue);
        if ($index === false || $index + $direction < 0 || $index + $direction >= count($settingvalue)) {
            // Element not found or new position is out of array bounds.
            return;
        }
        array_splice($settingvalue, $index, 1);
        array_splice($settingvalue, $index + $direction, 0, $field);
        set_config($settingname, join(',', $settingvalue), 'tool_catalogue');
    }

    /**
     * Sets a value for a setting (only for settings that can be changed in AJAX)
     *
     * See also {@see self::get_setting_name_allow_html_tags()}
     *
     * @param string $settingname
     * @param string $field
     * @param string $newstate
     */
    protected static function change_admin_setting_set_value(string $settingname, string $field,
            string $newstate = ''): void {
        // Validate setting name (prevent privilege escalation).
        $parts = explode(':', $settingname);
        if (count($parts) === 3 &&
                $parts[2] === 'html' &&
                in_array($parts[1], self::get_all_available_fields_for_setting($parts[0]))) {
            $value = (int)$newstate;
            set_config($settingname, $value, 'tool_catalogue');
        } else {
            throw new \coding_exception('Unrecognised setting or field name');
        }
    }

    /**
     * Forms a name of the setting that stores whether the HTML tags are allowed in a field
     *
     * @param string $listsettingname
     * @param string $field
     * @return string
     */
    public static function get_setting_name_allow_html_tags(string $listsettingname, string $field): string {
        return $listsettingname . ':' . $field . ':html';
    }

    /**
     * Processes the AJAX request to change a setting value
     *
     * Usually used for enabling/disabling fields in a setting that represents a list of fields,
     * or for changing the order of the fields in the list, or miscellaneous AJAX-only settings.
     *
     * @param string $action
     * @param string $settingname
     * @param string $field
     * @param string $newstate
     */
    public static function change_admin_setting(string $action, string $settingname, string $field,
            string $newstate = ''): void {
        if ($action === 'toggle') {
            // Toggle the item visibility.
            self::change_admin_setting_field_visibility($settingname, $field, (int)$newstate);
        } else if ($action === 'move') {
            // Move the item in the array up or down.
            self::change_admin_setting_field_order($settingname, $field, (int)$newstate);
        } else if ($action === 'set') {
            // Set a value for the setting.
            self::change_admin_setting_set_value($settingname, $field, $newstate);
        } else {
            throw new \coding_exception('Invalid action');
        }
    }
}
