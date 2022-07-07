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

namespace tool_catalogue\output;

use context_system;
use renderable;
use renderer_base;
use templatable;
use tool_catalogue\router;
use tool_program\persistent\program;
use tool_program\persistent\program_set;

/**
 * Class to prepare the program set content (directly accessing a set) for display.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_set_content implements templatable, renderable {

    /** @var program The program instance */
    private $program;
    /** @var program_set The program set instance */
    private $set;
    /** @var array Program sets */
    private $programsets;

    /**
     * Constructor
     *
     * @param program $program
     * @param program_set $set
     */
    public function __construct(program $program, program_set $set) {
        global $DB;

        $this->program = $program;
        $this->set = $set;
        // Get data from all sets in the program.
        $this->programsets = $DB->get_records('tool_program_sets', ['programid' => $this->program->get('id')]);
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        $contentview = new \tool_catalogue\output\program_content($this->program);
        $data = $contentview->export_for_template($output);
        $navitems = $this->get_set_navigation_item($this->set->get('id'));
        // We need to encode navitems, so the template can read them and decode for the JS.
        $data->navitems = json_encode($navitems, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return $data;
    }

    /**
     * Recursively get set navigation items
     *
     * @param int $setid
     * @return array
     */
    private function get_set_navigation_item(int $setid): array {
        $items = [];
        $set = $this->programsets[$setid];
        $parentset = $this->programsets[$set->parent];

        if ((int) $set->parent !== 0) {
            $options = ['context' => context_system::instance()];
            // We need to replace " with \" so JSON won't break in the JS.
            $items[] = (object)[
                'url' => router::build_program_set_url($this->program->get('id'), (int) $set->id)->out(),
                'name' => str_replace('"', '\"', format_string($set->name, true, $options)),
                'setid' => (int) $set->id,
            ];
            if ((int) $parentset->parent !== 0) {
                $items = array_merge($items, $this->get_set_navigation_item((int) $set->parent)); // Recursion.
            }
        }

        return array_reverse($items);
    }
}
