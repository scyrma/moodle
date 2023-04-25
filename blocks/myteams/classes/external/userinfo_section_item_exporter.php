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

namespace block_myteams\external;

use block_myteams\userinfo_section_item;
use context_system;
use core\external\exporter;
use renderer_base;

/**
 * User info section items exporter class
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userinfo_section_item_exporter extends exporter {

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'item' => userinfo_section_item::class,
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'title' => ['type' => PARAM_TEXT],
            'subtitle' => ['type' => PARAM_TEXT],
            'badges' => [
                'multiple' => true,
                'type' => [
                    'label' => ['type' => PARAM_TEXT],
                    'type' => ['type' => PARAM_TEXT],
                ],
                'optional' => true
            ],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var userinfo_section_item $item */
        $item = $this->related['item'];

        return [
            'title' => $item->get_title(),
            'subtitle' => $item->get_subtitle(),
            'badges' => $item->get_badges()
        ];
    }

    /**
     * Get the context fot the title field.
     *
     * @return array
     */
    protected function get_format_parameters_for_title(): array {
        return [
            'context' => context_system::instance()
        ];
    }

    /**
     * Get the context fot the subtitle field.
     *
     * @return array
     */
    protected function get_format_parameters_for_subtitle(): array {
        return [
            'context' => context_system::instance()
        ];
    }
}
