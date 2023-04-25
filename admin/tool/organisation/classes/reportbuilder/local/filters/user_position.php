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

use tool_organisation\helper;
use tool_organisation\organisation;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_wp\reportbuilder\local\exportimport\condition_with_mapping;

/**
 * Filter users who have at least one job in a specific position
 *
 * This filter does not require a join with "jobs" table, and each user is returned once even if they have
 * multiple jobs
 *
 * @package   tool_organisation
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_position extends \core_reportbuilder\local\filters\base implements condition_with_mapping {

    /**
     * Adds controls specific to this filter in the form.
     *
     * @param \MoodleQuickForm $mform
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setup_form(\MoodleQuickForm $mform): void {
        $objs = array();

        $options = organisation::get_all_positions_menu(
            [0 => get_string('anyposition', 'tool_organisation')]);

        $objs[$this->name] = $mform->createElement('selectgroups', $this->name, null,
            $options, array('class' => 'js-filter-select-val'));
        $objs[$this->name]->setLabel(get_string('position', 'tool_organisation'));

        $objs['limiter'] = $mform->createElement('advcheckbox', $this->name.'_operator', null,
            get_string('withsubpositions', 'tool_organisation'), array('class' => 'js-filter-advcheckbox-op'));

        $grp =& $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);
        $mform->disabledIf($this->name.'_operator', $this->name, 'eq', '');

    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        if (!$values) {
            return ['', []];
        }

        $operator = $values["{$this->name}_operator"] ?? null;
        $value = $values[$this->name] ?? 0;

        if (!$value) {
            return ['', []];
        }

        $usertablealias = $this->filter->get_field_sql();
        list ($sql, $params) = helper::user_has_position_select((int)$value, (bool)$operator, $usertablealias);
        return [$sql, $params];
    }

    /**
     * Return sample filter values
     *
     * @return array
     */
    public function get_sample_values(): array {
        return [
            "{$this->name}_operator" => true,
            "{$this->name}" => 1,
        ];
    }

    /**
     * Add position condition field mapping during export
     *
     * @param exporter_base $exporter
     * @param array $values
     */
    public function add_exporter_mapping(exporter_base $exporter, array $values): void {
        if (!empty($values[$this->name])) {
            $exporter->add_mapping('tool_organisation_position', (int) $values[$this->name]);
        }
    }

    /**
     * Get position ID condition field mapping during import
     *
     * @param importer_base $importer
     * @param array $values
     */
    public function get_importer_mapping(importer_base $importer, array &$values): void {
        if (!empty($values[$this->name])) {
            $values[$this->name] = $importer->get_mapping('tool_organisation_position',
                (int) $values[$this->name], IGNORE_MISSING) ?? 0;
        }
    }
}
