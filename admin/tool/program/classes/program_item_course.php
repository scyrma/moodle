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

use coding_exception;
use stdClass;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_item_course. Wrapper class to handle program courses as items within a program tree.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_item_course extends program_item {
    /** @var program_course|program_set */
    private $persistent;
    /** @var array|null */
    private $related;

    /**
     * program_item constructor.
     *
     * @param program_course $programcourse
     * @param array $related
     */
    public function __construct(program_course $programcourse, array $related) {
        if (empty($related['programid'])) {
            throw new coding_exception('Missing programid.');
        }
        if (empty($related['course'])) {
            throw new coding_exception('Missing course data.');
        }

        $this->persistent = $programcourse;
        $this->related = $related;
    }

    /**
     * Is set.
     *
     * @return bool
     */
    public function is_set(): bool {
        return false;
    }

    /**
     * Is course.
     *
     * @return bool
     */
    public function is_course(): bool {
        return true;
    }

    /**
     * Get parent id.
     *
     * @return int
     */
    public function get_parent_id(): int {
        return (int) $this->persistent->get('setid');
    }

    /**
     * Is base set.
     *
     * @return bool
     */
    public function is_base_set(): bool {
        return false;
    }

    /**
     * Get course id.
     *
     * @return int|null
     */
    public function get_courseid(): ?int {
        return (int) $this->persistent->get('courseid');
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
        return (string) $this->related['course']->fullname;
    }

    /**
     * Get program id.
     *
     * @return int
     */
    public function get_programid(): int {
        return (int) $this->related['programid'];
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
        return null;
    }

    /**
     * Get completion at least.
     *
     * @return int|null
     */
    public function get_completion_atleast(): ?int {
        return null;
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
     * @return stdClass
     */
    public function get_course(): stdClass {
        return $this->related['course'];
    }
}
