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

namespace tool_program\external;

use core\external\exporter;
use renderer_base;
use stdClass;
use tool_certification\certification;
use tool_certification\constants;
use tool_program\persistent\program_user;

/**
 * Class for exporting field data.
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
            'programuser' => program_user::class,
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
        /** @var program_user $programuser */
        $programuser = $this->related['programuser'];

        $certificationdata = $certification->to_record();
        $this->export_overriden_user_dates($certificationdata, $programuser);

        return (array) $certificationdata;
    }

    /**
     * Modifies certification dates with the overriden user dates if they are actually overriden for this user.
     *
     * @param stdClass $certificationdata
     * @param program_user $allocationdata
     */
    private function export_overriden_user_dates(stdClass $certificationdata, program_user $allocationdata): void {
        // We always give it dates to mobile as absolute dates (since we always give them the "calculated" dates).
        $certificationdata->startdatetype = constants::DATE_ABSOLUTE;
        $certificationdata->startdateabsolute = $allocationdata->get('startdate');
        $certificationdata->duedatetype = constants::DATE_ABSOLUTE;
        $certificationdata->duedateabsolute = $allocationdata->get('duedate');
        $certificationdata->enddatetype = constants::DATE_ABSOLUTE;
        $certificationdata->enddateabsolute = $allocationdata->get('enddate');
    }
}
