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
            0 => get_string('audienceusersall', 'tool_organisation'),
            1 => get_string('audiencecustomise', 'tool_organisation'),
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

        $mform->addElement('select', "{$this->name}_op", null, $this->get_operators());

        $mform->addElement('advcheckbox', "{$this->name}_reporting", get_string('audienceusersreporting', 'tool_organisation'));
        $mform->addHelpButton("{$this->name}_reporting", 'audienceusersreporting', 'tool_organisation');
        $mform->hideIf("{$this->name}_reporting", "{$this->name}_op", 'eq', 0);

        $options = [
            get_string('audienceand', 'tool_organisation'),
            get_string('audienceor', 'tool_organisation'),
        ];
        $mform->addElement('select', "{$this->name}_glue", null, $options);
        $mform->hideIf("{$this->name}_glue", "{$this->name}_reporting", 'notchecked');
        $mform->hideIf("{$this->name}_glue", "{$this->name}_dept", 'notchecked');

        $mform->addElement('advcheckbox', "{$this->name}_dept", get_string('audienceusersdept', 'tool_organisation'));
        $mform->hideIf("{$this->name}_dept", "{$this->name}_op", 'eq', 0);

        $mform->addElement('advcheckbox', "{$this->name}_subdept", get_string('withsubdepartments', 'tool_organisation'),
            null, ['class' => 'p-l-1']);
        $mform->hideIf("{$this->name}_subdept", "{$this->name}_op", 'eq', 0);
        $mform->hideIf("{$this->name}_subdept", "{$this->name}_op", 'eq', 0);
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

        $operator = $currentvalues["{$this->name}_op"] ?? 0;
        if ($operator != 1) {
            return ['', []];
        }

        $reporting = $currentvalues["{$this->name}_reporting"] ?? null;
        $glue = $currentvalues["{$this->name}_glue"] ?? 0;
        $dept = $currentvalues["{$this->name}_dept"] ?? null;
        $subdept = $currentvalues["{$this->name}_subdept"] ?? null;

        // No users to show.
        if (!$reporting && !$dept) {
            return ['1=0', []];
        }

        $useralias = $this->reportfilter->get_field_sql();
        $wheres = $params = [];
        $time = time();

        $userwithjobs = organisation::get_user_with_jobs($USER->id, $time);

        // Managed users.
        if ($reporting) {
            list($manageduserswhere, $managedusersparams) = helper::get_managed_users_select($userwithjobs, $useralias, 0, $time);

            $wheres[] = $manageduserswhere;
            $params = array_merge($params, $managedusersparams);
        }

        // Users in own department.
        if ($dept) {
            list($departmentuserswhere, $departmentusersparams) = helper::get_department_users_select($userwithjobs,
                $subdept, $useralias, $time);

            $wheres[] = $departmentuserswhere;
            $params = array_merge($params, $departmentusersparams);
        }

        $implodeglue = ($glue == 0 ? ' AND ' : ' OR ');

        return [implode($implodeglue, $wheres), $params];
    }
}