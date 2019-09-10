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
 * Class report_certifications
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_reportbuilder\datasources;

use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_program\local\helpers\program_entity;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course;
use tool_reportbuilder\local\entities\user;
use lang_string;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_certifications
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_certifications extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_certification', 'tc');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');

        $this->set_downloadable(true);
        $this->set_columns();

        $this->get_column('tool_certification:fullname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 1, SORT_ASC);

        $this->get_column('tool_program:fullnamewithimage')
            ->set_is_default(true, 2);

        $this->get_column('tool_certification:duedate')
            ->set_is_default(true, 3);

        $this->get_column('tool_certification:expirydate')
            ->set_is_default(true, 4);

        // Add default conditions.
        $conditions = $this->get_conditions();
        $conditions['tool_certification:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $conditions['tool_program:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $conditions['tool_program:visible']->set_is_default(true, ['visible_op' => 1, 'visible' => 1]);

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_certification:fullname']->set_is_default(true);
        $filters['tool_program:programselector']->set_is_default(true);
        $filters['tool_program:course']->set_is_default(true);
        $filters['tool_certification:timecreated']->set_is_default(true);
        $filters['tool_certification:timemodified']->set_is_default(true);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('certifications', 'tool_certification');
    }

    /**
     * Returns the certification_user/user join.
     *
     * @return string
     */
    private static function get_users_join(): string {
        // Added tcc join here because status in certification user uses completion table.
        return 'LEFT JOIN {tool_certification_users} tcu ON tcu.certificationid = tc.id
                LEFT JOIN {user} u ON u.id = tcu.userid
                LEFT JOIN {tool_certification_compltion} tcc
                ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid AND tcc.timerevoked = 0';
    }

    /**
     * Returns the certification_user/user join.
     *
     * @return string
     */
    private static function get_courses_join(): string {
        return 'LEFT JOIN {tool_program_sets} tps ON tps.programid = tp.id
                LEFT JOIN {tool_program_courses} tpc ON tpc.setid = tps.id
                LEFT JOIN {course} c ON c.id = tpc.courseid';
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new certification_entity('', 'tc'));
        $this->add_entity(new program_entity('', 'tp'));
        $this->add_entity(new certificationuser_entity(self::get_users_join(), 'tcu'));
        $this->add_entity(new user(self::get_users_join(), 'u'));
        $this->add_entity(new course(self::get_courses_join(), 'c'));
        // Modify name on course entity from 'Course' to 'Program course'.
        $this->annotate_entity('course', new lang_string('programcourse', 'tool_certification'));
    }
}
