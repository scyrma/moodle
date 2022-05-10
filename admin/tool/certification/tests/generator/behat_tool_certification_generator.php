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
 * Behat plugin generator
 *
 * @package    tool_certification
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Behat plugin generator
 *
 * @package    tool_certification
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_certification_generator extends behat_generator_base {

    /**
     * Get a list of the entities that can be created for this component
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'certifications' => [
                'datagenerator' => 'certification',
                'required' => [
                    'fullname'
                ],
                'switchids' => [
                    'tenant' => 'tenantid',
                    'program' => 'program',
                    'recertificationprogram' => 'recertificationprogram'
                ],
            ],
            'certification_users' => [
                'datagenerator' => 'certification_user',
                'required' => [
                    'user',
                    'certification',
                ],
                'switchids' => [
                    'user' => 'userid',
                    'certification' => 'certificationid',
                ],
            ],
        ];
    }

    /**
     * Get program id
     *
     * @param string $fullname
     * @return int
     */
    protected function get_program_id(string $fullname): int {
        global $DB;
        return $DB->get_field('tool_program', 'id', ['fullname' => $fullname], MUST_EXIST);
    }

    /**
     * Gets the tenant id.
     *
     * @param string $tenantname
     * @return int
     */
    protected function get_tenant_id(string $tenantname): int {
        global $DB;
        return $DB->get_field('tool_tenant', 'id', ['name' => $tenantname], MUST_EXIST);
    }

    /**
     * Get certification id
     *
     * @param string $fullname
     * @return int
     */
    protected function get_certification_id(string $fullname): int {
        global $DB;
        return $DB->get_field('tool_certification', 'id', ['fullname' => $fullname], MUST_EXIST);
    }

    /**
     * Get recertification program id
     *
     * @param string $fullname
     * @return int
     */
    protected function get_recertificationprogram_id(string $fullname): int {
        return $this->get_program_id($fullname);
    }
}
