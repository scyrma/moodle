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
 * This file contains the backend class for user_without_position condition.
 *
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_dynamicrule\condition;

use tool_organisation\helper;
use tool_organisation\organisation;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_organisation\permission;

/**
 * The backend class for user_without_position condition
 *
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_without_position extends condition_position_base {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserwithoutposition', 'tool_organisation');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        $mform->addElement('selectgroups', 'positionid', get_string('entityposition', 'tool_organisation'),
            organisation::get_all_positions_menu(), ['multiple' => true]);

        // The multi-select criteria is hardcoded to "all" which means that user is matched when they
        // have no jobs in any of the selected positions.
        $mform->addElement('hidden', 'criteria', self::CRITERIA_ALL);

        $mform->addElement('advcheckbox', 'withsubpositions',
            '', get_string('withsubpositions', 'tool_organisation'));
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        [$list, $params] = $DB->get_in_or_equal($this->get_positionid());
        $names = $DB->get_fieldset_sql("SELECT name FROM {tool_organisation_position} WHERE id "
            . $list . " ORDER BY name", $params);

        $deptnames = implode("', '", array_map(function(string $name) {
            return format_string($name, true, ['escape' => false]);
        }, $names));

        $options = [
            'posname' => $deptnames,
            'subposinclude' => $this->get_with_subpositions() ? get_string('included') : get_string('notincluded'),
        ];

        if (count($names) > 1) {
            // Many positions.
            $identifier = 'conditionuserpositions' . $this->get_criteria() . 'descriptionnegated';
        } else {
            // One position.
            $identifier = 'conditionuserpositiondescriptionnegated';
        }

        return get_string($identifier, 'tool_organisation', $options);
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $withsubpositions = $this->get_with_subpositions();
        $tenantid = $this->get_tenantid();

        $positionwheres = [];
        $positionparams = [];
        foreach ($this->get_positionid() as $positionid) {
            [$where, $params] = helper::user_has_position_select($positionid,
                $withsubpositions, 'u', null, $tenantid);

            $positionwheres[] = $where;
            $positionparams = array_merge($positionparams, $params);
        }

        if (count($positionwheres) > 1) {
            $separator = ($this->get_criteria() === self::CRITERIA_ALL) ? ' AND ' : ' OR ';
            $positionwheres = implode($separator, $positionwheres);
        } else {
            $positionwheres = $positionwheres[0];
        }

        return ['', ' NOT (' . $positionwheres . ')', $positionparams];
    }
}
