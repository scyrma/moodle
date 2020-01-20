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
 * This file replaces /theme/styles.php for workplace theme
 *
 * The purpose of this override is to server different stylessheets for
 * different organisations (tenants) using one Moodle instance.
 *
 * The tenant id is retreived from the CSS file called by the user.
 * for example the file "theme/1548946762/workplace/css/all-3_1548750271.css"
 * will server the CSS for tenant with id = 3.
 *
 * @package   theme_workplace
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Bas Brands <bas@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

define('ABORT_AFTER_CONFIG', true);
require('../../config.php');
require_once($CFG->dirroot.'/lib/csslib.php');
require_once($CFG->dirroot.'/theme/workplace/lib.php');

if ($slashargument = min_get_slash_argument()) {
    $slashargument = ltrim($slashargument, '/');
    if (substr_count($slashargument, '/') < 2) {
        css_send_css_not_found();
    }

    if (strpos($slashargument, '_s/') === 0) {
        // Can't use SVG.
        $slashargument = substr($slashargument, 3);
        $usesvg = false;
    } else {
        $usesvg = true;
    }

    list($themename, $rev, $type) = explode('/', $slashargument, 3);
    $themename = min_clean_param($themename, 'SAFEDIR');
    $rev       = min_clean_param($rev, 'RAW');
    $type      = min_clean_param($type, 'SAFEDIR');

} else {
    $themename = min_optional_param('theme', 'standard', 'SAFEDIR');
    $rev       = min_optional_param('rev', 0, 'RAW');
    $type      = min_optional_param('type', 'all', 'SAFEDIR');
    $usesvg    = (bool)min_optional_param('svg', '1', 'INT');
}


// Check if we received a theme sub revision which allows us
// to handle local caching on a per theme basis.
$values = explode('_', $rev);
$rev = min_clean_param(array_shift($values), 'INT');
$themesubrev = array_shift($values);

if (!is_null($themesubrev)) {
    $themesubrev = min_clean_param($themesubrev, 'INT');
}

// Check that type fits into the expected values.
if ($type === 'editor') {
    // The editor CSS is never chunked.
    $chunk = null;
} else if ((strpos($type, 'all') !== false)) {
    // We're fine.
    null;
} else {
     css_send_css_not_found();
}

$candidatedir = "$CFG->localcachedir/theme/$rev/$themename/css";
$candidatesheet = "{$candidatedir}/" . theme_workplace_styles_get_filename($type, $themesubrev, $usesvg);
$etag = theme_styles_get_etag($themename, $rev, $type, $themesubrev, $usesvg);

if (file_exists($candidatesheet)) {
    if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) || !empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        // We do not actually need to verify the etag value because our files
        // never change in cache because we increment the rev counter.
        css_send_unmodified(filemtime($candidatesheet), $etag);
    }
    css_send_cached_css($candidatesheet, $etag);
}

// Ok, now we need to start normal moodle script, we need to load all libs and $DB.
define('ABORT_AFTER_CONFIG_CANCEL', true);

define('NO_MOODLE_COOKIES', true); // Session not used here.
define('NO_UPGRADE_CHECK', true);  // Ignore upgrade check.

require("$CFG->dirroot/lib/setup.php");

$theme = theme_config::load($themename);

// The tenant id is fetched from the type which is the stripped filename.
if (preg_match('/all-(\d+)-\d+-?(rtl)?$/', $type, $matches)) {
    $tenantid = $matches[1];
    // Set the tenant id in the $theme->settings object this is used in the theme lib file
    // to serve the correct variables for this tenant.
    $theme->settings->tenantid = $tenantid;
    if (!empty($matches[2])) {
        $theme->set_rtl_mode(true);
    }
}
$theme->force_svg_use($usesvg);

$themerev = theme_get_revision();
$currentthemesubrev = theme_get_sub_revision_for_theme($themename);

$cache = true;
// If the client is requesting a revision that doesn't match both
// the global theme revision and the theme specific revision then
// tell the browser not to cache this style sheet because it's
// likely being regenerated.
if ($themerev <= 0 or $themerev != $rev or $themesubrev != $currentthemesubrev) {
    $rev = $themerev;
    $themesubrev = $currentthemesubrev;
    $cache = false;

    $candidatedir = "$CFG->localcachedir/theme/$rev/$themename/css";
    $candidatesheet = "{$candidatedir}/" . theme_workplace_styles_get_filename($type, $themesubrev, $usesvg);
    $etag = theme_styles_get_etag($themename, $rev, $type, $themesubrev, $usesvg);
}

make_localcache_directory('theme', false);

if ($type === 'editor') {
    $csscontent = $theme->get_css_content_editor();

    if ($cache) {
        css_store_css($theme, $candidatesheet, $csscontent);
        css_send_cached_css($candidatesheet, $etag);
    } else {
        css_send_uncached_css($csscontent);
    }

}

$candidatesheet = theme_workplace_styles_generate_and_store($theme, $rev, $themesubrev, $candidatedir, $type);

// Real browsers - this is the expected result!
css_send_cached_css($candidatesheet, $etag);


/**
 * Determine the correct etag for the specified configuration.
 *
 * @param   string  $themename The name of the theme
 * @param   int     $rev The revision number
 * @param   string  $type The requested sheet type
 * @param   int     $themesubrev The theme sub-revision
 * @param   bool    $usesvg Whether SVGs are allowed
 * @return  string  The etag to use for this request
 */
function theme_styles_get_etag($themename, $rev, $type, $themesubrev, $usesvg) {
    $etag = [$rev, $themename, $type, $themesubrev];

    if (!$usesvg) {
        $etag[] = 'nosvg';
    }

    return sha1(implode('/', $etag));
}