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

use block_myteams\userinfo_section;
use context_system;
use core\external\exporter;
use renderer_base;
use stdClass;

/**
 * User info section exporter class
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userinfo_section_exporter extends exporter {

    // TODO: This cap should be also modified in theme/workplace/scss/workplace/plugins/block_myteams.scss.
    /** @var int Maximim number of elements to show in description, before appending "plus X more" */
    private const SHOW_MORE_LIMIT = 3;

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'section' => userinfo_section::class,
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'link' => ['type' => PARAM_LOCALURL, 'null' => NULL_ALLOWED],
            'order' => ['type' => PARAM_INT],
            'items' => [
                'multiple' => true,
                'type' => userinfo_section_item_exporter::read_properties_definition()
            ],
            'showmorecount' => ['type' => PARAM_INT],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var userinfo_section $section */
        $section = $this->related['section'];

        $items = array_map(static function($item) use ($output): stdClass {
            return (new userinfo_section_item_exporter(null, ['item' => $item]))->export($output);
        }, $section->get_items());

        return [
            'name' => $section->get_name(),
            'link' => $section->get_link(),
            'order' => $section->get_order(),
            'items' => $items,
            'showmorecount' => max(0, count($items) - self::SHOW_MORE_LIMIT)
        ];
    }

    /**
     * Get the context fot the name field.
     *
     * @return array
     */
    protected function get_format_parameters_for_name(): array {
        return [
            'context' => context_system::instance()
        ];
    }
}
