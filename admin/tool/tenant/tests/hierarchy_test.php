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
 * Tests for shared space
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_tenant\tenant;
use tool_tenant\hierarchy;

/**
 * Tests for the tool_tenant\sharedspace class methods.
 *
 * @package    tool_tenant
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_hierarchy_testcase extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /** @var int */
    protected $instancecount = 0;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Creates new tenant
     *
     * TEMPORARY function while the generator create_tenant ignores the 'parentid'
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    protected function create_tenant($record = null) : \stdClass {
        \tool_tenant\tenancy::get_tenants();
        $record = $record ? (array)$record : [];
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New hierarhcy tenant ' . (++$this->instancecount);
        }
        $parentid = $record['parentid'] ?? null;
        $manager = (new \tool_tenant\manager());
        $tenant = $manager->create_tenant((object)$record);
        if ($parentid) {
            $parent = new tenant($parentid);
            $tenant->set('parentid', $parentid);
            $tenant->set('path', $parent->get('path').'/'.$tenant->get('id'));
            $tenant->set('depth', $parent->get('depth') + 1);
            $tenant->save();
            $manager->change_sortorder($tenant->get('id'));
        }
        return $tenant->to_record();
    }

    public function test_create_tenant() {
        global $DB;
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $this->assertEquals($tenant->id, $tenant2->parentid);
        $this->assertEquals('/'.$tenant->id, $tenant->path);
        $this->assertEquals('/'.$tenant->id.'/'.$tenant2->id, $tenant2->path);
        $this->assertEquals('/'.$tenant->id.'/'.$tenant2->id.'/'.$tenant3->id, $tenant3->path);
        $this->assertEquals(1, $tenant->depth);
        $this->assertEquals(2, $tenant2->depth);
        $this->assertEquals(3, $tenant3->depth);

        // Re-fetch from DB and check again.
        $tenant = $DB->get_record('tool_tenant', ['id' => $tenant->id]);
        $tenant2 = $DB->get_record('tool_tenant', ['id' => $tenant2->id]);
        $tenant3 = $DB->get_record('tool_tenant', ['id' => $tenant3->id]);
        $this->assertEquals($tenant->id, $tenant2->parentid);
        $this->assertEquals('/'.$tenant->id, $tenant->path);
        $this->assertEquals('/'.$tenant->id.'/'.$tenant2->id, $tenant2->path);
        $this->assertEquals('/'.$tenant->id.'/'.$tenant2->id.'/'.$tenant3->id, $tenant3->path);
        $this->assertEquals(1, $tenant->depth);
        $this->assertEquals(2, $tenant2->depth);
        $this->assertEquals(3, $tenant3->depth);
    }

    public function test_get_parent_tenants_ids() {
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);

        $this->assertEquals([], \tool_tenant\hierarchy::get_parent_tenants_ids($tenant->id));
        $this->assertEquals([$tenant->id], \tool_tenant\hierarchy::get_parent_tenants_ids($tenant2->id));
        $this->assertEquals([$tenant->id, $tenant2->id], \tool_tenant\hierarchy::get_parent_tenants_ids($tenant3->id));
        $this->assertEquals([$tenant->id, $tenant2->id], \tool_tenant\hierarchy::get_parent_tenants_ids($tenant4->id));
    }

    public function test_get_subtenants_sql() {
        global $DB;
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant();

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant->id, true);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant->id, $tenant2->id, $tenant3->id, $tenant4->id], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant->id, false);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant2->id, $tenant3->id, $tenant4->id], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant2->id, true);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant2->id, $tenant3->id, $tenant4->id], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant2->id, false);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant3->id, $tenant4->id], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant3->id, true);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant3->id], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant3->id, false);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([], $ids);
    }

    public function test_filter_own_or_parent_shared_entities_sql() {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);

        // TODO improve this test when we actually have tables with the 'shared' field.
        $baserecord = ['createdby' => $USER->id, 'timecreated' => time(), 'timemodified' => time()];
        $e2 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant2->id] + $baserecord);
        $e3 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant3->id] + $baserecord);
        $e4 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant4->id] + $baserecord);
        $e5 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant5->id] + $baserecord);

        \tool_tenant\tenancy::set_switched_tenant_id($tenant2->id);
        [$sql, $params] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('e.tenantid', '1=1');
        $this->assertEquals([$e2], $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        \tool_tenant\tenancy::set_switched_tenant_id($tenant3->id);
        [$sql, $params] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('e.tenantid', '1=1');
        $this->assertEquals([$e2, $e3], $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        \tool_tenant\tenancy::set_switched_tenant_id($tenant5->id);
        [$sql, $params] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('e.tenantid');
        $this->assertEquals([$e2, $e3, $e5],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Exactly same tests but without switching a tenant.
        \tool_tenant\tenancy::set_switched_tenant_id(\tool_tenant\tenancy::get_default_tenant_id());

        [$sql, $params] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('e.tenantid', '1=1', $tenant2->id);
        $this->assertEquals([$e2], $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        [$sql, $params] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('e.tenantid', '1=1', $tenant3->id);
        $this->assertEquals([$e2, $e3], $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        [$sql, $params] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('e.tenantid', null, $tenant5->id);
        $this->assertEquals([$e2, $e3, $e5],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));
    }

    public function test_is_own_or_parent_shared_entity() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);

        \tool_tenant\tenancy::set_switched_tenant_id($tenant2->id);
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, true));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, false));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant3->id, true));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant4->id, true));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant5->id, true));

        \tool_tenant\tenancy::set_switched_tenant_id($tenant5->id);
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, true));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, false));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant3->id, true));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant3->id, false));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant4->id, true));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant5->id, true));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant5->id, false));

        // Same tests but without switching a tenant.
        \tool_tenant\tenancy::set_switched_tenant_id(\tool_tenant\tenancy::get_default_tenant_id());
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, true, $tenant2->id));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, false, $tenant2->id));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant3->id, true, $tenant2->id));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant4->id, true, $tenant2->id));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant5->id, true, $tenant2->id));

        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, true, $tenant5->id));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant2->id, false, $tenant5->id));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant3->id, true, $tenant5->id));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant3->id, false, $tenant5->id));
        $this->assertFalse(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant4->id, true, $tenant5->id));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant5->id, true, $tenant5->id));
        $this->assertTrue(\tool_tenant\hierarchy::is_own_or_parent_shared_entity($tenant5->id, false, $tenant5->id));

    }

    public function test_is_subtenant_of() {
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);

        $this->assertEquals(true, \tool_tenant\hierarchy::is_subtenant_of($tenant2->id, $tenant->id));
        $this->assertEquals(true, \tool_tenant\hierarchy::is_subtenant_of($tenant4->id, $tenant->id));
        $this->assertEquals(false, \tool_tenant\hierarchy::is_subtenant_of($tenant5->id, $tenant4->id));
        $this->assertEquals(false, \tool_tenant\hierarchy::is_subtenant_of($tenant2->id, $tenant4->id));
    }

    public function test_fix_hierarchy_paths() {
        global $DB;
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);

        // Mess with some properties directly in the DB.
        // Make t3 orphaned.
        $DB->update_record('tool_tenant', ['parentid' => $tenant5->id + 10, 'id' => $tenant3->id]);
        // Break the path of t4 and depth of t2.
        $DB->update_record('tool_tenant', ['path' => '/', 'id' => $tenant4->id]);
        $DB->update_record('tool_tenant', ['depth' => 1, 'id' => $tenant2->id]);

        \tool_tenant\hierarchy::fix_hierarchy_paths();
        [$t0, $t1, $t2, $t3, $t4, $t5] = array_values($DB->get_records('tool_tenant', [], 'id'));
        $this->assertEquals(null, $t3->parentid);
        $this->assertEquals(1, $t3->depth);
        $this->assertEquals("/{$t3->id}", $t3->path);

        $this->assertEquals($t2->id, $t4->parentid);
        $this->assertEquals(3, $t4->depth);
        $this->assertEquals("/{$t1->id}/{$t2->id}/{$t4->id}", $t4->path);

        $this->assertEquals($t1->id, $t2->parentid);
        $this->assertEquals(2, $t2->depth);
        $this->assertEquals("/{$t1->id}/{$t2->id}", $t2->path);

        $this->assertEquals($t3->id, $t5->parentid);
        $this->assertEquals(2, $t5->depth);
        $this->assertEquals("/{$t3->id}/{$t5->id}", $t5->path);
    }

    public function test_get_users_subquery() {
        // Only covering hierarchy, other tests are in tenancy_test.php.
        global $DB, $CFG;
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $tenant6 = $this->create_tenant(['parentid' => $defaulttenantid]);

        $user01 = $this->getDataGenerator()->create_user();
        $user02 = $this->getDataGenerator()->create_user();
        $user11 = $this->generator->create_user(['tenantid' => $tenant->id]);
        $user21 = $this->generator->create_user(['tenantid' => $tenant2->id]);
        $user31 = $this->generator->create_user(['tenantid' => $tenant3->id]);
        $user41 = $this->generator->create_user(['tenantid' => $tenant4->id]);
        $user51 = $this->generator->create_user(['tenantid' => $tenant5->id]);
        $user61 = $this->generator->create_user(['tenantid' => $tenant6->id]);

        // For two users that belong to default tenant, one with a record in tool_tenant_users, one without.
        $this->generator->allocate_user($user01->id, $defaulttenantid);
        $this->assertTrue($DB->record_exists('tool_tenant_user', ['userid' => $user01->id]));
        $this->assertFalse($DB->record_exists('tool_tenant_user', ['userid' => $user02->id]));

        // Tests.
        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $tenant2->id);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$user21->id, $user31->id, $user41->id, $user51->id], $users);

        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $tenant2->id, false);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$user21->id], $users);

        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $defaulttenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([get_admin()->id, $user01->id, $user02->id, $user61->id], $users);

        // Make default tenant subtenant of tenant1.
        $DB->update_record('tool_tenant', ['parentid' => $tenant->id, 'id' => $defaulttenantid]);
        \tool_tenant\hierarchy::fix_hierarchy_paths();
        \cache::make('tool_tenant', 'tenants')->purge();

        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $defaulttenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([get_admin()->id, $user01->id, $user02->id, $user61->id], $users);

        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $defaulttenantid, false);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([get_admin()->id, $user01->id, $user02->id], $users);

        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $tenant->id);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([get_admin()->id, $user01->id, $user02->id, $user11->id, $user21->id, $user31->id,
            $user41->id, $user51->id, $user61->id], $users);

        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, true, "{$ualias}.id", $tenant->id);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where 1=1 ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([get_admin()->id, $user01->id, $user02->id, $user11->id, $user21->id, $user31->id,
            $user41->id, $user51->id, $user61->id], $users);
    }

    public function test_is_user_hidden_by_tenancy() {
        // Only covering hierarchy, other tests are in tenancy_test.php.
        global $DB, $CFG;
        $this->resetAfterTest();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $tenant6 = $this->create_tenant(['parentid' => $defaulttenantid]);

        $user01 = $this->getDataGenerator()->create_user();
        $user11 = $this->generator->create_user(['tenantid' => $tenant->id]);
        $user21 = $this->generator->create_user(['tenantid' => $tenant2->id]);
        $user22 = $this->generator->create_user(['tenantid' => $tenant2->id]);
        $user31 = $this->generator->create_user(['tenantid' => $tenant3->id]);
        $user32 = $this->generator->create_user(['tenantid' => $tenant3->id]);
        $user41 = $this->generator->create_user(['tenantid' => $tenant4->id]);
        $user51 = $this->generator->create_user(['tenantid' => $tenant5->id]);
        $user61 = $this->generator->create_user(['tenantid' => $tenant6->id]);

        // User from tenant 3 can see users from tenants 3 and 5 but nobody else.
        $this->setUser($user31);
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user01->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user11->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user21->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user32->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user41->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user51->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user61->id));

        // User from tenant 2 can see users from tenants 2, 3, 4 and 5.
        $this->setUser($user21);
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user01->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user11->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user22->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user31->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user41->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user51->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user61->id));

        // Same but passing the second parameter.
        $this->setAdminUser();

        // User from tenant 3 can see users from tenants 3 and 5 but nobody else.
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user01->id, $user31->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user11->id, $user31->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user21->id, $user31->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user32->id, $user31->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user41->id, $user31->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user51->id, $user31->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user61->id, $user31->id));

        // User from tenant 2 can see users from tenants 2, 3, 4 and 5.
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user01->id, $user21->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user11->id, $user21->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user22->id, $user21->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user31->id, $user21->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user41->id, $user21->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user51->id, $user21->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user61->id, $user21->id));
    }

    public function test_filter_own_or_sub_or_parent_shared_entities_sql() {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['parentid' => $tenant3->id]);
        $tenant3b = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4b = $this->create_tenant(['parentid' => $tenant3b->id]);
        $tenant6 = $this->create_tenant();

        // TODO improve this test when we actually have tables with the 'shared' field.
        $baserecord = ['createdby' => $USER->id, 'timecreated' => time(), 'timemodified' => time()];
        $e1 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant->id] + $baserecord);
        $e2 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant2->id] + $baserecord);
        $e3 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant3->id] + $baserecord);
        $e4 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant4->id] + $baserecord);
        $e5 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant5->id] + $baserecord);
        $e3b = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant3b->id] + $baserecord);
        $e4b = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant4b->id] + $baserecord);
        $e6 = $DB->insert_record('tool_wp_export', ['tenantid' => $tenant6->id] + $baserecord);

        // Tenant that has no parents or children.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant6->id);
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', '1=1');
        $this->assertEquals([$e6], $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Tenant that has only parents.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant5->id);
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid');
        $this->assertEquals([$e1, $e2, $e3, $e5],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Tenant that has only children.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', '1=1');
        $this->assertEquals([$e1, $e2, $e3, $e4, $e5, $e3b, $e4b],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Tenant that has both, parents and children.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant2->id);
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', '1=1');
        $this->assertEquals([$e1, $e2, $e3, $e4, $e5, $e3b, $e4b],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Exactly same tests but without switching a tenant.
        \tool_tenant\tenancy::set_switched_tenant_id(\tool_tenant\tenancy::get_default_tenant_id());

        // Tenant with no parents or children.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', '1=1', $tenant6->id);
        $this->assertEquals([$e6], $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Tenant that has only parents.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', null, $tenant5->id);
        $this->assertEquals([$e1, $e2, $e3, $e5],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Tenant that has only children.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', '1=1', $tenant->id);
        $this->assertEquals([$e1, $e2, $e3, $e4, $e5, $e3b, $e4b],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));

        // Tenant that has both, parents and children.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('e.tenantid', '1=1', $tenant2->id);
        $this->assertEquals([$e1, $e2, $e3, $e4, $e5, $e3b, $e4b],
            $DB->get_fieldset_sql("SELECT id from {tool_wp_export} e WHERE $sql ORDER BY id", $params));
    }

    /**
     * Test for hierarchy filter_own_or_sub_entities_sql method
     */
    public function test_filter_own_or_sub_entities_sql(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create the following hierarchy of tenants:
        // Tenant1
        // - Tenant2
        // -- Tenant3
        // --- Tenant5
        // -- Tenant4
        // -- Tenant6
        // --- Tenant7
        // Tenant8.
        $tenant1 = $this->create_tenant(['name' => 'Tenant 1']);
        $tenant2 = $this->create_tenant(['name' => 'Tenant 2', 'parentid' => $tenant1->id]);
        $tenant3 = $this->create_tenant(['name' => 'Tenant 3', 'parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant(['name' => 'Tenant 4', 'parentid' => $tenant2->id]);
        $tenant5 = $this->create_tenant(['name' => 'Tenant 5', 'parentid' => $tenant3->id]);
        $tenant6 = $this->create_tenant(['name' => 'Tenant 6', 'parentid' => $tenant2->id]);
        $tenant7 = $this->create_tenant(['name' => 'Tenant 7', 'parentid' => $tenant6->id]);
        $tenant8 = $this->create_tenant(['name' => 'Tenant 8']);

        // Tenant 1 + sub-tenants [2, 3, 4, 5, 6, 7].
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant1->id);
        $this->assertEquals([$tenant1->id, $tenant2->id, $tenant3->id, $tenant4->id, $tenant5->id, $tenant6->id, $tenant7->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 2 + sub-tenants [3, 4, 5, 6, 7].
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant2->id);
        $this->assertEquals([$tenant2->id, $tenant3->id, $tenant4->id, $tenant5->id, $tenant6->id, $tenant7->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 3 + sub-tenant 5.
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant3->id);
        $this->assertEquals([$tenant3->id, $tenant5->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 4.
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant4->id);
        $this->assertEquals([$tenant4->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 5.
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant5->id);
        $this->assertEquals([$tenant5->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 6 + sub-tenant 7.
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant6->id);
        $this->assertEquals([$tenant6->id, $tenant7->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 7.
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant7->id);
        $this->assertEquals([$tenant7->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));

        // Tenant 8.
        [$sql, $params] = hierarchy::filter_own_or_sub_entities_sql('t.id', $tenant8->id);
        $this->assertEquals([$tenant8->id],
            $DB->get_fieldset_sql("SELECT t.id FROM {tool_tenant} t WHERE {$sql} ORDER BY t.id", $params));
    }

    public function test_has_subtenants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1 = $this->create_tenant();
        $tenant2 = $this->create_tenant(['parentid' => $tenant1->id]);
        $tenant3 = $this->create_tenant(['parentid' => $tenant2->id]);
        $tenant4 = $this->create_tenant();

        $this->assertTrue(hierarchy::has_subtenants($tenant1->id));
        $this->assertTrue(hierarchy::has_subtenants($tenant2->id));
        $this->assertFalse(hierarchy::has_subtenants($tenant3->id));
        $this->assertFalse(hierarchy::has_subtenants($tenant4->id));
    }
}
