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
 * File for a class for exporting field data.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\external;

use core\external\exporter;
use renderer_base;
use stdClass;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting field data.
 *
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_certification_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'certification' => certification::class,
            'certificationuser' => certification_user::class,
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return certification::properties_definition() + [
                'enddatetype' => [
                    'type' => PARAM_INT,
                ],
                'enddateabsolute' => [
                    'type' => PARAM_INT,
                ],
            ];
    }

    /**
     * Get other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var certification $certification */
        $certification = $this->related['certification'];
        /** @var certification_user $certificationuser */
        $certificationuser = $this->related['certificationuser'];

        $certificationdata = $certification->to_record();
        $this->export_overriden_user_dates($certificationdata, $certificationuser);

        return (array) $certificationdata;
    }

    /**
     * Modifies certification dates with the overriden user dates if they are actually overriden for this user.
     *
     * @param stdClass $certificationdata
     * @param certification_user $allocationdata
     */
    private function export_overriden_user_dates(stdClass $certificationdata, certification_user $allocationdata): void {
        // We always give it dates to mobile as absolute dates (since we always give them the "calculated" dates).
        $certificationdata->startdatetype = constants::DATE_ABSOLUTE;
        $certificationdata->startdateabsolute = $allocationdata->get('startdate');
        $certificationdata->duedatetype = constants::DATE_ABSOLUTE;
        $certificationdata->duedateabsolute = $allocationdata->get('duedate');
        $certificationdata->enddatetype = constants::DATE_ABSOLUTE;
        $certificationdata->enddateabsolute = $allocationdata->get('enddate');
    }
}
