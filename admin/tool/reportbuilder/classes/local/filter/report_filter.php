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
 * Abstract class for filtering.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

namespace tool_reportbuilder\local\filter;

use tool_reportbuilder\form\filters;
use tool_reportbuilder\report_base;

defined('MOODLE_INTERNAL') || die;

/**
 * Class report_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */
abstract class report_filter {

    /** @var \tool_reportbuilder\filter_base[] $fields */
    public $fields;
    /** @var $_addform */
    public $_addform;
    /** @var filters $_activeform */
    public $_activeform;
    /** @var array $_enablefilters */
    public $_enablefilters;
    /** @var array $_activefilters */
    public $_activefilters;
    /** @var bool $editing */
    public $editing;

    /**
     * report_filter constructor.
     *
     * @param report_base $report
     * @param bool $editing
     */
    public abstract function __construct(report_base $report, bool $editing);

    /**
     * Print the active filter form.
     */
    public final function display_active() {
        return $this->_activeform->render();
    }

    /**
     * Set form values.
     *
     * @param array $definitions
     *
     * @return array
     */
    protected function set_form_values($definitions) {
        $formvalues = array();
        foreach ($definitions as $field => $definition) {
            $formvalues[$field] = $definition->value;
            $formvalues[$field . '_op'] = $definition->operator;
        }
        return $formvalues;
    }
}