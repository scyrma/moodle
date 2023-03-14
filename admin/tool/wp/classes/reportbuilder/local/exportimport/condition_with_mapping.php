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

namespace tool_wp\reportbuilder\local\exportimport;

use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Interface for reportbuilder conditions providing export-import mapping for the "Migrations"
 *
 * @package   tool_wp
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
interface condition_with_mapping {
    /**
     * Add condition values mapping during export
     *
     * @param exporter_base $exporter
     * @param array $values
     */
    public function add_exporter_mapping(exporter_base $exporter, array $values): void;

    /**
     * Allows to substitute condition values with the mapped values during import
     *
     * @param importer_base $importer
     * @param array $values all condition values for the report (may also contain values for other conditions)
     * @return array
     */
    public function get_importer_mapping(importer_base $importer, array &$values): void;
}
