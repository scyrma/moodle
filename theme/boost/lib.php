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
 * Theme functions.
 *
 * @package    theme_boost
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post process the CSS tree.
 *
 * @param string $tree The CSS tree.
 * @param theme_config $theme The theme config object.
 */
function theme_boost_css_tree_post_processor($tree, $theme) {
    $prefixer = new theme_boost\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_boost_get_extra_scss($theme) {
    return !empty($theme->settings->scss) ? $theme->settings->scss : '';
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_boost_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_boost', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_boost_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        'brandcolor' => ['brand-primary'],
    ];

    // Prepend variables first.
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * The ads for teachers and admins require some JS to be added to the page header.
 */
function theme_boost_get_ad_header($context) {
    // do a capability check to see if this is a teacher. The same capability as is used with page_doc_link().
    // i.e. "is teacher?"
    if (theme_boost_is_teacher($context)) {
        if (defined('MOODLECLOUD_FEATURE_TEACHERADS_DISABLED') && MOODLECLOUD_FEATURE_TEACHERADS_DISABLED) {
            // Teacher ads are disabled.
            return '';
        } else {
            return file_get_contents(__DIR__ . '/ads/teacher_head.html');
        }
    }
}

/**
 * Partners ads for teachers and admins, adsense for students.
 */
function theme_boost_get_ad($context) {
    global $PAGE, $SESSION;

    // These strings are used by the JS checker.
    $PAGE->requires->strings_for_js(array(
        'adunblock_title',
        'adunblock_message',
    ), 'theme_moodlecloud');

    $adconfig = array(
        'id'            => 'moodlecloud_ad',
        'data-notified' => isset($SESSION->theme_boost_adblock_notified),
        'style'         => 'margin-left:auto;margin-right:auto;display:block !important;',
    );

    // do a capability check to see if this is a teacher. The same capability as is used with page_doc_link()
    // i.e. "is teacher?"
    if (theme_boost_is_teacher($context)) {
        // User is an administrator of some kind.
        if (defined('MOODLECLOUD_FEATURE_TEACHERADS_DISABLED') && MOODLECLOUD_FEATURE_TEACHERADS_DISABLED) {
            // Teacher ads are disabled.
            return '';
        } else {
            return html_writer::div(file_get_contents(__DIR__ . '/ads/teacher_body.html'), '', $adconfig);
        }
    } else {
        // User is not an administrator.

        if (defined('MOODLECLOUD_FEATURE_STUDENTADS_DISABLED') && MOODLECLOUD_FEATURE_STUDENTADS_DISABLED) {
            // Student ads are disabled.
            return '';
        } else {
            // Display the student ads.
            return html_writer::div(file_get_contents(__DIR__ . '/ads/general_body.html'), '', $adconfig);
        }
    }
}

function theme_boost_get_footerlinks($context) {
    global $OUTPUT;

    $links = array();

    if ($doclink = $OUTPUT->page_doc_link()) {
        $links[] = $doclink;
    }

    if (theme_boost_is_teacher($context)) {
        $title = get_string('supportforums', 'theme_boost');
        $link = new moodle_url('https://moodle.org/community');
        $links[] = html_writer::link($link, $title, array('target' => '_blank'));
    }
    if (is_siteadmin()) {
        $title = get_string('faq', 'theme_boost');
        $link = new moodle_url('https://moodle.com/cloud/faq');
        $links[] = html_writer::link($link, $title, array('target' => '_blank'));
    }
    return implode(' | ', $links);
}

function theme_boost_is_teacher($context) {
    // The same capability as is used with page_doc_link().
    return has_capability('moodle/site:doclinks', $context);
}
