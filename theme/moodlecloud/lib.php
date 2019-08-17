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
 * Theme moodlecloud lib.
 *
 * @package    theme_moodlecloud
 * @copyright  2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string All fixed Sass for this theme.
 */
function theme_moodlecloud_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    $scss .= file_get_contents($CFG->dirroot . '/theme/moodlecloud/scss/moodlecloud/pre.scss');
    if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_classic', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/moodlecloud/scss/preset/default.scss');
    }

    //$scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/classic/post.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/moodlecloud/scss/moodlecloud/post.scss');

    return $scss;

  /*  $fs = get_file_storage();

    // Main CSS - Get the CSS from theme Classic.
    $scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/classic/pre.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/preset/default.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/classic/post.scss');

    // Pre CSS - this is loaded AFTER any prescss from the setting but before the main scss.
    $pre = file_get_contents($CFG->dirroot . '/theme/moodlecloud/scss/pre.scss');

    // Post CSS - this is loaded AFTER the main scss but before the extra scss from the setting.
    $post = file_get_contents($CFG->dirroot . '/theme/moodlecloud/scss/post.scss');

    // Combine them together.
    return $pre . "\n" . $scss . "\n" . $post;*/
}

/**
 * Get compiled CSS.
 *
 * @return string compiled CSS
 */
function theme_moodlecloud_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/moodlecloud/style/moodle.css');
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_moodlecloud_get_extra_scss($theme) {
    global $CFG;
    $content = '';

    // Set the page background image.
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');

    if (!empty($imageurl)) {
        $content .= 'body { ';
        $content .= "background-image: url('{$imageurl}');";
        $content .= "background-size: auto;";

        if (!empty($theme->settings->backgroundfixed)) {
            $content .= 'background-attachment: fixed;';
        }

        if (!empty($theme->settings->backgroundposition)) {
            $content .= 'background-position: ' . str_replace('_', ' ', $theme->settings->backgroundposition) . ';';
        }

        if (!empty($theme->settings->backgroundrepeat)) {
            $content .= 'background-repeat: ' . $theme->settings->backgroundrepeat . ';';
        }

        $content .= ' }';
    }

    // If there the user wants a background for the content, we need to make it look consistent,
    // therefore we need to round its borders, and adapt the border colour.
    if (!empty($theme->settings->invert)) {
        $content .= file_get_contents($CFG->dirroot .
            '/theme/moodlecloud/scss/moodlecloud/navbar-dark.scss');
    } else {
        $content .= file_get_contents($CFG->dirroot .
            '/theme/moodlecloud/scss/moodlecloud/navbar-light.scss');
    }

    // Apply the the logo image if one is set.
    $logoURL = $theme->setting_file_url('logo', 'logo');
    if (!empty($logoURL)) {
        $content .= "\$logoURL: '{$logoURL}';";
        $content .= file_get_contents($CFG->dirroot .
            '/theme/moodlecloud/scss/moodlecloud/logo.scss');
    }

    // Apply Cloud custom CSS.
    $content .= file_get_contents($CFG->dirroot .
        '/theme/moodlecloud/scss/moodlecloud/cloud-specific.scss');

   // Apply admin editable custom CSS.
    if (!empty($theme->settings->customcss)) {
        $content .= $theme->settings->customcss;
    }

    return $content;
}

/**
 * Get SCSS to prepend (eg SCSS variables to set/override on parent theme).
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_moodlecloud_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key           => [variableName, ...].
        'bodybackground'        => ['body-bg'],
        'textcolor'             => ['textColor'],
        'linkcolor'             => ['linkColor'],
        'secondarybackground'   => ['cardBackground'],
        'contentbackground'     => ['contentBackground']
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
 * Serve any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_moodlecloud_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('moodlecloud');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}
