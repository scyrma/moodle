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
 * File containing tests for tool_certification\classes\tags_manager class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * TFile containing tests for tool_certification\classes\tags_manager class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_tags_manager_testcase extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
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
        $this->assertStringContainsString($certification->get('fullname'), $res->content);
        $this->assertStringNotContainsString($certification2->get('fullname'), $res->content);
        $this->assertStringNotContainsString($certification3->get('fullname'), $res->content);

        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'bye'),
            /*$exclusivemode = */false, /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */0);
        $this->assertStringNotContainsString($certification->get('fullname'), $res->content);
        $this->assertStringContainsString($certification2->get('fullname'), $res->content);
        $this->assertStringContainsString($certification3->get('fullname'), $res->content);

        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'dog'),
            /*$exclusivemode = */false, /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */0);
        $this->assertStringNotContainsString($certification->get('fullname'), $res->content);
        $this->assertStringContainsString($certification2->get('fullname'), $res->content);
        $this->assertStringNotContainsString($certification3->get('fullname'), $res->content);

        // Not context system.
        $course = self::getDataGenerator()->create_course();
        $othercontext = context_course::instance($course->id)->id;
        $res = \tool_certification\tags_manager::get_tagged_certifications(core_tag_tag::get_by_name(0, 'bye'),
            /*$exclusivemode = */false, /*$fromctx = */$othercontext, /*$ctx = */$othercontext, /*$rec = */1, /*$page = */0);
        $this->assertStringNotContainsString($certification->get('fullname'), $res->content);
        $this->assertStringNotContainsString($certification2->get('fullname'), $res->content);
        $this->assertStringNotContainsString($certification3->get('fullname'), $res->content);
    }
}
