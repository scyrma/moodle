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
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_workplace
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Workplace team
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace theme_workplace\output;

use tool_reportbuilder\permission;

defined('MOODLE_INTERNAL') || die;

/**
 * Theme renderer
 *
 * @package    theme_workplace
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Workplace team
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /** @var object workplacemenucontent  */
    private $workplacemenucontent = null;

    /**
     * Wrapper for header elements.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        global $COURSE;

        if (isset($this->page->layout_options['noheader'])) {
            $header = new \stdClass();
            $header->pageheadingbutton = $this->page_heading_button();
            return $this->render_from_template('theme_workplace/headerbtn', $header);
        }

        $header = new \stdClass();
        $header->settingsmenu = $this->context_header_settings_menu();
        $header->headeractions = $this->page->get_header_actions();
        $header->contextheader = $this->context_header();
        $header->hasnavbar = empty($this->page->layout_options['nonavbar']);
        $header->navbar = $this->navbar();
        $header->pageheadingbutton = $this->page_heading_button();
        $header->courseheader = $this->course_header();

        if ($COURSE->id > 1 ) {
            $exporter = new \core_course\external\course_summary_exporter($COURSE, ['context' => $this->page->context]);
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
     * Whether a user is currently on their Dashboard page
     *
     * @return bool
     */
    public function is_dashboard() {
        return ($this->page->pagelayout == 'mydashboard' &&
            $this->page->url->compare(new \moodle_url('/my/index.php'), URL_MATCH_BASE));
    }

    /**
     * Create the workplace usermenu
     * @param  Object $user  User Object
     * @param  bool $withlinks Show links
     * @return String  HTML for user menu
     */
    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG;
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
            $opts = user_get_user_navigation_info($user, $this->page, ['avatarsize' => '50']);

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
            $courses = course_get_recent_courses($USER->id);
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
     * @param string|null $imgidentifier string identifier for the quick link image
     *     (if empty the name of the section will be used)
     * @return array|null null if the current user is not allowed to view the item
     *     otherwise array ['name' => <display name>, 'url' => <link>, 'imgurl' => <link>]
     */
    protected function get_workplace_quick_link(string $section, string $nameidentifier = '', string $namecomponent = '',
                                                string $imgidentifier = null) :? array {

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
        $imgurl = $this->image_url('menu/' . ($imgidentifier ?? $section), 'theme')->out(false);

        return ['url' => $url->out(false), 'name' => $name, 'imgurl' => $imgurl];
    }

    /**
     * Returns content for the templates:
     *
     * - theme_workplace/wpmenu_link
     * - theme_workplace/wpmenu_dropdown
     * - theme_workplace/wpmenu_modal
     *
     * @return \stdClass
     */
    private function workplace_menu_content() {
        global $CFG;
        require_once($CFG->libdir.'/adminlib.php');
        if (!$this->workplacemenucontent) {
            $template = new \stdClass();
            $template->linkcount = 0;
            if (!isloggedin() || isguestuser() || is_major_upgrade_required()) {
                $this->workplacemenucontent = $template;
                return $this->workplacemenucontent;
            }

            $template->elements = [];
            $template->globalelements = [];

            // Basic element links.
            $template->elements[2] = $this->get_workplace_quick_link('programs');
            $template->elements[3] = $this->get_workplace_quick_link('certifications');
            $template->elements[7] = $this->get_workplace_quick_link('tool_organisation_structure', '', '',
                    'organization_structure');
            $template->elements[5] = $this->get_workplace_quick_link('tool_tenant_users',
                'users', '', 'tenant_management');
            $template->elements[6] = $this->get_workplace_quick_link('tool_tenant_theme',
                'appearance', '', 'tenant_appearance');
            $identifier = component_class_callback(permission::class, 'can_create', [true]) ? 'pluginname' : 'customreports';
            $template->elements[9] = $this->get_workplace_quick_link('tool_reportbuilder',
                $identifier, 'tool_reportbuilder', 'report_builder');
            $template->elements[8] = $this->get_workplace_quick_link('tool_dynamicrule', '', '', 'dynamic_rules');

            // Global elements links.
            $canswitchtenant = component_class_callback('\tool_tenant\permission', 'can_switch_tenant', []);
            $elementstype = $canswitchtenant ? 'globalelements' : 'elements';

            $template->{$elementstype}[10] = $this->get_workplace_quick_link('tool_tenant_allusers', '', '',  'users');
            if ($canswitchtenant) {
                $template->globalelements[11] = $this->get_workplace_quick_link('tool_tenant', 'alltenants', 'tool_tenant',
                    'tenants');
            }

            // Basic|Global element links.
            $courses = $this->get_workplace_quick_link('coursemgmt',
                'courses', '', 'courses');
            if (!$courses && \core_course_category::has_capability_on_any(['moodle/category:manage', 'moodle/course:create'])) {
                // User can not manage courses on system level but can manage them somewhere, still display the link.
                $url = new \moodle_url('/course/management.php');
                $name = get_string('courses');
                $courses = ['url' => $url->out(false), 'name' => $name,
                    'imgurl' => $this->image_url('menu/courses', 'theme')->out(false)];
            }
            $template->{$elementstype}[4] = $this->get_workplace_quick_link('tool_certificate/managetemplates',
                'certificates', 'tool_certificate', 'certificates');
            $template->{$elementstype}[1] = $courses;
            $template->{$elementstype}[12] = $this->get_workplace_quick_link('tool_wp_exportimport', 'exportimport', 'tool_wp',
                'exportimport');

            // Reorder arrays by key and remove empty values.
            ksort($template->elements);
            $template->elements = array_values(array_filter($template->elements));
            ksort($template->globalelements);
            $template->globalelements = array_values(array_filter($template->globalelements));

            $numlinks = count($template->elements);
            $numgloballinks = count($template->globalelements);
            $template->columns = $numlinks > 3 || $numgloballinks > 3 ? 3 : max($numlinks, $numgloballinks);
            $template->haslinks = $numlinks || $numgloballinks;
            $template->hasgloballinks = $numgloballinks;

            $this->workplacemenucontent = $template;
        }
        return $this->workplacemenucontent;
    }
    /**
     * Create the workplace menu link.
     * @return string HTML containing the workplace menu.
     */
    public function workplace_menu_link() {
        if (!empty($this->page->theme->settings->wpmenumodal)) {
            return $this->render_from_template('theme_workplace/wpmenu_link', $this->workplace_menu_content());
        } else {
            return $this->render_from_template('theme_workplace/wpmenu_dropdown', $this->workplace_menu_content());
        }
    }

    /**
     * Create the workplace menu modal
     * @return string HTML containing the workplace menu.
     */
    public function workplace_menu_modal() {
        if (!empty($this->page->theme->settings->wpmenumodal)) {
            return $this->render_from_template('theme_workplace/wpmenu_modal', $this->workplace_menu_content());
        }
    }

    /**
     * Calls tool_tenant to create the tenant switch menu.
     *
     * @uses \tool_tenant\tenancy::tenant_menu()
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
        if ($this->page->pagelayout == 'incourse') {
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
        if ($this->page->pagelayout == 'incourse') {
            return $this->render_from_template('theme_workplace/larrow', new \stdClass());
        }
        return $this->page->theme->larrow;
    }

    /**
     * Returns the URL for the favicon.
     *
     * @since Moodle 2.5.1 2.6
     * @return \moodle_url The favicon URL
     */
    public function favicon() {
        $standardicon = $this->image_url('favicon', 'theme');

        if (during_initial_install() || !class_exists('\tool_tenant\tenancy')) {
            return $standardicon;
        }

        $tenantid = \tool_tenant\tenancy::get_tenant_id();
        $tenantid = \tool_tenant\sharedspace::is_shared_space($tenantid) ?
            \tool_tenant\tenancy::get_default_tenant_id() : $tenantid;
        $manager = new \tool_tenant\manager();
        $tenantfavicon = $manager->get_favicon($tenantid);

        // Return tenant icon if it exists, otherwise the standard theme icon.
        return $tenantfavicon ?: $standardicon;
    }

    /**
     * Renders the login form.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        global $CFG, $SITE;

        $context = $form->export_for_template($this);
        // Prevent sign-up if the user limit is reached.
        if (class_exists('tool_tenant\permission') && !empty($context->cansignup)) {
            $context->cansignup = \tool_tenant\permission::check_quotas_to_add_users(0, 1);
        }

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl = $url;
        $context->sitename = format_string($SITE->fullname, true,
                ['context' => \context_course::instance(SITEID), "escape" => false]);

        $context->workplace_lang_menu = $this->workplace_lang_menu();

        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Create a language selection menu.
     *
     * This function is used as an alternative to custom_menu() and will never
     * include any configured custom menu itmes.
     *
     * @return string The lang menu HTML or empty string.
     */
    public function workplace_lang_menu() {

        $currlang = current_language();
        $langs = get_string_manager()->get_list_of_translations();

        if (count($langs) < 2) {
            return '';
        }

        $url = new \moodle_url('/login/index.php', ['lang' => $currlang]);
        $menu = ['haschildren' => [
            'url' => $url->out(),
            'text' => $langs[$currlang],
            'children' => []
        ]];
        foreach ($langs as $langtype => $langname) {
            $url->param('lang', $langtype);
            $menu['haschildren']['children'][] = [
                'url' => $url->out(),
                'text' => $langname
            ];
        }
        return $this->render_from_template('core/custom_menu_item', $menu);
    }
    /**
     * Rendering custom footer for current tenant
     * @return string
     */
    public function custom_footer() {
        $output = '';
        $output .= format_text(component_class_callback('\tool_tenant\manager', 'get_footer_text', []),
            FORMAT_HTML, array('context' => \context_system::instance()));
        return $output;
    }
}
