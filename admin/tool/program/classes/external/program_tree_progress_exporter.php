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
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\external;

use context;
use core\external\exporter;
use html_writer;
use renderer_base;
use stdClass;
use tool_program\api;
use tool_program\persistent\program;
use tool_program\program_item;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting field data.
 *
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_tree_progress_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'user' => 'stdClass',
            'programtreeprogress' => program_tree_progress::class,
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
                'type' => program_set_progress_exporter::read_properties_definition(),
            ],
        ];
    }

    /**
     * Get other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var context $context */
        $context = $this->related['context'];
        /** @var stdClass $user */
        $user = $this->related['user'];
        /** @var program_tree_progress $programtreeprogress */
        $programtreeprogress = $this->related['programtreeprogress'];
        $parentset = $programtreeprogress->get_baseset();

        $exportedparentset = $this->export_program_set_with_progress($parentset, $output, $context);
        $this->export_program_call_to_action($programtreeprogress, $exportedparentset, $user->id);
        $this->export_courses_unlock_requirements($exportedparentset);

        return [
            'parentset' => $exportedparentset,
        ];
    }

    /**
     * Export set with progress and locked information (recursively)
     *
     * @param program_item $set
     * @param renderer_base $output
     * @param context $context
     * @return stdClass
     */
    private function export_program_set_with_progress(program_item $set, renderer_base $output, context $context): stdClass {
        $exporter = new program_set_progress_exporter($set->get_persistent(), [
            'context' => $context,
            'programitemset' => $set,
        ]);
        $exporteset = $exporter->export($output);

        $exporteset->items = [];
        foreach ($set->items as $childitem) {
            if ($childitem->is_set()) {
                $exporteset->items[] = $this->export_program_set_with_progress($childitem, $output, $context); // Recursion.
            } else if ($childitem->is_course()) {
                $exporteset->items[] = $this->export_program_course_with_progress($childitem, $output);
            }
        }

        return $exporteset;
    }

    /**
     * Exports program course.
     *
     * @param program_item $childitem
     * @param renderer_base $output
     * @return stdClass
     */
    private function export_program_course_with_progress(program_item $childitem, renderer_base $output): stdClass {
        $exporter = new program_course_progress_exporter(null, [
            'context' => $this->related['context'],
            'programitemcourse' => $childitem,
        ]);
        return $exporter->export($output);
    }

    /**
     * Calculates and exports data related to the main button/call to action of the program in the programs overview.
     *
     * @param program_tree_progress $programtree
     * @param stdClass $exported
     * @param int $userid
     */
    private function export_program_call_to_action(program_tree_progress $programtree, stdClass $exported, int $userid): void {
        // Defaults.
        $exported->calltoaction = null;
        $exported->ongoingcourseid = null;
        $exported->calltoactionstr = null;
        $exported->ongoingisenrolled = null;

        $baseset = $programtree->get_baseset();
        if ($baseset->iscompleted) {
            $exported->calltoaction = 'completed';
            $exported->calltoactionstr = get_string('completed', 'tool_program');
            $exported->ongoingcourseid = null;
            $exported->ongoingisenrolled = null;
        } else {
            $exported->calltoaction = $baseset->progresspercentage <= 0 ? 'start' : 'continue';
            $exported->ongoingcourseid = $programtree->get_ongoing_courseid();
            $exported->ongoingisenrolled = null;
            if (null !== $exported->ongoingcourseid) {
                $exported->ongoingisenrolled = api::is_actively_enrolled_with_enrol_program($baseset->get_programid(),
                    $exported->ongoingcourseid, $userid);
                if ($exported->ongoingisenrolled && $exported->calltoaction === 'start') {
                    // If call to action is "start" but ongoing course has some progress, change it to "continue".
                    $courseitem = $programtree->get_first_program_course_item_by_courseid($exported->ongoingcourseid);
                    if ($courseitem && $courseitem->completeditems > 0) {
                        $exported->calltoaction = 'continue';
                    }
                }
            }
            $exported->calltoactionstr = get_string($exported->calltoaction, 'tool_program');
        }
    }

    /**
     * Calculates recursively the requirement for a locked course to be unlocked by the user.
     *
     * @param stdClass $exportedset
     * @return void
     */
    private function export_courses_unlock_requirements(stdClass $exportedset): void {
        switch ($exportedset->completiontype) {
            case 'completeallinanyorder':
            case 'completeatleast':
                foreach ($exportedset->items as $childitem) {
                    if (!$exportedset->unlocked && !empty($childitem->courseid)) {
                        // All immediate children courses are locked/pending because of the parent being locked.
                        if ($childitem->iscompleted) {
                            $strid = 'pendingreasonparent';
                        } else {
                            $strid = 'lockedreasonparent';
                        }
                        $childitem->unlockrequirement = get_string($strid, 'tool_program', $exportedset->name);
                    }
                    if (empty($childitem->courseid)) {
                        // Calculate course unlock requirements of this set children.
                        $this->export_courses_unlock_requirements($childitem);
                    }
                }
                break;
            case 'completeallinorder':
                /** @var stdClass|null $previousitem */
                $previousitem = null;
                foreach ($exportedset->items as $childitem) {
                    if ($previousitem === null && !$exportedset->unlocked && !empty($childitem->courseid)) {
                        if ($childitem->iscompleted) {
                            $strid = 'pendingreasonparent';
                        } else {
                            $strid = 'lockedreasonparent';
                        }
                        $childitem->unlockrequirement = get_string($strid, 'tool_program', $exportedset->name);
                    }
                    if ($previousitem !== null && !$childitem->unlocked && !empty($childitem->courseid)) {
                        if ($childitem->iscompleted) {
                            $strid = empty($previousitem->courseid) ? 'pendingreasonpreviousset' : 'pendingreasonpreviouscourse';
                        } else {
                            $strid = empty($previousitem->courseid) ? 'lockedreasonpreviousset' : 'lockedreasonpreviouscourse';
                        }
                        $childitem->unlockrequirement = get_string($strid, 'tool_program', $previousitem->name);
                    }
                    if (empty($childitem->courseid)) {
                        // Calculate course unlock requirements of this set children.
                        $this->export_courses_unlock_requirements($childitem);
                    }
                    $previousitem = $childitem;
                }
                break;
        }
    }
}
