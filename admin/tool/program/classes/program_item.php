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
 * Program tree item class for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program;

use stdClass;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_tree_item. Wrapper class to handle program sets and program courses as items within a program tree.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class program_item {
    /** @var int How deeply nested is this item. */
    public $level;
    /** @var program_item[] Children items of this item. */
    public $items = [];

    /*
     * Progress related properties.
     */

    /** @var int Number of children (items count in the case of sets, modules count in the case of courses). */
    public $totalitems = 0;
    /** @var int Number of immediate children items marked as completed */
    public $completeditems = 0;
    /** @var int Completion value of this item. */
    public $completion = 0;
    /** @var int Completion weight value of this item. */
    public $weight = 0;
    /** @var array Pairs of completion-weight values of the children of this item. */
    public $childrencompletionweightpairs = [];
    /** @var int Completion progress of this item calculated as a rounded percentage. */
    public $progresspercentage = 0;
    /** @var bool Wether this item has been marked as completed. */
    public $iscompleted = false;

    /*
     * Locked related properties.
     */

    /** @var bool Wether the provided user is enrolled to this item. */
    public $isenrolled;
    /** @var bool Wether the provided user has access to this item or its children. */
    public $isunlocked;

    /**
     * Is set.
     *
     * @return bool
     */
    abstract public function is_set(): bool;

    /**
     * Is course.
     *
     * @return bool
     */
    abstract public function is_course(): bool;

    /**
     * Get parent id.
     *
     * @return int|null
     */
    abstract public function get_parent_id(): ?int;

    /**
     * Is base set.
     *
     * @return bool
     */
    abstract public function is_base_set(): bool;

    /**
     * Get course id.
     *
     * @return int|null
     */
    abstract public function get_courseid(): ?int;

    /**
     * Get id.
     *
     * @return int
     */
    abstract public function get_id(): int;

    /**
     * Get name.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Get program id.
     *
     * @return int
     */
    abstract public function get_programid(): int;

    /**
     * Get sort order.
     *
     * @return int
     */
    abstract public function get_sortorder(): int;

    /**
     * Get completion criteria.
     *
     * @return int|null
     */
    abstract public function get_completion_criteria(): ?int;

    /**
     * Get completion at least.
     *
     * @return int|null
     */
    abstract public function get_completion_atleast(): ?int;

    /**
     * Returns underlying persistent related to this program item.
     *
     * @return program_course|program_set
     */
    abstract public function get_persistent();

    /**
     * Get related course.
     *
     * @return stdClass|null
     */
    abstract public function get_course(): ?stdClass;
}
