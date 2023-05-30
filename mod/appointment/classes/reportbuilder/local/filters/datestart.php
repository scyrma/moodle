<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

declare(strict_types=1);

namespace mod_appointment\reportbuilder\local\filters;

use core_reportbuilder\local\filters\date;

/**
 * Session datestart filter, primarily for the sessions system report
 *
 * Expects that ['sessionfieldsql' => '...'] option is specified when defining filter
 *
 * @package     mod_appointment
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datestart extends date {

    /**
     * Generate SQL filter, wrapping parent SQL/params inside EXISTS statement
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        // Set filter SQL relative to our sub-select.
        $this->filter->set_field_sql('timestart');

        [$timestartsql, $params] = parent::get_sql_filter($values);
        if (empty($timestartsql)) {
            return ['', []];
        }

        $sessionfieldsql = $this->filter->get_options()['sessionfieldsql'];

        $sql = "EXISTS (
            SELECT 1
              FROM {appointment_sessions_dates}
             WHERE sessionid = {$sessionfieldsql} AND ({$timestartsql})
        )";

        return [$sql, $params];
    }
}
