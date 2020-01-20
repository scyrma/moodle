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
 * File containing tests for jobs.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The job test class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_organisation_testcase extends advanced_testcase {

    /** @var stdClass */
    protected $pf;
    /** @var stdClass */
    protected $pfother;
    /** @var stdClass */
    protected $pftenantother;
    /** @var stdClass */
    protected $pa;
    /** @var stdClass */
    protected $pb;
    /** @var stdClass */
    protected $pa1;
    /** @var stdClass */
    protected $pa2;
    /** @var stdClass */
    protected $pb1;


    /** @var stdClass */
    protected $df;
    /** @var stdClass */
    protected $dfother;
    /** @var stdClass */
    protected $dftenantother;
    /** @var stdClass */
    protected $da;
    /** @var stdClass */
    protected $db;
    /** @var stdClass */
    protected $da1;
    /** @var stdClass */
    protected $da2;
    /** @var stdClass */
    protected $db1;

    /** @var array */
    protected $users = [];

    /** @var stdClass */
    protected $tenant;
    /** @var stdClass */
    protected $tenantother;

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator() : tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Generates a user, allocates to the tenant and gives a job
     *
     * @param string $username
     * @param string|null $position
     * @param string|null $department
     * @return stdClass
     */
    protected function generate_user(string $username, string $position = null, string $department = null) : stdClass {
        if (!array_key_exists($username, $this->users)) {
            $user = $this->getDataGenerator()->create_user(['username' => $username]);
            $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
            $this->users[$username] = $user;
        }
        if ($position && $department) {
            $this->get_generator()->assign_job((object)['userid' => $this->users[$username]->id,
                'positionid' => $this->{$position}->id,
                'departmentid' => $this->{$department}->id]);
        }

        return $this->users[$username];
    }

    /**
     * Generate test structure
     */
    protected function generate_structure() {
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->tenantother = $this->get_tenant_generator()->create_tenant();

        $generator = $this->get_generator();

        $this->pf = $generator->create_position(['tenantid' => $this->tenant->id]);
        $this->pfother = $generator->create_position(['tenantid' => $this->tenant->id]);
        $this->pftenantother = $generator->create_position(['tenantid' => $this->tenantother->id]);

        $this->pa = $generator->create_position(['parentid' => $this->pf->id, 'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS]);
        $this->pb = $generator->create_position(['parentid' => $this->pf->id, 'departmentmanager' => 1]);
        $this->pa1 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pa2 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pb1 = $generator->create_position(['parentid' => $this->pb->id]);

        $this->df = $generator->create_department(['tenantid' => $this->tenant->id]);
        $this->dfother = $generator->create_department(['tenantid' => $this->tenant->id]);
        $this->dftenantother = $generator->create_department(['tenantid' => $this->tenantother->id]);

        $this->da = $generator->create_department(['parentid' => $this->df->id]);
        $this->db = $generator->create_department(['parentid' => $this->df->id]);
        $this->da1 = $generator->create_department(['parentid' => $this->da->id]);
        $this->da2 = $generator->create_department(['parentid' => $this->da->id]);
        $this->db1 = $generator->create_department(['parentid' => $this->db->id]);
    }

    /**
     * Tests for user_with_jobs::create , get_jobs
     */
    public function test_user_with_jobs_create() {
        global $DB;
        $this->generate_structure();

        $user0 = $this->generate_user('u0');
        $user1 = $this->generate_user('u1', 'pa', 'da');
        $user2 = $this->generate_user('u2', 'pb', 'db');
        $this->generate_user('u2', 'pa', 'da');
        $this->setUser($user0);

        $u0 = tool_organisation\organisation::get_user_with_jobs($user0->id);
        $this->assertEquals(0, count($u0->get_jobs()));

        $u1 = tool_organisation\organisation::get_user_with_jobs($user1->id);
        $this->assertEquals(1, count($u1->get_jobs()));

        $u2 = tool_organisation\organisation::get_user_with_jobs($user2->id);
        $this->assertEquals(2, count($u2->get_jobs()));
    }

    /**
     * Helps assertion that organisation::get_managed_users_select() returns what we expect
     *
     * @param string $managedusers
     * @param string $managername
     * @param int $permissionbitmask
     * @param int $time
     */
    public function assert_managed_users(string $managedusers, string $managername, $permissionbitmask = 0, $time = 0) {
        global $DB;
        $managers = tool_organisation\organisation::get_users_with_jobs('u.username=:username', ['username' => $managername]);
        $manager = reset($managers);
        if (!$manager) {
            $this->fail('User with username '.$managername.' is not found or does not belong to the current tenant');
        }
        list ($sql, $params) = \tool_organisation\helper::get_managed_users_select($manager, 'u', $permissionbitmask, $time);
        $usernames = $DB->get_fieldset_sql("SELECT username FROM {user} u WHERE ".$sql, $params);
        $expected = preg_split('/,\s*/', $managedusers, -1, PREG_SPLIT_NO_EMPTY);
        $this->assertEquals($expected, $usernames, '', 0, 10, true);
    }

    /**
     * Tests for organisation::get_managed_users_select()
     */
    public function test_get_managed_users_sql_single_job() {
        $this->generate_structure();

        // Generate one user in each position and department combination (each user holds only one job).
        foreach (['pa', 'pa1', 'pa2', 'pb', 'pb1'] as $p) {
            foreach (['da', 'da1', 'da2', 'db'] as $d) {
                $this->generate_user("u_{$p}_{$d}", $p, $d);
            }
        }

        $this->setUser($this->users['u_pa_da']);

        // User with position $this->pa is the manager of anybody in positions pa1 and pa2 regardless of their departments.
        $this->assert_managed_users('u_pa1_da, u_pa1_da1, u_pa1_da2, u_pa1_db, u_pa2_da, u_pa2_da1, u_pa2_da2, u_pa2_db',
            'u_pa_da');
        $this->assert_managed_users('u_pa1_da, u_pa1_da1, u_pa1_da2, u_pa1_db, u_pa2_da, u_pa2_da1, u_pa2_da2, u_pa2_db',
            'u_pa_da1');
        $this->assert_managed_users('u_pa1_da, u_pa1_da1, u_pa1_da2, u_pa1_db, u_pa2_da, u_pa2_da1, u_pa2_da2, u_pa2_db',
            'u_pa_da2');
        $this->assert_managed_users('u_pa1_da, u_pa1_da1, u_pa1_da2, u_pa1_db, u_pa2_da, u_pa2_da1, u_pa2_da2, u_pa2_db',
            'u_pa_db');

        // Users with positions $this->pa1 and $this->pa2 are not managers.
        $this->assert_managed_users('', 'u_pa1_da');
        $this->assert_managed_users('', 'u_pa1_da1');
        $this->assert_managed_users('', 'u_pa1_da2');
        $this->assert_managed_users('', 'u_pa1_db');
        $this->assert_managed_users('', 'u_pa2_da');
        $this->assert_managed_users('', 'u_pa2_da1');
        $this->assert_managed_users('', 'u_pa2_da2');
        $this->assert_managed_users('', 'u_pa2_db');

        // Users with position $this->pb are managers of anybody in the same department as themselves or in the subdepartments.
        $this->assert_managed_users('u_pa_da, u_pa_da1, u_pa_da2, u_pa1_da, u_pa1_da1, u_pa1_da2, u_pa2_da, u_pa2_da1, ' .
            'u_pa2_da2, u_pb_da1, u_pb_da2, u_pb1_da, u_pb1_da1, u_pb1_da2', 'u_pb_da');
        $this->assert_managed_users('u_pa_da1, u_pa1_da1, u_pa2_da1, u_pb1_da1', 'u_pb_da1');
        $this->assert_managed_users('u_pa_da2, u_pa1_da2, u_pa2_da2, u_pb1_da2', 'u_pb_da2');
        $this->assert_managed_users('u_pa_db, u_pa1_db, u_pa2_db, u_pb1_db', 'u_pb_db');

        // Users with position $this->pb1 are not managers.
        $this->assert_managed_users('', 'u_pb1_da');
        $this->assert_managed_users('', 'u_pb1_da1');
        $this->assert_managed_users('', 'u_pb1_da2');
        $this->assert_managed_users('', 'u_pb1_db');
    }

    /**
     * Test for get_managed_users_sql() where manager has multiple manager jobs
     */
    public function test_get_managed_users_sql_multiple() {
        global $PAGE, $DB;
        $this->generate_structure();

        // Generate one user in each position and department combination (each user holds only one job).
        foreach (['pa', 'pa1', 'pa2', 'pb'] as $p) {
            foreach (['da', 'da1', 'db', 'db1'] as $d) {
                $this->generate_user("u_{$p}_{$d}", $p, $d);
            }
        }

        // Manager holds several jobs.
        $u = $this->generate_user('u', 'pb', 'db'); // Dep manager in dep b.
        $this->generate_user('u', 'pa', 'da'); // Glob manager in pos a.
        $this->setUser($u);
        // This user will be manager of anybody in positions a1 or a2 and of anybody in departments b and b1.
        $this->assert_managed_users('u_pa2_da, u_pa2_da1, u_pa1_da, u_pa1_da1, u_pa_db, u_pa1_db, u_pa2_db, ' .
            'u_pb_db, u_pa_db1, u_pa1_db1, u_pa2_db1, u_pb_db1', 'u');

        // Retrieve all managed users and check that each of them has one job (and it is relevant).
        $manager = tool_organisation\organisation::get_user_with_jobs($u->id);
        list($select, $params) = \tool_organisation\helper::get_managed_users_select($manager);
        $managedusers = tool_organisation\organisation::get_users_with_jobs($select, $params);
        $this->assertEquals(12, count($managedusers));
        foreach ($managedusers as $user) {
            $this->assertEquals(1, count($user->get_jobs()));
            $this->assertEquals(1, count($user->get_relevant_jobs($manager)));
        }

        // Receive all other users and check that each of them has one job but it is not relevant.
        $likesql = $DB->sql_like('u.username', ':username');
        $allusers = tool_organisation\organisation::get_users_with_jobs($likesql, ['username' => 'u_%']);
        $this->assertEquals(16, count($allusers));
        $otherusers = array_diff_key($allusers, $managedusers);
        $this->assertEquals(4, count($otherusers));
        foreach ($otherusers as $user) {
            $this->assertEquals(1, count($user->get_jobs()));
            $this->assertEquals(0, count($user->get_relevant_jobs($manager)));
        }
    }

    /**
     * Test get_department_users_select helper method
     *
     * @return void
     */
    public function test_get_department_users_select() {
        global $DB;

        $this->generate_structure();

        $manager = $this->generate_user('manager', 'pa', 'da');
        $this->setUser($manager);
        $userwithjobs = tool_organisation\organisation::get_user_with_jobs($manager->id);

        // Alice is in the managers own department, Bob is in a sub-department.
        $this->generate_user('alice', 'pa1', 'da');
        $this->generate_user('bob', 'pa1', 'da1');

        // Own department.
        list($select, $params) = tool_organisation\helper::get_department_users_select($userwithjobs, false);
        $users = $DB->get_records_sql("SELECT u.username FROM {user} u WHERE {$select}", $params);

        $this->assertCount(2, $users);
        $this->assertEqualsCanonicalizing(['manager', 'alice'], array_keys($users));

        // Include sub-departments.
        list($select, $params) = tool_organisation\helper::get_department_users_select($userwithjobs, true);
        $users = $DB->get_records_sql("SELECT u.username FROM {user} u WHERE {$select}", $params);

        $this->assertCount(3, $users);
        $this->assertEqualsCanonicalizing(['manager', 'alice', 'bob'], array_keys($users));

        // Add manager to a second department.
        $this->generate_user($manager->username, 'pa', 'db');
        $userwithjobs = tool_organisation\organisation::get_user_with_jobs($manager->id);

        // Charlie is in the same department, Daniel is in a sub-department.
        $this->generate_user('charlie', 'pa1', 'db');
        $this->generate_user('daniel', 'pa1', 'db1');

        // Own department.
        list($select, $params) = tool_organisation\helper::get_department_users_select($userwithjobs, false);
        $users = $DB->get_records_sql("SELECT u.username FROM {user} u WHERE {$select}", $params);

        $this->assertCount(3, $users);
        $this->assertEqualsCanonicalizing(['manager', 'alice', 'charlie'], array_keys($users));

        // Include sub-departments.
        list($select, $params) = tool_organisation\helper::get_department_users_select($userwithjobs, true);
        $users = $DB->get_records_sql("SELECT u.username FROM {user} u WHERE {$select}", $params);

        $this->assertCount(5, $users);
        $this->assertEqualsCanonicalizing(['manager', 'alice', 'bob', 'charlie', 'daniel'], array_keys($users));
    }

    /**
     * Test for tool_organisation\helper::get_hierarchical_menu
     */
    public function test_get_hierarchical_menu() {
        global $DB;
        $this->generate_structure();
        $d = $this->get_generator()->create_department(['parentid' => $this->dfother->id]);
        $user = $this->generate_user('u1');
        $this->setUser($user);

        $records = $DB->get_records('tool_organisation_department',
            ['tenantid' => \tool_tenant\tenancy::get_tenant_id()]);
        $menu = \tool_organisation\helper::get_hierarchical_menu($records);
        $ds = \tool_organisation\helper::DOUBLE_SPACE;
        $this->assertEquals([
            $this->df->name => [
                $this->da->id => $this->da->name,
                $this->da1->id => $ds . $this->da1->name,
                $this->da2->id => $ds . $this->da2->name,
                $this->db->id => $this->db->name,
                $this->db1->id => $ds . $this->db1->name,
            ],
            $this->dfother->name => [
                $d->id => $d->name,
            ]
        ], $menu);
    }

    /**
     * Test for tool_organisation\helper::get_hierarchical_managed_menu()
     */
    public function test_get_hierarchical_managed_menu() {
        global $DB;
        $this->generate_structure();
        $d = $this->get_generator()->create_department(['parentid' => $this->dfother->id]);
        $user = $this->generate_user('u1');
        $this->setUser($user);
        $params = ['tenantid' => \tool_tenant\tenancy::get_tenant_id()];

        // No records are managed - empty menu is returned.
        $records = $DB->get_records('tool_organisation_department', $params);
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $this->assertEmpty($menu);

        // All records are managed - same result as in previous test.
        $records = $DB->get_records('tool_organisation_department', $params);
        array_walk($records, function($r) {
            $r->ismanaged = 1;
        });
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $ds = \tool_organisation\helper::DOUBLE_SPACE;
        $this->assertEquals([
            $this->df->name => [
                $this->da->id => $this->da->name,
                $this->da1->id => $ds . $this->da1->name,
                $this->da2->id => $ds . $this->da2->name,
                $this->db->id => $this->db->name,
                $this->db1->id => $ds . $this->db1->name,
            ],
            $this->dfother->name => [
                $d->id => $d->name,
            ]
        ], $menu);

        // One record is managed.
        $records = $DB->get_records('tool_organisation_department', $params);
        $records[$this->da1->id]->ismanaged = 1;
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $this->assertEquals(['' => [
            $this->da1->id => $this->da1->name
        ]], $menu);

        // Some records in the same framework are managed.
        $records = $DB->get_records('tool_organisation_department', $params);
        $records[$this->da1->id]->ismanaged = 1;
        $records[$this->db1->id]->ismanaged = 1;
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $this->assertEquals(['' => [
            $this->da1->id => $this->da1->name,
            $this->db1->id => $this->db1->name,
        ]], $menu);

        // Some records in the same framework are managed.
        $records = $DB->get_records('tool_organisation_department', $params);
        $records[$this->da1->id]->ismanaged = 1;
        $records[$this->da2->id]->ismanaged = 1;
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $this->assertEquals(['' => [
            $this->da->id => $this->da->name,
            $this->da1->id => $ds . $this->da1->name,
            $this->da2->id => $ds . $this->da2->name,
        ]], $menu);

        // Some records in the same framework are managed.
        $records = $DB->get_records('tool_organisation_department', $params);
        $records[$this->da1->id]->ismanaged = 1;
        $records[$this->db->id]->ismanaged = 1;
        $records[$this->db1->id]->ismanaged = 1;
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $this->assertEquals(['' => [
            $this->da1->id => $this->da1->name,
            $this->db->id => $this->db->name,
            $this->db1->id => $ds . $this->db1->name,
        ]], $menu);

        // Some records in the different framework are managed.
        $records = $DB->get_records('tool_organisation_department', $params);
        $records[$this->da1->id]->ismanaged = 1;
        $records[$this->da2->id]->ismanaged = 1;
        $records[$this->db1->id]->ismanaged = 1;
        $records[$d->id]->ismanaged = 1;
        $menu = \tool_organisation\helper::get_hierarchical_managed_menu($records);
        $this->assertEquals([
            $this->df->name => [
                $this->da->id => $this->da->name,
                $this->da1->id => $ds . $this->da1->name,
                $this->da2->id => $ds . $this->da2->name,
                $this->db1->id => $this->db1->name,
            ],
            $this->dfother->name => [
                $d->id => $d->name,
            ]
        ], $menu);
    }

    /**
     * Test for \tool_organisation\organisation::get_managed_users_departments_menu() and get_managed_users_positions_menu
     */
    public function test_get_managed_users_menu() {
        $this->generate_structure();

        // Generate one user in each position and department combination (each user holds only one job).
        $users = [];
        foreach (['pa', 'pa1', 'pa2', 'pb'] as $p) {
            foreach (['da', 'da1', 'db1'] as $d) {
                $users["u_{$p}_{$d}"] = $this->generate_user("u_{$p}_{$d}", $p, $d);
            }
        }
        $this->setUser($users['u_pa_da']);

        $manager = \tool_organisation\organisation::get_user_with_jobs($this->users['u_pa_da']->id);

        $menu = \tool_organisation\organisation::get_managed_users_departments_menu($manager);
        $ds = \tool_organisation\helper::DOUBLE_SPACE;
        $this->assertEquals(['' => [
            $this->da->id => $this->da->name,
            $this->da1->id => $ds . $this->da1->name,
            $this->db1->id => $this->db1->name,
        ]], $menu);

        $menu = \tool_organisation\organisation::get_managed_users_positions_menu($manager);
        $this->assertEquals(['' => [
            $this->pa1->id => $this->pa1->name,
            $this->pa2->id => $this->pa2->name,
        ]], $menu);
    }

    /**
     * Test for \tool_organisation\organisation::get_all_departments_menu() and get_all_positions_menu()
     */
    public function test_get_all_menu() {
        $this->generate_structure();
        $pf1 = $this->get_generator()->create_position(['parentid' => $this->pfother->id]);

        $u = $this->generate_user('uu');
        $this->setUser($u);

        $menu = \tool_organisation\organisation::get_all_departments_menu();
        $ds = \tool_organisation\helper::DOUBLE_SPACE;
        $this->assertEquals(['' => [
            $this->da->id => $this->da->name,
            $this->da1->id => $ds . $this->da1->name,
            $this->da2->id => $ds . $this->da2->name,
            $this->db->id => $this->db->name,
            $this->db1->id => $ds . $this->db1->name,
        ]], $menu);

        $menu = \tool_organisation\organisation::get_all_departments_menu(['' => '-']);
        $ds = \tool_organisation\helper::DOUBLE_SPACE;
        $this->assertEquals(['' => [
            '' => '-',
            $this->da->id => $this->da->name,
            $this->da1->id => $ds . $this->da1->name,
            $this->da2->id => $ds . $this->da2->name,
            $this->db->id => $this->db->name,
            $this->db1->id => $ds . $this->db1->name,
        ]], $menu);

        $menu = \tool_organisation\organisation::get_all_positions_menu();
        $this->assertEquals([
            $this->pf->name => [
                $this->pa->id => $this->pa->name,
                $this->pa1->id => $ds . $this->pa1->name,
                $this->pa2->id => $ds . $this->pa2->name,
                $this->pb->id => $this->pb->name,
                $this->pb1->id => $ds . $this->pb1->name,
            ],
            $this->pfother->name => [
                $pf1->id => $pf1->name,
            ]
        ], $menu);

        $menu = \tool_organisation\organisation::get_all_positions_menu(['' => '-']);
        $this->assertEquals([
            '' => ['' => '-'],
            $this->pf->name => [
                $this->pa->id => $this->pa->name,
                $this->pa1->id => $ds . $this->pa1->name,
                $this->pa2->id => $ds . $this->pa2->name,
                $this->pb->id => $this->pb->name,
                $this->pb1->id => $ds . $this->pb1->name,
            ],
            $this->pfother->name => [
                $pf1->id => $pf1->name,
            ]
        ], $menu);
    }

    /**
     * Compares the list of selected usernames with expected list
     *
     * @param string $expectedusernames comma-separated list of usernames
     * @param string $where
     * @param array $params
     */
    public function assert_selected_users(string $expectedusernames, string $where, array $params) {
        global $DB;
        $usernames = $DB->get_fieldset_sql('SELECT username from {user} u WHERE '.$where, $params);
        $expected = preg_split('/,\s*/', $expectedusernames, -1, PREG_SPLIT_NO_EMPTY);
        $this->assertEquals($expected, $usernames, '', 0, 10, true);
    }

    /**
     * Tests for user_is_in_department_select() and user_has_position_select()
     */
    public function test_user_is_in_department_or_position_select() {
        $this->generate_structure();

        // Generate one user in each position and department combination (each user holds only one job).
        foreach (['pa', 'pa1', 'pa2', 'pb'] as $p) {
            foreach (['da', 'da1', 'db', 'db1'] as $d) {
                $this->generate_user("u_{$p}_{$d}", $p, $d);
            }
        }

        // Create two items of each entity in other tenant framework.
        $dt = $this->get_generator()->create_department(['parentid' => $this->dftenantother->id,
            'tenantid' => $this->tenantother->id]);
        $dt1 = $this->get_generator()->create_department(['parentid' => $dt->id, 'tenantid' => $this->tenantother->id]);
        $pt = $this->get_generator()->create_position(['parentid' => $this->pftenantother->id,
            'tenantid' => $this->tenantother->id]);
        $pt1 = $this->get_generator()->create_position(['parentid' => $pt->id, 'tenantid' => $this->tenantother->id]);
        $usert = $this->generate_user('u_pt_dt');
        $this->get_tenant_generator()->allocate_user($usert->id, $this->tenantother->id);
        $this->get_generator()->assign_job((object)[
            'userid' => $usert->id,
            'positionid' => $pt->id,
            'departmentid' => $dt->id
        ]);
        $usert1 = $this->generate_user('u_pt1_dt1');
        $this->get_tenant_generator()->allocate_user($usert1->id, $this->tenantother->id);
        $this->get_generator()->assign_job((object)[
            'userid' => $usert1->id,
            'positionid' => $pt1->id,
            'departmentid' => $dt1->id
        ]);

        // Test with default tenant.
        $this->setUser($this->users['u_pa_da']);

        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($this->da->id);
        $this->assert_selected_users('u_pa_da,u_pb_da,u_pa1_da,u_pa2_da', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa1_da1,u_pa1_db,u_pa1_db1,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($this->da->id, 1);
        $this->assert_selected_users('u_pa_da,u_pb_da,u_pa1_da,u_pa2_da,u_pa2_da1,u_pa_da1,'.
            'u_pa1_da1,u_pb_da1', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa1_db,u_pa1_db1,u_pa2_db,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_db,u_pb_db1,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($this->pa->id);
        $this->assert_selected_users('u_pa_da,u_pa_da1,u_pa_db,u_pa_db1', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa1_da1,u_pa1_db,u_pa1_db1,u_pa2_da1,u_pa2_db,'.
            'u_pa2_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pb_da,u_pa1_da,u_pa2_da,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($this->pa->id, 1);
        $this->assert_selected_users('u_pa_da,u_pa_da1,u_pa_db,u_pa_db1,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa1_da,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa2_db1', $where, $params);
        $this->assert_selected_users('admin,guest,u_pb_da1,u_pb_db,u_pb_db1,u_pb_da,u_pt_dt,u_pt1_dt1',
            "NOT " . $where, $params);

        // Test with other tenant.
        $this->setUser($usert);

        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($dt->id);
        $this->assert_selected_users('u_pt_dt', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($dt->id, 1);
        $this->assert_selected_users('u_pt_dt,u_pt1_dt1', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($pt->id);
        $this->assert_selected_users('u_pt_dt', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($pt->id, 1);
        $this->assert_selected_users('u_pt_dt,u_pt1_dt1', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1', "NOT " . $where, $params);

        // Test using explicit tenant argument.
        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($this->da->id,
            false, 'u', null, $this->tenant->id);
        $this->assert_selected_users('u_pa_da,u_pb_da,u_pa1_da,u_pa2_da', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa1_da1,u_pa1_db,u_pa1_db1,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($dt->id,
            false, 'u', null, $this->tenantother->id);
        $this->assert_selected_users('u_pt_dt', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($this->pa->id,
            false, 'u', null, $this->tenant->id);
        $this->assert_selected_users('u_pa_da,u_pa_da1,u_pa_db,u_pa_db1', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa1_da1,u_pa1_db,u_pa1_db1,u_pa2_da1,u_pa2_db,u_pa2_db1,'.
            'u_pb_da1,u_pb_db,u_pb_db1,u_pb_da,u_pa1_da,u_pa2_da,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($pt->id,
            false, 'u', null, $this->tenantother->id);
        $this->assert_selected_users('u_pt_dt', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt1_dt1', "NOT " . $where, $params);

        // Test with tenant not matching department/position.
        $tenant = $this->get_tenant_generator()->create_tenant();

        list($where, $params) = \tool_organisation\helper::user_is_in_department_select($this->da->id,
            true, 'u', null, $tenant->id);
        $this->assert_selected_users('', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pb_da,u_pa1_da,u_pa1_da1,u_pa1_db,'.
            'u_pa1_db1,u_pa2_da,u_pa2_da1,u_pa2_db,u_pa_da1,'.
            'u_pa2_db1,u_pa_db,u_pa_db1,u_pb_da1,u_pb_db,u_pb_db1,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

        list($where, $params) = \tool_organisation\helper::user_has_position_select($this->pa->id,
            true, 'u', null, $tenant->id);
        $this->assert_selected_users('', $where, $params);
        $this->assert_selected_users('admin,guest,u_pa_da,u_pa_da1,u_pa_db,u_pa_db1,u_pa1_da1,'.
            'u_pa1_db,u_pa1_db1,u_pa2_da1,u_pa2_db,u_pa2_db1,u_pb_da1,'.
            'u_pb_db,u_pb_db1,u_pb_da,u_pa1_da,u_pa2_da,u_pt_dt,u_pt1_dt1', "NOT " . $where, $params);

    }

    /**
     * Tests for relevant jobs
     */
    public function test_relevant_jobs() {
        $this->generate_structure();

        $u1 = $this->generate_user('u1', 'pa', 'da1'); // Manager job.
        $this->generate_user('u1', 'pb1', 'da1'); // Other job of a manager.

        $u2 = $this->generate_user('u2', 'pa1', 'db'); // Subordinate job.
        $this->generate_user('u2', 'pb', 'db'); // Other job of a subordinate.
        $this->setUser($u1);

        $manager = \tool_organisation\organisation::get_user_with_jobs($u1->id);
        $user = \tool_organisation\organisation::get_user_with_jobs($u2->id);

        $jobs = $manager->get_jobs();
        $this->assertTrue($manager->is_manager());
        $this->assertCount(2, $jobs);
        $jobs = $manager->get_jobs(true);
        $this->assertCount(1, $jobs);
        $jobs = $manager->get_relevant_manager_jobs($user);
        $this->assertCount(1, $jobs);
        $job = reset($jobs);
        $this->assertEquals($this->pa->id, $job->get_position()->get('id'));
        $this->assertEmpty($manager->get_relevant_jobs($user));

        $jobs = $user->get_jobs();
        $this->assertCount(2, $jobs);
        $jobs = $user->get_relevant_jobs($manager);
        $this->assertCount(1, $jobs);
        $job = reset($jobs);
        $this->assertEquals($this->pa1->id, $job->get_position()->get('id'));
        $this->assertEmpty($user->get_relevant_manager_jobs($manager));
    }

    /**
     * Tests for is_manager_over_user()
     */
    public function test_manager_over_user() {
        $this->generate_structure();

        $u1 = $this->generate_user('u1', 'pa', 'da1'); // Manager job.
        $this->generate_user('u1', 'pb1', 'da1'); // Other job of a manager.

        $u2 = $this->generate_user('u2', 'pa1', 'db'); // Subordinate job.
        $this->generate_user('u2', 'pb', 'db'); // Other job of a subordinate.
        $this->setUser($u1);

        $u3 = $this->generate_user('u3', 'pb', 'da');

        $manager = \tool_organisation\organisation::get_user_with_jobs($u1->id);
        $user = \tool_organisation\organisation::get_user_with_jobs($u2->id);

        $this->assertTrue($manager->is_manager_over_user($u2->id));
        $this->assertTrue($manager->is_manager_over_user($u2->id, \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS));
        $this->assertTrue($manager->is_manager_over_user($u2->id, \tool_organisation\organisation::PERM_VIEW_REPORTS));
        $this->assertFalse($manager->is_manager_over_user($u2->id, \tool_organisation\organisation::PERM_RECEIVE_NOTIFICATIONS));

        $this->assertFalse($manager->is_manager_over_user($u3->id));
        $this->assertFalse($user->is_manager_over_user($u1->id));
        $this->assertFalse($user->is_manager_over_user($u3->id));
    }

    /**
     * Test that manager role is created on installation
     */
    public function test_roles() {
        $this->resetAfterTest();

        $allroles = get_all_roles();
        $roles = array_combine(array_keys($allroles), array_column($allroles, 'shortname'));
        $this->assertContains('tool_organisation_manager', $roles);
        $fliproles = array_flip($roles);

        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        role_assign($fliproles['tool_tenant_admin'], $user->id, $context->id);
        $this->setUser($user);

        // Tenant admin can assign tool_organisation_manager in the system context.
        $assignableroles = get_assignable_roles($context);
        $this->assertTrue(array_key_exists($fliproles['tool_organisation_manager'], $assignableroles));

        // The manager role has four capabilities, they are valid and belong to this project (plus doclinks).
        $caps = get_capabilities_from_role_on_context((object)['id' => $fliproles['tool_organisation_manager']], $context);
        $this->assertEquals(4, count($caps));
        foreach ($caps as $cap) {
            if (!preg_match('|^tool/organisation:|', $cap->capability) && $cap->capability != 'moodle/site:doclinks') {
                $this->fail('Capability ' . $cap->capability . ' does not belong to this plugin');
            }
            get_capability_info($cap->capability);
        }
    }

    /**
     * Test for test_get_all_managers()
     */
    public function test_get_all_managers(): void {
        // There are no managers and we pass no permission.
        $managers = \tool_organisation\organisation::get_all_managers();
        $this->assertCount(0, $managers);

        // There are no managers.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_VIEW_REPORTS);
        $this->assertCount(0, $managers);

        $this->generate_structure();

        // User1 has manager permission to view reports and allocate users.
        $user1 = $this->getDataGenerator()->create_user(['username' => 'u1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $this->tenant->id);
        $jobuser1 = $this->get_generator()->assign_job((object)[
            'userid' => $user1->id,
            'positionid' => $this->pa->id,
            'departmentid' => $this->da->id
        ]);

        // User2 has manager permission to view reports and allocate users.
        $user2 = $this->getDataGenerator()->create_user(['username' => 'u2']);
        $this->get_tenant_generator()->allocate_user($user2->id, $this->tenant->id);
        $jobuser2 = $this->get_generator()->assign_job((object)[
            'userid' => $user2->id,
            'positionid' => $this->pa->id,
            'departmentid' => $this->da->id
        ]);

        // User3 has no manager permissions.
        $this->generate_user('u3', 'pb', 'db');
        // User4 has no manager permissions.
        $this->generate_user('u4', 'pa1', 'da');
        $user0 = $this->generate_user('u0');
        $this->setUser($user0);

        // Only user1 and user2 have manager permissions to view reports.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_VIEW_REPORTS);
        $this->assertCount(2, $managers);

        $this->assertEquals($this->pa->id, $managers[$jobuser1->id]->positionid);
        $this->assertEquals($this->pa->name, $managers[$jobuser1->id]->positionname);
        $this->assertEquals($this->da->id, $managers[$jobuser1->id]->departmentid);
        $this->assertEquals($user1->id, $managers[$jobuser1->id]->userid);
        $this->assertEquals($user1->firstname, $managers[$jobuser1->id]->firstname);
        $this->assertEquals($user1->lastname, $managers[$jobuser1->id]->lastname);
        $this->assertEquals($user1->username, $managers[$jobuser1->id]->username);

        $this->assertEquals($this->pa->id, $managers[$jobuser2->id]->positionid);
        $this->assertEquals($this->pa->name, $managers[$jobuser2->id]->positionname);
        $this->assertEquals($this->da->id, $managers[$jobuser2->id]->departmentid);
        $this->assertEquals($user2->id, $managers[$jobuser2->id]->userid);
        $this->assertEquals($user2->firstname, $managers[$jobuser2->id]->firstname);
        $this->assertEquals($user2->lastname, $managers[$jobuser2->id]->lastname);
        $this->assertEquals($user2->username, $managers[$jobuser2->id]->username);

        // Only user1 and user2 have manager permissions to allocate users to programs.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS);
        $this->assertCount(2, $managers);

        // There are no manager with permission to receive notifications.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_RECEIVE_NOTIFICATIONS);
        $this->assertCount(0, $managers);

        // We create a new position C with permission to alocate users to programs.
        $pc = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'departmentmanager' => 1,
            'departmentpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS]);

        // We assign the new position C to User5 so it has manager permission to allocate users.
        $user5 = $this->getDataGenerator()->create_user(['username' => 'u5']);
        $this->get_tenant_generator()->allocate_user($user5->id, $this->tenant->id);
        $this->get_generator()->assign_job((object)[
            'userid' => $user5->id,
            'positionid' => $pc->id,
            'departmentid' => $this->da->id
        ]);

        // Only user1, user2 and user5 have manager permissions to allocate users to programs.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS);
        $this->assertCount(3, $managers);

        // Only user1 and user2 have manager permissions to view reports.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_VIEW_REPORTS);
        $this->assertCount(2, $managers);

        // No user has permission to receive notifications.
        $managers = \tool_organisation\organisation::get_all_managers(\tool_organisation\organisation::PERM_RECEIVE_NOTIFICATIONS);
        $this->assertCount(0, $managers);

        // There are managers and we pass no permission.
        $managers = \tool_organisation\organisation::get_all_managers();
        $this->assertCount(4, $managers);
    }
}
