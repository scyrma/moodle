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
 * Class for conditions filtering logic.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use core_reportbuilder\local\helpers\database;
use tool_reportbuilder\local\helpers\conditions;
use tool_reportbuilder\report_base;

/**
 * Class report_conditions
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_conditions extends report_filter {

    /** @var array $enableconditions */
    protected $enableconditions;

    /**
     * report_conditions constructor.
     *
     * @param report_base $report
     * @param bool $editing
     * @param array $conditionsinuse
     * @throws \coding_exception
     */
    public function __construct(report_base $report, bool $editing, $conditionsinuse = array()) {
        $this->editing = $editing;

        $conditions = new conditions($report);
        $filterfields = $conditions->get_report_conditions();

        $filterform = new \tool_reportbuilder\form\conditions(
            null,
            ['report' => $report],
            'post',
            '',
            array('class' => 'form-conditions')
        );

        $this->enableconditions = $conditionsinuse;
        $this->fields = $filterform->get_fields();

        $filterform->set_data(['reportid' => $report->get_id()]);
        $filterform->set_data(['canreset' => false]);
        $this->_activeform = $filterform;
        $this->_activeform->set_data($filterfields);
    }

    /**
     * Returns sql where statement based on active user filters
     *
     * @param string     $extra
     * @param array|null $params
     * @param array $joins
     *
     * @return array
     */
    public function get_sql_filter($extra = '', array $params = [], array $joins = []) {
        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }

        foreach ($this->enableconditions as $fname => $finfo) {
            $field = $this->fields[$fname];
            if ($field) {
                list($s, $p) = $field->get_sql_filter($field->get_values());
                if (!$s) {
                    continue;
                }

                $fieldjoins = $field->get_joins();
                foreach ($fieldjoins as $fieldjoin) {
                    $joins[$fieldjoin] = $fieldjoin;
                }

                database::validate_params($p);
                $sqls[] = $s;
                $params = $params + $p;
            }
        }

        $sqls = implode(' AND ', $sqls);
        return array($sqls, $params, $joins);
    }
}
