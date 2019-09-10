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
 * Class course_reset_entity
 *
 * @package   tool_wp
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp\local\helpers;

use lang_string;
use tool_certification\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the course_reset_entity and can be reused in any report datasource
 *
 * @package     tool_wp
 * @copyright   2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reset_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'twpcr';
    /** @var array */
    protected $excludecolumns = [];
    /** @var string */
    protected $tableuseralias = 'u';

    /**
     * course_reset constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     * @param string $tableuseralias
     */
    public function __construct(string $join = '', string $tablealias = 'twpcr', array $excludecolumns = [],
                                string $tableuseralias = 'u') {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
        $this->tableuseralias = $tableuseralias;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_wp_course_reset';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entitycoursereset', 'tool_wp');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        // Column course fullname.
        $newcolumn = (new report_column(
            'course',
            new lang_string('course'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_join("INNER JOIN {course} c ON c.id = $this->tablealias.courseid")
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field('c.fullname', 'course')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column user.
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        [$sql, $params] = \tool_reportbuilder\db::sql_fullname($this->tableuseralias, $viewfullnames);
        $newcolumn = (new report_column(
            'user',
            new lang_string('user'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'fullname', $params)
            ->add_aggregation_fields('count', $this->tablealias . '.userid')
            ->set_groupby_sql(\tool_reportbuilder\db::sql_fullname($this->tableuseralias, $viewfullnames, true));
        $columns[] = $newcolumn;

        // Column program fullname.
        $tpalias = db::generate_alias();
        $newcolumn = (new report_column(
            'program',
            new lang_string('entityprogram', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_join("LEFT JOIN {tool_program} $tpalias ON $tpalias.id = $this->tablealias.programid")
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tpalias.fullname", 'program')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column certification fullname.
        $tcalias = db::generate_alias();
        $newcolumn = (new report_column(
            'certification',
            new lang_string('entitycertification', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_join("LEFT JOIN {tool_certification} $tcalias ON $tcalias.id = $this->tablealias.certificationid")
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tcalias.fullname", 'certification')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column reason for course reset.
        $newcolumn = (new report_column(
            'reason',
            new lang_string('reason', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.reason", 'reason')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column time requested.
        $newcolumn = (new report_column(
            'timerequested',
            new lang_string('timerequested', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timerequested", 'timerequested')
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column user requested.
        $ureqtable = db::generate_alias();
        $userrequestedjoin = "LEFT JOIN {user} {$ureqtable} " .
            "ON {$this->tablealias}.userid = {$ureqtable}.id AND {$ureqtable}.deleted = 0";
        [$sql, $params] = \tool_reportbuilder\db::sql_fullname($ureqtable, $viewfullnames);
        $newcolumn = (new report_column(
            'userrequested',
            new lang_string('userrequested', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_join($userrequestedjoin)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'fullname', $params)
            ->add_aggregation_fields('count', $this->tablealias . '.userrequested')
            ->set_groupby_sql(\tool_reportbuilder\db::sql_fullname($ureqtable, $viewfullnames, true));
        $columns[] = $newcolumn;

        // Column was completed.
        $newcolumn = (new report_column(
            'wascompleted',
            new lang_string('wascompleted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$this->tablealias.wascompleted", 'wascompleted')
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column grade.
        $newcolumn = (new report_column(
            'grade',
            new lang_string('grade', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("$this->tablealias.grade", 'grade')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column resetstatus.
        $newcolumn = (new report_column(
            'resetstatus',
            new lang_string('resetstatus', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$this->tablealias.resetstatus", 'resetstatus')
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column resetinfo.
        $newcolumn = (new report_column(
            'resetinfo',
            new lang_string('resetinfo', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->add_field("$this->tablealias.resetinfo", 'resetinfo')
            ->add_callback([course_reset_format::class, 'reset_info'])
            ->disable_aggregation('count')
            ->disable_aggregation('countdistinct')
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $columns[] = $newcolumn;

        // Column time reseted.
        $newcolumn = (new report_column(
            'timereseted',
            new lang_string('timereseted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timecreated", 'timereseted')
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        return $columns;
    }

    /**
     * Returns all available filters.
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        return $this->get_filters_or_conditions(false);
    }

    /**
     * Returns all available conditions.
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        return $this->get_filters_or_conditions(true);
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Filter for timereseted.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timereseted',
            new lang_string('timereseted', 'tool_wp'),
            $this->get_entity_name(),
            "$this->tablealias.timecreated"
        ))
            ->add_join($this->join);

        // Filter Was completed.
        $filters[] = (new report_filter(
            checkbox::class,
            'wascompleted',
            new lang_string('wascompleted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("(CASE WHEN ({$this->tablealias}.wascompleted > 0) THEN 1 ELSE 0 END)");

        // Filter program selector.
        $filters[] = (new report_filter(
            select::class,
            'programselector',
            new lang_string('programs', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.programid")
            ->set_options(\tool_program\api::get_programs_in_tenant_fieldset());

        // Filter certification selector.
        $filters[] = (new report_filter(
            select::class,
            'certificationselector',
            new lang_string('certifications', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.certificationid")
            ->set_options(api::get_certifications_in_tenant_fieldset());

        return $filters;
    }
}