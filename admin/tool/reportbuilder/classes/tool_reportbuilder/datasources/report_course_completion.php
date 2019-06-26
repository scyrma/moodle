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
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_reportbuilder\constants;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_column;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_course_completion
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_course_completion extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('tool_datastore_action', 'dsa');
        $this->add_base_condition_simple('dsa.action', 'course_completed');
        $this->add_base_join('left join {course} c on c.id = dsa.originalcourseid');
        $this->add_base_join('left join {user} u on u.id = dsa.relateduserid AND u.deleted = 0');

        $this->set_columns();
        $this->add_organisation_condition('u');

        $this->get_column('tool_datastore_action:timecompleted')
            ->set_is_default(true, 3);
        $this->get_column('tool_datastore__course:fullname')
            ->set_is_default(true, 2);
        $this->get_column('tool_datastore__user:firstname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 1);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportcoursecompletion', 'tool_reportbuilder');
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function set_columns() {
        $this->annotate_entity('tool_datastore_action', new \lang_string('entitydatastoreaction', 'tool_reportbuilder'));
        $this->annotate_entity('tool_datastore__course', new \lang_string('entitydatastorecourse', 'tool_reportbuilder'));
        $this->annotate_entity('tool_datastore__user', new \lang_string('entitydatastoreuser', 'tool_reportbuilder'));
        $this->annotate_entity('course_completions', new \lang_string('entitycoursecompletion', 'tool_reportbuilder'));
        $this->add_entity(new user_entity('', 'u'));
        $this->add_entity(new course_entity('', 'c'));

        $this->add_column((new report_column(
            'timecompleted',
            new \lang_string('timecompleted', 'tool_reportbuilder'),
            'tool_datastore_action'))
            ->add_field('dsa.timecreated')
            ->add_callback([format::class, 'userdate']));

        $p1 = db::generate_param_name();
        $p2 = db::generate_param_name();
        $newcolumn = (new report_column(
            'fullname',
            new \lang_string('fullnamecourse'),
            'tool_datastore__course'
        ))
            ->add_field("(SELECT
            value
        FROM
            {tool_datastore_idx_fields} idx
                INNER JOIN
            {tool_datastore_entity} ent ON ent.id = idx.entityid
                AND ent.type = :{$p1}
                AND idx.name = :{$p2}
        WHERE
            idx.actionid = dsa.id
                AND dsa.originalcourseid = ent.originalid)",
                'fullname',
                [$p1 => 'course', $p2 => 'fullname'])
            ->set_groupby_sql('dsa.id, dsa.originalcourseid')
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->add_callback([format::class, 'format_string']);
        $this->add_column($newcolumn);

        $p1 = db::generate_param_name();
        $p2 = db::generate_param_name();
        $newcolumn = (new report_column(
            'firstname',
            new \lang_string('firstname'),
            'tool_datastore__user'
        ))
            ->add_field("(SELECT
            value
        FROM
            {tool_datastore_idx_fields} idx
                INNER JOIN
            {tool_datastore_entity} ent ON ent.id = idx.entityid
                AND ent.type = :{$p1}
                AND idx.name = :{$p2}
        WHERE
            idx.actionid = dsa.id
                AND dsa.relateduserid = ent.originalid)",
                'firstname',
                [$p1 => 'user', $p2 => 'firstname'])
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->set_groupby_sql('dsa.id, dsa.relateduserid');
        $this->add_column($newcolumn);

        $p1 = db::generate_param_name();
        $p2 = db::generate_param_name();
        $newcolumn = (new report_column(
            'email',
            new \lang_string('email'),
            'tool_datastore__user'
        ))
            ->add_field("(SELECT
            value
        FROM
            {tool_datastore_idx_fields} idx
                INNER JOIN
            {tool_datastore_entity} ent ON ent.id = idx.entityid
                AND ent.type = :{$p1}
                AND idx.name = :{$p2}
        WHERE
            idx.actionid = dsa.id
                AND dsa.relateduserid = ent.originalid)",
                'email',
                [$p1 => 'user', $p2 => 'email'])
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->set_groupby_sql('dsa.id, dsa.relateduserid');
        $this->add_column($newcolumn);

        // Course completion columns.
        $newcolumn = (new report_column(
            'timecompleted',
            new \lang_string('timecompleted', 'tool_reportbuilder'),
            'course_completions'
        ))
            ->add_join('left join {course_completions} cc on cc.course = dsa.originalcourseid AND cc.userid = dsa.relateduserid')
            ->add_field("cc.timecompleted")
            ->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);
    }

    /**
     * This report is available to organisation managers with the permission to view reports
     *
     * Only users who are managed by the current user will be displayed
     *
     * @return bool
     */
    public static function supports_organisation_filter(): bool {
        return true;
    }
}