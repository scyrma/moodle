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

namespace tool_catalogue\privacy;

use core_privacy\local\request\transform;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\metadata\collection;
use \core_privacy\local\request\writer;

/**
 * Privacy Subsystem for tool catalogue.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements \core_privacy\local\metadata\provider, user_preference_provider {

    /**
     * Returns meta-data information for tool catalogue.
     *
     * @param  \core_privacy\local\metadata\collection $collection A collection of meta-data.
     * @return \core_privacy\local\metadata\collection Return the collection of meta-data.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_user_preference('tool_catalogue_hide_program_cover_help', 'privacy:metadata:showprogramcoverhelp');
        return $collection;
    }

    /**
     * Export the user preferences for tool catalogue.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        static::get_and_export_user_preference($userid, 'tool_catalogue_hide_program_cover_help', true);
        static::get_and_export_user_preference($userid, 'tool_catalogue_my_courses_filter');
        static::get_and_export_user_preference($userid, 'tool_catalogue_my_courses_sort');

        // These preferences contain an id at the end like 'tool_catalogue_show_course_content_2".
        $preferences = get_user_preferences(null, null, $userid);
        foreach ($preferences as $name => $value) {
            if (strpos($name, 'tool_catalogue_show_program_content') !== false ||
                strpos($name, 'tool_catalogue_show_course_content') !== false) {
                writer::export_user_preference(
                    'tool_catalogue',
                    $name,
                    transform::yesno($value),
                    get_string('privacy:request:preference:set', 'tool_catalogue', (object) [
                        'name' => $name,
                        'value' => transform::yesno($value),
                    ])
                );
            }
        }
    }

    /**
     * Get and export a user preference.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     * @param string $userpreference The user preference to export.
     * @param bool $transform If true, transform value to yesno.
     */
    protected static function get_and_export_user_preference(int $userid, string $userpreference, bool $transform = false) {
        $prefvalue = get_user_preferences($userpreference, null, $userid);
        if ($prefvalue !== null) {
            $transformedvalue = $transform ? transform::yesno($prefvalue) : $prefvalue;
            writer::export_user_preference(
                'tool_catalogue',
                $userpreference,
                $transformedvalue,
                get_string('privacy:metadata:preference:'.$userpreference, 'tool_catalogue')
            );
        }
    }
}
