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

namespace tool_wp\local\exportimport;

use core\persistent;
use tool_wp\event\export_created;
use tool_wp\event\export_deleted;
use tool_wp\event\export_updated;

/**
 * Class export_persistent represents a line from the DB table tool_wp_export
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_persistent extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_wp_export';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'createdby' => [
                'type' => PARAM_INT,
            ],
            'tenantid' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'exporter' => [
                'type' => PARAM_RAW_TRIMMED,
            ],
            'entrypoint' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => '',
                'optional' => true,
            ],
            'entrypointid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'optional' => true,
            ],
            'configdata' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'reviewdata' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'status' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
        ];
    }

    /**
     * Hook to execute after creation
     *
     * @return void
     */
    protected function after_create() {
        parent::after_create();
        export_created::create_from_persistent($this)->trigger();
    }

    /**
     * Hook to execute after update
     *
     * @param bool $result Whether update was successful
     */
    protected function after_update($result) {
        parent::after_update($result);

        if ($result) {
            export_updated::create_from_persistent($this)->trigger();
        }
    }

    /**
     * Hook to execute after deletion
     *
     * @param bool $result Whether deletion was successful
     * @return void
     */
    protected function after_delete($result) {
        parent::after_delete($result);

        if ($result) {
            export_deleted::create_from_persistent($this)->trigger();
        }
    }

    /**
     * Hook to execute before a delete.
     *
     * @return void
     */
    protected function before_delete() {
        parent::before_delete();
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'tool_wp', 'export', $this->get('id'));
    }
}
