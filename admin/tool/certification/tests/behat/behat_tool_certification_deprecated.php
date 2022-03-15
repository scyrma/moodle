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
 * Steps definitions for deprecated plugin Behat steps
 *
 * @package   tool_certification
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/tests/behat/behat_deprecated.php');

/**
 * Class containing deprecated plugin steps
 *
 * @package   tool_certification
 * @category  test
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_certification_deprecated extends behat_deprecated {

    /**
     * Creates the specified data in the given tool_certification entity.
     *
     * @Given /^the following tool certification data "(?P<element_string>(?:[^"]|\\")*)" exist:$/
     *
     * @param string $elementname The name of the entity to add
     * @param TableNode $data
     *
     * @deprecated Please use {@see behat_data_generators::the_following_entities_exist}
     */
    public function the_following_tool_certification_data_exist($elementname, TableNode $data): void {
        $this->deprecated_message(['behat_data_generators::the_following_entities_exist']);

        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_certification > certifications', $data]);
    }

    /**
     * Allocates users to certifications
     *
     * @Given /^the following users allocations to certifications exist:$/
     *
     * @param TableNode $data
     *
     * @deprecated Please use {@see behat_data_generators::the_following_entities_exist}
     */
    public function the_following_user_allocations_to_certifications_exist(TableNode $data) {
        $this->deprecated_message(['behat_data_generators::the_following_entities_exist']);

        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_certification > certification_users', $data]);
    }
}
