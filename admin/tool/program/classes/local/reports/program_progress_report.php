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
 * File for the class user_program.
 *
 * @copyright 2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use lang_string;
use tool_program\local\helpers\programitem_format;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class user_program
 *
 * @copyright 2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_program
 */
class program_progress_report extends system_report {
    /**
     * @var int $userid
     */
    private $userid;
    /**
     * @var int $programid
     */
    private $program;

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
     * Initialise report
     *
     * TODO add programs sets and courses progress as rows.
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);

        $u = 'u'; // User table alias.
        $p = 'p'; // Program table alias.
        $c = 'c'; // Course table alias.
        $pc = 'pc'; // Program course tabla alias.
        $ps = 'ps'; // Program set table alias.
        $pri = 'pri'; // Program items "union result table" alias.

        $userparam = db::generate_param_name();
        $this->set_main_table(program::TABLE, $p);
        $this->add_base_condition_simple("$p.id", $this->get_program()->get('id'));
        $this->add_base_join("
            INNER JOIN (
                   SELECT $ps.*,
                          1 AS isset,
                          0 AS iscourse,
                          null AS courseid
                     FROM {" . program_set::TABLE . "} $ps
                UNION ALL
                   SELECT $pc.id,
                          (SELECT $ps.programid FROM {" . program_set::TABLE . "} $ps WHERE $ps.id = $pc.setid) AS programid,
                          $pc.setid AS parent,
                          (SELECT $c.fullname FROM {course} $c WHERE $c.id = $pc.courseid) AS name,
                          $pc.sortorder,
                          NULL AS completioncriteria,
                          NULL AS completionatleast,
                          $pc.timemodified,
                          $pc.timecreated,
                          0 AS isset,
                          1 AS iscourse,
                          $pc.courseid
                     FROM {" . program_course::TABLE . "} $pc
            ) $pri ON $pri.programid = $p.id
            INNER JOIN {user} $u ON $u.id = :$userparam
        ", [$userparam => $this->userid]);

        // Base condition is a visibility check (programs non archived, from correct tenant and not hidden).
        $usertenantid = tenancy::get_tenant_id($this->userid);
        $tenant = db::generate_param_name();
        $this->add_base_condition_sql("
                $p.archived = 0
                AND $p.tenantid = :{$tenant}
                AND $p.visible = 1 ", [$tenant => $usertenantid]);

        $this->set_columns($p, $pri, $u);
        $this->set_filters();
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_user_programs_progress($this->userid, $this->get_program());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportuserprogram', 'tool_program');
    }

    /**
     * Set the columns for the report.
     *
     * @param string $p Program table alias.
     * @param string $pri Program items table alias.
     * @param string $u User table alias.
     */
    protected function set_columns($p = 'p', $pri = 'pri', $u = 'u'): void {
        $this->annotate_entity(program::TABLE, new lang_string('entityprogram', 'tool_program'));
        $this->annotate_entity(program_set::TABLE, new lang_string('entityprogramset', 'tool_program'));
        $this->annotate_entity(program_course::TABLE, new lang_string('entityprogramcourse', 'tool_program'));

        // Column program item type.
        $newcolumn = (new report_column(
            'type',
            new lang_string('type', 'tool_program'),
            program_set::TABLE
        ))
            ->add_field("$pri.isset")
            ->set_is_default(true, 1)
            ->add_callback([programitem_format::class, 'type']);
        $this->add_column($newcolumn);

        // Column program item name.
        $newcolumn = (new report_column(
            'name',
            new lang_string('name', 'tool_program'),
            program_set::TABLE
        ))
            ->add_field("$p.fullname")
            ->add_field("$pri.name")
            ->add_field("$pri.isset")
            ->set_is_default(true, 2)
            ->add_callback([programitem_format::class, 'name']);
        $this->add_column($newcolumn);

        // Column completion criteria.
        $newcolumn = (new report_column(
            'completioncriteria',
            new lang_string('completioncriteria', 'tool_program'),
            program_set::TABLE
        ))
            ->add_field("$pri.completioncriteria")
            ->add_field("$pri.completionatleast")
            ->add_field("$pri.isset")
            ->set_is_default(true, 3)
            ->add_callback([programitem_format::class, 'completioncriteria']);
        $this->add_column($newcolumn);

        // Column parent name.
        $newcolumn = (new report_column(
            'parentname',
            new lang_string('parentname', 'tool_program'),
            program_set::TABLE
        ))
            ->add_field("$pri.parent")
            ->add_field("$u.id", 'userid')
            ->add_field("$p.id", 'programid')
            ->set_is_default(true, 4)
            ->add_callback([programitem_format::class, 'parentname']);
        $this->add_column($newcolumn);

        // Column progress percentage.
        $newcolumn = (new report_column(
            'progresspercent',
            new lang_string('progresspercent', 'tool_program'),
            program_set::TABLE
        ))
            ->add_field("$pri.isset")
            ->add_field("$pri.id")
            ->add_field("$pri.courseid")
            ->add_field("$u.id", 'userid')
            ->add_field("$p.id", 'programid')
            ->set_is_default(true, 5)
            ->add_callback([programitem_format::class, 'progress']);
        $this->add_column($newcolumn);

        // TODO column completion status.
        // TODO column completion date.
    }

    /**
     * Set filters.
     */
    protected function set_filters(): void {
        // No filters defined.
    }

    /**
     * Set conditions.
     */
    protected function set_conditions(): void {
        // No conditions defined.
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {
        // No actions defined.
    }
}
