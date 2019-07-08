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
 * Class for report filtering.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

namespace tool_reportbuilder\local\filter;

use tool_reportbuilder\form\filters;
use tool_reportbuilder\report_base;
use tool_wp\db;
use tool_reportbuilder\filter_base;

defined('MOODLE_INTERNAL') || die;

/**
 * Class report_filtering
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */
class report_filtering extends report_filter{

    /** @var report_base  */
    protected $report;

    /** @var array $enablefilters */
    protected $enablefilters;
    /**
     * report_filtering constructor.
     *
     * @param report_base $report
     * @param bool $editing
     * @param array $filtersinuse
     * @throws \coding_exception
     */
    public function __construct(report_base $report, bool $editing, $filtersinuse = array()) {
        $this->report = $report; // TODO: delete it...
        $this->editing = $editing;

        $filters = new \tool_reportbuilder\local\helpers\filters($report->get_id());
        $filtersfield = $filters->get_report_filters();

        $filterform = new filters(
            null,
            ['reportid' => $report->get_id()],
            'post',
            '',
            array('class' => 'form-filters')
        );

        $this->enablefilters = $filtersinuse;
        $this->fields = $filterform->get_fields();

        $filterform->set_data(['reportid' => $report->get_id()]);
        $filterform->set_data(['canreset' => false]);
        $this->_activeform = $filterform;
        $this->_activeform->set_data($filtersfield);
    }

    /**
     * Returns sql where statement based on active user filters
     *
     * @param string     $extra
     * @param array|null $params
     * @param array      $joins
     *
     * @return array
     */
    public function get_sql_filter($extra = '', array $params = [], array $joins = []) {
        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }
        $activefilters = $this->enablefilters;
        foreach ($activefilters as $fname => $finfo) {
            /** @var filter_base $field */
            $field = $this->fields[$fname];
            if ($field) {
                list($s, $p) = $field->get_sql_filter($finfo->values);
                $fieldjoins = $field->get_joins();
                foreach ($fieldjoins as $fieldjoin) {
                    $joins[$fieldjoin] = $fieldjoin;
                }
                if ($s) {
                    db::validate_params($p);
                    $sqls[] = $s;
                    $params = $params + $p;
                }
            }
        }

        $sqls = implode(' AND ', $sqls);
        return array($sqls, $params, $joins);
    }
}