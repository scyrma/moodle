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
 * File containing tests for tool_certification\classes\tags_manager class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * TFile containing tests for tool_certification\classes\tags_manager class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_tags_manager_testcase extends advanced_testcase {
    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    public function test_get_tagged_certifications() {
        $certdata = $this->generator->get_dummy_certificationdata();
        $certification = \tool_certification\api::create_certification($certdata);
        $certdata->fullname = 'Program example two';
        $certdata->certification_tags = ['bye', 'dog'];
        $certification2 = \tool_certification\api::create_certification($certdata);
        $certdata->fullname = 'Program example three';
        $certdata->certification_tags = ['bye', 'cat'];
        $certification3 = \tool_certification\api::create_certification($certdata);

        $this->setAdminUser();

        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'hello'),
            /*$exclusivemode = */false, /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */0);
        $this->assertRegExp('/'.$certification->get('fullname').'/', $res->content);
        $this->assertNotRegExp('/'.$certification2->get('fullname').'/', $res->content);
        $this->assertNotRegExp('/'.$certification3->get('fullname').'/', $res->content);

        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'bye'),
            /*$exclusivemode = */false, /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */0);
        $this->assertNotRegExp('/'.$certification->get('fullname').'/', $res->content);
        $this->assertRegExp('/'.$certification2->get('fullname').'/', $res->content);
        $this->assertRegExp('/'.$certification3->get('fullname').'/', $res->content);

        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'dog'),
            /*$exclusivemode = */false, /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */0);
        $this->assertNotRegExp('/'.$certification->get('fullname').'/', $res->content);
        $this->assertRegExp('/'.$certification2->get('fullname').'/', $res->content);
        $this->assertNotRegExp('/'.$certification3->get('fullname').'/', $res->content);

        // Not context system.
        $course = self::getDataGenerator()->create_course();
        $othercontext = context_course::instance($course->id)->id;
        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'bye'),
            /*$exclusivemode = */false, /*$fromctx = */$othercontext, /*$ctx = */$othercontext, /*$rec = */1, /*$page = */0);

        $this->assertNotRegExp('/'.$certification->get('fullname').'/', $res->content);
        $this->assertNotRegExp('/'.$certification2->get('fullname').'/', $res->content);
        $this->assertNotRegExp('/'.$certification3->get('fullname').'/', $res->content);
    }
}