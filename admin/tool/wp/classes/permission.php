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

/**
 * Class permission
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\import_persistent;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * Current user can use export/import API
     *
     * @param int|null $userid
     * @return bool
     */
    public static function can_use_export_import(?int $userid = null): bool {
        return has_capability('tool/wp:useexportimport', \context_system::instance(), $userid);
    }

    /**
     * Current user can manage export/import API
     *
     * @return bool
     */
    public static function can_manage_export_import(): bool {
        return has_capability('tool/wp:manageexportimport', \context_system::instance());
    }

    /**
     * Require current user to have capability to use export/import
     *
     * @throws \required_capability_exception
     */
    public static function require_can_use_export_import() {
        if (!self::can_use_export_import()) {
            throw new \required_capability_exception(\context_system::instance(), 'tool/wp:useexportimport',
                'nopermissions', '');
        }
    }

    /**
     * Current user can view all exports and imports, including those made by other people
     *
     * @return bool
     */
    public static function can_view_all_exports_imports(): bool {
        return self::can_use_export_import() && self::can_manage_export_import();
    }

    /**
     * Is current user allowed to view the given export
     *
     * @param int $exportid
     * @return bool
     */
    public static function can_view_export(int $exportid): bool {
        global $USER;
        if (!self::can_use_export_import() || !export_persistent::record_exists($exportid)) {
            return false;
        }
        $export = new export_persistent($exportid);
        if ($export->get('createdby') != $USER->id && !self::can_view_all_exports_imports()) {
            return false;
        }
        if ($export->get('tenantid') != tenancy::get_tenant_id() && !\tool_tenant\permission::can_switch_tenant()) {
            return false;
        }
        return true;
    }

    /**
     * Checks if current user is allowed to view an export
     *
     * @param int $id
     * @throws \moodle_exception
     */
    public static function require_can_view_export(int $id): void {
        if (!self::can_view_export($id)) {
            throw new \moodle_exception(get_string('exportnotfound', 'tool_wp'));
        }
    }

    /**
     * Is current user allowed to view the given import
     *
     * @param int $importid
     * @return bool
     */
    public static function can_view_import(int $importid): bool {
        global $USER;
        if (!self::can_use_export_import()) {
            return false;
        }
        $import = new import_persistent($importid);
        if ($import->get('createdby') != $USER->id && !self::can_view_all_exports_imports()) {
            return false;
        }
        if ($import->get('tenantid') != tenancy::get_tenant_id() && !\tool_tenant\permission::can_switch_tenant()) {
            return false;
        }
        return true;
    }

    /**
     * Checks if current user is allowed to view an import
     *
     * @param int $id
     * @throws \moodle_exception
     */
    public static function require_can_view_import(int $id): void {
        if (!self::can_view_import($id)) {
            throw new \moodle_exception(get_string('importnotfound', 'tool_wp'));
        }
    }

    /**
     * Is current user allowed to delete an export
     *
     * @param int $id
     * @return bool
     */
    public static function can_delete_export(int $id): bool {
        global $USER;

        $persistent = new export_persistent($id);

        // If this record is not created by current user and does not have manage capability.
        if ($persistent->get('createdby') != $USER->id && !self::can_manage_export_import()) {
            return false;
        }

        if ($persistent->get('tenantid') != tenancy::get_tenant_id() && !\tool_tenant\permission::can_switch_tenant()) {
            return false;
        }

        return true;
    }

    /**
     * Checks if current user is allowed to delete an export
     *
     * @param int $id
     * @throws \moodle_exception
     */
    public static function require_can_delete_export(int $id): void {
        // Capability to manage export/import.
        if (!self::can_delete_export($id)) {
            throw new \moodle_exception('errorcantdeleteexport', 'tool_wp');
        }
    }

    /**
     * Is current user allowed to delete an import
     *
     * @param int $id
     * @return bool
     */
    public static function can_delete_import(int $id): bool {
        global $USER;

        $persistent = new import_persistent($id);

        // If this record is not created by current user and does not have manage capability.
        if ($persistent->get('createdby') != $USER->id && !self::can_manage_export_import()) {
            return false;
        }

        if ($persistent->get('tenantid') != tenancy::get_tenant_id() && !\tool_tenant\permission::can_switch_tenant()) {
            return false;
        }

        return true;
    }

    /**
     * Checks if current user is allowed to delete an import
     *
     * @param int $id
     * @throws \moodle_exception
     */
    public static function require_can_delete_import(int $id): void {
        // Capability to manage export/import.
        if (!self::can_delete_import($id)) {
            throw new \moodle_exception('errorcantdeleteimport', 'tool_wp');
        }
    }

    /**
     * Check if current user can export course content.
     *
     * @return bool
     */
    public static function can_export_course_content(): bool {
        return has_capability('moodle/site:config', \context_system::instance()) ||
            !empty(get_config('tool_wp', 'coursecontentbackup'));
    }
}
