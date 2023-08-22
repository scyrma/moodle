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
 * Class admin_externalpage
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use moodle_url;
use navigation_node;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Class admin_externalpage
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class admin_externalpage extends \admin_externalpage {

    /** @var callable */
    protected $accesscheckcallback;

    /**
     * admin_externalpage constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $url
     * @param callable $accesscheckcallback a method that will be executed to check if user has permission
     *     to access this item. The instance of this setting ($this) is passed as an argument to this callback.
     * @param bool $hidden
     */
    public function __construct(string $name, string $visiblename, string $url, callable $accesscheckcallback,
                                bool $hidden = false) {
        parent::__construct($name, $visiblename, $url, [], $hidden);
        $this->accesscheckcallback = $accesscheckcallback;
    }

    /**
     * see \admin_externalpage
     *
     * @return bool Returns true for yes false for no
     */
    public function check_access() {
        $callback = $this->accesscheckcallback;
        return $callback($this);
    }

    /**
     * Initialise admin page - this function does require login and permission
     * checks specified in page definition.
     *
     * This is a copy of admin_externalpage_setup() without the search bar and with workplace body classes
     *
     * @param string $section name of page
     * @param string $extrabutton extra HTML that is added after the blocks editing on/off button.
     * @param array $extraurlparams an array paramname => paramvalue, or parameters that need to be
     *      added to the turn blocks editing on/off form, so this page reloads correctly.
     * @param moodle_url|string $actualurl if the actual page being viewed is not the normal one for this
     *      page (e.g. admin/roles/allow.php, instead of admin/roles/manage.php, you can pass the alternate URL here.
     * @param array $options Additional options that can be specified for page setup.
     *      pagelayout - This option can be used to set a specific pagelyaout, admin is default.
     */
    public static function setup_page(string $section, string $extrabutton = '', ?array $extraurlparams = null,
                                      $actualurl = '', array $options = []) {
        global $PAGE, $SITE, $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        if (strlen($extrabutton)) {
            // This function is using exactly the same parameters as admin_externalpage_setup(), so whatever
            // is deprecated there is deprecated here too.
            debugging('Parameter extrabutton is not supported', DEBUG_DEVELOPER);
        }

        $PAGE->set_context(\context_system::instance());
        require_login(null, false);

        $PAGE->set_pagelayout(!empty($options['pagelayout']) ? $options['pagelayout'] : 'admin');

        $adminroot = admin_get_root(false, false); // Settings not required for external pages.
        $extpage = $adminroot->locate($section, true);

        if (empty($extpage) || !($extpage instanceof \admin_externalpage) || !$extpage->check_access()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        navigation_node::require_admin_tree();

        $PAGE->set_url($actualurl ?: $extpage->url, $extraurlparams);
        if (strpos($PAGE->pagetype, 'admin-') !== 0) {
            $PAGE->set_pagetype('admin-' . $PAGE->pagetype);
        }

        if (empty($SITE->fullname) || empty($SITE->shortname)) {
            // During initial install.
            $strinstallation = get_string('installation', 'install');
            $strsettings = get_string('settings');
            $PAGE->navbar->add($strsettings);
            $PAGE->set_title($strinstallation);
            $PAGE->set_heading($strinstallation);
            $PAGE->set_cacheable(false);
            return;
        }

        // Locate the current item on the navigation and make it active when found.
        $path = $extpage->path;
        $node = $PAGE->settingsnav;
        while ($node && count($path) > 0) {
            $node = $node->get(array_pop($path));
        }
        if ($node) {
            $node->make_active();
        }

        // In Workplace we set the title and heading as the name of the page and add a body class.
        $pagename = reset($extpage->visiblepath);
        $PAGE->set_title($pagename . ' - ' . $SITE->fullname);
        $PAGE->set_heading($pagename);
        $PAGE->add_body_class('workplace-admin-page');

        // Prevent caching in nav block.
        $PAGE->navigation->clear_cache();
    }

    /**
     * Adds a subpage to the page title, heading, and the breadcrumbs
     *
     * Can only be called after setup_page()
     *
     * @param string $pagename name to be added to title, set as heading and add to breadcrumb (not formatted string)
     * @param moodle_url $url optional url to link in the breadcrumb
     * @return void
     */
    public static function setup_subpage(string $pagename, ?moodle_url $url = null) {
        global $PAGE;
        $PAGE->set_title($pagename . ' - ' . $PAGE->title);
        $PAGE->set_heading($pagename);
        $PAGE->navbar->add(format_string($pagename), $url);
        $PAGE->set_primary_active_tab('siteadminnode');
    }
}
