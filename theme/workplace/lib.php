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
 * Callbacks
 *
 * @package     theme_workplace
 * @copyright   2018 SP
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of $THEME->scss
 *
 * @param theme_config $theme
 * @return string
 */
function theme_workplace_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    if (class_exists('\tool_tenant\tenancy')) {
        $tenantid = !empty($theme->settings->tenantid) ? $theme->settings->tenantid : 0;
        $scss .= \tool_tenant\tenancy::get_theme_scss($tenantid);
    }

    $scss .= file_get_contents($CFG->dirroot . '/theme/workplace/scss/default.scss');

    return $scss;
}

/**
 * Render the workplace menu
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function theme_workplace_render_navbar_output(\renderer_base $renderer) {
    if (!method_exists($renderer, 'workplace_menu')) {
        return '';
    }

    return $renderer->workplace_menu();
}

/**
 * Allows to modify URL and cache file for the theme CSS
 *
 * @param moodle_url[] $urls
 */
function theme_workplace_alter_css_urls(&$urls) {
    global $CFG;
    if (during_initial_install() || !class_exists('\tool_tenant\tenancy') || !\tool_tenant\tenancy::is_site_multi_tenant()) {
        return;
    }
    if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
        // No CSS switch during behat runs, or it will take ages to run a scenario.
        return;
    }

    $tenantid = \tool_tenant\tenancy::get_tenant_id();

    $rev = theme_get_revision();

    if ($rev == -1) {
        foreach (array_keys($urls) as $i) {
            if ($urls[$i]->get_param('type') == 'scss') {
                unset($urls[$i]);
            }
        }
    } else {
        $urls = [];
    }

    $rev = $CFG->themerev;
    $subrev = get_config('theme_workplace', 'themerev');

    $themecss = new moodle_url('/theme/workplace/wpcss.php');
    $cssfile = right_to_left() ? 'all-' . $tenantid . '-rtl' : 'all-' . $tenantid;
    if (!empty($CFG->slasharguments)) {
        $themecss->set_slashargument('/workplace/' . $rev .  '_' . $subrev . '/' . $cssfile);
    } else {
        $params = array('theme' => 'workplace', 'rev' => $rev, 'type' => $cssfile);
        $themecss->params($params);
    }
    $urls[] = $themecss;
}
