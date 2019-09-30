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
 * This file contains the definition o rules matches report.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die;

use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_reportbuilder\local\helpers\format;

/**
 * System report with users that matched and unmatched a given rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_matches_report extends system_report {
    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('tool_dynamicrule', 'r');

        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        $this->add_base_join('JOIN {tool_dynamicrule_match} m ON (m.ruleid = r.id)');
        $this->add_base_join('JOIN {user} u ON (u.id = m.userid)');

        $ruleidparam = \tool_wp\db::generate_param_name();
        $where = "r.id = :{$ruleidparam}";
        $this->add_base_condition_sql($where, [$ruleidparam => $ruleid]);
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

        $this->annotate_entity('match', new \lang_string('match', 'tool_dynamicrule'));

        $newcolumn = (new report_column(
            'matchedtime',
            new \lang_string('matchedtime', 'tool_dynamicrule'),
            'match'
        ))
            ->add_field('m.matchedtime')
            ->set_is_default(true, 4)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'));
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'unmatchedtime',
            new \lang_string('unmatchedtime', 'tool_dynamicrule'),
            'match'
        ))
            ->add_field('m.unmatchedtime')
            ->set_is_default(true, 4)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'));
        $this->add_column($newcolumn);
    }

    /**
     * Return if the user can view current report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        try {
            $rule = \tool_dynamicrule\api::get_rule($ruleid);
        } catch (\Exception $e) {
            return false;
        }
        return permission::can_view_matching_users($rule);
    }

    /**
     * Visible name of the data source.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_name() {
        return get_string('reportrulematches', 'tool_dynamicrule');
    }
}
