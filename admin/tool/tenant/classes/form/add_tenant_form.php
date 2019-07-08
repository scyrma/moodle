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
 * Class add_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2018 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\form;

use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class add_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2018 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_tenant_form extends modal_form {

    /**
     * Form definition
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'basic', get_string('basicinformation', 'tool_tenant'));

        $mform->addElement('text', 'name', get_string('name', 'tool_tenant'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('text', 'sitename', get_string('sitename', 'tool_tenant'));
        $mform->setType('sitename', PARAM_TEXT);
        $mform->addElement('text', 'idnumber', get_string('idnumber', 'tool_tenant'));
        $mform->setType('idnumber', PARAM_RAW);

        $mform->addElement('header', 'management', get_string('management', 'tool_tenant'));

        // Add a advanced select element to select a tenant admin. Currently only present users who are in the tenant.
        // Obviously this will be no-one when creating the tenant.
        if (!empty($this->_ajaxformdata['id'])) {
            $options = [
                'ajax' => 'tool_wp/form-potential-user-selector',
                'multiple' => true,
                'data-component' => 'tool_tenant',
                'data-area' => 'tenantadmin',
                'data-itemid' => empty($this->_ajaxformdata['id']) ? 0 : $this->_ajaxformdata['id'],
                'valuehtmlcallback' => [$this, 'get_admin_name'] // TODO SP-365 this gives different results then WS. Fix properly.
            ];
            $mform->addElement('autocomplete', 'tenantadmin', get_string('administrators', 'tool_tenant'), [], $options);
        }

        $mform->addElement('select', 'categoryid', get_string('category'), $this->get_available_categories());
        $mform->setType('categoryid', PARAM_INT);

        // Add the buttons just in case we ever use this form not inside a modal.
        $this->add_action_buttons();
    }

    /**
     * Returns all unassigned categories available to be associated to a tenant.
     *
     * @return array A list suitable to use in a select element.
     */
    protected function get_available_categories() : array {
        $tenantid = empty($this->_ajaxformdata['id']) ? 0 : $this->_ajaxformdata['id'];

        $categories = array_map(function($categoryobject) {
            return format_string($categoryobject->name);
        }, \core_course_category::top()->get_children());

        $manager = new \tool_tenant\manager();
        $tenants = $manager->get_tenants();
        foreach ($tenants as $tenant) {
            if (isset($categories[$tenant->get('categoryid')]) && $tenant->get('id') !== $tenantid) {
                unset($categories[$tenant->get('categoryid')]);
            }
        }
        // Add a no category option.
        $categories[0] = get_string('nocategory', 'tool_tenant');
        ksort($categories);
        return $categories;
    }

    /**
     * Check access
     */
    public function require_access() {
        return require_capability('tool/tenant:manage', \context_system::instance());
    }

    /**
     * Validation of form elements.
     *
     * @param  array $tenant The new tenant details.
     * @param  array $files Files related to the user.
     * @return array An array of errors if the validation fails.
     */
    public function validation($tenant, $files) {
        // We should check that the selected category has not been used elsewhere.
        $err = [];

        if (!\tool_tenant\manager::can_change_category($tenant['id'], $tenant['categoryid'])) {
            $err['categoryid'] = get_string('categorytaken', 'tool_tenant');
        }
        if (count($err) == 0) {
            return true;
        } else {
            return $err;
        }
    }

    /**
     * User full name
     * @param int $id
     * @return bool|string
     */
    public function get_admin_name($id) {
        global $DB;
        $fieldssql = \user_picture::fields('u');
        $user = $DB->get_record_sql("SELECT $fieldssql FROM {user} u WHERE u.id = ?", [$id]);
        return $user ? fullname($user) : false;
    }

    /**
     * Prepare the tenant record before calling set_data()
     *
     * @param \tool_tenant\tenant $tenant
     * @param array $tenantadmins A list of tenant admin IDs.
     * @return \stdClass
     */
    protected function prepare_data_for_form(\tool_tenant\tenant $tenant, array $tenantadmins) : \stdClass {
        $data = $tenant->to_record();
        $data->tenantadmin = $tenantadmins;
        return $data;
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $manager = new \tool_tenant\manager();
        $id = $data->id;
        if (!$id) {
            $tenants = $manager->get_tenants();
            $last = end($tenants);
            $data->sortorder = $last ? ($last->get('sortorder') + 1) : 0;

            unset($data->tenantadmin);
            $tenant = $manager->create_tenant($data);
            $tenant->save();
        } else {
            // Check for a change in category.
            $oldtenant = $manager->get_tenant($id);
            if ($oldtenant->get('categoryid') != $data->categoryid) {
                $manager->change_tenant_category($id, $data->categoryid);
            }
            // Change admin.
            $data->tenantadmin = !empty($data->tenantadmin) ? $data->tenantadmin : [];
            $manager->assign_tenant_admin_role($id, $data->tenantadmin, $data->categoryid);
            unset($data->tenantadmin);
            // Update other tenant information.
            $manager->update_tenant($id, $data);
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_modal() {
        $data = (object)$this->_ajaxformdata;
        if (!empty($data->id)) {
            $manager = new \tool_tenant\manager();
            $tenant = $manager->get_tenant($data->id);
            // Get the tenant admins.
            $tenantadmins = \tool_tenant\tenancy::get_tenant_admins($data->id);
            $this->set_data($this->prepare_data_for_form($tenant, $tenantadmins));
        }
    }
}
