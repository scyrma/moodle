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

namespace tool_organisation\reportbuilder\local\filters;

use core_reportbuilder\local\helpers\database;
use MoodleQuickForm;
use tool_organisation\helper;
use tool_organisation\organisation;

/**
 * Filter for user audience
 *
 * @package   tool_organisation
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience_select extends \core_reportbuilder\local\filters\base {

    /** @var int Operator for "All users" */
    public const OPERATOR_ALLUSERS = 0;

    /** @var int Operator for "Custom selection" */
    public const OPERATOR_CUSTOM = 1;

    /** @var int Operator for "Self" */
    public const OPERATOR_SELF = 2;

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
     * Adds filter-specific form elements
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {

        // We create a hidden label for the select element, so it's still accessible and can be targeted by Behat.
        $mform->addElement('select', "{$this->name}_operator", get_string('audienceselectinitial', 'tool_organisation'),
            $this->get_operators())->setHiddenLabel(true);

        // Remaining form elements are only available for the "Custom" operator.
        $mform->addElement('advcheckbox', "{$this->name}_reporting", get_string('audienceusersreporting', 'tool_organisation'));
        $mform->addHelpButton("{$this->name}_reporting", 'audienceusersreporting', 'tool_organisation');
        $mform->hideIf("{$this->name}_reporting", "{$this->name}_operator", 'neq', self::OPERATOR_CUSTOM);

        $mform->addElement('advcheckbox', "{$this->name}_reportingdirect",
            get_string('audienceusersreportingdirect', 'tool_organisation'), null, ['class' => 'pl-3']);
        $mform->hideIf("{$this->name}_reportingdirect", "{$this->name}_operator", 'neq', self::OPERATOR_CUSTOM);
        $mform->hideIf("{$this->name}_reportingdirect", "{$this->name}_reporting", 'notchecked');

        $options = [
            get_string('audienceand', 'tool_organisation'),
            get_string('audienceor', 'tool_organisation'),
        ];
        $mform->addElement('select', "{$this->name}_glue", null, $options);
        $mform->hideIf("{$this->name}_glue", "{$this->name}_operator", 'neq', self::OPERATOR_CUSTOM);
        $mform->hideIf("{$this->name}_glue", "{$this->name}_reporting", 'notchecked');
        $mform->hideIf("{$this->name}_glue", "{$this->name}_dept", 'notchecked');

        $mform->addElement('advcheckbox', "{$this->name}_dept", get_string('audienceusersdept', 'tool_organisation'));
        $mform->hideIf("{$this->name}_dept", "{$this->name}_operator", 'neq', self::OPERATOR_CUSTOM);

        $mform->addElement('advcheckbox', "{$this->name}_subdept", get_string('withsubdepartments', 'tool_organisation'),
            null, ['class' => 'pl-3']);
        $mform->hideIf("{$this->name}_subdept", "{$this->name}_operator", 'neq', self::OPERATOR_CUSTOM);
        $mform->hideIf("{$this->name}_subdept", "{$this->name}_dept", 'notchecked');
    }

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array|null $values
     * @return array
     */
    public function get_sql_filter(?array $values) : array {
        global $USER;

        // The field SQL contains an alias for the user table.
        $usertablealias = $this->filter->get_field_sql();

        $operator = $values["{$this->name}_operator"] ?? self::OPERATOR_ALLUSERS;
        if ($operator == self::OPERATOR_ALLUSERS) {
            return ['', []];
        } else if ($operator == self::OPERATOR_SELF) {
            $paramuser = database::generate_param_name();

            return ["{$usertablealias}.id = :{$paramuser}", [$paramuser => $USER->id]];
        }

        $reporting = (bool)($values["{$this->name}_reporting"] ?? false);
        $reportingdirect = (bool)($values["{$this->name}_reportingdirect"] ?? false);
        $glue = $values["{$this->name}_glue"] ?? 0;
        $dept = (bool)($values["{$this->name}_dept"] ?? false);
        $subdept = (bool)($values["{$this->name}_subdept"] ?? false);

        // No users to show.
        if (!$reporting && !$dept) {
            return ['1=0', []];
        }

        $time = time();
        if (!$userwithjobs = organisation::get_user_with_jobs((int)$USER->id, $time)) {
            return ['1=0', []];
        }

        $wheres = $params = [];

        // Managed users.
        if ($reporting) {
            list($manageduserswhere, $managedusersparams) = helper::get_managed_users_select($userwithjobs,
                $usertablealias, 0, $time, $reportingdirect);

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

    /**
     * Return sample filter values
     *
     * @return array
     */
    public function get_sample_values(): array {
        return [
            "{$this->name}_operator" => self::OPERATOR_SELF,
        ];
    }
}
