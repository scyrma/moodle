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
 * File for class programitem_format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\helpers;

use context_system;
use stdClass;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

/**
 * Class programitem_format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programitem_format {
    /**
     * Returns program item name
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function name(string $value, stdClass $row): string {
        $options = ['context' => context_system::instance(), 'escape' => false];
        if ($row->isset && empty($row->name)) {
            $name = format_string($row->fullname, true, $options);
            $name .= ' (' . get_string('baseset', 'tool_program') . ')';
            return $name;
        }
        return format_string($row->name, true, $options);
    }

    /**
     * Returns program item type
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function type(string $value, stdClass $row): string {
        return get_string($row->isset ? 'set' : 'course', 'tool_program');
    }

    /**
     * Returns program item parent name
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function parentname(string $value, stdClass $row): string {
        if (0 === (int) $row->parent) {
            return '-';
        }
        $program = new program($row->programid);
        $progress = new program_tree_progress($program, $row->userid);
        $basesetitem = $progress->get_baseset();
        if ($basesetitem->get_id() === (int) $row->parent) {
            return get_string('baseset', 'tool_program');
        }
        $parentset = new program_set($row->parent);
        $options = ['context' => context_system::instance(), 'escape' => false];
        return format_string($parentset->get('name'), true, $options);
    }

    /**
     * Returns program item progress
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function progress(string $value, stdClass $row): string {
        $program = new program($row->programid);
        $progress = new program_tree_progress($program, $row->userid);
        if ($row->isset) {
            $programitem = $progress->get_branch_by_parentsetid($row->id);
        } else {
            $programitem = $progress->get_first_program_course_item_by_courseid($row->courseid);
        }
        return $programitem ? $programitem->progresspercentage . '%' : '-';
    }

    /**
     * Returns program item completion criteria
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function completioncriteria(?string $value, stdClass $row): string {
        if (!$row->isset) {
            return '-';
        }
        switch ($row->completioncriteria) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
                return get_string('completeallinanyorder', 'tool_program');
            case program_set::COMPLETION_ALL_IN_ORDER:
                return get_string('completeallinorder', 'tool_program');
            case program_set::COMPLETION_AT_LEAST:
                return get_string('completeatleast', 'tool_program') . ' ' . $row->completionatleast;
        }
        return '-';
    }
}
