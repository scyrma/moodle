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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Callbacks
 *
 * @package     theme_workplace
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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

    if (class_exists('\tool_tenant\manager')) {
        $tenantid = !empty($theme->settings->tenantid) ? $theme->settings->tenantid : 0;
        $scss .= (new \tool_tenant\manager())->get_theme_scss($tenantid);
    }

    $scss .= file_get_contents($CFG->dirroot . '/theme/workplace/scss/default.scss');

    return $scss;
}

/**
 * Inject additional SCSS.
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_workplace_get_extra_scss($theme) {
    $content = '';
    if (class_exists('\tool_tenant\manager')) {
        $tenantid = !empty($theme->settings->tenantid) ? $theme->settings->tenantid : 0;
        $content .= (new \tool_tenant\manager())->get_theme_scss($tenantid);
    }
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}

/**
 * Allows to modify URL and cache file for the theme CSS
 *
 * @param moodle_url[] $urls
 */
function theme_workplace_alter_css_urls(&$urls) {
    global $CFG, $DB;

    if (during_initial_install() || !class_exists('\tool_tenant\tenancy') || !\tool_tenant\tenancy::is_site_multi_tenant()) {
        return;
    }
    if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
        // No CSS switch during behat runs, or it will take ages to run a scenario.
        return;
    }

    $tenantid = \tool_tenant\tenancy::get_tenant_id();
    $tenantid = \tool_tenant\sharedspace::is_shared_space($tenantid) ? \tool_tenant\tenancy::get_default_tenant_id() : $tenantid;
    $alltenants = \tool_tenant\tenancy::get_tenants();

    if (array_key_exists($tenantid, $alltenants)) {
        $tenant = $alltenants[$tenantid];
        $tenantid .= '-' . $tenant->timemodified;
    }

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
    $subrev = theme_get_sub_revision_for_theme('workplace');

    $themecss = new moodle_url('/theme/workplace/wpcss.php');
    $cssfile = right_to_left() ? 'all-' . $tenantid . '-rtl' : 'all-' . $tenantid;
    if (!empty($CFG->slasharguments)) {
        $themecss->set_slashargument('/workplace/' . $rev . '_' . $subrev . '/' . $cssfile);
    } else {
        $params = array('theme' => 'workplace', 'rev' => $rev, 'type' => $cssfile);
        $themecss->params($params);
    }
    $urls[] = $themecss;
}

/**
 * Generate all the CSS for all tenants.
 */
function theme_workplace_generate_tenant_css() {
    global $CFG, $DB;
    $theme = theme_config::load('workplace');
    $rev = theme_get_revision();
    $themesubrev = theme_get_sub_revision_for_theme('workplace');
    $candidatedir = "$CFG->localcachedir/theme/$rev/workplace/css";
    $alltenants = \tool_tenant\tenancy::get_tenants();
    unset($alltenants[\tool_tenant\sharedspace::get_shared_space_id()]);
    foreach ($alltenants as $tenant) {
        $type = 'all-' . $tenant->id . '-' . $tenant->timemodified;

        $compiledsheet = "{$candidatedir}/" . theme_workplace_styles_get_filename($type, $themesubrev, true);
        if (!file_exists($compiledsheet)) {
            theme_workplace_styles_generate_and_store($theme, $rev, $themesubrev, $candidatedir, $type);
        }

        $typertl = $type . '-rtl';
        $compiledsheetrtl = "{$candidatedir}/" . theme_workplace_styles_get_filename($typertl, $themesubrev, true);
        if (!file_exists($compiledsheetrtl)) {
            theme_workplace_styles_generate_and_store($theme, $rev, $themesubrev, $candidatedir, $typertl);
        }
    }

}

/**
 * Get the filename for the specified configuration.
 *
 * @param string $type The requested sheet type
 * @param int $themesubrev The theme sub-revision
 * @param bool $usesvg Whether SVGs are allowed
 * @return  string  The filename for this sheet
 */
function theme_workplace_styles_get_filename($type, $themesubrev = 0, $usesvg = true) {
    $filename = $type;
    $filename .= ($themesubrev > 0) ? "_{$themesubrev}" : '';
    $filename .= $usesvg ? '' : '-nosvg';

    return "{$filename}.css";
}

/**
 * Generate the theme CSS and store it.
 * @param theme_config $theme The theme to be generated
 * @param int $rev The theme revision
 * @param int $themesubrev The theme sub-revision
 * @param string $candidatedir The directory that it should be stored in
 * @param string $type The path that the primary (non-chunked) CSS was written to
 * @return  string
 * @throws coding_exception
 */
function theme_workplace_styles_generate_and_store($theme, $rev, $themesubrev, $candidatedir, $type = null) {
    global $CFG;
    require_once("{$CFG->libdir}/csslib.php");
    require_once("{$CFG->libdir}/filelib.php");

    $filename = theme_workplace_styles_get_filename($type, $themesubrev, $theme->use_svg_icons());
    $cachekey = str_replace('-', '_', pathinfo($filename, PATHINFO_FILENAME));
    $cache = cache::make('core', 'postprocessedcss');

    if (!$csscontent = $cache->get($cachekey)) {
        $csscontent = $theme->get_css_content();
        $cache->set($cachekey, $csscontent);
    }

    // Determine the candidatesheet path.
    // Note: Do not pass any value for chunking as this is calculated during css storage.
    $candidatesheet = "{$candidatedir}/" . $filename;

    // Store the CSS.
    css_store_css($theme, $candidatesheet, $csscontent);

    // Clean up old CSS files for the current tenant.
    $tenantid = 0;
    $timemodified = 0;
    // The $type variable holds the tenant CSS file base. For example "all-2-1579173749".
    if (preg_match('/all-(\d+)-(\d+)-?(rtl)?$/', $type, $matches)) {
        $tenantid = $matches[1];
        $timemodified = $matches[2];
    }

    if ($tenantid && $timemodified) {
        $tenantcssfiles = glob("{$CFG->localcachedir}/theme/{$rev}/{$theme->name}/css/all-{$tenantid}-*.css");
        foreach ($tenantcssfiles as $tenantcss) {
            $filematches = [];
            preg_match('/all-' . $tenantid . '-(\d+)[-|_](.*)css$/', $tenantcss, $filematches);
            if (isset($filematches[1]) && $filematches[1] !== $timemodified) {
                fulldelete($tenantcss);
            }
        }
    }

    // Delete older revisions from localcache.
    $themecachedirs = glob("{$CFG->localcachedir}/theme/*", GLOB_ONLYDIR);
    foreach ($themecachedirs as $localcachedir) {
        $cachedrev = [];
        preg_match("/\/theme\/([0-9]+)$/", $localcachedir, $cachedrev);
        $cachedrev = isset($cachedrev[1]) ? intval($cachedrev[1]) : 0;
        if ($cachedrev > 0 && $cachedrev < $rev) {
            fulldelete($localcachedir);
        }
    }

    // Delete older theme subrevision CSS from localcache.
    $subrevfiles = glob("{$CFG->localcachedir}/theme/{$rev}/{$theme->name}/css/*.css");
    foreach ($subrevfiles as $subrevfile) {
        $cachedsubrev = [];
        preg_match("/_([0-9]+)\.([0-9]+\.)?css$/", $subrevfile, $cachedsubrev);
        $cachedsubrev = isset($cachedsubrev[1]) ? intval($cachedsubrev[1]) : 0;
        if ($cachedsubrev > 0 && $cachedsubrev < $themesubrev) {
            fulldelete($subrevfile);
        }
    }

    return $candidatesheet;
}

/**
 * Get inital SCSS for the theme.
 * @param theme_config $theme The theme to be generated
 * @return string
 */
function theme_workplace_get_pre_scss($theme) {
    $scss = '';
    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }
    return $scss;
}
