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
 * The columns layout for the classic theme.
 *
 * @package   theme_classic
 * @copyright 2018 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();
$blockspre = $OUTPUT->blocks('side-pre');
$blockspost = $OUTPUT->blocks('side-post');

$hassidepre = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
$hassidepost = $PAGE->blocks->region_has_content('side-post', $OUTPUT);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockspre,
    'sidepostblocks' => $blockspost,
    'haspreblocks' => $hassidepre,
    'haspostblocks' => $hassidepost,
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

// MoodleCloud Portal SSO Tab
if (isset($USER->auth) && $USER->auth === 'moodlecloud') {
    $url = new moodle_url('/auth/moodlecloud/portal.php');
    $templatecontext['showportallink'] = true;
    $templatecontext['cloudportalurl'] = $url->out();
    $theme = theme_config::load('boost');
    $templatecontext['cloudinvertedimgurl'] = $theme->image_url('cloud-logo-inverted', 'theme');
}

$templatecontext['footer_links'] = theme_boost_get_footerlinks($OUTPUT->page->context);

echo $OUTPUT->render_from_template('theme_classic/columns', $templatecontext);
