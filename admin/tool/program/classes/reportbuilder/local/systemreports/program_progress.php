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

namespace tool_program\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use stdClass;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\program_tree_progress;
use tool_program\reportbuilder\local\formatters\program_content as program_content_formatter;

/**
 * Program progress system report implementation
 *
 * @package   tool_program
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_progress extends system_report {

    /** @var int $userid */
    private $userid;
    /** @var program $program */
    private $program;
    /** @var string ENTITY_NAME */
    private const ENTITY_NAME = 'program_progress';

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program(): program {
        if (!$this->program) {
            $programid = $this->get_parameter('programid', 0, PARAM_INT);
            $this->program = new program($programid);
        }
        return $this->program;
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);
        $userparam = database::generate_param_name();

        $this->set_main_table('tool_program', 'program');

        $this->annotate_entity(self::ENTITY_NAME, new lang_string('progressreport', 'tool_program'));
        $this->add_base_condition_simple("program.id", $this->get_program()->get('id'));

        // This report shows 'program items', which can be courses or sets. To make this happen we need to use a union of these
        // two tables (tool_program_sets and tool_program_courses) to show them all together and identify them with 'isset' and
        // 'iscourse' fields.
        $this->add_join("
            INNER JOIN (
                   SELECT programset.*,
                          1 AS isset,
                          null AS courseid
                     FROM {tool_program_sets} programset
                UNION ALL
                   SELECT programcourse.id,
                          (SELECT programset.programid FROM {tool_program_sets} programset
                          WHERE programset.id = programcourse.setid) AS programid,
                          programcourse.setid AS parent,
                          (SELECT course.fullname FROM {course} course WHERE course.id = programcourse.courseid) AS name,
                          programcourse.sortorder,
                          NULL AS completioncriteria,
                          NULL AS completionatleast,
                          programcourse.timemodified,
                          programcourse.timecreated,
                          0 AS isset,
                          programcourse.courseid
                     FROM {tool_program_courses} programcourse
            ) programitem ON programitem.programid = program.id
            INNER JOIN {user} useralias ON useralias.id = :$userparam
        ", [$userparam => $this->userid]);

        $this->add_columns();
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_user_programs_progress($this->userid, $this->get_program());
    }

    /**
     * Adds the columns we want to display in the report
     */
    protected function add_columns(): void {
        // Type column.
        $this->add_column(new column(
            'type',
            new lang_string('type', 'tool_program'),
            self::ENTITY_NAME
        ))
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("programitem.isset")
            ->add_callback([self::class, 'type']);

        // Program item name column (set/course).
        $this->add_column(new column(
            'name',
            new lang_string('name', 'tool_program'),
            self::ENTITY_NAME
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("program.fullname, programitem.name, programitem.isset")
            ->add_callback([self::class, 'name']);

        // Completion criteria column.
        $this->add_column(new column(
            'completioncriteria',
            new lang_string('completioncriteria', 'tool_program'),
            self::ENTITY_NAME
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("programitem.completioncriteria, programitem.completionatleast, programitem.isset")
            ->add_callback([self::class, 'completioncriteria']);

        // Parent name column.
        $this->add_column(new column(
            'parentname',
            new lang_string('parentname', 'tool_program'),
            self::ENTITY_NAME
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field("programitem.parent")
            ->add_field("useralias.id", 'userid')
            ->add_field("program.id", 'programid')
            ->add_callback([self::class, 'parentname']);

        // Progress percentage column.
        $this->add_column(new column(
            'progresspercent',
            new lang_string('progresspercent', 'tool_program'),
            self::ENTITY_NAME
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("programitem.isset, programitem.id, programitem.courseid")
            ->add_field("useralias.id", 'userid')
            ->add_field("program.id", 'programid')
            ->add_callback([self::class, 'progress']);
    }

    /**
     * Returns program item type
     *
     * @param bool $isset
     * @return string
     */
    public static function type(bool $isset): string {
        return get_string($isset ? 'set' : 'course', 'tool_program');
    }

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
        return program_content_formatter::completion_criteria($value, $row);
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
        $program = new program((int)$row->programid);
        $progress = new program_tree_progress($program, (int)$row->userid);
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
        $program = new program((int)$row->programid);
        $progress = new program_tree_progress($program, (int)$row->userid);
        if ($row->isset) {
            $programitem = $progress->get_branch_by_parentsetid((int)$row->id);
        } else {
            $programitem = $progress->get_first_program_course_item_by_courseid((int)$row->courseid);
        }
        return $programitem ? $programitem->progresspercentage . '%' : '-';
    }
}
