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

/**
 * This file contains the definition of matching users report.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use tool_reportbuilder\system_report;
use tool_reportbuilder\local\entities\user as user_entity;

/**
 * System report with users matching a given rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
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

        if (api::is_matching_users_count_needed($ruleid)) {
            // This report is only accessible when there are no rule matches yet.
            // so we do not need to take into account cases when mathing
            // limitation is configured.
            list($join, $where, $params) = api::get_matching_join_sql($ruleid);
            list($condjoin, $condwhere, $condparams) = api::get_rule_conditions_sql($ruleid);
            $this->add_base_join($join);
            $this->add_base_join($condjoin);
            $basewhere = $where . $condwhere . ' AND mlastmatch.id IS NULL ';
            $this->add_base_condition_sql($basewhere, $params + $condparams);
        } else {
            // No conditions in this rule. Add a condition that will make the list empty.
            $this->add_base_condition_sql('1=2');
        }
        $this->add_base_fields(\core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects);
        $this->set_downloadable(false);
    }

    /**
     * Define columns.
     *
     */
    protected function set_columns() {
        global $CFG;
        $userentity = new user_entity();
        $this->add_entity($userentity);

        $this->get_column('user:fullnamewithpicturelink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true)
            ->set_visiblename(new \lang_string('fullname'));

        // Get additional fields.
        $context = \context_system::instance();
        $additionaluserfields = \core_user\fields::for_identity($context, true)->get_required_fields();
        $extra = preg_split('/,/', $CFG->showuseridentity, -1, PREG_SPLIT_NO_EMPTY);
        $order = 2;
        foreach ($extra as $userfield) {
            if ($column = $this->get_column('user:'.$userentity->resolve_column_name($userfield))) {
                $column
                    ->set_is_default(true, $order++)
                    ->set_is_sortable(true)
                    ->set_is_available(in_array($userfield, $additionaluserfields));
            }
        }
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
