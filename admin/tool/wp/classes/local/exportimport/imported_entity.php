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
 * Class imported_entity
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class imported_entity
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class imported_entity {
    /** @var import_manager */
    protected $importmanager;
    /** @var string */
    protected $entityname;
    /** @var int */
    protected $entityid;
    /** @var array */
    protected $data;
    /** @var callable */
    protected $importcallback;
    /** @var bool */
    protected $isimported = false;
    /** @var callable */
    protected $validationcallback;

    /**
     * Prepare record with all necessary fields excluded/mapped, ready for import
     *
     * This function is called after validation passed before actual import. This function is never
     * called when collecting errors or reviewing import settings.
     *
     * @return array
     * @throws \coding_exception
     */
    abstract protected function get_record_for_import(): array;

    /**
     * Get raw value for a field from the import file (without any mappings applied)
     *
     * @param string $fieldname
     * @param null $default
     * @return mixed|null
     */
    public function get_raw_field(string $fieldname, $default = null) {
        return array_key_exists($fieldname, $this->data) ? $this->data[$fieldname] : $default;
    }

    /**
     * Set the callback that will actually perform import. This is required before calling import()
     *
     * The record with all necessary fields excluded/mapped will be passed to the callback as the first argument
     * and this object (imported_entity) as the second argument.
     * Callback should return int for the new id of the entity
     *
     * Important: callback MUST return new id. All validation has to be performed in "validate()" callback
     *
     * @param callable $callback takes arguments: (array $record, imported_entity $obj), returns int id of the inserted
     *     record
     * @return imported_entity
     */
    public function set_import_callback(callable $callback): imported_entity {
        $this->ensure_not_imported();
        $this->importcallback = $callback;
        return $this;
    }

    /**
     * Set the callback that will validate the record
     *
     * Example:
     * set_validation_callback(function(array $recordwithmappedfields, imported_entity $caller) {
     *     $this->add_details_to_log(['originalidnumber' => $recordwithmappedfields['idnumber']]);
     *     if (....) {
     *         $this->add_error_to_log('idnumberconflict');
     *     }
     * });
     *
     * The record with all necessary fields excluded/mapped(*) will be passed to the callback as the first argument
     * and this object (imported_entity) as the second argument.
     * Callback does not return anything, import is not executed if at least one field is not mapped or the
     * importer added any error to the log.
     * The callback should always analyse and add errors to log, even if some fields are not mapped or the conflict
     * resolutions are present. The import manager will analyse presence of conflict resolutions and will ignore such
     * errors.
     *
     * (*)Some fields may have value "-1". This means that the entity does not exist yet but will be created during import
     *
     * @param callable $validationcallback
     * @return imported_entity
     */
    public function set_validation_callback(callable $validationcallback): imported_entity {
        $this->ensure_not_imported();
        $this->validationcallback = $validationcallback;
        return $this;
    }

    /**
     * Make sure the import function has not been called (warning for devs)
     *
     * @throws \coding_exception
     */
    protected function ensure_not_imported() {
        if ($this->isimported) {
            throw new \coding_exception('This method can not be called after import');
        }
    }

    /**
     * Can be called after import to get the id of the imported entity, returns null if not imported
     *
     * @return int|null
     * @throws \coding_exception
     */
    public function get_new_id(): ?int {
        if (!$this->isimported) {
            return null;
        }
        return $this->importmanager->get_mapping($this->entityname, $this->entityid);
    }

    /**
     * Original entity id
     *
     * @return int
     */
    public function get_original_id(): int {
        return $this->entityid;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return $this->entityname;
    }

    /**
     * Pre-validation of the import, checks that all mappings exist and executes validation callback
     *
     * @return bool
     */
    public function validate(): bool {
        $record = $this->get_record_for_import();

        // If the entity has defined a validation callback, call it. Validation callbacks do not return anything,
        // they raise errors and when error is raised it marks the entity as not validated.
        if ($this->validationcallback) {
            $callback = $this->validationcallback;
            $callback($record, $this);
        }

        return $this->importmanager->get_current_import_detail_persistent()->is_validated();
    }

    /**
     * Perform import, after the end call get_new_id() to obtain the new id
     *
     * @param importer_base $importer
     * @return imported_entity
     * @throws \coding_exception
     */
    public function import(importer_base $importer): imported_entity {
        $this->ensure_not_imported();
        $callback = $this->importcallback;
        if (!$callback) {
            throw new \coding_exception('Import callback must be set before calling import');
        }

        // Prepare the object that will keep the log details of this import.
        $this->importmanager->importing_entity_start($importer, $this->entityname, $this->entityid);

        if (!$this->validate()) {
            // Validate method is responsible for logging the error details.
            // We only log the error status here. Save log details.
            $this->importmanager->importing_entity_finish(null);
            return $this;
        }
        // This function may be called not to actually import but to "predict" import results.
        $isdryrun = $this->importmanager->get_stage() != import_manager::STAGE_IMPORT;
        if ($isdryrun) {
            $id = -1;
        } else {
            $this->importmanager->importing_entity_start_writing_to_database();
            $id = $callback($this->get_record_for_import(), $this);
        }
        $this->importmanager->set_mapping($this->entityname, $this->entityid, $id);
        $this->isimported = true;
        $this->post_import();
        // Set status as success and save log detail.
        $this->importmanager->importing_entity_finish($id);

        return $this;
    }

    /**
     * Executed after import
     */
    abstract protected function post_import();
}
