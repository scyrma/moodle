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
 * Class view_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenant;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class view_tenant_form - read-only tenant details displayed to tenant administrator
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class view_tenant_form extends modal_form {

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
    public function require_access() {
        return permission::can_view_tenant_details($this->get_tenant()->get('id'));
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     */
    public function process(\stdClass $data) {
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_modal() {
        $tenant = $this->get_tenant();
        $this->set_data($tenant->to_record());
    }
}
