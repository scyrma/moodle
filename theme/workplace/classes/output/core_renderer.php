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

namespace theme_workplace\output;

use core_collator;
use html_writer;
use stdClass;
use moodle_url;
use context_course;
use context_system;
use core_component;
use core_auth\output\login;
use tool_tenant\permission as tenant_permission;
use tool_tenant\manager as tenant_manager;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Theme renderer
 *
 * @package    theme_workplace
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Workplace team
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /** @var object workplacemenudata  */
    private $workplacemenudata = null;

    /**
     * Whether a user is logged in.
     *
     * @return bool
     */
    public function is_logged_in() {
        return isloggedin();
    }

    /**
     * Get workplace menu items
     *
     * @return stdClass
     */
    private function get_workplace_menu_data(): stdClass {
        if (!$this->workplacemenudata) {
            $allitems = [];
            // Look for plugins adding workplace menu items.
            $pluginswithcallback = get_plugins_with_function('theme_workplace_menu_items');
            foreach ($pluginswithcallback as $plugintype => $plugincallbacks) {
                foreach ($plugincallbacks as $callback) {
                    // Add menu items returned by the plugin callback.
                    $allitems = array_merge($allitems, $callback());
                }
            }

            // Separate standard items and global items (global items are displayed in a different section).
            $globalitems = array_values(array_filter($allitems, static function(array $item) {
                return isset($item['name'], $item['isglobal']) && $item['isglobal'];
            }));
            $items = array_values(array_filter($allitems, static function(array $item) {
                return isset($item['name']) && (empty($item['isglobal']));
            }));

            // Reorder all items alphabetically by name.
            core_collator::asort_array_of_arrays_by_key($globalitems, 'name');
            core_collator::asort_array_of_arrays_by_key($items, 'name');

            $this->workplacemenudata = (object) [
                'hasitems' => count($items) > 0,
                'items' => array_values($items),
                'hasglobalitems' => count($globalitems) > 0,
                'globalitems' => array_values($globalitems),
            ];
        }
        return $this->workplacemenudata;
    }

    /**
     * Create the workplace menu button.
     *
     * @return string HTML containing the workplace menu.
     */
    public function workplace_menu() {
        $data = $this->get_workplace_menu_data();
        // Empty return if there are no items.
        if (!$data->hasitems && !$data->hasglobalitems) {
            return '';
        }
        return $this->render_from_template('theme_workplace/local/wpmenu/dropdown', $data);
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
     * Returns the URL for the favicon.
     *
     * @since Moodle 2.5.1 2.6
     * @return moodle_url The favicon URL
     */
    public function favicon() {
        $standardicon = $this->image_url('favicon', 'theme');

        if (during_initial_install() || !class_exists('\tool_tenant\tenancy')) {
            return $standardicon;
        }

        $tenantid = tenancy::get_tenant_id();
        $tenantid = sharedspace::is_shared_space($tenantid) ?
            tenancy::get_default_tenant_id() : $tenantid;
        $manager = new tenant_manager();
        $tenantfavicon = $manager->get_favicon($tenantid);

        // Return tenant icon if it exists, otherwise the standard theme icon.
        return $tenantfavicon ?: $standardicon;
    }

    /**
     * Renders the login form.
     *
     * @param login $form The renderable.
     * @return string
     */
    public function render_login(login $form) {
        global $CFG, $SITE;

        $context = $form->export_for_template($this);
        // Prevent sign-up if the user limit is reached.
        if (class_exists('tool_tenant\permission') && !empty($context->cansignup)) {
            $context->cansignup = tenant_permission::check_quotas_to_add_users(0, 1);
        }

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);
        $context->logourl = $this->get_logo_url();
        $context->sitename = format_string($SITE->fullname, true,
                ['context' => context_course::instance(SITEID), "escape" => false]);

        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Rendering custom footer for current tenant
     *
     * @return string
     */
    public function custom_footer() {
        return format_text(component_class_callback('\tool_tenant\manager', 'get_footer_text', []),
            FORMAT_HTML, array('context' => context_system::instance()));
    }

    /**
     * Renders custom header
     *
     * @param string $title
     * @param moodle_url $closeurl
     * @param bool $showeditswitch
     * @return string
     */
    public function render_custom_navbar(string $title, moodle_url $closeurl, bool $showeditswitch = false): string {
        $closebutton = html_writer::link($closeurl, get_string('close', 'core_form'), [
            'class' => 'btn btn-secondary align-self-center',
            'role' => 'button'
        ]);
        $context = [
            'title' => $title,
            'closebutton' => $closebutton,
            'showeditswitch' => $showeditswitch,
            'editswitch' => $this->edit_switch()
        ];
        return $this->render_from_template('theme_workplace/custom_navbar', $context);
    }

    /**
     * Overrides {@see renderer_base::get_logo_url()} to serve tenant logo.
     *
     * @param int $maxwidth
     * @param int $maxheight
     * @return moodle_url|false
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        if (!class_exists(tenant_manager::class)) {
            return parent::get_logo_url($maxwidth, $maxheight);
        }
        $tenantmanager = new tenant_manager();
        $url = $tenantmanager->get_tenant_file_url(tenancy::get_tenant_id(), 'loginlogo');
        return $url ?? false;
    }

    /**
     * Overrides {@see renderer_base::get_compact_logo_url()} to serve tenant compact logo.
     *
     * @param int $maxwidth
     * @param int $maxheight
     * @return moodle_url|false
     */
    public function get_compact_logo_url($maxwidth = 300, $maxheight = 300) {
        if (!class_exists(tenant_manager::class)) {
            return parent::get_compact_logo_url($maxwidth, $maxheight);
        }
        $tenantmanager = new tenant_manager();
        $url = $tenantmanager->get_tenant_file_url(tenancy::get_tenant_id(), 'headerlogo');
        return $url ?? false;
    }

    /**
     * Renders the context header for the page.
     *
     * @param array $headerinfo Heading information.
     * @param int $headinglevel What 'h' level to make the heading.
     * @return string A rendered context header.
     */
    public function context_header($headerinfo = null, $headinglevel = 1): string {
        $contextheader = component_class_callback('\tool_catalogue\manager', 'get_context_header', []);
        if (isset($contextheader)) {
            return $this->render_context_header($contextheader);
        }
        return parent::context_header($headerinfo, $headinglevel);
    }

}
