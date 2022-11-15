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

use MoodleQuickForm;
use core_reportbuilder\local\filters\base;
use tool_organisation\helper;

/**
 * Filter for jobs
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class showjobs extends base {

    /** @var int Show all jobs */
    public const ALL = 0;
    /** @var int Show only current jobs */
    public const ONLYCURRENT = 1;
    /** @var int Show only past jobs */
    public const ONLYPAST = 2;
    /** @var int Show only future jobs */
    public const ONLYFUTURE = 3;

    /**
     * Adds controls specific to this filter in the form
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $options = [
            self::ALL => get_string('all'),
            self::ONLYCURRENT => get_string('onlycurrent', 'tool_organisation'),
            self::ONLYPAST => get_string('onlypast', 'tool_organisation'),
            self::ONLYFUTURE => get_string('onlyfuture', 'tool_organisation')
        ];
        $mform->addElement('select', $this->name, '', $options);
    }

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        $value = (int) ($values[$this->name] ?? self::ALL);

        if ($value === self::ALL) {
            return ['', []];
        }

        $alias = $this->filter->get_field_sql();
        $now = helper::round_time(time());

        switch ($value) {
            case self::ONLYCURRENT:
                $where = "{$now} >= {$alias}.startdate
                AND ({$now} <= {$alias}.enddate OR {$alias}.enddate = 0 OR {$alias}.enddate IS NULL)";
                break;
            case self::ONLYPAST:
                $where = "{$now} > {$alias}.enddate AND {$alias}.enddate <> 0";
                break;
            case self::ONLYFUTURE:
                $where = "{$alias}.startdate > {$now}";
                break;
            default:
                $where = '';
                break;
        }

        return [$where, []];
    }

    /**
     * Return sample filter values
     *
     * @return array
     */
    public function get_sample_values(): array {
        return [
            "{$this->name}" => self::ONLYCURRENT,
        ];
    }
}
