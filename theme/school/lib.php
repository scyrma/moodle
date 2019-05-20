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
 * Theme school lib.
 *
 * @package    theme_school
 * @copyright  2019 Mathew May
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
function theme_school_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $scss .= theme_school_get_pre_scss($theme);
    $scss .= file_get_contents($CFG->dirroot . '/theme/school/scss/pre.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/fontawesome.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/bootstrap.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/classic/scss/moodle.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/school/scss/post.scss');

    return $scss;
}

/**
 * Get compiled CSS.
 *
 * @return string compiled CSS
 */
function theme_school_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/school/style/school.css');
}

function theme_school_get_setting($setting, $format = false) {
    global $CFG;
    require_once($CFG->dirroot . '/lib/weblib.php');
    static $theme;
    if (empty($theme)) {
        $theme = theme_config::load('school');
    }
    if (empty($theme->settings->$setting)) {
        return false;
    } else if (!$format) {
        return $theme->settings->$setting;
    } else if ($format === 'format_text') {
        return format_text($theme->settings->$setting, FORMAT_PLAIN);
    } else if ($format === 'format_html') {
        return format_text($theme->settings->$setting, FORMAT_HTML, array('trusted' => true, 'noclean' => true));
    } else {
        return format_string($theme->settings->$setting);
    }
}

/**
 * Get SCSS to prepend (eg SCSS variables to set/override on parent theme).
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_school_get_pre_scss($theme) {
    global $CFG;
    $scss = '';
    $configurable = [
        // Config key           => [variableName, ...].
        'primarycolour'         => ['primary'],
        'primaryfontcolour'     => ['font'],
        'primarylinkcolour'     => ['linkstandard'],
        'secondarycolour'       => ['secondary'],
        'secondaryfontcolour'   => ['secondaryfont'],
        'secondarylinkcolour'   => ['secondarylink'],
        'footercolour'          => ['footerbackground'],
        'footerfontcolour'      => ['footerfont'],
        'footerlinkcolour'      => ['footerlinkstandard'],
        'blocklinkcolour'       => ['blockfont'],
        'mainlinkcolour'        => ['link-color'],
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

    if (theme_school_get_setting('fontselect') === '2') {
        $headingfont = theme_school_get_setting('fontnameheading');
        if ($headingfont) {
            $scss .= theme_school_set_fontfiles('heading', $headingfont);
            $scss .= '$headings-font-family: ' . $headingfont . ";\n";
        }

        $bodyfont = theme_school_get_setting('fontnamebody');
        if ($bodyfont) {
            $scss .= theme_school_set_fontfiles('body', $bodyfont);
            $scss .= '$font-family-sans-serif: ' . $bodyfont . ";\n";
        }
    }

    return $scss;
}

/**
 * Adds the font to CSS.
 *
 * @param string $css The CSS.
 * @param string $font The font name.
 * @return string The parsed CSS
 */
function theme_school_set_fontfiles($type, $fontname) {

    static $theme;
    if (empty($theme)) {
        $theme = theme_config::load('school');  // $theme needs to be us for child themes.
    }

    $fontfiles = array();
    $fontfileeot = $theme->setting_file_url('fontfileeot' . $type, 'fontfileeot' . $type);
    if (!empty($fontfileeot)) {
        $fontfiles[] = "url('" . $fontfileeot . "?#iefix') format('embedded-opentype')";
    }
    $fontfilewoff = $theme->setting_file_url('fontfilewoff' . $type, 'fontfilewoff' . $type);
    if (!empty($fontfilewoff)) {
        $fontfiles[] = "url('" . $fontfilewoff . "') format('woff')";
    }
    $fontfilewofftwo = $theme->setting_file_url('fontfilewofftwo' . $type, 'fontfilewofftwo' . $type);
    if (!empty($fontfilewofftwo)) {
        $fontfiles[] = "url('" . $fontfilewofftwo . "') format('woff2')";
    }
    $fontfileotf = $theme->setting_file_url('fontfileotf' . $type, 'fontfileotf' . $type);
    if (!empty($fontfileotf)) {
        $fontfiles[] = "url('" . $fontfileotf . "') format('opentype')";
    }
    $fontfilettf = $theme->setting_file_url('fontfilettf' . $type, 'fontfilettf' . $type);
    if (!empty($fontfilettf)) {
        $fontfiles[] = "url('" . $fontfilettf . "') format('truetype')";
    }
    $fontfilesvg = $theme->setting_file_url('fontfilesvg' . $type, 'fontfilesvg' . $type);
    if (!empty($fontfilesvg)) {
        $fontfiles[] = "url('" . $fontfilesvg . "') format('svg')";
    }

    $css = '@font-face {' . PHP_EOL . 'font-family: "' . $fontname . '";' . PHP_EOL;
    $css .= !empty($fontfileeot) ? "src: url('" . $fontfileeot . "');" . PHP_EOL : '';
    if (!empty($fontfiles)) {
        $css .= "src: ";
        $css .= implode("," . PHP_EOL . " ", $fontfiles);
        $css .= ";";
    }
    $css .= '' . PHP_EOL . "}";
    return $css;
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
function theme_school_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    static $theme;
    if (empty($theme)) {
        $theme = theme_config::load('school');
    }
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        if ($filearea === 'logo') {
            return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
        } else if ($filearea === 'style') {
            theme_school_serve_css($args[1]);
        } else if ($filearea === 'pagebackground') {
            return $theme->setting_file_serve('pagebackground', $args, $forcedownload, $options);
        } else if ($filearea === 'icon') {
            return $theme->setting_file_serve('icon', $args, $forcedownload, $options);
        } else if ($filearea === 'frontpagemediaimage') {
            return $theme->setting_file_serve('frontpagemediaimage', $args, $forcedownload, $options);
        } else if ($filearea === 'uploadvideo') {
            return $theme->setting_file_serve('uploadvideo', $args, $forcedownload, $options);
        } else if ($filearea === 'logobackgroundimage') {
            return $theme->setting_file_serve('logobackgroundimage', $args, $forcedownload, $options);
        } else if (preg_match("/^fontfile(eot|otf|svg|ttf|woff|wofftwo)(heading|body)$/", $filearea)) { // http://www.regexr.com/.
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if (preg_match("/^(marketing|slide)[1-9][0-9]*image$/", $filearea)) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if($filearea === 'slideimage1') {
            return $theme->setting_file_serve('slideimage1', $args, $forcedownload, $options);
        } else if($filearea === 'slideimage2') {
            return $theme->setting_file_serve('slideimage2', $args, $forcedownload, $options);
        } else if($filearea === 'slideimage3') {
            return $theme->setting_file_serve('slideimage3', $args, $forcedownload, $options);
        } else if($filearea === 'slideimage4') {
            return $theme->setting_file_serve('slideimage4', $args, $forcedownload, $options);
        } else if($filearea === 'slideimage5') {
            return $theme->setting_file_serve('slideimage5', $args, $forcedownload, $options);
        } else if($filearea === 'faviconurl') {
            return $theme->setting_file_serve('faviconurl', $args, $forcedownload, $options);
        } else if($filearea === 'feedbackslideimage_1') {
            return $theme->setting_file_serve('feedbackslideimage_1', $args, $forcedownload, $options);
        } else if($filearea === 'feedbackslideimage_2') {
            return $theme->setting_file_serve('feedbackslideimage_2', $args, $forcedownload, $options);
        } else if($filearea === 'feedbackslideimage_3') {
            return $theme->setting_file_serve('feedbackslideimage_3', $args, $forcedownload, $options);
        } else if($filearea === 'feedbackslideimage_4') {
            return $theme->setting_file_serve('feedbackslideimage_4', $args, $forcedownload, $options);
        } else {
            send_file_not_found();
        }
    } else {
        send_file_not_found();
    }
}
