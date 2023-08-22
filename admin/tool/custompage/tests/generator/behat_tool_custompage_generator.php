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

declare(strict_types=1);

use tool_custompage\local\models\page;

/**
 * Plugin Behat data generator
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_custompage_generator extends behat_generator_base {

    /**
     * Get a list of the entities that can be created for this component
     *
     * @return array[]
     */
    protected function get_creatable_entities(): array {
        return [
            'Pages' => [
                'singular' => 'Page',
                'datagenerator' => 'page',
                'required' => [
                    'name',
                    'weight',
                ],
                'switchids' => [
                    'tenant' => 'tenantid',
                ],
            ],
            'Blocks' => [
                'singular' => 'Block',
                'datagenerator' => 'page_block',
                'required' => [
                    'page',
                    'blockname',
                ],
                'switchids' => [
                    'page' => 'pageid',
                ],
            ],
            'Audiences' => [
                'singular' => 'Audience',
                'datagenerator' => 'audience',
                'required' => [
                    'page',
                    'configdata',
                ],
                'switchids' => [
                    'page' => 'pageid',
                ],
            ]
        ];
    }

    /**
     * Look up tenant ID from given name
     *
     * @param string $name
     * @return int
     */
    protected function get_tenant_id(string $name): int {
        global $DB;

        return (int) $DB->get_field('tool_tenant', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Look up page ID from given name
     *
     * @param string $name
     * @return int
     */
    protected function get_page_id(string $name): int {
        global $DB;

        return (int) $DB->get_field(page::TABLE, 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Pre-process audience entity, generate correct config structure
     *
     * @param array $audience
     * @return array
     */
    protected function preprocess_audience(array $audience): array {
        $audience['configdata'] = (array) json_decode($audience['configdata']);

        return $audience;
    }
}
