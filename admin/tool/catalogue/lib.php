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
 * Plugin version and other meta-data are defined here.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_catalogue\manager;

/**
 * This function extends the navigation with the course info tab.
 *
 * @param navigation_node $parentnode The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param context_course $context The context of the course
 */
function tool_catalogue_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
        $url = new moodle_url('/admin/tool/catalogue/courseinfo.php', ['id' => $course->id]);
        $coursecover = $parentnode->add(get_string('information', 'tool_catalogue'), $url, navigation_node::TYPE_CONTAINER,
        null, 'courseinfo');
}

/**
 * Get the current user preferences that are available
 *
 * @return mixed Array representing current options along with defaults
 */
function tool_catalogue_user_preferences() {
    $preferences['/^tool_catalogue_show_program_content_(\d)+$/'] = [
        'isregex' => true,
        'type' => PARAM_RAW,
        'null' => NULL_NOT_ALLOWED,
        'default' => '[]',
        'permissioncallback' => [\tool_catalogue\permission::class, 'can_edit_preference'],
    ];
    $preferences['/^tool_catalogue_show_course_content_(\d)+$/'] = [
        'isregex' => true,
        'type' => PARAM_RAW,
        'null' => NULL_NOT_ALLOWED,
        'default' => '[]',
        'permissioncallback' => [\tool_catalogue\permission::class, 'can_edit_preference'],
    ];
    $preferences['tool_catalogue_hide_program_cover_help'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_INT,
    ];
    $preferences['tool_catalogue_my_courses_filter'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_TEXT,
    ];
    $preferences['tool_catalogue_my_courses_sort'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_TEXT,
    ];
    $preferences['tool_catalogue_collapse_recently_accessed_courses'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_INT,
        'permissioncallback' => [\tool_catalogue\permission::class, 'can_edit_preference'],
    ];
    return $preferences;
}

/**
 * Add course cover modal in course view page.
 *
 * @return string
 */
function tool_catalogue_before_footer(): string {
    global $PAGE;

    if (!$PAGE->course || $PAGE->course->id == SITEID) {
        return '';
    }

    if ($PAGE->url->compare(new moodle_url('/enrol/index.php'), URL_MATCH_BASE)) {
        // Add the preference to hide course cover modal.
        // This is executed here to avoid adding the preference when the "enrol/index.php" is accessed and redirected.
        // This way, we make sure that the user has accessed and viewed the page.
        set_user_preference('tool_catalogue_show_course_content_' . $PAGE->course->id, true);
    }

    if ($PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
        return manager::get_course_cover_modal($PAGE->course);
    }
    return '';
}
