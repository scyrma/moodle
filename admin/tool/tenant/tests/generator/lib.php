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
 * tool_tenant data generator.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tool_tenant data generator class.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        if ($tenantid != \tool_tenant\tenancy::get_default_tenant_id()) {
            \tool_tenant\manager::preallocate_new_user((object)$userrecord, $tenantid, 'tool_tenant', 'unittest');
        }
        $user = $this->datagenerator->create_user($userrecord);
        // TODO WP-1638 remove after MDL-68333 - start.
        if ($CFG->branch === '38') {
            $hasprofilefields = array_filter($userrecord, function($key){
                return strpos($key, 'profile_field_') === 0;
            }, ARRAY_FILTER_USE_KEY);
            if ($hasprofilefields) {
                require_once($CFG->dirroot.'/user/profile/lib.php');
                $usernew = (object)(['id' => $user->id] + $userrecord);
                profile_save_data($usernew);
            }
            \core\event\user_created::create_from_userid($user->id)->trigger();
        }
        // WP-1638 remove end.
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
}
