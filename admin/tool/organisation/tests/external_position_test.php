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

namespace tool_organisation;

use externallib_advanced_testcase;
use invalid_parameter_exception;
use stdClass;
use tool_organisation\external\get_potential_parent_positions;
use tool_organisation_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Get potential parents for positions unit test class
 *
 * @package   tool_organisation
 * @covers    \tool_organisation\external\get_potential_parent_positions
 * @covers    \tool_organisation\external\update_job
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Odei Alba <odei.alba@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_position_test extends externallib_advanced_testcase {
    /** @var stdClass */
    protected $fw1;
    /** @var stdClass */
    protected $fw2;
    /** @var stdClass */
    protected $p11;
    /** @var stdClass */
    protected $p12;
    /** @var stdClass */
    protected $p111;
    /** @var stdClass */
    protected $p112;
    /** @var stdClass */
    protected $p113;
    /** @var stdClass */
    protected $p121;
    /** @var stdClass */
    protected $p122;
    /** @var stdClass */
    protected $p123;
    /** @var stdClass */
    protected $p1231;
    /** @var stdClass */
    protected $p21;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_tenant_organisation_structure();
    }

    /**
     *  Test failure getting parents without providing any position or framework
     */
    public function test_fail_get_potential_parent_positions() {
        $this->expectException(invalid_parameter_exception::class);
        get_potential_parent_positions::execute('', 0, 0);
    }

    /**
     *  Test getting potential parent positions
     */
    public function test_get_potential_parent_positions() {
        // Providing a position id, it should return the main framework and all the positions under it that are not under the given
        // position.
        $potentialparents = get_potential_parent_positions::execute('', $this->p11->id, 0);
        $potentialparentids = array_column($potentialparents, 'id');
        $expectedpotentialparentids = [
            $this->fw1->id,
            $this->p12->id,
            $this->p121->id,
            $this->p122->id,
            $this->p123->id,
            $this->p1231->id,
        ];

        $this->assertEqualsCanonicalizing($expectedpotentialparentids, $potentialparentids);

        $potentialparents2 = get_potential_parent_positions::execute('', $this->p21->id, 0);
        $potentialparentids2 = array_column($potentialparents2, 'id');
        $expectedpotentialparentids2 = [
            $this->fw2->id,
        ];

        $this->assertEqualsCanonicalizing($expectedpotentialparentids2, $potentialparentids2);

        // Providing a framework id, it should return the given framework and all the positions under it.
        $potentialparentsnew = get_potential_parent_positions::execute('', 0, $this->fw1->id);
        $potentialparentidsnew = array_column($potentialparentsnew, 'id');
        $expectedpotentialparentidsnew = [
            $this->fw1->id,
            $this->p11->id,
            $this->p111->id,
            $this->p112->id,
            $this->p113->id,
            $this->p12->id,
            $this->p121->id,
            $this->p122->id,
            $this->p123->id,
            $this->p1231->id,
        ];

        $this->assertEqualsCanonicalizing($expectedpotentialparentidsnew, $potentialparentidsnew);

        $potentialparentsnew2 = get_potential_parent_positions::execute('', 0, $this->fw2->id);
        $potentialparentidsnew2 = array_column($potentialparentsnew2, 'id');
        $expectedpotentialparentidsnew2 = [
            $this->fw2->id,
            $this->p21->id,
        ];

        $this->assertEqualsCanonicalizing($expectedpotentialparentidsnew2, $potentialparentidsnew2);
    }

    /**
     * Create positions structure data
     */
    protected function create_tenant_organisation_structure() {
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        /*
         * Current position structure:
         * fw1
         *      p11
         *          p111
         *          p112
         *          p113
         *      p12
         *          p121
         *          p122
         *          p123
         *              p1231
         * fw2
         *      p21
         */

        $this->fw1 = $generator->create_position();
        $this->fw2 = $generator->create_position();

        $this->p11 = $generator->create_position(['parentid' => $this->fw1->id]);
        $this->p111 = $generator->create_position(['parentid' => $this->p11->id]);
        $this->p112 = $generator->create_position(['parentid' => $this->p11->id]);
        $this->p113 = $generator->create_position(['parentid' => $this->p11->id]);
        $this->p12 = $generator->create_position(['parentid' => $this->fw1->id]);
        $this->p121 = $generator->create_position(['parentid' => $this->p12->id]);
        $this->p122 = $generator->create_position(['parentid' => $this->p12->id]);
        $this->p123 = $generator->create_position(['parentid' => $this->p12->id]);
        $this->p1231 = $generator->create_position(['parentid' => $this->p123->id]);
        $this->p21 = $generator->create_position(['parentid' => $this->fw2->id]);
    }
}
