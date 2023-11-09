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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_catalogue\local\helpers;

use advanced_testcase;
use tool_catalogue\configuration;

/**
 * Unit tests for search helper class
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\local\helpers\search
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class search_test extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get tenant generator
     *
     * @return \tool_tenant_generator
     */
    protected function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test search_courses for general functionality.
     *
     * @covers \tool_catalogue\local\helpers\search::search_courses
     */
    public function test_search_courses(): void {
        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'tc', 'fullname' => 'test course']);
        $catcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'pc', 'fullname' => 'proper course']);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'stc', 'fullname' => 'sub test course']);
        $subcatcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'spc', 'fullname' => 'sub proper course']);
        $category1 = $this->getDataGenerator()->create_category();

        // Matching by searchterms fullname.
        $this->assertEqualsCanonicalizing([$catcourse0->id, $catcourse1->id, $subcatcourse0->id, $subcatcourse1->id],
            array_keys(search::search_courses('course')));
        $this->assertEqualsCanonicalizing([$subcatcourse0->id, $subcatcourse1->id],
            array_keys(search::search_courses('sub')));
        $this->assertEqualsCanonicalizing([$catcourse1->id, $subcatcourse1->id],
            array_keys(search::search_courses('proper course')));
        $this->assertEquals([$subcatcourse1->id],
            array_keys(search::search_courses('sub proper course')));

        // Searchterm with control signs.
        $this->assertEquals([$catcourse1->id],
            array_keys(search::search_courses('-sub proper course')));
        $this->assertEquals([$catcourse1->id, $subcatcourse1->id],
            array_keys(search::search_courses('+proper course')));

        // Matching by searchterms shortname.
        $this->assertEqualsCanonicalizing([$catcourse0->id, $subcatcourse0->id],
            array_keys(search::search_courses('tc')));
        $this->assertEqualsCanonicalizing([$subcatcourse0->id],
            array_keys(search::search_courses('stc')));

        // Empty searchterm returns everything.
        $this->assertEqualsCanonicalizing([$catcourse0->id, $catcourse1->id, $subcatcourse0->id, $subcatcourse1->id],
            array_keys(search::search_courses('')));

        // Empty result for non-existing name.
        $this->assertCount(0, search::search_courses('hello course'));

        // Ensure regex injection is not possible.
        $this->assertCount(0, search::search_courses('proper|course'));
        $this->assertCount(0, search::search_courses('proper)?.+\\'));
    }

    /**
     * Test get_courses_by_category for general functionality.
     *
     * @covers \tool_catalogue\local\helpers\search::get_courses_by_category
     */
    public function test_get_courses_by_category(): void {
        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'tc', 'fullname' => 'test course']);
        $catcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'pc', 'fullname' => 'proper course']);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'stc', 'fullname' => 'sub test course']);
        $subcatcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'spc', 'fullname' => 'sub proper course']);
        $category1 = $this->getDataGenerator()->create_category();

        // Matching by category.
        $this->assertEqualsCanonicalizing([$catcourse0->id, $catcourse1->id, $subcatcourse0->id, $subcatcourse1->id],
            array_keys(search::get_courses_by_category($category->id)));
        $this->assertEqualsCanonicalizing([$subcatcourse0->id, $subcatcourse1->id],
            array_keys(search::get_courses_by_category($subcategory->id)));

        // Empty result for invalid category.
        $this->assertCount(0, search::get_courses_by_category($category1->id));
        $this->assertCount(0, search::get_courses_by_category(1000));

        // Category 0 returns everything.
        $this->assertEqualsCanonicalizing([$catcourse0->id, $catcourse1->id, $subcatcourse0->id, $subcatcourse1->id],
            array_keys(search::get_courses_by_category()));
    }

    /**
     * Test search_courses for priority.
     *
     * @covers \tool_catalogue\local\helpers\search::search_courses
     */
    public function test_search_courses_priority(): void {
        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'tc', 'fullname' => 'test course', 'summary' => 'use proper']);
        $catcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'pc', 'fullname' => 'proper course', 'summary' => 'use test']);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'stc', 'fullname' => 'sub test course', 'summary' => 'use proper']);
        $subcatcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'spc', 'fullname' => 'sub proper course', 'summary' => 'use test']);
        $category1 = $this->getDataGenerator()->create_category();

        // Fullname matches first, then summary.
        $searchresult = search::search_courses('proper');
        $this->assertEquals([$catcourse1->id, $subcatcourse1->id, $catcourse0->id, $subcatcourse0->id],
            array_keys($searchresult));
        $this->assertEquals([1, 1, 0, 0],
            array_values($searchresult));
    }

    /**
     * Test get_courses_by_category for priority.
     *
     * @covers \tool_catalogue\local\helpers\search::get_courses_by_category
     */
    public function test_get_courses_by_category_priority(): void {
        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'tc', 'fullname' => 'test course', 'summary' => 'use proper']);
        $catcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $category->id, 'shortname' => 'pc', 'fullname' => 'proper course', 'summary' => 'use test']);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'stc', 'fullname' => 'sub test course', 'summary' => 'use proper']);
        $subcatcourse1 = $this->getDataGenerator()->create_course(
            ['category' => $subcategory->id, 'shortname' => 'spc', 'fullname' => 'sub proper course', 'summary' => 'use test']);
        $category1 = $this->getDataGenerator()->create_category();

        $this->assertEqualsCanonicalizing([0, 0, 0, 0],
            array_values(search::get_courses_by_category($category->id)));
    }

    /**
     * Test search_courses using searchterm does not disclose courses from other tenants.
     *
     * @covers \tool_catalogue\local\helpers\search::search_courses
     */
    public function test_search_courses_tenants(): void {
        // We need to change core roles to match workplace setup,
        // so that capabilities are respecting tenancy.
        \tool_tenant\manager::change_core_roles();

        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $catcourse1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);
        $subcatcourse1 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);

        $tenant0category = $this->getDataGenerator()->create_category();
        $tenant0catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant0category->id]);
        $tenant0catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant0category->id]);
        $tenant0subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant0category->id]);
        $subtenant0catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant0subcategory->id]);
        $subtenant0catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant0subcategory->id]);

        $tenant1category = $this->getDataGenerator()->create_category();
        $tenant1catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant1category->id]);
        $subtenant1catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant1subcategory->id]);
        $subtenant1catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant1subcategory->id]);

        // Tenant and users.
        $tenant0 = $this->get_tenant_generator()->create_tenant(['categoryid' => $tenant0category->id]);
        $tenantadmin0 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant0->id, 'tenantadmin' => true]);
        $tenantuser0 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant0->id, 'tenantadmin' => true]);
        $tenant1 = $this->get_tenant_generator()->create_tenant(['categoryid' => $tenant1category->id]);

        self::setAdminUser();

        // Admin user can find all courses.
        $this->assertCount(12, search::search_courses());

        self::setUser($tenantadmin0);

        // Tenant 0 admin can only find tenant ones.
        $this->assertEqualsCanonicalizing([$tenant0catcourse0->id, $tenant0catcourse1->id, $subtenant0catcourse0->id,
            $subtenant0catcourse1->id, ], array_keys(search::search_courses()));

        self::setUser($tenantuser0);

        // Tenant 0 user can only find tenant ones.
        $this->assertEqualsCanonicalizing([$tenant0catcourse0->id, $tenant0catcourse1->id, $subtenant0catcourse0->id,
            $subtenant0catcourse1->id, ], array_keys(search::search_courses()));
    }

    /**
     * Test get_courses_by_category using searchterm does not disclose courses from other tenants.
     *
     * @covers \tool_catalogue\local\helpers\search::get_courses_by_category
     */
    public function test_get_courses_by_category_tenants(): void {
        // We need to change core roles to match workplace setup,
        // so that capabilities are respecting tenancy.
        \tool_tenant\manager::change_core_roles();

        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $catcourse1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);
        $subcatcourse1 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);

        $tenant0category = $this->getDataGenerator()->create_category();
        $tenant0catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant0category->id]);
        $tenant0catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant0category->id]);
        $tenant0subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant0category->id]);
        $subtenant0catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant0subcategory->id]);
        $subtenant0catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant0subcategory->id]);

        $tenant1category = $this->getDataGenerator()->create_category();
        $tenant1catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant1category->id]);
        $subtenant1catcourse0 = $this->getDataGenerator()->create_course(['category' => $tenant1subcategory->id]);
        $subtenant1catcourse1 = $this->getDataGenerator()->create_course(['category' => $tenant1subcategory->id]);

        // Tenant and users.
        $tenant0 = $this->get_tenant_generator()->create_tenant(['categoryid' => $tenant0category->id]);
        $tenantadmin0 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant0->id, 'tenantadmin' => true]);
        $tenantuser0 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant0->id, 'tenantadmin' => true]);
        $tenant1 = $this->get_tenant_generator()->create_tenant(['categoryid' => $tenant1category->id]);

        self::setAdminUser();

        // Admin user can find all courses.
        $this->assertCount(4, search::get_courses_by_category($category->id));
        $this->assertCount(4, search::get_courses_by_category($tenant0category->id));
        $this->assertCount(4, search::get_courses_by_category($tenant1category->id));
        $this->assertCount(12, search::get_courses_by_category());

        self::setUser($tenantadmin0);

        // Tenant 0 admin can only find tenant ones.
        $this->assertCount(0, search::get_courses_by_category($category->id));
        $this->assertEqualsCanonicalizing([$tenant0catcourse0->id, $tenant0catcourse1->id, $subtenant0catcourse0->id,
            $subtenant0catcourse1->id, ], array_keys(search::get_courses_by_category($tenant0category->id)));
        $this->assertCount(0, search::get_courses_by_category($tenant1category->id));

        // Tenant 0 admin can only find onlytenant ones when searching by top category.
        $this->assertEqualsCanonicalizing([$tenant0catcourse0->id, $tenant0catcourse1->id, $subtenant0catcourse0->id,
            $subtenant0catcourse1->id, ], array_keys(search::get_courses_by_category()));

        self::setUser($tenantuser0);

        // Tenant 0 user can only find tenant ones.
        $this->assertCount(0, search::get_courses_by_category($category->id));
        $this->assertEqualsCanonicalizing([$tenant0catcourse0->id, $tenant0catcourse1->id, $subtenant0catcourse0->id,
            $subtenant0catcourse1->id, ], array_keys(search::get_courses_by_category($tenant0category->id)));
        $this->assertCount(0, search::get_courses_by_category($tenant1category->id));

        // Tenant 0 user can only find tenant ones when searching by top category.
        $this->assertEqualsCanonicalizing([$tenant0catcourse0->id, $tenant0catcourse1->id, $subtenant0catcourse0->id,
            $subtenant0catcourse1->id, ], array_keys(search::get_courses_by_category()));
    }

    /**
     * Test search_courses does not disclose hidden courses.
     *
     * @covers \tool_catalogue\local\helpers\search::search_courses
     */
    public function test_search_courses_hidden(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $catcourse1 = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => false]);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);
        $subcatcourse1 = $this->getDataGenerator()->create_course(['category' => $subcategory->id, 'visible' => false]);

        // Admin can see all courses.
        self::setAdminUser();
        $this->assertCount(4, search::search_courses());

        // Use ordinary user.
        self::setUser($user);
        $this->assertEqualsCanonicalizing([$catcourse0->id, $subcatcourse0->id],
            array_keys(search::search_courses()));

        // Grant hidden courses view capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $contextid = \context_system::instance()->id;
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, $contextid);
        role_assign($roleid, $user->id, $contextid);
        accesslib_clear_all_caches_for_unit_testing();

        // Purge cache and test again, all courses should be listed.
        $coursecatcache = \cache::make('core', 'coursecat');
        $coursecatcache->purge();

        $this->assertCount(4, search::search_courses());
    }

    /**
     * Test get_courses_by_category does not disclose hidden courses.
     *
     * @covers \tool_catalogue\local\helpers\search::get_courses_by_category
     */
    public function test_get_courses_by_category_hidden(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $catcourse1 = $this->getDataGenerator()->create_course(['category' => $category->id, 'visible' => false]);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);
        $subcatcourse1 = $this->getDataGenerator()->create_course(['category' => $subcategory->id, 'visible' => false]);

        // Admin can see all courses.
        self::setAdminUser();
        $this->assertCount(4, search::get_courses_by_category($category->id));

        // Use ordinary user.
        self::setUser($user);
        $this->assertEqualsCanonicalizing([$catcourse0->id, $subcatcourse0->id],
            array_keys(search::get_courses_by_category($category->id)));

        // Grant hidden courses view capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $contextid = \context_system::instance()->id;
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, $contextid);
        role_assign($roleid, $user->id, $contextid);
        accesslib_clear_all_caches_for_unit_testing();

        // Purge cache and test again, all courses should be listed.
        $coursecatcache = \cache::make('core', 'coursecat');
        $coursecatcache->purge();

        $this->assertCount(4, search::get_courses_by_category($category->id));
    }

    /**
     * Test search_courses restricted subcategories.
     *
     * @covers \tool_catalogue\local\helpers\search::search_courses
     */
    public function test_search_courses_restricted_subcategory(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $catcourse1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);
        $subcatcourse1 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);

        // Prohibit authenticated user to view courses at subcategory.
        $userrole = $DB->get_record('role', ['shortname' => 'user']);
        assign_capability('moodle/category:viewcourselist', CAP_PROHIBIT, $userrole->id, $subcategory->get_context()->id);

        // Admin can see all courses.
        self::setAdminUser();
        $this->assertCount(4, search::search_courses());

        // Use ordinary user.
        self::setUser($user);
        $this->assertEqualsCanonicalizing([$catcourse0->id, $catcourse1->id],
            array_keys(search::search_courses()));
    }

    /**
     * Test get_courses_by_category restricted subcategories.
     *
     * @covers \tool_catalogue\local\helpers\search::get_courses_by_category
     */
    public function test_get_courses_by_category_restricted_subcategory(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        // Categories.
        $category = $this->getDataGenerator()->create_category();
        $catcourse0 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $catcourse1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $subcatcourse0 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);
        $subcatcourse1 = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);

        // Prohibit authenticated user to view courses at subcategory.
        $userrole = $DB->get_record('role', ['shortname' => 'user']);
        assign_capability('moodle/category:viewcourselist', CAP_PROHIBIT, $userrole->id, $subcategory->get_context()->id);

        // Admin can see all courses.
        self::setAdminUser();
        $this->assertCount(4, search::get_courses_by_category($category->id));

        // Use ordinary user.
        self::setUser($user);
        $this->assertEqualsCanonicalizing([$catcourse0->id, $catcourse1->id],
            array_keys(search::get_courses_by_category($category->id)));
    }

    /**
     * Test get_courses.
     *
     * @covers \tool_catalogue\local\helpers\search::get_courses
     */
    public function test_get_courses(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Categories.
        $category1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
        $category1course = $this->getDataGenerator()->create_course(['category' => $category1->id, 'shortname' => 'tc1',
            'fullname' => 'Mathematics', ]);
        $subcategory1 = $this->getDataGenerator()->create_category(['parent' => $category1->id, 'name' => 'SubCategory 1A']);
        $subcategory1course = $this->getDataGenerator()->create_course(['category' => $subcategory1->id, 'shortname' => 'tc1a',
            'fullname' => 'Astronomy', 'summary' => 'Titan is the largest moon of Saturn. Let\'s study it.', ]);
        $category2 = $this->getDataGenerator()->create_category(['name' => 'Category 2']);
        $category2course = $this->getDataGenerator()->create_course(['category' => $category2->id, 'shortname' => 'tc2',
            'fullname' => 'Laws of Physics', ]);
        $subcategory2 = $this->getDataGenerator()->create_category(['parent' => $category2->id, 'name' => 'SubCategory 2A']);
        $cubcategory2course = $this->getDataGenerator()->create_course(['category' => $subcategory2->id, 'shortname' => 'tc2a',
            'fullname' => 'Is Titan a moon?', 'summary' => 'Astronomy: Deep understanding of our solar system', ]);

        // Check that category = 0 returns all courses.
        $this->assertCount(4, search::get_courses(new filters()));
        $this->assertEquals(4, search::count_courses(new filters()));
        // Check that searching in category2 returns 2 courses.
        $filters = filters::create_from_json(json_encode(['categoryid' => $category2->id]));
        $results = search::get_courses($filters);
        $this->assertEqualsCanonicalizing([$category2course->id, $cubcategory2course->id], array_keys($results));
        $this->assertEquals(2, search::count_courses($filters));

        // Check that only returns the Physics course.
        $filters = filters::create_from_json(json_encode(['searchstring' => 'Physics']));
        $results = search::get_courses($filters);
        $this->assertEquals([$category2course->id], array_keys($results));
        $this->assertEquals(1, search::count_courses($filters));
        // Check that returns no courses with a not found search string.
        $filters = filters::create_from_json(json_encode(['searchstring' => 'Biology']));
        $this->assertCount(0, search::get_courses($filters));
        $this->assertEquals(0, search::count_courses($filters));

        set_config(configuration::SETTING_COURSES_PER_PAGE_MAIN, "2", 'tool_catalogue');

        // Pagination checks.
        // Check that returns 2 courses because by default returns only the 1st page ($category1course and $subcategory1course).
        $results = search::get_courses(new filters(), [], 0, 2);
        $this->assertEqualsCanonicalizing([$category1course->id, $subcategory1course->id], array_keys($results));
        // Check that requesting second page returns the other 2 courses ($category2course and $cubcategory2course).
        $results = search::get_courses(new filters(), [], 2, 2);
        $this->assertEqualsCanonicalizing([$category2course->id, $cubcategory2course->id], array_keys($results));

        // Sorting checks.
        // Check that returns 2 courses, first $subcategory1course because it has the word 'Astronomy' in the fullname,
        // then $cubcategory2course because it has the word in the summary.
        $filters = filters::create_from_json(json_encode(['searchstring' => 'Astronomy']));
        $results = search::get_courses($filters);
        $this->assertEquals([$subcategory1course->id, $cubcategory2course->id], array_keys($results));
        $this->assertEquals(2, search::count_courses($filters));
        // Check that returns 2 courses, first $cubcategory2course because it has the word 'Titan' in the fullname,
        // then $subcategory1course because it has the word in the summary.
        $filters = filters::create_from_json(json_encode(['searchstring' => 'Titan']));
        $results = search::get_courses($filters);
        $this->assertEquals([$cubcategory2course->id, $subcategory1course->id], array_keys($results));
        $this->assertEquals(2, search::count_courses($filters));

        // Check that returns no courses with invalid category id.
        $filters = filters::create_from_json(json_encode(['categoryid' => 123]));
        $this->assertCount(0, search::get_courses($filters));
        $this->assertEquals(0, search::count_courses($filters));
        // Check that returns all courses with no search string.
        $filters = filters::create_from_json(json_encode(['searchstring' => '']));
        $this->assertCount(4, search::get_courses($filters));
        $this->assertEquals(4, search::count_courses($filters));
    }
}
