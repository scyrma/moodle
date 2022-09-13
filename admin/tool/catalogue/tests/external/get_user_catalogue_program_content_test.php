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

namespace tool_catalogue\external;

use external_api;
use externallib_advanced_testcase;
use html_writer;
use moodle_exception;
use tool_catalogue\router;
use tool_program\persistent\program_set;
use tool_program_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_catalogue get_user_catalogue_program_content external class.
 *
 * @covers     \tool_catalogue\external\get_user_catalogue_program_content
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue_program_content_test extends externallib_advanced_testcase {

    /**
     * Test execute
     */
    public function test_execute(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $course1 = $programgenerator->generate_course_with_completion_self();
        $course2 = $programgenerator->generate_course_with_completion_self();
        $course3 = $programgenerator->generate_course_with_completion_self();
        $program = $programgenerator->generate_program();
        $baseset = $program->get_base_set();
        $baseset->set('completioncriteria', program_set::COMPLETION_AT_LEAST);
        $baseset->set('completionatleast', 1);
        $baseset->update();
        $set1 = $programgenerator->generate_set((object) [
            'name' => 'My child set 1',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 1,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);

        $programgenerator->add_course_to_set((int) $course1->id, $baseset->get('id'), 2);
        $programgenerator->add_course_to_set((int) $course2->id, $set1->get('id'), 1);
        // Add course3 to set1 to check lock restrictions.
        $programgenerator->add_course_to_set((int) $course3->id, $set1->get('id'), 2);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        $result = get_user_catalogue_program_content::execute($program->get('id'), (int) $USER->id);
        $cleanresult = external_api::clean_returnvalue(get_user_catalogue_program_content::execute_returns(), $result);

        $this->assertCount(2, $cleanresult['sets']);
        $this->assertCount(3, $cleanresult['courses']);

        // Check both sets.
        $basesetdata = array_filter($cleanresult['sets'], static function(array $set): bool {
            return $set['parentsetid'] === 0;
        });
        $basesetdata = reset($basesetdata);
        $this->assertTrue($basesetdata['isset']);
        $this->assertEquals($baseset->get('id'), $basesetdata['setid']);
        $this->assertEquals($baseset->get('parent'), $basesetdata['parentsetid']);
        $this->assertEquals($baseset->get('sortorder'), $basesetdata['sortorder']);
        $this->assertEquals($baseset->get('completioncriteria'), $basesetdata['setcriteria']);
        $this->assertEquals(get_string('completeatleast', 'tool_catalogue', '1'), $basesetdata['setcriteriastr']);
        $this->assertEquals(0, $basesetdata['completeditems']);
        $this->assertEquals(3, $basesetdata['numcourses']);
        $this->assertFalse($basesetdata['iscompleted']);
        $this->assertEquals(0, $basesetdata['progress']);
        $this->assertEquals(2, $basesetdata['totalitems']);
        $this->assertFalse($basesetdata['islocked']);

        // Assert base set contains Set1.
        $set1data = array_filter($cleanresult['sets'], static function(array $set): bool {
            return $set['parentsetid'] !== 0;
        });
        $set1data = reset($set1data);
        $this->assertEquals($set1->get('id'), $set1data['setid']);
        $this->assertEquals($baseset->get('id'), $set1data['parentsetid']);
        $this->assertEquals(2, $set1data['totalitems']);

        // Assert base set contains Course1.
        $course1data = array_filter($cleanresult['courses'], static function(array $course) use ($course1): bool {
            return $course['courseid'] === (int) $course1->id;
        });
        $course1data = reset($course1data);
        $this->assertFalse($course1data['isset']);
        $this->assertEquals($baseset->get('id'), $course1data['setid']);
        $this->assertEquals($course1->id, $course1data['courseid']);
        $this->assertEquals(2, $course1data['sortorder']);
        $this->assertEquals($course1->fullname, $course1data['fullname']);
        $this->assertFalse($course1data['iscompleted']);
        $this->assertEquals(0, $course1data['progress']);
        $this->assertFalse($course1data['islocked']);
        $this->assertFalse($course1data['ishidden']);
        $this->assertNotEmpty($course1data['image']);
        $this->assertNotEmpty($course1data['url']);

        // Assert Set1 contains Course2.
        $course2data = array_filter($cleanresult['courses'], static function(array $course) use ($course2): bool {
            return $course['courseid'] === (int) $course2->id;
        });
        $course2data = reset($course2data);
        $this->assertFalse($course2data['isset']);
        $this->assertEquals($set1->get('id'), $course2data['setid']);
        $this->assertEquals($course2->id, $course2data['courseid']);

        // Assert Set1 contains Course3.
        $course3data = array_filter($cleanresult['courses'], static function(array $course) use ($course3): bool {
            return $course['courseid'] === (int) $course3->id;
        });
        $course3data = reset($course3data);
        $this->assertFalse($course3data['isset']);
        $this->assertEquals($set1->get('id'), $course3data['setid']);
        $this->assertEquals($course3->id, $course3data['courseid']);
        $link = html_writer::link(router::build_course_url((int) $course2->id), $course2->fullname);
        $this->assertEquals(get_string('notavailableuntil', 'tool_catalogue', $link), $course3data['unlockrequirement']);
    }

    /**
     * Test execute when requested userid does not exist
     */
    public function test_execute_exception_invalid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid user');
        get_user_catalogue_program_content::execute(1234, 1234);
    }

    /**
     * Test execute when requested userid is suspended
     */
    public function test_execute_exception_suspended(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Suspended account');
        get_user_catalogue_program_content::execute(1234, $user->id);
    }

    /**
     * Test execute when requested program does not exist
     */
    public function test_execute_exception_invalid2(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Can\'t find data record in database table tool_program');
        get_user_catalogue_program_content::execute(1234, (int) $user->id);
    }
}
