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
 * Class for exporting program course data.
 *
 * @package    tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\external;

use context;
use core\external\exporter;
use renderer_base;
use stdClass;
use tool_program\program_tree;
use tool_program\program_item;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting field data.
 *
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_tree_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'programtree' => program_tree::class,
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'parentset' => [
                'type' => program_set_exporter::read_properties_definition(),
            ],
        ];
    }

    /**
     * Get other values.
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var context $context */
        $context = $this->related['context'];

        $data = [];

        /** @var program_tree $programtree */
        $programtree = $this->related['programtree'];
        $parentset = $programtree->get_baseset();
        $exportedparentset = $this->export_program_set($parentset, $output);

        $data['parentset'] = $exportedparentset;

        return $data;
    }

    /**
     * Add set completion forms.
     *
     * @param program_item $set
     * @param renderer_base $output
     * @return stdClass
     */
    private function export_program_set(program_item $set, renderer_base $output): stdClass {
        $exporter = new program_set_exporter($set->get_persistent(), ['context' => $this->related['context']]);
        $exportedset = $exporter->export($output);
        $exportedset->level = $set->level;

        $exportedset->items = [];
        foreach ($set->items as $childitem) {
            if ($childitem->is_set()) {
                $exportedset->items[] = $this->export_program_set($childitem, $output);
            } else if ($childitem->is_course()) {
                $exportedset->items[] = $this->export_program_course($childitem, $output);
            }
        }

        return $exportedset;
    }

    /**
     * Exports a program course.
     *
     * @param program_item $childitem
     * @param renderer_base $output
     * @return stdClass
     */
    private function export_program_course(program_item $childitem, renderer_base $output): stdClass {
        $exporter = new program_course_exporter($childitem->get_persistent(), [
            'context' => $this->related['context'],
            'programid' => $childitem->get_programid(),
            'course' => $childitem->get_course(),
        ]);
        $exportedcourse = $exporter->export($output);
        $exportedcourse->level = $childitem->level;

        return $exportedcourse;
    }
}
