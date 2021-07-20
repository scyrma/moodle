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
 * tool_tenant data generator.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core\oauth2\endpoint;
use core\oauth2\issuer;

defined('MOODLE_INTERNAL') || die();

/**
 * tool_tenant data generator class.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_generator extends component_generator_base {

    /**
     * Number of instances created
     * @var int
     */
    protected $instancecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->instancecount = 0;
    }

    /**
     * Creates new tenant
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    public function create_tenant($record = null) : \stdClass {
        \tool_tenant\tenancy::get_tenants();
        $record = $record ? (array)$record : [];
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New tenant ' . (++$this->instancecount);
        }
        return (new \tool_tenant\manager())->create_tenant((object)$record)->to_record();
    }

    /**
     * Creates a new child tenant with given parent.
     *
     * @param array|stdClass $record Specifically $record['parentid'] to create a
     * child tenant with the provided parent.
     * @return stdClass Child tenant
     */
    public function create_tenant_with_parent($record = null ): \stdClass {
        \tool_tenant\tenancy::get_tenants();
        $record = $record ? (array)$record : [];
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New hierarchy tenant ' . (++$this->instancecount);
        }
        $parentid = $record['parentid'] ?? null; // Create a new child tenant with the parent,if provided.
        $manager = (new \tool_tenant\manager());
        $tenant = $manager->create_tenant((object)$record);
        if ($parentid) {
            $parent = new \tool_tenant\tenant($parentid);
            $tenant->set('parentid', $parentid);
            $tenant->set('path', $parent->get('path').'/'.$tenant->get('id'));
            $tenant->set('depth', $parent->get('depth') + 1);
            $tenant->save();
            $manager->change_sortorder($tenant->get('id'));
        }
        return $tenant->to_record();
    }
    /**
     * Allocates a user to a tenant
     *
     * @param int $userid
     * @param int $tenantid
     */
    public function allocate_user(int $userid, int $tenantid) {
        (new \tool_tenant\manager())->allocate_user($userid, $tenantid, 'tool_tenant', 'testing');
    }

    /**
     * Create a user and allocates to the given tenant
     *
     * @param array|stdClass $userrecord similar to standard generator create_user but also accepts tenantid
     * @return stdClass
     */
    public function create_user($userrecord = []) {
        global $CFG;
        $userrecord = $this->generate_username($userrecord);
        $tenantid = !empty($userrecord['tenantid']) ? $userrecord['tenantid'] : \tool_tenant\tenancy::get_tenant_id();
        unset($userrecord['tenantid']);
        $istenantadmin = !empty($userrecord['tenantadmin']);
        unset($userrecord['tenantadmin']);

        if ($tenantid != \tool_tenant\tenancy::get_default_tenant_id()) {
            \tool_tenant\manager::preallocate_new_user((object)$userrecord, $tenantid, 'tool_tenant', 'unittest');
        }
        $user = $this->datagenerator->create_user($userrecord);
        if ($istenantadmin) {
            (new \tool_tenant\manager())->assign_tenant_admin_roles([$user->id], $tenantid);
        }
        return $user;
    }

    /**
     * Create a tenant with several users already allocated to it
     *
     * @param int $userscount
     * @param array|stdClass $basetenantrecord
     * @param array|stdClass $baseuserrecord
     * @return array [$tenant, $users] where users is a simple array of stdClass user objects
     */
    public function create_tenant_and_users(int $userscount, $basetenantrecord = null, $baseuserrecord = null) {
        $tenant = $this->create_tenant($basetenantrecord);
        $users = [];
        for ($i = 0; $i < $userscount; $i++) {
            $user = $this->create_user(['tenantid' => $tenant->id] + ($baseuserrecord ? (array)$baseuserrecord : []));
            $users[] = $user;
        }
        return [$tenant, $users];
    }

    /**
     * Generate unique username for the new user record
     *
     * @param array|stdClass $baseuserrecord
     * @return array
     */
    protected function generate_username($baseuserrecord): array {
        global $DB, $CFG;
        static $usercounter = 0;
        $record = (array)(object)$baseuserrecord;
        if (isset($record['username'])) {
            return $record;
        }
        $record['username'] = 'tuser'.$usercounter;
        $j = 2;
        if (!isset($record['mnethostid'])) {
            $record['mnethostid'] = $CFG->mnet_localhost_id;
        }
        while ($DB->record_exists('user', ['username' => $record['username'], 'mnethostid' => $record['mnethostid']])) {
            $record['username'] = 'tuser' . $usercounter . '_' . $j;
            $j++;
        }
        $usercounter++;
        return $record;
    }

    /**
     * Create a mock oauth2 issuer, mostly used in behat
     *
     * @param array $params
     * @param string|null $wwwroot site wwwroot if diferent from $CFG->wwwroot (to be used in behat)
     * @return issuer
     */
    public function create_test_oauth2_issuer(array $params = [], ?string $wwwroot = null): issuer {
        global $DB;
        $params += [
            'name' => 'Moodle test oauth',
            'showonloginpage' => 1,
            'image' => '',
            'clientid' => 1,
            'clientsecret' => 1,
            'loginscopes' => 'openid profile email',
            'loginscopesoffline' => 'openid profile email',
            'requireconfirmation' => 1,

        ];
        $record = (object)(array_intersect_key($params, issuer::properties_definition()));
        $issuer = new issuer(0, $record);
        $issuer->create();

        $url = new moodle_url('/admin/tool/tenant/tests/fixtures/oauthlogin.php');
        foreach (['authorization', 'userinfo', 'token'] as $endpoint) {
            $url->param('endpoint', $endpoint);
            $record = (object) [
                'issuerid' => $issuer->get('id'),
                'name' => $endpoint . '_endpoint',
                'url' => 'https://example.com', // Will be changed below.
            ];
            $endpoint = new endpoint(0, $record);
            $endpoint->create();
            // We have to modify the url manually because persistent validation fails on test local URLs.
            $endpointurl = $wwwroot ? $wwwroot . $url->out_as_local_url(false) : $url->out(false);
            $DB->update_record(endpoint::TABLE, ['id' => $endpoint->get('id'), 'url' => $endpointurl]);
        }

        $record = (object)[
            'issuerid' => $issuer->get('id'),
            'externalfield' => 'email',
            'internalfield' => 'email',
        ];
        $mapping = new \core\oauth2\user_field_mapping(0, $record);
        $mapping->create();

        return $issuer;
    }
}
