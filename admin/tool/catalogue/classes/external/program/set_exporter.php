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

namespace tool_catalogue\external\program;

use coding_exception;
use core\external\exporter;
use renderer_base;
use tool_catalogue\router;
use tool_program\persistent\program_set;
use tool_program\program_item;

/**
 * Program set exporter class
 *
 * Exports all data related to a program set.
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class set_exporter extends exporter {

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'programitemset' => program_item::class,
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
                'isset' => [
                    'type' => PARAM_BOOL,
                ],
                'fullname' => [
                    'type' => PARAM_TEXT,
                ],
                'setid' => [
                    'type' => PARAM_INT,
                ],
                'parentsetid' => [
                    'type' => PARAM_INT,
                ],
                'setcriteria' => [
                    'type' => PARAM_INT,
                ],
                'setcriteriastr' => [
                    'type' => PARAM_TEXT,
                ],
                'setcriteriaicon' => [
                    'type' => PARAM_URL,
                ],
                'completeditems' => [
                    'type' => PARAM_INT,
                ],
                'numcourses' => [
                    'type' => PARAM_INT,
                ],
                'iscompleted' => [
                    'type' => PARAM_BOOL,
                ],
                'progress' => [
                    'type' => PARAM_INT,
                ],
                'totalitems' => [
                    'type' => PARAM_INT,
                ],
                'islocked' => [
                    'type' => PARAM_BOOL,
                ],
                'url' => [
                    'type' => PARAM_URL,
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

        /** @var program_item $programitemset */
        $programitemset = $this->related['programitemset'];

        $data = [];
        $data['isset'] = true;
        $data['fullname'] = $programitemset->get_name();
        $data['setid'] = $programitemset->get_persistent()->get('id');
        $data['parentsetid'] = $programitemset->get_parent_id();
        $data['setcriteria'] = $programitemset->get_completion_criteria();
        $data['setcriteriastr'] = $this->get_completion_type_string();
        $data['setcriteriaicon'] = $this->get_completion_type_icon();
        $data['islocked'] = !$programitemset->isunlocked;
        $data['totalitems'] = $programitemset->totalitems;
        $data['completeditems'] = $programitemset->completeditems;
        $data['numcourses'] = $this->get_set_num_courses($programitemset);
        $data['progress'] = $programitemset->progresspercentage;
        $data['iscompleted'] = $programitemset->iscompleted;
        $program = $programitemset->get_persistent()->get_program();
        $data['url'] = router::build_program_set_url($program->get('id'), $programitemset->get_persistent()->get('id'));
        if ($data['parentsetid'] === $program->get_base_set()->get('id')) {
            $data['parentsetid'] = 0;
        }

        return $data;
    }

    /**
     * Get completion type icon.
     *
     * @return string
     */
    private function get_completion_type_icon(): string {
        global $OUTPUT;

        /** @var program_item $programitemset */
        $programitemset = $this->related['programitemset'];

        switch ($programitemset->get_completion_criteria()) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
                return $OUTPUT->image_url('all-in-any-order', 'tool_catalogue')->out(false);
            case program_set::COMPLETION_ALL_IN_ORDER:
                return $OUTPUT->image_url('all-in-order', 'tool_catalogue')->out(false);
            case program_set::COMPLETION_AT_LEAST:
                return $OUTPUT->image_url('at-least-x', 'tool_catalogue')->out(false);
            default:
                throw new coding_exception('Unexpected program set completion criteria');
        }
    }

    /**
     * Get completion type string.
     *
     * @return string
     */
    private function get_completion_type_string(): string {

        /** @var program_item $programitemset */
        $programitemset = $this->related['programitemset'];

        switch ($programitemset->get_completion_criteria()) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
                return get_string('completeallinanyorder', 'tool_program');
            case program_set::COMPLETION_ALL_IN_ORDER:
                return get_string('completeallinorder', 'tool_program');
            case program_set::COMPLETION_AT_LEAST:
                return get_string('completeatleast', 'tool_catalogue', $programitemset->get_completion_atleast());
            default:
                throw new coding_exception('Unexpected program set completion criteria');
        }
    }

    /**
     * Return total amount of courses in a set (and the sets within this one)
     *
     * @param program_item $set
     * @return int
     */
    private function get_set_num_courses(program_item $set): int {
        $totalcourses = 0;

        foreach ($set->items as $item) {
            if ($item->is_set()) {
                $totalcourses += $this->get_set_num_courses($item); // Recursion.
            } else {
                $totalcourses++;
            }
        }

        return $totalcourses;
    }
}
