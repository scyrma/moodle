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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class containing implementation of the user audience filter
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Paul Holden <paulh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\helper;
use tool_organisation\organisation;
use tool_reportbuilder\filter_base;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

/**
 * Filter for user audience
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Paul Holden <paulh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience_select extends filter_base {

    /** @var int Operator for "All users" */
    const OPERATOR_ALLUSERS = 0;

    /** @var int Operator for "Custom selection" */
    const OPERATOR_CUSTOM = 1;

    /** @var int Operator for "Self" */
    const OPERATOR_SELF = 2;

    /**
     * Returns a human friendly description of the filter used as label
     *
     * @param array $data
     * @return string
     */
    public function get_label(array $data) : string {
        return 'audience_select';
    }

    /**
     * Returns an array of comparison operators
     *
     * @return array
     */
    public function get_operators() : array {
        return [
            self::OPERATOR_ALLUSERS => get_string('audienceusersall', 'tool_organisation'),
            self::OPERATOR_SELF => get_string('audienceself', 'tool_organisation'),
            self::OPERATOR_CUSTOM => get_string('audiencecustomise', 'tool_organisation'),
        ];
    }

    /**
     * Adds controls specific to this filter in the form
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);

        // We create a hidden label for the select element, so it's still accessible and can be targeted by Behat.
        $mform->addElement('select', "{$this->name}_op", get_string('audienceselectinitial', 'tool_organisation'),
            $this->get_operators())->setHiddenLabel(true);

        // Remaining form elements are only available for the "Custom" operator.
        $mform->addElement('advcheckbox', "{$this->name}_reporting", get_string('audienceusersreporting', 'tool_organisation'));
        $mform->addHelpButton("{$this->name}_reporting", 'audienceusersreporting', 'tool_organisation');
        $mform->hideIf("{$this->name}_reporting", "{$this->name}_op", 'neq', self::OPERATOR_CUSTOM);

        $options = [
            get_string('audienceand', 'tool_organisation'),
            get_string('audienceor', 'tool_organisation'),
        ];
        $mform->addElement('select', "{$this->name}_glue", null, $options);
        $mform->hideIf("{$this->name}_glue", "{$this->name}_op", 'neq', self::OPERATOR_CUSTOM);
        $mform->hideIf("{$this->name}_glue", "{$this->name}_reporting", 'notchecked');
        $mform->hideIf("{$this->name}_glue", "{$this->name}_dept", 'notchecked');

        $mform->addElement('advcheckbox', "{$this->name}_dept", get_string('audienceusersdept', 'tool_organisation'));
        $mform->hideIf("{$this->name}_dept", "{$this->name}_op", 'neq', self::OPERATOR_CUSTOM);

        $mform->addElement('advcheckbox', "{$this->name}_subdept", get_string('withsubdepartments', 'tool_organisation'),
            null, ['class' => 'pl-3']);
        $mform->hideIf("{$this->name}_subdept", "{$this->name}_op", 'neq', self::OPERATOR_CUSTOM);
        $mform->hideIf("{$this->name}_subdept", "{$this->name}_dept", 'notchecked');

        $this->common_footer($mform);
    }

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array|null $currentvalues
     * @return array
     */
    public function get_sql_filter(?array $currentvalues) : array {
        global $USER;

        // The field SQL contains an alias for the user table.
        $usertablealias = $this->reportfilter->get_field_sql();

        $operator = $currentvalues["{$this->name}_op"] ?? self::OPERATOR_ALLUSERS;
        if ($operator == self::OPERATOR_ALLUSERS) {
            return ['', []];
        } else if ($operator == self::OPERATOR_SELF) {
            $paramuser = db::generate_param_name();

            return ["{$usertablealias}.id = :{$paramuser}", [$paramuser => $USER->id]];
        }

        $reporting = $currentvalues["{$this->name}_reporting"] ?? null;
        $glue = $currentvalues["{$this->name}_glue"] ?? 0;
        $dept = $currentvalues["{$this->name}_dept"] ?? null;
        $subdept = $currentvalues["{$this->name}_subdept"] ?? null;

        // No users to show.
        if (!$reporting && !$dept) {
            return ['1=0', []];
        }

        $time = time();
        if (!$userwithjobs = organisation::get_user_with_jobs($USER->id, $time)) {
            return ['1=0', []];
        }

        $wheres = $params = [];

        // Managed users.
        if ($reporting) {
            list($manageduserswhere, $managedusersparams) = helper::get_managed_users_select($userwithjobs,
                $usertablealias, 0, $time);

            $wheres[] = $manageduserswhere;
            $params = array_merge($params, $managedusersparams);
        }

        // Users in own department.
        if ($dept) {
            list($departmentuserswhere, $departmentusersparams) = helper::get_department_users_select($userwithjobs,
                $subdept, $usertablealias);

            $wheres[] = $departmentuserswhere;
            $params = array_merge($params, $departmentusersparams);
        }

        $implodeglue = ($glue == 0 ? ' AND ' : ' OR ');

        return ['(' . implode($implodeglue, $wheres) . ')', $params];
    }
}
