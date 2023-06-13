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

namespace tool_program;

use stdClass;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

/**
 * Class program_tree_item. Wrapper class to handle program sets and program courses as items within a program tree.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
    /** @var bool If completion is enabled for this course. */
    public $completionenabled = false;

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
