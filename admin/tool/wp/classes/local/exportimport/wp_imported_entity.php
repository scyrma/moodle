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
 * Class wp_imported_entity
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Allows to prepare and import one entity from the workplace-format import file
 *
 * To initiate call importer_base::get_entities_in_workplace_export_file()
 * and iterate through results
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class wp_imported_entity {
    /** @var import_manager */
    protected $importmanager;
    /** @var string */
    protected $entityname;
    /** @var int */
    protected $entityid;
    /** @var array */
    protected $data;
    /** @var array */
    protected $mappingscallbacks = [];
    /** @var array */
    protected $excludedfields = ['_files', 'id'];
    /** @var callable */
    protected $importcallback;
    /** @var bool */
    protected $isimported = false;
    /** @var array */
    protected $files = [];
    /** @var array */
    protected $filesforitemid = [];
    /** @var callable */
    protected $validationcallback;

    /**
     * wp_imported_entity constructor.
     *
     * To initiate call importer_base::get_entities_in_workplace_export_file()
     * and iterate through results
     *
     * @param import_manager $importmanager
     * @param string $entityname
     * @param int $entityid
     */
    public function __construct(import_manager $importmanager, string $entityname, int $entityid) {
        $this->importmanager = $importmanager;
        $this->entityname = $entityname;
        $this->entityid = $entityid;
        $this->data = $this->importmanager->get_raw_data_from_workplace_export_file($entityname, $entityid);
    }

    /**
     * Annotate a mapping for the fields in the main entity
     *
     * @param string $fieldname name of the field in the entity data, the value of this field will be treated as id
     * @param string $entityname name of the entity that we will look for a mapper for
     * @param int $strictness IGNORE_MISSING/MUST_EXIST: log the error if the mapping can not be found
     * @param null $default default value to return if the mapping can not be found (only if $strictness=IGNORE_MISSING)
     * @return wp_imported_entity
     */
    public function add_mapping(string $fieldname, string $entityname, $strictness = MUST_EXIST, $default = null): self {
        $this->ensure_not_imported();
        $this->add_mapping_callback($fieldname, function(int $oldid) use ($entityname, $strictness, $default) {
            return $this->importmanager->get_mapping($entityname, $oldid, $strictness) ?: $default;
        });
        return $this;
    }

    /**
     * In some situations the simple mapping is not enough, but the callback is needed
     *
     * @param string $fieldname name of the field in the entity data, the value of this field will be treated as id
     * @param callable|null $callback takes the arguments (int $oldid, wp_imported_entity $thisobj) and returns the int $newid
     * @return wp_imported_entity
     */
    public function add_mapping_callback(string $fieldname, ?callable $callback): self {
        $this->ensure_not_imported();
        $this->mappingscallbacks[$fieldname] = $callback;
        return $this;
    }

    /**
     * Prepare record with all necessary fields excluded/mapped, ready for import
     *
     * This function is called after validation passed before actual import. This function is never
     * called when collecting errors or reviewing import settings.
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_record_for_import(): array {
        $array = [];
        // Apply mappings. Call mapping callbacks with the third argument "true" which means that mappers can
        // make modifications in the database, such as create missing entities.
        foreach ($this->data as $key => $value) {
            if (in_array($key, $this->excludedfields)) {
                continue;
            }
            if ($value && !empty($this->mappingscallbacks[$key])) {
                $callback = $this->mappingscallbacks[$key];
                $array[$key] = $callback($value, $this);
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    /**
     * Retrieve nested entities from the import, with specific fields excluded (except for 'id' that is always excluded)
     *
     * @param string $entityname
     * @param string[] $excludefields
     * @return array
     */
    public function get_nested_entities(string $entityname, array $excludefields = []): array {
        $this->ensure_not_imported();

        $nestedentityname = helper::pluralize_entityname($entityname);
        $entities = $this->data[$nestedentityname] ?? [];

        if (count($entities) > 0) {
            $excludefields = array_merge(['id'], $excludefields);

            foreach ($entities as &$entity) {
                // Store the original entity id before removing it.
                if (isset($entity['id'])) {
                    $entity['_originalid'] = $entity['id'];
                }
                // Remove excluded fields entries from the entity.
                $entity = array_diff_key($entity, array_flip($excludefields));
            }
        }

        return $entities;
    }

    /**
     * Annotate fields that need to be excluded before calling the import callback (except for 'id' that is always excluded)
     *
     * @param array $fields
     * @return wp_imported_entity
     */
    public function exclude_fields(array $fields): self {
        $this->ensure_not_imported();
        $this->excludedfields = array_merge($this->excludedfields, $fields);
        return $this;
    }

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
     * and this object (wp_imported_entity) as the second argument.
     * Callback should return int for the new id of the entity
     *
     * Important: callback MUST return new id. All validation has to be performed in "validate()" callback
     *
     * @param callable $callback takes arguments: (array $record, wp_imported_entity $obj), returns int id of the inserted
     *     record
     * @return wp_imported_entity
     */
    public function set_import_callback(callable $callback): self {
        $this->ensure_not_imported();
        $this->importcallback = $callback;
        return $this;
    }

    /**
     * Set the callback that will validate the record
     *
     * Example:
     * set_validation_callback(function(array $recordwithmappedfields, wp_imported_entity $caller) {
     *     $this->add_details_to_log(['originalidnumber' => $recordwithmappedfields['idnumber']]);
     *     if (....) {
     *         $this->add_error_to_log('idnumberconflict');
     *     }
     * });
     *
     * The record with all necessary fields excluded/mapped(*) will be passed to the callback as the first argument
     * and this object (wp_imported_entity) as the second argument.
     * Callback does not return anything, import is not executed if at least one field is not mapped or the
     * importer added any error to the log.
     * The callback should always analyse and add errors to log, even if some fields are not mapped or the conflict
     * resolutions are present. The import manager will analyse presence of conflict resolutions and will ignore such
     * errors.
     *
     * (*)Some fields may have value "-1". This means that the entity does not exist yet but will be created during import
     *
     * @param callable $validationcallback
     * @return wp_imported_entity
     */
    public function set_validation_callback(callable $validationcallback): self {
        $this->ensure_not_imported();
        $this->validationcallback = $validationcallback;
        return $this;
    }

    /**
     * Pre-validation of the import, checks that all mappings exist and executes validation callback
     *
     * @return bool
     */
    public function validate(): bool {
        $record = array_diff_key($this->data, array_fill_keys($this->excludedfields, 1));
        foreach ($this->mappingscallbacks as $key => $callback) {
            if ($value = $this->get_raw_field($key)) {
                $record[$key] = $callback($value, $this);
            }
        }

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
     * @return wp_imported_entity
     * @throws \coding_exception
     */
    public function import(importer_base $importer): self {
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
        if (!$isdryrun) {
            $this->_import_files();
        }
        // Set status as success and save log detail.
        $this->importmanager->importing_entity_finish($id);

        return $this;
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
     * Retrieve the list of files that match parameters, without any mappings applied
     *
     * @param array $parameters array of parameters, for example
     *     ['contextid' => 1, 'component' => 'tool_xyz', 'filearea' => 'xyz', 'itemid' => 0]
     * @return array
     */
    public function get_raw_files(array $parameters = []): array {
        $allfiles = $this->get_raw_field('_files', []);
        $files = [];
        foreach ($allfiles as $file) {
            foreach ($parameters as $key => $value) {
                if (''.$file[$key] !== ''.$value) {
                    continue 2;
                }
            }
            $files[] = $file;
        }
        return $files;
    }

    /**
     * Returns a path to the file attached to some entity (in the temporary directory)
     *
     * @param array $filerecord a records returned of get_raw_files(), only contenthash is used
     * @return string
     */
    public function get_file_path(array $filerecord): string {
        return $this->importmanager->get_file_path($filerecord);
    }

    /**
     * Annotate files to import or import them (if called after import())
     *
     * @param array $parameters array of parameters, for example
     *     ['contextid' => 1, 'component' => 'tool_xyz', 'filearea' => 'xyz', 'itemid' => 0]
     * @return wp_imported_entity
     */
    public function import_files(array $parameters = []): self {
        $this->files[] = $parameters;
        if ($this->isimported) {
            $this->_import_files();
        }
        return $this;
    }

    /**
     * Annotate files with the same itemid as the entity id to import or import them (if called after import())
     *
     * @param array $parameters additinal parameters, for example
     *     ['contextid' => 1, 'component' => 'tool_xyz', 'filearea' => 'xyz']
     * @param string|null $entityname entityname for mapping
     * @return wp_imported_entity
     */
    public function import_files_for_itemid(array $parameters = [], ?string $entityname = null): self {
        $parameters['_mapper'] = $entityname;
        $this->filesforitemid[] = $parameters;
        if ($this->isimported) {
            $this->_import_files();
        }
        return $this;
    }

    /**
     * Actually import files
     */
    protected function _import_files() {
        // TODO check for errors during import. For example, filename collision.
        foreach ($this->files as $parameters) {
            foreach ($this->get_raw_files($parameters) as $file) {
                $this->importmanager->import_file($file);
            }
        }
        $this->files = [];
        foreach ($this->filesforitemid as $parameters) {
            $entityname = $parameters['_mapper'] ?: $this->entityname;
            unset($parameters['_mapper']);
            foreach ($this->get_raw_files($parameters) as $file) {
                if ($file['itemid'] = $this->importmanager->get_mapping($entityname, $file['itemid'], IGNORE_MISSING)) {
                    $this->importmanager->import_file($file);
                }
                // TODO else: log that itemid was not mapped.
            }
        }
        $this->filesforitemid = [];
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
}
