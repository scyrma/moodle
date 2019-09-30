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
 * Program tree class for tool_program
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program;

use coding_exception;
use completion_info;
use stdClass;
use tool_program\event\program_completed;
use tool_program\event\program_set_completed;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_tree_with_progress. Builds a program tree with progress data.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_tree_progress extends program_tree {
    /** @var int */
    private $userid;

    /** @var array $coursenrolled */
    private $coursenrolled = [];

    /**
     * program_tree constructor.
     *
     * @param program $program
     * @param int $userid
     */
    public function __construct(program $program, int $userid) {
        parent::__construct($program);
        $this->userid = $userid;
        $this->calculate_sets_progress($this->get_baseset());
        $this->calculate_items_locked_state($this->get_baseset());
    }

    /**
     * Calculates the program set progress and other progress related data for the given set and its children sets and courses.
     *
     * @param program_item $set
     * @param program_item|null $parentset
     */
    private function calculate_sets_progress(program_item $set, ?program_item $parentset = null): void {
        // The parent set progress depends on the children, so we calculate the progress of the children first.
        foreach ($set->items as $item) {
            if ($item->is_set()) {
                $this->calculate_sets_progress($item, $set); // Recursion.
            } else if ($item->is_course()) {
                $this->calculate_course_progress($item, $set);
            } else {
                throw new coding_exception('Unexpected program tree item type');
            }
        }

        // The children progress has been already calculated, so now we calculate the current set progress.
        switch ($set->get_completion_criteria()) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
            case program_set::COMPLETION_ALL_IN_ORDER:
                $this->calculate_completion_all_set_progress($set, $parentset);
                break;
            case program_set::COMPLETION_AT_LEAST:
                $this->calculate_completion_at_least_set_progress($set, $parentset);
                break;
            default:
                throw new coding_exception('Unexpected program set completion criteria');
        }
    }

    /**
     * Calculates locked state of the given program set or course.
     *
     * @param program_item $set
     * @param bool $firstchild
     */
    private function calculate_items_locked_state(program_item $set, bool $firstchild = true): void {
        $nextitemunlocked = false;
        if ($set->is_base_set()) {
            $set->isunlocked = true;
        }
        $completiontype = $set->get_completion_criteria();
        if ($completiontype === program_set::COMPLETION_ALL_IN_ANY_ORDER || $completiontype === program_set::COMPLETION_AT_LEAST) {
            foreach ($set->items as $childitem) {
                if (!$set->isunlocked) {
                    // Parent set is not unlocked => Children are not unlocked.
                    $childitem->isunlocked = false;
                } else {
                    // All children items are unlocked so the user can start in any order.
                    $childitem->isunlocked = true;
                }
                // If is a set call recursively.
                if ($childitem->is_set()) {
                    $this->calculate_items_locked_state($childitem, $firstchild);
                }
            }
        } else if ($completiontype === program_set::COMPLETION_ALL_IN_ORDER) {
            foreach ($set->items as $childitem) {
                if (!$set->isunlocked) {
                    // Parent set is not unlocked => Children are not unlocked.
                    $childitem->isunlocked = false;
                    $nextitemunlocked = false;
                } else if ($firstchild) {
                    // Parent set is unlocked => First item in tree must be unlocked so user can start working.
                    $childitem->isunlocked = true;
                    $nextitemunlocked = $childitem->iscompleted;
                    $firstchild = false;
                } else if ($nextitemunlocked) {
                    // Current item should be unlocked according to previous iterations.
                    $childitem->isunlocked = true;
                    $nextitemunlocked = $childitem->iscompleted;
                } else {
                    // Current item should be locked according to previous iterations.
                    $childitem->isunlocked = false;
                    $nextitemunlocked = false;
                }
                if ($childitem->is_set()) {
                    $this->calculate_items_locked_state($childitem);
                }
            }
        } else {
            throw new coding_exception('Unexpected completion criteria');
        }
    }

    /**
     * Calculates the progress and other progress related data for the given program course.
     *
     * @param program_item $courseitem
     * @param program_item $parentset
     */
    private function calculate_course_progress(program_item $courseitem, program_item $parentset): void {
        [$progresspercentage, $modulescount, $modulescompleted] = $this->get_course_progress_information($courseitem->get_course(),
            $this->userid);
        $courseitem->progresspercentage = $progresspercentage;
        $iscompleted = $courseitem->progresspercentage >= 100;
        $courseitem->totalitems = $modulescount;
        $courseitem->completeditems = $modulescompleted;
        $courseitem->isenrolled = $this->is_enrolled_to_course($parentset->get_programid(), $courseitem->get_courseid(),
            $this->userid);
        $courseitem->weight = 1;
        $courseitem->iscompleted = $iscompleted;
        $courseitem->completion = $iscompleted ? 1 : 0;

        if (null === $parentset) {
            return;
        }
        $parentset->totalitems += 1;
        $parentset->completeditems += $iscompleted ? 1 : 0;
        $parentset->childrencompletionweightpairs[] = [
            'completion' => $courseitem->completion,
            'weight' => $courseitem->weight,
        ];
    }

    /**
     * Calculates set progress for a set with completion criteria ALL child items completed.
     * Set completion = Σ children item completions
     * Set weight = Σ children item weights
     * Set progress % = 100 * completion / weight
     *
     * @param program_item $set
     * @param program_item|null $parentset
     */
    private function calculate_completion_all_set_progress(program_item $set, ?program_item $parentset): void {
        if ($this->is_saved_as_completed($set)) {
            $set->iscompleted = true;
            $set->progresspercentage = 100;
            $set->completion = array_sum(array_column($set->childrencompletionweightpairs, 'completion'));
            $set->weight = array_sum(array_column($set->childrencompletionweightpairs, 'weight'));
            if ($parentset === null) {
                return;
            }
            $parentset->totalitems += 1;
            $parentset->completeditems += 1;
            $parentset->childrencompletionweightpairs[] = [
                'completion' => $set->completion,
                'weight' => $set->weight,
            ];
            return;
        }

        if (empty($set->childrencompletionweightpairs)) {
            $set->completion = 0;
            $set->weight = 0;
            $set->progresspercentage = 0;
            $iscompleted = false;
            $set->iscompleted = false;
        } else {
            $set->completion = array_sum(array_column($set->childrencompletionweightpairs, 'completion'));
            $set->weight = array_sum(array_column($set->childrencompletionweightpairs, 'weight'));
            $set->progresspercentage = $set->weight > 0 ? floor(100 * $set->completion / $set->weight) : 0;
            $iscompleted = $set->progresspercentage >= 100;
            $set->iscompleted = $iscompleted;
        }

        if ($iscompleted) {
            $this->save_set_as_completed($set);
        }

        if (null === $parentset) {
            return;
        }
        $parentset->totalitems += 1;
        $parentset->completeditems += $iscompleted ? 1 : 0;
        $parentset->childrencompletionweightpairs[] = [
            'completion' => $set->completion,
            'weight' => $set->weight,
        ];
    }

    /**
     * Calculates set progress for a set with completion criteria AT LEAST N child items completed.
     *
     * @param program_item $item
     * @param program_item|null $parentset
     */
    private function calculate_completion_at_least_set_progress(program_item $item, ?program_item $parentset): void {
        if ($this->is_saved_as_completed($item)) {
            $item->iscompleted = true;
            $item->progresspercentage = 100;
            [$item->completion, $item->weight] = $this->calculate_completion_and_weight_with_heaviest_children($item);
            if (null === $parentset) {
                return;
            }
            $parentset->totalitems += 1;
            $parentset->completeditems += 1;
            $parentset->childrencompletionweightpairs[] = [
                'completion' => $item->completion,
                'weight' => $item->weight,
            ];
            return;
        }

        if (empty($item->childrencompletionweightpairs)) {
            $item->completion = 0;
            $item->weight = 0;
            $item->progresspercentage = 0;
            $iscompleted = false;
            $item->iscompleted = false;
        } else {
            [$item->completion, $item->weight] = $this->calculate_completion_and_weight_with_heaviest_children($item);
            $item->progresspercentage = $item->weight > 0 ? floor(100 * $item->completion / $item->weight) : 0;
            $iscompleted = $item->progresspercentage >= 100;
            $item->iscompleted = $iscompleted;
        }

        if ($iscompleted) {
            $this->save_set_as_completed($item);
        }

        if (null === $parentset) {
            return;
        }
        $parentset->totalitems += 1;
        $parentset->completeditems += $iscompleted ? 1 : 0;
        $parentset->childrencompletionweightpairs[] = [
            'completion' => $item->completion,
            'weight' => $item->weight,
        ];
    }

    /**
     * Check if set has been previously completed for the given user.
     *
     * @param program_item $set
     * @return bool
     */
    private function is_saved_as_completed(program_item $set): bool {
        $setcompletion = program_set_completion::get_record([
            'userid' => $this->userid,
            'setid' => $set->get_id(),
        ]);

        return !empty($setcompletion);
    }

    /**
     * Saves set completion state for the given user.
     *
     * @param program_item $set
     */
    private function save_set_as_completed(program_item $set): void {
        if ($this->program->is_archived() || !api::has_unsuspended_allocations($set->get_programid(), $this->userid)) {
            // If program archived or user has all allocations to the program suspended, program/sets can not be saved as completed.
            return;
        }

        $setcompletion = new program_set_completion(0, (object) [
            'userid' => $this->userid,
            'setid' => $set->get_id(),
            'completeddate' => time(),
        ]);
        $setcompletion->create();

        // Trigger set completed event.
        program_set_completed::create_from_program_set_completed($setcompletion, $set->get_programid())->trigger();

        if ($set->is_base_set()) {
            // Trigger program completed event.
            program_completed::create_from_program_completed($setcompletion, $set->get_programid())->trigger();
        }
    }

    /**
     * Calculates and returns last accessed course id from the program tree considering program progress and course enrolments.
     *
     * @param program_item[] $availablecourseids
     * @return int|null
     */
    private function get_last_accessed_available_courseid(array $availablecourseids): ?int {
        global $DB;

        [$inorequal, $params] = $DB->get_in_or_equal($availablecourseids, SQL_PARAMS_NAMED);
        $params += ['userid' => $this->userid];
        $sql = "SELECT courseid
                  FROM {user_lastaccess}
                 WHERE userid = :userid
                   AND courseid $inorequal
              GROUP BY courseid
              ORDER BY MAX(timeaccess) DESC ";
        $lastcoursesidsaccessed = $DB->get_fieldset_sql($sql, $params);
        if ($lastcourseidaccessed = reset($lastcoursesidsaccessed)) {
            return $lastcourseidaccessed;
        }

        return null;
    }

    /**
     * Gets a sorted list of course ids of available and uncompleted courses within the course.
     *
     * @return array
     */
    private function get_available_courseids(): array {
        $programitems = $this->to_list();
        $availablecourses = [];
        foreach ($programitems as $programitem) {
            $courseid = $programitem->get_courseid();
            if (!array_key_exists($courseid, $availablecourses) && $this->is_available_course($programitem)) {
                $availablecourses[$courseid] = $courseid;
            }
        }

        return $availablecourses;
    }

    /**
     * Checks if a program item is an available course.
     *
     * @param program_item $programitem
     * @return bool
     */
    private function is_available_course(program_item $programitem): bool {
        return $programitem->isunlocked
            && !$programitem->iscompleted
            && $programitem->is_course();
    }

    /**
     * Gets ongoing course id.
     *
     * @return int|null Ongoing course id or null if there is no available (uncompleted + unlocked) courses within the program.
     */
    public function get_ongoing_courseid(): ?int {
        $availablecourseids = $this->get_available_courseids();

        if (empty($availablecourseids)) {
            return null;
        }

        $ongoingcourseid = $this->get_last_accessed_available_courseid($availablecourseids);
        if (null !== $ongoingcourseid) {
            return $ongoingcourseid;
        }

        return reset($availablecourseids);
    }

    /**
     * Returns the course percentage completed by a certain user, modules count and completed modules count.
     * This method is based on \core_completion\progress::get_course_progress_percentage($course).
     *
     * @param stdClass $course Moodle course object
     * @param int $userid The id of the user
     * @return array
     *          - Percentage or 0 if completion is not supported or there are no activities that support completion.
     *          - Modules count
     *          - Completed modules count
     */
    private function get_course_progress_information(stdClass $course, int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $progress = null;
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            $progress = 0;
        }
        if ($completion->is_course_complete($userid)) {
            $progress = 100;
        }

        $modules = $completion->get_activities();
        $modulescount = count($modules);

        // Get the number of modules that have been completed.
        $modulescompleted = 0;
        foreach ($modules as $module) {
            $data = $completion->get_data($module, false, $userid);
            $modulescompleted += COMPLETION_INCOMPLETE === (int) $data->completionstate ? 0 : 1;
        }

        if ($progress === null) {
            $progress = $modulescount > 0 ? floor(100 * $modulescompleted / $modulescount) : 0;
        }

        return [
            $progress,
            $modulescount,
            $modulescompleted,
        ];
    }

    /**
     * Checks if user is enrolled to this course.
     * Stores the result to return it in case the course appears more than once within the program.
     *
     * @param int $programid
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    private function is_enrolled_to_course(int $programid, int $courseid, int $userid): bool {
        if (!array_key_exists($courseid, $this->coursenrolled)) {
            $isenrolled = api::is_actively_enrolled_with_enrol_program($programid, $courseid, $userid);
            $this->coursenrolled[$courseid] = $isenrolled;
        }

        return $this->coursenrolled[$courseid];
    }

    /**
     * Calculates the completion and weight of a set following the "at least" calculation criteria.
     *
     * @param program_item $set
     * @return int[]
     */
    private function calculate_completion_and_weight_with_heaviest_children(program_item $set): array {
        $completions = array_column($set->childrencompletionweightpairs, 'completion');
        $weights = array_column($set->childrencompletionweightpairs, 'weight');
        array_multisort($completions, SORT_DESC, $weights, SORT_ASC, $set->childrencompletionweightpairs);
        $itemcompletion = array_sum(array_slice($completions, 0, $set->get_completion_atleast()));
        $itemweight = array_sum(array_slice($weights, 0, $set->get_completion_atleast()));

        return [$itemcompletion, $itemweight];
    }

    /**
     * Return the overall program progress as a percentage.
     *
     * @return string
     */
    public function get_program_progress_as_percentage(): string {
        return $this->get_baseset()->progresspercentage . '%';
    }
}
