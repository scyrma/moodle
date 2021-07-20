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
 * Class view_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenant;

/**
 * Class view_tenant_form - read-only tenant details displayed to tenant administrator
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class view_tenant_form extends dynamic_form {

    /** @var tenant Do not access directly, use $this->get_tenant() */
    protected $tenant;

    /**
     * Form definition
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $tenant = $this->get_tenant();
        $notspecified = get_string('notspecified', 'tool_tenant');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if (permission::can_edit_tenant($tenant->get('id'))) {
            $mform->addElement('static', 'namestatic', get_string('name', 'tool_tenant'),
                $tenant->get_formatted_name());

            $mform->addElement('static', 'idnumberstatic', get_string('idnumber', 'tool_tenant'),
                strlen($tenant->get('idnumber')) ? s($tenant->get('idnumber')) : $notspecified);
        }

        $defaultsite = $DB->get_record('course', ['category' => 0]); // Do not use $SITE, it was already overridden.
        $sitename = strlen($tenant->get('sitename')) ? $tenant->get('sitename') : $defaultsite->fullname;
        $mform->addElement('static', 'sitenamestatic', get_string('sitename', 'tool_tenant'),
            format_string($sitename));

        $shortname = strlen($tenant->get('siteshortname')) ? $tenant->get('siteshortname') : $defaultsite->shortname;
        $mform->addElement('static', 'siteshortnamestatic', get_string('siteshortname', 'tool_tenant'),
            format_string($shortname));

        // Login URLs.
        $urls = $this->get_tenant() ? $this->get_tenant()->get_login_urls() : [];
        $mform->addElement('static', 'loginurl', get_string('loginurl', 'tool_tenant'),
            $urls ? join('<br/>', $urls) : $notspecified);

        // Tenant administrators.
        $tenantadmins = (new manager())->get_tenant_admins($tenant->get('id'));
        $admins = user_get_users_by_id($tenantadmins);
        $mform->addElement('static', 'tenantadmin', get_string('administrators', 'tool_tenant'),
            $admins ? join('<br/>', array_map('fullname', $admins)) : $notspecified);

        // Course category.
        if ($tenant->get('categoryid') && ($category = \core_course_category::get($tenant->get('categoryid'), IGNORE_MISSING))) {
            $catname = $category->get_formatted_name();
        } else {
            $catname = get_string('nocategory', 'tool_tenant');
        }
        $mform->addElement('static', 'category', get_string('category', 'tool_tenant'),
            $catname);
    }

    /**
     * Tenant being edited
     *
     * @return tenant
     */
    protected function get_tenant() : tenant {
        if ($this->tenant === null) {
            $id = $this->optional_param('tenantid', 0, PARAM_INT);
            $manager = new manager();
            $this->tenant = $manager->get_tenant($id);
        }
        return $this->tenant;
    }

    /**
     * Check access
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_view_tenant_details($this->get_tenant()->get('id'));
    }

    /**
     * Process form submission
     */
    public function process_dynamic_submission() {
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $tenant = $this->get_tenant();
        $this->set_data($tenant->to_record());
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'tenantid' => $this->get_tenant()->get('id'),
        ]);
    }
}
