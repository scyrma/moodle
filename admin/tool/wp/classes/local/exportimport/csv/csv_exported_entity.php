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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_wp\local\exportimport\csv;

use tool_wp\local\exportimport\export_manager;

/**
 * Allows to work with one entity when preparing it for csv-format export
 *
 * To initiate call exporter_base::prepare_data_for_csv_export()
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @author      2023 Roberto Bravo <roberto.bravo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class csv_exported_entity {
    /** @var export_manager */
    protected $exportmanager;
    /** @var string */
    protected $entityname;
    /** @var array */
    protected $entitydata;
    /** @var int */
    protected $entityid;
    /** @var bool */
    protected $isexported = false;

    /**
     * csv_exported_entity constructor.
     *
     * To initiate call exporter_base::prepare_data_for_csv_export()
     *
     * @param export_manager $exportmanager
     * @param string $entityname
     * @param array $entitydata
     */
    public function __construct(export_manager $exportmanager, string $entityname, array $entitydata) {
        $this->exportmanager = $exportmanager;
        $this->entityname = $entityname;
        $this->entitydata = $entitydata;
        $this->entityid = $entitydata['id'];
    }

    /**
     * Annotate fields that do not need to be exported
     *
     * Usually ['timecreated', 'timemodified']
     *
     * @param array $fields
     * @return csv_exported_entity
     */
    public function exclude_fields(array $fields): csv_exported_entity {
        $this->ensure_not_exported();
        foreach ($fields as $key) {
            unset($this->entitydata[$key]);
        }
        return $this;
    }

    /**
     * Actually do export
     */
    public function export(): void {
        $this->ensure_not_exported();
        $this->exportmanager->write_to_csv_file($this->entitydata);
        $this->isexported = true;
    }

    /**
     * Make sure the entity has not been exported yet (check for developers)
     *
     * @throws \coding_exception
     */
    protected function ensure_not_exported(): void {
        if ($this->isexported) {
            throw new \coding_exception('This function can not be called after export');
        }
    }

    /**
     * Store instance data for review.
     *
     * It must be called before excluding fields.
     * For example:
     * mycsvexporter->prepare_data_for_csv_export()
     *           ->store_instances_for_review()
     *           ->exclude_fields()
     *           ->export();
     *
     * @return csv_exported_entity
     */
    public function store_instances_for_review(): csv_exported_entity {
        $this->exportmanager->store_instance_name_for_review($this->entityname, $this->entitydata);
        return $this;
    }
}
