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
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_workplace
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Workplace team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_workplace\output;

use theme_workplace\api;
use tool_reportbuilder\permission;

defined('MOODLE_INTERNAL') || die;

/**
 * Theme renderer
 *
 * @package    theme_workplace
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Workplace team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Wrapper for header elements.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        global $PAGE, $COURSE;

        if (isset($PAGE->layout_options['noheader'])) {
            $header = new \stdClass();
            $header->pageheadingbutton = $this->page_heading_button();
            return $this->render_from_template('theme_workplace/headerbtn', $header);
        }

        $header = new \stdClass();
        $header->settingsmenu = $this->context_header_settings_menu();
        $header->contextheader = $this->context_header();
        $header->hasnavbar = empty($PAGE->layout_options['nonavbar']);
        $header->navbar = $this->navbar();
        $header->pageheadingbutton = $this->page_heading_button();
        $header->courseheader = $this->course_header();

        $context = $PAGE->context;
        if ($COURSE->id > 1 ) {
            $exporter = new \core_course\external\course_summary_exporter($COURSE, ['context' => $context]);
            $courseinfo = $exporter->export($this);
            $header->image = $this->render_from_template('theme_workplace/course_header_image', $courseinfo);
        }
        return $this->render_from_template('theme_workplace/header', $header);
    }

    /**
     * Whether a user is logged in.
     *
     * @return bool
     */
    public function is_logged_in() {
        return isloggedin();
    }


    /**
     * Whether a user is logged in.
     *
     * @return bool
     */
    public function is_dashboard() {
        global $PAGE;
        return ($PAGE->pagelayout == 'mydashboard' && $PAGE->url->out_as_local_url(false, []) === '/my/index.php');
    }

    /**
     * Create the workplace usermenu
     * @param  Object $user  User Object
     * @param  bool $withlinks Show links
     * @return String  HTML for user menu
     */
    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG, $PAGE;
        require_once($CFG->dirroot . '/user/lib.php');

        if (!$user) {
            $user = $USER;
        }

        $template = new \stdClass();

        // If during initial install, return the empty return string.
        if (during_initial_install()) {
            return "";
        }

        $template->loginurl = get_login_url();
        // If not logged in, show the typical not-logged-in string.
        if (isloggedin()) {
            $template->loggedin = true;
        } else {
            $template->loggedin = false;
        }

        // If logged in as a guest user, show a string to that effect.
        if (isguestuser()) {
            $template->guest = true;
        }

        // Get some navigation opts.
        if (isloggedin() && !isguestuser()) {
            $opts = user_get_user_navigation_info($user, $PAGE, ['avatarsize' => '50']);

            $template->email = $user->email;

            $template->avatar = $opts->metadata['useravatar'];
            $template->username = $opts->metadata['userfullname'];

            // Other user.
            if (!empty($opts->metadata['asotheruser'])) {
                $template->loggedinas = true;
                $template->realuseravatar = $opts->metadata['realuseravatar'];
                $template->realusername = $opts->metadata['realuserfullname'];
                $template->userfullname = $opts->metadata['userfullname'];
            }

            // Role.
            if (!empty($opts->metadata['asotherrole'])) {
                $template->otherrole = true;
                $template->rolename = $opts->metadata['rolename'];
            }

            // User login failures.
            if (!empty($opts->metadata['userloginfail'])) {
                $template->loginfail = true;
                $template->loginfailcontent = $opts->metadata['userloginfail'];
            }

            // MNet.
            if (!empty($opts->metadata['asmnetuser'])) {
                $template->mnetuser = true;
                $template->mnetusercontents = $opts->metadata['mnetidprovidername'];
            }

            $template->courses = [];
            $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
            if ($courses) {
                foreach ($courses as $course) {
                    \context_helper::preload_from_record($course);
                    $context = \context_course::instance($course->id);
                    $exporter = new \core_course\external\course_summary_exporter($course, ['context' => $context]);
                    $template->courses[] = $exporter->export($this);
                }
            }

            $template->navitems = [];

            $lastitem = '';
            foreach ($opts->navitems as $key => $value) {
                $navitem = new \stdClass();

                if ($value->pix == 'i/dashboard' || $value->pix == 't/message') {
                    continue;
                }
                if ($value->pix == 't/preferences') {
                    $value->pix = 't/preferences';
                }
                switch ($value->itemtype) {
                    case 'divider':
                        break;

                    case 'invalid':
                        break;

                    case 'link':
                        $navitem->icon = $this->image_url($value->pix);

                        $navitem->iconraw = $value->pix;

                        $navitem->url = $value->url;

                        $navitem->title = $value->title;

                        if ($value->pix == 'a/logout') {
                            $lastitem = $navitem;
                        } else {
                            $template->navitems[] = $navitem;
                        }
                        break;
                }
            }
            $template->navitems[] = $lastitem;
        }

        return $this->render_from_template('theme_workplace/usermenu', $template);
    }

    /**
     * Prepares a quick link for the workplace menu
     *
     * @param string $section name of the section in the admin tree
     * @param string $nameidentifier string identifier for the quick link name
     *     (if empty or not exist, the name of the setting will be used)
     * @param string $namecomponent component for the quick link name string
     * @return array|null null if the current user is not allowed to view the item
     *     otherwise array ['name' => <display name>, 'url' => <link>]
     */
    protected function get_workplace_quick_link(string $section,
                                                string $nameidentifier = '', string $namecomponent = '') :? array {
        $adminroot = admin_get_root(false, false);
        $setting = $adminroot->locate($section);
        if (!$setting) {
            return null;
        } else if ($setting instanceof \admin_settingpage && $setting->check_access()) {
            $url = new \moodle_url('/admin/settings.php', array('section' => $section));
            $name = $setting->visiblename;
        } else if ($setting instanceof \admin_externalpage && $setting->check_access()) {
            $url = new \moodle_url($setting->url);
            $name = $setting->visiblename;
        } else if ($setting instanceof \admin_category && $setting->check_access()) {
            $url = new \moodle_url('/admin/category.php', array('category' => $section));
            $name = $setting->visiblename;
        } else {
            return null;
        }
        if ($nameidentifier &&
                get_string_manager()->string_exists($nameidentifier, $namecomponent)) {
            $name = get_string($nameidentifier, $namecomponent);
        }
        return ['url' => $url->out(false), 'name' => $name];
    }

    /**
     * Create the workplace menu template
     * @return string HTML containing the workplace menu.
     */
    public function workplace_menu() {
        global $CFG;
        require_once($CFG->libdir.'/adminlib.php');
        if (!isloggedin() || isguestuser() || is_major_upgrade_required()) {
            return '';
        }
        $template = new \stdClass();

        $template->linkcount = 0;

        $template->courses = $this->get_workplace_quick_link('coursemgmt',
            'courses', '');
        if (!$template->courses &&
                \core_course_category::has_capability_on_any(['moodle/category:manage', 'moodle/course:create'])) {
            // User can not manage courses on system level but can manage them somewhere, still display the link.
            $url = new \moodle_url('/course/management.php');
            $name = get_string('courses');
            $template->courses = ['url' => $url->out(false), 'name' => $name];
        }

        $template->programs = $this->get_workplace_quick_link('programs');

        $template->certifications = $this->get_workplace_quick_link('certifications');

        $template->certificates = $this->get_workplace_quick_link('tool_certificate/managetemplates',
            'certificates', 'tool_certificate');

        $template->organisation = $this->get_workplace_quick_link('tool_organisation_structure');

        $template->users = $this->get_workplace_quick_link('editusers', 'users') ?:
            $this->get_workplace_quick_link('tool_tenant_users', 'users');

        $template->tenants = $this->get_workplace_quick_link('tool_tenant',
            'tenants', 'tool_tenant') ?:
            $this->get_workplace_quick_link('tool_tenant_theme',
                'managethemewpmenu', 'tool_tenant');

        $identifier = component_class_callback(permission::class, 'can_create', [true]) ? 'pluginname' : 'customreports';
        $template->reportbuilder = $this->get_workplace_quick_link('tool_reportbuilder',
            $identifier, 'tool_reportbuilder');

        $template->dynamicrules = $this->get_workplace_quick_link('tool_dynamicrule');

        $links = ['courses', 'programs', 'certifications', 'organisation', 'users', 'tenants', 'reportbuilder', 'dynamicrules'];

        $numlinks = 0;
        foreach ($links as $link) {
            if (isset($template->$link)) {
                $numlinks++;
            }
        }
        $template->columns = $numlinks > 3 ? 3 : $numlinks;
        $template->haslinks = $numlinks;

        return $this->render_from_template('theme_workplace/wpmenu', $template);
    }

    /**
     * Calls tool_tenant to create the tenant switch menu.
     * @return string HTML containing the tenant menu.
     */
    public function tenant_menu() {
        return component_class_callback('tool_tenant\tenancy', 'tenant_menu', [$this], '');
    }

    /**
     * Override core arrows for activity navigation only.
     *
     * @return string
     */
    public function rarrow() {
        global $PAGE;
        if ($PAGE->pagelayout == 'incourse') {
            return $this->render_from_template('theme_workplace/rarrow', new \stdClass());
        }
        return $this->page->theme->rarrow;
    }

    /**
     * Override core arrows for activity navigation only.
     *
     * @return string
     */
    public function larrow() {
        global $PAGE;
        if ($PAGE->pagelayout == 'incourse') {
            return $this->render_from_template('theme_workplace/larrow', new \stdClass());
        }
        return $this->page->theme->larrow;
    }

    /**
     * Returns the URL for the favicon.
     *
     * @since Moodle 2.5.1 2.6
     * @return string The favicon URL
     */
    public function favicon() {
        $standardicon = $this->image_url('favicon', 'theme');

        if (during_initial_install() || !class_exists('\tool_tenant\tenancy')) {
            return $standardicon;
        }

        $tenantid = \tool_tenant\tenancy::get_tenant_id();
        $manager = new \tool_tenant\manager();
        $tenantfavicon = $manager->get_favicon($tenantid);

        if (!empty($tenantfavicon)) {
            return $tenantfavicon;
        } else {
            return $standardicon;
        }
    }
}
