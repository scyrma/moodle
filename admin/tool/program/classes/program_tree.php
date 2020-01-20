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
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

use Closure;
use coding_exception;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_tree. Builds program trees.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_tree {
    /**
     * @var program_item[] $tree
     */
    protected $tree = [];

    /**
     * @var program
     */
    protected $program;

    /**
     * program_tree constructor.
     *
     * @param program $program
     */
    public function __construct(program $program) {
        $this->program = $program;
        $this->generate_tree($this->get_all_program_items());
    }

    /**
     * Generate tree
     *
     * @param program_item[] $programitems
     */
    protected function generate_tree(array $programitems): void {
        $baseset = $this->fetch_base_set($programitems);
        $this->add_tree_item($programitems, $this->tree, $baseset);
    }

    /**
     * Recursive function to build a program tree by adding and sorting items and their children.
     *
     * @param program_item[] $remainingtreeitems
     * @param program_item[] $tree
     * @param program_item $itemtobeadded
     * @param int $nextlevel
     */
    private function add_tree_item(array &$remainingtreeitems, array &$tree, program_item $itemtobeadded,
        int $nextlevel = 0): void {
        $itemtobeadded->level = $nextlevel++;
        $itemtobeadded->items = [];
        $tree[] = $itemtobeadded;
        $tree = &$itemtobeadded->items; // Now tree points to the children container.

        if ($itemtobeadded->is_set()) { // Add set children.
            $childrenitems = $this->fetch_children_items($remainingtreeitems, $itemtobeadded);

            // Sort children items by sortorder.
            usort($childrenitems, $this->sort_program_tree_items());

            foreach ($childrenitems as $childitem) {
                $this->add_tree_item($remainingtreeitems, $tree, $childitem, $nextlevel);
            }
        }
    }

    /**
     * Returns program baseset (the base set contains all other program items).
     *
     * @return program_item
     */
    public function get_baseset(): program_item {
        return $this->tree[0];
    }

    /**
     * Returns program tree excluding the base set.
     *
     * @return program_item[]
     */
    public function get_baseset_children_items(): array {
        return $this->tree[0]->items;
    }

    /**
     * Gets branch, parent set included, by parent set id
     *
     * @param int $setid
     * @return null|program_item
     */
    public function get_branch_by_parentsetid(int $setid): ?program_item {
        if ($set = $this->find_set_by_setid($this->tree, $setid)) {
            return $set;
        }

        return null;
    }

    /**
     * Recursive function to get the content (items) within a given set.
     *
     * @param program_item[] $treeitems
     * @param int $setid
     * @return program_item|null
     */
    private function find_set_by_setid(array $treeitems, int $setid): ?program_item {
        foreach ($treeitems as $item) {
            if ($item->is_set()) {
                if ($setid === $item->get_id()) {
                    return $item;
                }
                if ($founditem = $this->find_set_by_setid($item->items, $setid)) {
                    return $founditem;
                }
            }
        }

        return null;
    }

    /**
     * Fetches the base set from a list of program tree items and removes it from the original list.
     *
     * @param program_item[] $items
     * @return program_item
     */
    private function fetch_base_set(array &$items): program_item {
        foreach ($items as $key => $item) {
            if ($item->is_base_set()) {
                unset($items[$key]);
                return $item;
            }
        }

        throw new coding_exception('The original items list for the program tree does not contain a base set!');
    }

    /**
     * Fetches the children items of the passed item and removes them from the original passed list of items.
     *
     * @param program_item[] $remainingitems
     * @param program_item $parentitem
     * @return program_item[]
     */
    private function fetch_children_items(array &$remainingitems, program_item $parentitem): array {
        $childrenitems = [];
        foreach ($remainingitems as $key => $item) {
            if ($item->get_parent_id() === $parentitem->get_id()) {
                $childrenitems[] = $item;
                unset($remainingitems[$key]);
            }
        }

        return $childrenitems;
    }

    /**
     * Returns sorting function for program tree items.
     *
     * @return Closure
     */
    private function sort_program_tree_items(): Closure {
        return static function(program_item $child1, program_item $child2) {
            return $child1->get_sortorder() <=> $child2->get_sortorder();
        };
    }

    /**
     * Returns a plain (not nested) list of all the program tree items.
     *
     * @return program_item[]
     */
    public function to_list(): array {
        $list = [];
        $this->add_item_to_list($list, $this->get_baseset());

        return $list;
    }

    /**
     * Adds items to a plain (not nested) list of items recursively.
     *
     * @param array $list
     * @param program_item $treeitem
     */
    private function add_item_to_list(array &$list, program_item $treeitem): void {
        $list[] = $treeitem;
        if ($treeitem->is_set()) {
            foreach ($treeitem->items as $childitem) {
                $this->add_item_to_list($list, $childitem);
            }
        }
    }

    /**
     * Returns program sets and courses as an unsorted array of program items.
     *
     * @return program_item[]
     */
    private function get_all_program_items(): array {
        $programitems = [];

        $programsets = $this->program->get_sets();
        foreach ($programsets as $programset) {
            $programitems[] = new program_item_set($programset);
        }

        $programcourses = $this->program->get_program_courses();
        $courses = $this->program->get_courses();
        foreach ($programcourses as $programcourse) {
            $programitems[] = new program_item_course($programcourse, [
                'programid' => $this->program->get('id'),
                'course' => $courses[$programcourse->get('courseid')],
            ]);
        }

        return $programitems;
    }

    /**
     * Get first occurrence of a course item withing the program tree, with the given course id.
     *
     * @param int $courseid
     * @return program_item|null
     */
    public function get_first_program_course_item_by_courseid(int $courseid): ?program_item {
        foreach ($this->to_list() as $programitem) {
            if ($programitem->get_courseid() === $courseid) {
                return $programitem;
            }
        }

        return null;
    }
}
