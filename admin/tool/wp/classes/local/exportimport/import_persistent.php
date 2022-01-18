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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class import_persistent
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use core\persistent;
use tool_wp\event\import_created;
use tool_wp\event\import_deleted;
use tool_wp\event\import_updated;

defined('MOODLE_INTERNAL') || die();

/**
 * Class import_persistent represents a line from DB table tool_wp_import
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_persistent extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_wp_import';

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
            'importer' => [
                'type' => PARAM_RAW_TRIMMED,
                'optional' => true,
                'default' => '',
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
        import_created::create_from_persistent($this)->trigger();
    }

    /**
     * Hook to execute after update
     *
     * @param bool $result Whether update was successful
     */
    protected function after_update($result) {
        parent::after_update($result);

        if ($result) {
            import_updated::create_from_persistent($this)->trigger();
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
            import_deleted::create_from_persistent($this)->trigger();
        }
    }

    /**
     * Hook to execute before a delete.
     *
     * @return void
     */
    protected function before_delete() {
        parent::before_delete();
        if ($details = import_detail_persistent::get_records(['importid' => $this->get('id')])) {
            foreach ($details as $detail) {
                $detail->delete();
            }
        }
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'tool_wp', 'import', $this->get('id'));
    }
}
