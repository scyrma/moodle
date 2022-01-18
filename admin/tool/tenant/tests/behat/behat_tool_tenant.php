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
 * tool_tenant steps definitions.
 *
 * @package    tool_tenant
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_tenant.
 *
 * @package    tool_tenant
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_tenant extends behat_base {

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     */
    protected function get_generator() : tool_tenant_generator {
        $datagenerator = testing_util::get_data_generator();
        return $datagenerator->get_plugin_generator('tool_tenant');
    }

    /**
     * Generates tenants with given names
     *
     * This method is deprecated. Instead use:
     * Given the following "tool_tenant > tenants" exist:
     *
     * @Given /^the following tenants exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_tenants_exist(TableNode $data) {
        $generator = $this->get_generator();

        // Every test that uses this step will use the same setup as the default workplace installation.
        \tool_tenant\manager::change_core_roles();

        foreach ($data->getHash() as $elementdata) {
            $tenant = (object)array_diff_key($elementdata, ['category' => 1]);
            if (!empty($elementdata['category'])) {
                $tenant->categoryid = $this->get_category_id($elementdata['category']);
            }
            if (!empty($elementdata['idnumber'])) {
                $tenant->idnumber = $elementdata['idnumber'];
            }
            $generator->create_tenant($tenant);
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

        if (!$id = $DB->get_field('tool_tenant', 'id', array('name' => $tenantname))) {
            throw new Exception('The specified tenant with name "' . $tenantname . '" does not exist');
        }
        return $id;
    }

    /**
     * Get the course category id from an identifier (either idnumber or name)
     *
     * @param string $identifier
     * @return int
     * @throws Exception For non-matching identifier
     */
    protected function get_category_id(string $identifier): int {
        if (!$id = parent::get_category_id($identifier)) {
            throw new Exception('The specified category with identifier "' . $identifier . '" does not exist');
        }
        return $id;
    }

    /**
     * Allocates users to tenants
     *
     * This method is deprecated. Instead use:
     * Given the following "tool_tenant > users" exist:
     *
     * @Given /^the following users allocations to tenants exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_user_allocations_to_tenants_exist(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $generator->allocate_user($this->get_user_id($elementdata['user']), $this->get_tenant_id($elementdata['tenant']));
        }
    }

    /**
     * Create a course from backup file with completion enabled
     *
     * @param int $categoryid
     * @param string $fullname
     * @param string $shortname
     */
    protected function create_course($categoryid, $fullname, $shortname) {
        global $CFG;

        $data = array('backupfile' => $CFG->dirroot . '/admin/tool/tenant/tests/fixtures/backup.mbz',
            'summary' => '', 'category' => $categoryid, 'fullname' => $fullname, 'shortname' => $shortname,
            'enablecompletion' => true, 'format' => 'wplist');
        $mode = tool_uploadcourse_processor::MODE_CREATE_NEW;
        $updatemode = tool_uploadcourse_processor::UPDATE_ALL_WITH_DATA_ONLY;
        $co = new tool_uploadcourse_course($mode, $updatemode, $data);
        $co->prepare();
        $co->proceed();
    }

    /**
     * Quickly create several tenants, users and courses
     *
     * phpcs:ignore moodle.Files.LineLength.TooLong
     * @Given /^"(?P<tenant_number>\d+)" tenants exist with "(?P<users_number>\d+)" users and "(?P<courses_number>\d+)" courses in each$/
     *
     * @param int $tenants
     * @param int $users
     * @param int $courses
     */
    public function tenants_exist_with_users_and_courses_in_each($tenants, $users, $courses) {
        // Create categories and tenants.
        $categories = [['name', 'category', 'idnumber']];
        $tenantsrows = [['name', 'category', 'idnumber']];
        for ($i = 1; $i <= $tenants; $i++) {
            $categories[] = ["Category{$i}", '0', "CAT{$i}"];
            $tenantsrows[] = ['Tenant' . $i, "Category{$i}", 'tenant' . $i];
        }
        $this->execute("behat_data_generators::the_following_entities_exist", ["categories", new TableNode($categories)]);
        $this->execute("behat_tool_tenant::the_following_tenants_exist", new TableNode($tenantsrows));

        // Assign admins and create courses.
        $manager = new \tool_tenant\manager();
        for ($i = 1; $i <= $tenants; $i++) {
            $tenantid = $this->get_tenant_id("Tenant{$i}");

            // Create users.
            $userrecord = ['username' => "tenantadmin{$i}", 'password' => "tenantadmin{$i}", 'firstname' => 'Tenantadmin',
                'lastname' => $i, 'email' => "tenantadmin{$i}@invalid.com", 'tenantid' => $tenantid];
            $tenantadmin = $this->get_generator()->create_user($userrecord);
            for ($j = 1; $j <= $users - 1; $j++) {
                $userrecord = ['username' => "user{$i}{$j}", 'password' => "user{$i}{$j}", 'firstname' => 'User',
                    'lastname' => $i . $j, 'email' => "user{$i}{$j}@invalid.com", 'tenantid' => $tenantid];
                $this->get_generator()->create_user($userrecord);
            }

            // Assign admins to tenant.
            $manager->assign_tenant_admin_role($tenantid, [$tenantadmin->id]);

            // Create courses.
            $categoryid = $this->get_category_id("Category{$i}");
            for ($j = 1; $j <= $courses; $j++) {
                $this->create_course($categoryid, "Course{$i}{$j}", "C{$i}{$j}");
            }
        }
    }

    /**
     * Visit homepage for a tenant
     *
     * @When /^I am on homepage for tenant "(?P<tenantname_string>.*)"$/
     *
     * @param string $tenantname
     */
    public function i_am_on_homepage_for_tenant($tenantname) {
        $tenantid = $this->get_tenant_id($tenantname);
        $url = new moodle_url('/', ['tenantid' => $tenantid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Visit homepage for a tenant
     *
     * @When /^I am on homepage for tenant "(?P<tenantidnumber_string>.*)" using idnumber$/
     *
     * @param string $tenantidnumber
     */
    public function i_am_on_homepage_for_tenant_using_idnumber($tenantidnumber) {
        $url = new moodle_url('/', ['tenant' => $tenantidnumber]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Enable shared space
     *
     * IMPORTANT! This is a temporary method and it will be removed when tenants hierarchy is implemented
     *
     * @Given /^shared space is enabled$/
     */
    public function shared_space_is_enabled() {
        \tool_tenant\sharedspace::enable_shared_space();
    }

    /**
     * Switch to tenant
     *
     * @When /^I switch to tenant "(?P<tenantname_string>.*)"$/
     *
     * @param string $tenantname
     */
    public function i_switch_to_tehant($tenantname) {

        if ($this->getSession()->getPage()->find('css', '.tenantswitch .dropdown-toggle')) {
            $this->execute('behat_general::i_click_on',
                ['.tenantswitch .dropdown-toggle', 'css_element']);
            $this->execute('behat_general::i_click_on_in_the',
                [$tenantname, 'link', '.tenantswitch .dropdown-menu.show', 'css_element']);
        } else {
            $this->execute('behat_general::i_click_on',
                ['.tenantswitch .nav-link', 'css_element']);
            $this->execute('behat_forms::i_set_the_field_in_container_to',
                ["Select tenant", "Switch tenant", "dialogue", $tenantname]);
            $this->execute('behat_general::i_click_on_in_the', ['Switch tenant', 'button',
                '.modal.show .modal-footer', 'css_element']);
        }
    }

    /**
     * Create a test oAuth2 issuer
     *
     * @Given /^the test OAuth2 issuer with the following properties exists:$/
     *
     * @param TableNode $data
     */
    public function the_test_oauth2_issuer_with_the_following_properties_exists(TableNode $data) {
        $this->get_generator()->create_test_oauth2_issuer($data->getRowsHash(),
            rtrim($this->locate_path('/'), '/'));
    }

    /**
     * Emulate clicking on confirmation link from the email
     *
     * @When /^I confirm OAuth2 email for "(?P<username>(?:[^"]|\\")*)"$/
     *
     * @param string $username
     */
    public function i_confirm_oauth2_email_for($username) {
        global $DB;
        $secret = $DB->get_field('user', 'secret', ['username' => $username], MUST_EXIST);
        $confirmationurl = new moodle_url('/auth/oauth2/confirm-account.php', ['token' => $secret, 'username' => $username]);

        $this->execute('behat_general::i_visit', [$confirmationurl->out_as_local_url(false)]);
    }

    /**
     * Emulate clicking on confirmation link from the email
     *
     * @When /^I confirm OAuth2 linked email for "(?P<username>(?:[^"]|\\")*)"$/
     *
     * @param string $username
     */
    public function i_confirm_oauth2_linked_email($username) {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $linkedlogin = \auth_oauth2\linked_login::get_record(['userid' => $userid]);
        $params = [
            'token' => $linkedlogin->get('confirmtoken'),
            'userid' => $linkedlogin->get('userid'),
            'username' => $linkedlogin->get('username'),
            'issuerid' => $linkedlogin->get('issuerid'),
        ];
        $confirmationurl = new moodle_url('/auth/oauth2/confirm-linkedlogin.php', $params);

        $this->execute('behat_general::i_visit', [$confirmationurl->out_as_local_url(false)]);
    }

    /**
     * Return the list of partial named selectors.
     *
     * Those selectors can be used to capture Tenant selector elements. Examples:
     *    And I click on "Site 4" "tool_tenant > Tenant selector card"
     *    And I click on "Change site" "tool_tenant > Tenant selector button"
     *
     * @return array
     */
    public static function get_partial_named_selectors(): array {
        return [
            new behat_component_named_selector('Tenant selector card', [
                <<<XPATH
    .//div[contains(concat(' ', normalize-space(@class), ' '), ' tenant-selector-modal ')]
    //div[contains(concat(' ', normalize-space(@class), ' '), ' tenant-selector-card ')
    and contains(., %locator%)]
XPATH
            ],
                true),
            new behat_component_named_selector('Tenant selector button', [
                <<<XPATH
    .//div[contains(concat(' ', normalize-space(@class), ' '), ' tenant-selector-modal ')]
    //button[contains(., %locator%)]
XPATH
            ],
                true),
        ];
    }

    /**
     * Configure user profile category to be only visible to specified tenants
     *
     * @Given /^profile category "(?P<category>(?:[^"]|\\")*)" is available only for tenants "(?P<tenantnames>(?:[^"]|\\")*)"$/
     * @param string $category
     * @param string $tenantnames comma-separated names of tenants
     */
    public function profile_category_is_available_only_for_tenants($category, $tenantnames) {
        global $DB;
        $cat = $DB->get_record('user_info_category', ['name' => $category], 'id');
        [$sql, $params] = $DB->get_in_or_equal(preg_split("/\s*,\s*/", $tenantnames));
        $tenants = $DB->get_fieldset_select('tool_tenant', 'id', 'name '.$sql, $params);
        \tool_tenant\profile_manager::save_category_config((object)[
            'id' => $cat->id,
            \tool_tenant\profile_manager::AVAILABILITY => \tool_tenant\profile_manager::TENANT_ONLY,
            \tool_tenant\profile_manager::ONLYTENANTS => $tenants
        ]);
    }
}
