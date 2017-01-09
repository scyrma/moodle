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

defined('MOODLE_INTERNAL') || die();

/**
 * A login page layout for the boost theme.
 *
 * @package   theme_boost
 * @copyright 2016 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$bodyattributes = $OUTPUT->body_attributes();

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes
];

// add the Google Analytics Tracking Code to the footer (if we have the settings)
if ((defined('MOODLECLOUD_GA_GLOBAL_PROPERTY') && MOODLECLOUD_GA_GLOBAL_PROPERTY) &&
    (defined('MOODLECLOUD_GA_REGION_PROPERTY') && MOODLECLOUD_GA_REGION_PROPERTY) &&
    (defined('MOODLECLOUD_PLAN') && MOODLECLOUD_PLAN)
) {
    $templatecontext['ga_global_property'] = MOODLECLOUD_GA_GLOBAL_PROPERTY;
    $templatecontext['ga_region_property'] = MOODLECLOUD_GA_REGION_PROPERTY;
    $templatecontext['ga_plan'] = MOODLECLOUD_PLAN;
}

// MoodleCloud Google AdSense Banner Ads
$templatecontext['moodlecloud_ad_header'] = theme_boost_get_ad_header($OUTPUT->page->context);
$templatecontext['moodlecloud_ad'] = theme_boost_get_ad($OUTPUT->page->context);

echo $OUTPUT->render_from_template('theme_boost/login', $templatecontext);

