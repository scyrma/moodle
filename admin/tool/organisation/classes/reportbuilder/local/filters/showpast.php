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

use lang_string;
use MoodleQuickForm;
use core_reportbuilder\local\filters\base;
use core_reportbuilder\local\helpers\database;
use tool_organisation\helper;

/**
 * Filter for past jobs (those with an enddate before current time)
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Paul Holden <paulh@moodle.com>
 * @author    2019 Daniel Neis <daniel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class showpast extends base {

    /**
     * Adds controls specific to this filter in the form
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $label = new lang_string('filterfieldvalue', 'core_reportbuilder', $this->get_header());
        $options = [1 => new lang_string('yes'), 0 => new lang_string('no')];

        $mform->addElement('select', "{$this->name}_value", $label, $options)->setHiddenLabel(true);
        $mform->setType("{$this->name}_value", PARAM_BOOL);
        $mform->setDefault("{$this->name}_value", false);
    }

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values) : array {
        $value = !empty($values["{$this->name}_value"]);
        if ($value) {
            return ['', []];
        }

        $fieldsql = $this->filter->get_field_sql();
        $params = $this->filter->get_field_params();

        $paramtime = database::generate_param_name();

        $where = "({$fieldsql} = 0 OR {$fieldsql} >= :{$paramtime})";
        $params[$paramtime] = helper::round_time(time());

        return [$where, $params];
    }

    /**
     * This filter is only active when showing past jobs
     *
     * @param array $values
     * @return bool
     */
    public function applies_to_values(array $values): bool {
        return !empty($values["{$this->name}_value"]);
    }
}
