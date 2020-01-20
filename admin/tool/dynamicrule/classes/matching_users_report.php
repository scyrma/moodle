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
 * This file contains the definition of matching users report.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die;

use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;

/**
 * System report with users matching a given rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class matching_users_report extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('user', 'u');

        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);

        if (api::static_conditions_are_matching($ruleid)) {
            list($join, $where, $params) = api::get_matching_join_sql($ruleid);
            list($condjoin, $condwhere, $condparams) = api::get_rule_conditions_sql($ruleid);
            list($restrictionjoin, $groupbyhaving, $restrictionparams) = api::get_rule_restriction_sql($ruleid);
            $this->add_base_join($join);
            $this->add_base_join($condjoin);
            $this->add_base_join($restrictionjoin);

            $basewhere = $where . $condwhere . $groupbyhaving . ' AND mlastmatch.id IS NULL ';
            $this->add_base_condition_sql($basewhere, $params + $condparams + $restrictionparams);
        } else {
            // Add a condition that will make the list empty.
            $this->add_base_condition_sql('1=2');
        }
        $this->set_downloadable(false);
    }

    /**
     * Define columns.
     *
     */
    protected function set_columns() {
        // TODO SP-422 use user_fields.
        $this->annotate_entity('user', new \lang_string('user'));
        $newcolumn = (new report_column(
            'firstname',
            new \lang_string('firstname'),
            'user'
        ))
            ->add_field('u.firstname')
            ->set_is_default(true, 1);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'lastname',
            new \lang_string('lastname'),
            'user'
        ))
            ->add_field('u.lastname')
            ->set_is_default(true, 2);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'email',
            new \lang_string('email'),
            'user'
        ))
            ->add_field('u.email')
            ->set_is_default(true, 3);
        $this->add_column($newcolumn);
    }

    /**
     * Visible name of the data source.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_name() {
        return get_string('reportmatchingusers', 'tool_dynamicrule');
    }

    /**
     * Return if the user can view current report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        try {
            $rule = api::get_rule($ruleid);
        } catch (\Exception $e) {
            return false;
        }
        return permission::can_view_matching_users($rule);
    }
}
