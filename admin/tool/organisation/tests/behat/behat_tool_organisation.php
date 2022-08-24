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
 * tool_organisation steps definitions.
 *
 * @package    tool_organisation
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_organisation.
 *
 * @package    tool_organisation
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_organisation extends behat_base {

    /**
     * Returns the organisation generator
     * @return tool_organisation_generator
     */
    protected function get_generator() : tool_organisation_generator {
        $datagenerator = testing_util::get_data_generator();
        return $datagenerator->get_plugin_generator('tool_organisation');
    }

    /**
     * Generates departments
     *
     * @Given /^the following departments exist in organisation structure:$/
     *
     * @param TableNode $data
     */
    public function the_following_departments_exist_in_organisation_structure(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $data = (object)((array)$elementdata + ['parent' => '', 'tenant' => '']);
            $data->parentid = $generator->lookup_department($data->parent);
            $data->tenantid = $this->get_tenant_id($data->tenant);
            unset($data->parent, $data->tenant);
            $generator->create_department($data);
        }
    }

    /**
     * Gets the user id from it's username.
     * @throws Exception
     * @param string $username
     * @return int
     */
    protected function get_user_id($username) {
        global $DB;

        if (!$id = $DB->get_field('user', 'id', array('username' => $username))) {
            throw new Exception('The specified user with username "' . $username . '" does not exist');
        }
        return $id;
    }

    /**
     * Gets the tenant id from it's name.
     * @throws Exception
     * @param string $tenantname
     * @return int
     */
    protected function get_tenant_id($tenantname) {
        global $DB;
        if (empty($tenantname)) {
            return \tool_tenant\tenancy::get_default_tenant_id();
        }

        if (!$id = $DB->get_field('tool_tenant', 'id', array('name' => $tenantname))) {
            throw new Exception('The specified tenant with name "' . $tenantname . '" does not exist');
        }
        return $id;
    }

    /**
     * Generates positions
     *
     * @Given /^the following positions exist in organisation structure:$/
     *
     * @param TableNode $data
     */
    public function the_following_positions_exist_in_organisation_structure(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $data = (object)((array)$elementdata + ['parent' => '', 'tenant' => '']);
            $data->parentid = $generator->lookup_position($data->parent);
            $data->tenantid = $this->get_tenant_id($data->tenant);
            unset($data->parent, $data->tenant);
            $generator->create_position($data);
        }
    }

    /**
     * Generates positions
     *
     * @Given /^the following job assignments exist in organisation structure:$/
     *
     * @param TableNode $data
     */
    public function the_following_job_assignments_exist_in_organisation_structure(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $data = (object)((array)$elementdata);
            $data->userid = $this->get_user_id($data->user);
            $data->positionid = $generator->lookup_position($data->position);
            $data->departmentid = $generator->lookup_department($data->department);
            unset($data->position, $data->user, $data->department);
            foreach (['startdate', 'enddate'] as $key) {
                if (isset($data->$key) && trim($data->$key) !== '') {
                    $data->$key = strtotime($data->$key);
                } else {
                    unset($data->$key);
                }
            }
            $generator->assign_job($data);
        }
    }

    /**
     * Get permission status xpath.
     *
     * @param string $status
     * @param string $managerclass
     * @param string $permname
     * @return string
     * @throws Exception
     */
    private function get_permission_status_xpath($status, $managerclass, $permname) {
        $containerclass = "tool-ogranisation-perm-icon";
        $xpath = "//*[contains(@class,'$managerclass')]";
        if ($status === "enabled") {
            $xpath .= "//*[contains(@class,'$containerclass') and not(contains(@class,'noset')) ".
                " and contains(@title,'With permission') and contains(@title,'$permname')]";
        } else if ($status === "disabled") {
            $xpath .= "//*[contains(@class,'$containerclass') and contains(@class,'noset') ".
                " and contains(@title,'Without permission') and contains(@title,'$permname')]";
        } else {
            throw new Exception('Unknown permission status: ' . $status);
        }
        return $xpath;
    }

    /**
     * Check that the permission icon is present and enabled/disabled in the specified row of a table tree
     *
     * phpcs:ignore
     * @Then /^"(?P<permission_string>(?:[^"]|\\")*)" permission should be "(?P<status_string>(?:[^"]|\\")*)" in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
     *
     * @param string $permission
     * @param string $status
     * @param string $treenode
     * @throws Exception
     */
    public function permission_should_be_in_the_table_tree_node($permission, $status, $treenode) {
        global $CFG;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/organisation/lib.php');

        $elsglob = array_filter(tool_organisation\organisation::get_global_manager_permissions(), function($el) use ($permission) {
            return $el['name'] === $permission;
        });
        $elsdep = array_filter(tool_organisation\organisation::get_department_manager_permissions(),
            function($el) use ($permission) {
                return $el['name'] === $permission;
            });
        if ($elsglob) {
            $managerclass = 'perm-global-manager';
            $el = reset($elsglob);
        } else if ($elsdep) {
            $managerclass = 'perm-department-manager';
            $el = reset($elsdep);
        } else {
            throw new Exception('Unknown permission: ' . $permission);
        }
        $xpath = $this->get_permission_status_xpath($status, $managerclass, $el['title']);

        $this->execute('behat_tool_wp::should_exist_in_the_table_tree_node', [$xpath, 'xpath_element', $treenode]);
    }

    /**
     * Check that the permission icon is present and enabled/disabled in the specified row.
     *
     * phpcs:ignore
     * @Then /^"(?P<permission_string>(?:[^"]|\\")*)" permission should be "(?P<status_string>(?:[^"]|\\")*)" in the "(?P<el_string>(?:[^"]|\\")*)" "(?P<sel_string>[^"]*)"$/

     * @param string $permission The name of permission e.g. "organisation:allocateuserstoprogramcertificationsdept".
     * @param string $status permission status enabled|disabled
     * @param string $containerelement element identifier to be searched within
     * @param string $containerselectortype selector type of element
     * @throws Exception
     */
    public function permission_should_be_in_the($permission, $status, $containerelement, $containerselectortype) {
        global $CFG;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/organisation/lib.php');

        $elsglob = array_filter(tool_organisation\organisation::get_global_manager_permissions(), function($el) use ($permission) {
            return $el['name'] === $permission;
        });
        $elsdep = array_filter(tool_organisation\organisation::get_department_manager_permissions(),
            function($el) use ($permission) {
                return $el['name'] === $permission;
            });
        if ($elsglob) {
            $managerclass = 'perm-global-manager';
            $el = reset($elsglob);
        } else if ($elsdep) {
            $managerclass = 'perm-department-manager';
            $el = reset($elsdep);
        } else {
            throw new Exception('Unknown permission: ' . $permission);
        }
        $xpath = $this->get_permission_status_xpath($status, $managerclass, $el['title']);
        $this->execute('behat_general::should_exist_in_the', [$xpath, 'xpath_element', $containerelement,
            $containerselectortype]);
    }

    /**
     * Create a simple org structure that makes one user manager over others
     * phpcs:ignore
     * @Given /^user "(?P<manager_string>(?:[^"]|\\")*)" has a manager position over users "(?P<users_string>(?:[^"]|\\")*)" with permissions "(?P<courses_number>\d+)"$/
     * @param string $manager
     * @param string $users
     * @param int $permission
     * @throws Exception
     */
    public function user_has_a_manager_position_over_users_with_permissions($manager, $users, $permission) {
        $managerid = $this->get_user_id($manager);
        $tenantid = \tool_tenant\tenancy::get_tenant_id($managerid);
        $userids = array_map([$this, 'get_user_id'], preg_split('/\s*,\s*/', trim($users), -1, PREG_SPLIT_NO_EMPTY));
        $generator = $this->get_generator();
        $depframework = $generator->create_department(['tenantid' => $tenantid]);
        $dep = $generator->create_department(['parentid' => $depframework->id]);
        $posframework = $generator->create_position(['tenantid' => $tenantid]);
        $pos1 = $generator->create_position(['parentid' => $posframework->id, 'globalmanager' => 1,
            'globalpermissions' => $permission]);
        $pos2 = $generator->create_position(['parentid' => $pos1->id]);
        $generator->assign_job(['userid' => $managerid, 'departmentid' => $dep->id, 'positionid' => $pos1->id]);
        foreach ($userids as $userid) {
            $generator->assign_job(['userid' => $userid, 'departmentid' => $dep->id, 'positionid' => $pos2->id]);
        }
    }

    /**
     * Create a simple org structure that makes one user manager over others
     * phpcs:ignore
     * @Given /^user "(?P<manager_string>(?:[^"]|\\")*)" has a department lead position over users "(?P<users_string>(?:[^"]|\\")*)" with permissions "(?P<courses_number>\d+)"$/
     * @param string $manager
     * @param string $users
     * @param int $permission
     * @throws Exception
     */
    public function user_has_a_department_lead_position_over_users_with_permissions($manager, $users, $permission) {
        $managerid = $this->get_user_id($manager);
        $tenantid = \tool_tenant\tenancy::get_tenant_id($managerid);
        $userids = array_map([$this, 'get_user_id'], preg_split('/\s*,\s*/', trim($users), -1, PREG_SPLIT_NO_EMPTY));
        $generator = $this->get_generator();
        $depframework = $generator->create_department(['tenantid' => $tenantid]);
        $dep1 = $generator->create_department(['parentid' => $depframework->id]);
        $posframework = $generator->create_position(['tenantid' => $tenantid]);
        $pos1 = $generator->create_position(['parentid' => $posframework->id, 'departmentmanager' => 1,
            'departmentpermissions' => $permission]);
        $pos2 = $generator->create_position(['parentid' => $posframework->id]);
        $generator->assign_job(['userid' => $managerid, 'departmentid' => $dep1->id, 'positionid' => $pos1->id]);
        foreach ($userids as $userid) {
            $generator->assign_job(['userid' => $userid, 'departmentid' => $dep1->id, 'positionid' => $pos2->id]);
        }
    }

}
