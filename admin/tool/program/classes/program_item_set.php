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

/**
 * Program tree item class for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

use stdClass;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_item_set. Wrapper class to handle program sets as items within a program tree.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_item_set extends program_item {
    /** @var program_course|program_set */
    private $persistent;

    /**
     * program_item constructor.
     *
     * @param program_set $programset
     */
    public function __construct($programset) {
        $this->persistent = $programset;
    }

    /**
     * Is set.
     *
     * @return bool
     */
    public function is_set(): bool {
        return true;
    }

    /**
     * Is course.
     *
     * @return bool
     */
    public function is_course(): bool {
        return false;
    }

    /**
     * Get parent id.
     *
     * @return int
     */
    public function get_parent_id(): int {
        return (int) $this->persistent->get('parent');
    }

    /**
     * Is base set.
     *
     * @return bool
     */
    public function is_base_set(): bool {
        return 0 === $this->get_parent_id();
    }

    /**
     * Get course id.
     *
     * @return int|null
     */
    public function get_courseid(): ?int {
        return null;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function get_id(): int {
        return (int) $this->persistent->get('id');
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function get_name(): string {
        return (string) $this->persistent->get('name');
    }

    /**
     * Get program id.
     *
     * @return int
     */
    public function get_programid(): int {
        return (int) $this->persistent->get('programid');
    }

    /**
     * Get sort order.
     *
     * @return int
     */
    public function get_sortorder(): int {
        return (int) $this->persistent->get('sortorder');
    }

    /**
     * Get completion criteria.
     *
     * @return int|null
     */
    public function get_completion_criteria(): ?int {
        return (int) $this->persistent->get('completioncriteria');
    }

    /**
     * Get completion at least.
     *
     * @return int|null
     */
    public function get_completion_atleast(): ?int {
        return (int) $this->persistent->get('completionatleast');
    }

    /**
     * Returns underlying persistent related to this program item.
     *
     * @return program_course|program_set
     */
    public function get_persistent() {
        return $this->persistent;
    }

    /**
     * Get related course.
     *
     * @return stdClass|null
     */
    public function get_course(): ?stdClass {
        return null;
    }
}
