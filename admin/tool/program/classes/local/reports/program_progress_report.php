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
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_program\local\helpers\programitem_entity;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class user_program
 *
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);

        $u = 'u'; // User table alias.
        $p = 'tp'; // Program table alias.
        $c = 'tc'; // Course table alias.
        $pc = 'tpc'; // Program course tabla alias.
        $ps = 'tps'; // Program set table alias.
        $pri = 'tpitem'; // Program items "union result table" alias.

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
        $this->add_actions();
        $this->set_downloadable(false);

        // Add default columns.
        if ($column = $this->get_column('tool_program_item:type')) {
            $column->set_is_default(true, 1);
        }
        if ($column = $this->get_column('tool_program_item:name')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_program_item:completioncriteria')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_program_item:parentname')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_program_item:progresspercent')) {
            $column->set_is_default(true, 5);
        }
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
        $this->add_entity((new programitem_entity())
            ->set_table_alias('program_item', $pri)
            ->set_table_alias('tool_program', $p)
            ->set_table_alias('user', $u)
        );
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {
        // No actions defined.
    }
}
