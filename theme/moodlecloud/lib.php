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
 * @copyright  2014 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extra LESS code to inject.
 *
 * This will generate some LESS code from the settings used by the user. We cannot use
 * the {@link theme_moodlecloud_less_variables()} here because we need to create selectors or
 * alter existing ones.
 *
 * @param theme_config $theme The theme config object.
 * @return string Raw LESS code.
 */
function theme_moodlecloud_extra_less($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    // Sets the background image, and its settings.
    if (!empty($imageurl)) {
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl');";
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
    if (!empty($theme->settings->contentbackground)) {
        $content .= '
            #region-main {
                .well;
                background-color: ' . $theme->settings->contentbackground . ';
                border-color: darken(' . $theme->settings->contentbackground . ', 7%);
            }';
    }
    return $content;
}

/**
 * Returns variables for LESS.
 *
 * We will inject some LESS variables from the settings that the user has defined
 * for the theme. No need to write some custom LESS for this.
 *
 * @param theme_config $theme The theme config object.
 * @return array of LESS variables without the @.
 */
function theme_moodlecloud_less_variables($theme) {
    $variables = array();
    if (!empty($theme->settings->bodybackground)) {
        $variables['bodyBackground'] = $theme->settings->bodybackground;
    }
    if (!empty($theme->settings->textcolor)) {
        $variables['textColor'] = $theme->settings->textcolor;
    }
    if (!empty($theme->settings->linkcolor)) {
        $variables['linkColor'] = $theme->settings->linkcolor;
    }
    if (!empty($theme->settings->secondarybackground)) {
        $variables['wellBackground'] = $theme->settings->secondarybackground;
    }
    return $variables;
}

/**
 * Parses CSS before it is cached.
 *
 * This function can make alterations and replace patterns within the CSS.
 *
 * @param string $css The CSS
 * @param theme_config $theme The theme config object.
 * @return string The parsed CSS The parsed CSS.
 */
function theme_moodlecloud_process_css($css, $theme) {

    // Set the background image for the logo.
    $logo = $theme->setting_file_url('logo', 'logo');
    $css = theme_moodlecloud_set_logo($css, $logo);

    // Set custom CSS.
    if (!empty($theme->settings->customcss)) {
        $customcss = $theme->settings->customcss;
    } else {
        $customcss = null;
    }
    $css = theme_moodlecloud_set_customcss($css, $customcss);

    return $css;
}

/**
 * Adds the logo to CSS.
 *
 * @param string $css The CSS.
 * @param string $logo The URL of the logo.
 * @return string The parsed CSS
 */
function theme_moodlecloud_set_logo($css, $logo) {
    $tag = '[[setting:logo]]';
    $replacement = $logo;
    if (is_null($replacement)) {
        $replacement = '';
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}

/**
 * Serves any files associated with the theme settings.
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

/**
 * Adds any custom CSS to the CSS before it is cached.
 *
 * @param string $css The original CSS.
 * @param string $customcss The custom CSS to add.
 * @return string The CSS which now contains our custom CSS.
 */
function theme_moodlecloud_set_customcss($css, $customcss) {
    $tag = '[[setting:customcss]]';
    $replacement = $customcss;
    if (is_null($replacement)) {
        $replacement = '';
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}

function theme_moodlecloud_is_teacher($context) {
    // The same capability as is used with page_doc_link().
    return has_capability('moodle/site:doclinks', $context);
}

function theme_moodlecloud_get_footerlinks($context) {
    global $OUTPUT;

    $links = array();

    if ($doclink = $OUTPUT->page_doc_link()) {
        $links[] = $doclink;
    }

    if (theme_moodlecloud_is_teacher($context)) {
        $title = get_string('supportforums', 'theme_moodlecloud');
        $link = new moodle_urL('https://moodle.org/community');
        $links[] = html_writer::link($link, $title, array('target' => '_blank'));
    }
    if (is_siteadmin()) {
        $title = get_string('faq', 'theme_moodlecloud');
        $link = new moodle_urL('https://moodle.com/cloud/faq');
        $links[] = html_writer::link($link, $title, array('target' => '_blank'));
    }
    return implode(' | ', $links);;
}

/**
 * The ads for teachers and admins require some JS to be added to the page header.
 */
function theme_moodlecloud_get_ad_header($context) {
    if (theme_moodlecloud_is_teacher($context)) {
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
function theme_moodlecloud_get_ad($context) {
    global $PAGE, $SESSION;

    // These strings are used by the JS checker.
    $PAGE->requires->strings_for_js(array(
            'adunblock_title',
            'adunblock_message',
        ), 'theme_moodlecloud');

    $adconfig = array(
            'id'            => 'moodlecloud_ad',
            'data-notified' => isset($SESSION->theme_moodlecloud_adblock_notified),
            'style'         => 'margin-left:auto;margin-right:auto;display:block !important;',
        );

    if (theme_moodlecloud_is_teacher($context)) {
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

function theme_moodlecloud_get_gatc() {
    global $OUTPUT;

    // we need the global and region property as well as the plan to output
    if ((defined('MOODLECLOUD_GA_GLOBAL_PROPERTY') && MOODLECLOUD_GA_GLOBAL_PROPERTY) &&
        (defined('MOODLECLOUD_GA_REGION_PROPERTY') && MOODLECLOUD_GA_REGION_PROPERTY) &&
        (defined('MOODLECLOUD_PLAN') && MOODLECLOUD_PLAN)
    ) {
        return $OUTPUT->render_from_template('theme_moodlecloud/google_analytics', array(
            'ga_global_property' => MOODLECLOUD_GA_GLOBAL_PROPERTY,
            'ga_region_property' => MOODLECLOUD_GA_REGION_PROPERTY,
            'ga_plan' => MOODLECLOUD_PLAN
        ));
    }
}

function theme_moodlecloud_portal_link() {
    global $USER, $CFG;

    if (isset($USER->auth) && $USER->auth === 'moodlecloud') {
        $url = new moodle_url('/auth/moodlecloud/portal.php');
        $title = get_string('cloudportallink', 'theme_moodlecloud');
        $alt = get_string('cloudlogo', 'theme_moodlecloud');
        $text = get_string('yourportal', 'theme_moodlecloud');
        $theme = theme_config::load('moodlecloud');
        $imageurl = $theme->image_url('moodlecloud-logo-inverted', 'theme');
        $imghtml = html_writer::img($imageurl, $alt);
        $linkhtml = html_writer::link($url->out(), sprintf("%s %s", $imghtml, $text),
            array('id' => 'portal-link', 'title' => $title, 'target' => '_blank'));

        return html_writer::div($linkhtml, '', array('id' => 'portal-link-container'));
    } else {
        return '';
    }
}
