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
 * Class containing the logic for the organisation structure filter.
 *
 * @package   tool_organisation
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\helper;
use tool_organisation\organisation;
use tool_reportbuilder\filter_base;
use tool_wp\db;

/**
 * Organisation structure filter
 *
 * @package   tool_organisation
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class org_structure_filter extends filter_base {

    /** @var int Show direct reports only */
    const OPERATOR_DIRECT_ONLY = 1;

    /** @var int show everybody reporting to me */
    const OPERATOR_EVERYONE = 2;

    /** @var int Customize by position and department */
    const OPERATOR_CUSTOM = 3;

    /**
     * Returns a human friendly description of the filter used as label
     *
     * @param array $data
     * @return string
     */
    public function get_label(array $data) : string {
        return 'org_structure_select';
    }

    /**
     * Adds controls specific to this filter in the form
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);

        $mform->addElement('radio', "{$this->name}_op", null,
            get_string('orgfilterdirectreports', 'tool_organisation'), self::OPERATOR_DIRECT_ONLY);
        $mform->addElement('radio', "{$this->name}_op", null,
            get_string('orgfiltereverybody', 'tool_organisation'), self::OPERATOR_EVERYONE);
        $mform->addElement('radio', "{$this->name}_op", null,
            get_string('orgfiltercustomise', 'tool_organisation') . '...', self::OPERATOR_CUSTOM);
        $mform->setDefault("{$this->name}_op", self::OPERATOR_DIRECT_ONLY);

        $manager = organisation::get_user_with_jobs();
        $depoptions = organisation::get_managed_users_departments_menu($manager, 0,
            ['' => get_string('anydepartment', 'tool_organisation')]);
        $posoptions = organisation::get_managed_users_positions_menu($manager, 0,
            ['' => get_string('anyposition', 'tool_organisation')]);

        $departmentstr = get_string('department', 'tool_organisation');
        $groupdep = [];
        $groupdep['department'] = $mform->createElement('selectgroups', "{$this->name}_department", null,
            $depoptions, array('class' => 'js-filter-select-val'));
        $groupdep['department']->setLabel($departmentstr);
        $groupdep[] = $mform->createElement('advcheckbox', "{$this->name}_department_op", null,
            get_string('withsubdepartments', 'tool_organisation'), array('class' => 'js-filter-advcheckbox-op'));
        $mform->addGroup($groupdep, 'customgroup', $departmentstr, '', false);
        $mform->hideIf('customgroup', "{$this->name}_op", 'noteq', self::OPERATOR_CUSTOM);

        $positionstr = get_string('position', 'tool_organisation');
        $grouppos['position'] = $mform->createElement('selectgroups', "{$this->name}_position", null,
            $posoptions, array('class' => 'js-filter-select-val'));
        $grouppos['position']->setLabel($positionstr);
        $grouppos[] = $mform->createElement('advcheckbox', "{$this->name}_position_op", null,
            get_string('withsubpositions', 'tool_organisation'), array('class' => 'js-filter-advcheckbox-op'));
        $mform->addGroup($grouppos, 'customgroup', $positionstr, '', false);
        $mform->hideIf('customgroup', "{$this->name}_op", 'noteq', self::OPERATOR_CUSTOM);

        $this->common_footer($mform);
    }

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array|null $currentvalues
     * @return array
     */
    public function get_sql_filter(?array $currentvalues) : array {
        $operator = (int) ($currentvalues["{$this->name}_op"] ?? self::OPERATOR_DIRECT_ONLY);

        if ($operator === self::OPERATOR_DIRECT_ONLY) {
            $manager = organisation::get_user_with_jobs();
            return helper::get_direct_managed_users_select($manager);
        } else if ($operator === self::OPERATOR_EVERYONE) {
            return ['', []];
        } else {
            // Custom selector.
            $wheres = $params = [];
            $usertablealias = 'u'; // TODO SP-422 replace hardcoded value.
            $subdepartments = $currentvalues["{$this->name}_department_op"] ?? null;
            $department = $currentvalues["{$this->name}_department"] ?? null;
            $subpositions = $currentvalues["{$this->name}_position_op"] ?? null;
            $position = $currentvalues["{$this->name}_position"] ?? null;

            if (!$department && !$position) {
                return ['', []];
            }

            if ($department) {
                [$wheredep, $paramsdep] = helper::user_is_in_department_select($department, $subdepartments, $usertablealias);
                $wheres[] = $wheredep;
                $params = array_merge($params, $paramsdep);
            }

            if ($position) {
                [$wherepos, $paramspos] = helper::user_has_position_select($position, $subpositions, $usertablealias);
                $wheres[] = $wherepos;
                $params = array_merge($params, $paramspos);
            }

            $sql = '(' . implode(' AND ', $wheres) . ')';
            return [$sql , $params];
        }
    }

    /**
     * The default operator returns SQL filter data, which by default is how Report builder determines whether a filter is active,
     * in our case we just want to check whether user has selected anything other than the default operator
     *
     * @param array|null $currentvalues
     * @return bool
     */
    protected function is_active(?array $currentvalues): bool {
        $value = $currentvalues["{$this->name}_op"] ?? self::OPERATOR_DIRECT_ONLY;

        return $value != self::OPERATOR_DIRECT_ONLY;
    }
}
