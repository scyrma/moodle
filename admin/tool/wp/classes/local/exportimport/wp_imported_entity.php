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
 * Class wp_imported_entity
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
 * Allows to prepare and import one entity from the workplace-format import file
 *
 * To initiate call importer_base::get_entities_in_workplace_export_file()
 * and iterate through results
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class wp_imported_entity extends imported_entity {
    /** @var array */
    protected $mappingscallbacks = [];
    /** @var array */
    protected $excludedfields = ['_files', 'id'];
    /** @var array */
    protected $files = [];
    /** @var array */
    protected $filesforitemid = [];
    /** @var self[] */
    static protected $activecourserestores = [];

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
     * Retrieve the list of files that match parameters, without any mappings applied
     *
     * @param array $parameters array of parameters, for example
     *     ['contextid' => 1, 'component' => 'tool_xyz', 'filearea' => 'xyz', 'itemid' => 0, 'newcontextid' => 1]
     * @return array
     */
    public function get_raw_files(array $parameters = []): array {
        $allfiles = $this->get_raw_field('_files', []);
        $files = [];
        foreach ($allfiles as $file) {
            foreach ($parameters as $key => $value) {
                // Do not use newcontextid to search for the file.
                if ($key === 'newcontextid') {
                    $file['newcontextid'] = $value;
                } else if (''.$file[$key] !== ''.$value) {
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
     * @param bool $skipfileexistscheck
     * @return wp_imported_entity
     */
    public function import_files(array $parameters = [], bool $skipfileexistscheck = true): self {
        $this->files[] = $parameters;
        if ($this->isimported) {
            $this->import_files_int($skipfileexistscheck);
        }
        return $this;
    }

    /**
     * Annotate files with the same itemid as the entity id to import or import them (if called after import())
     *
     * @param array $parameters additinal parameters, for example
     *     ['contextid' => 1, 'component' => 'tool_xyz', 'filearea' => 'xyz', 'newcontextid' => 1]
     * @param string|null $entityname entityname for mapping
     * @param bool $skipfileexistscheck
     * @return wp_imported_entity
     */
    public function import_files_for_itemid(array $parameters = [], ?string $entityname = null,
            bool $skipfileexistscheck = true): self {

        $parameters['_mapper'] = $entityname;
        $this->filesforitemid[] = $parameters;
        if ($this->isimported) {
            $this->import_files_int($skipfileexistscheck);
        }
        return $this;
    }

    /**
     * Actually import files
     *
     * @param bool $skipfileexistscheck
     */
    protected function import_files_int(bool $skipfileexistscheck = true) {
        // TODO check for errors during import. For example, filename collision.
        foreach ($this->files as $parameters) {
            foreach ($this->get_raw_files($parameters) as $file) {
                $this->importmanager->import_file($file, $skipfileexistscheck);
            }
        }
        $this->files = [];
        foreach ($this->filesforitemid as $parameters) {
            $entityname = $parameters['_mapper'] ?: $this->entityname;
            unset($parameters['_mapper']);
            foreach ($this->get_raw_files($parameters) as $file) {
                if ($file['itemid'] = $this->importmanager->get_mapping($entityname, $file['itemid'], IGNORE_MISSING)) {
                    $this->importmanager->import_file($file, $skipfileexistscheck);
                }
                // TODO else: log that itemid was not mapped.
            }
        }
        $this->filesforitemid = [];
    }

    /**
     * Executed after import
     */
    protected function post_import() {
        $isdryrun = $this->importmanager->get_stage() != import_manager::STAGE_IMPORT;
        if (!$isdryrun) {
            $this->import_files_int();
        }
    }

    /**
     * Start course restore process
     *
     * To be used only from courses importer
     *
     * @param string $restoreid
     */
    public function start_course_restore($restoreid) {
        self::$activecourserestores[$restoreid] = $this;
    }

    /**
     * End course restore process
     *
     * To be used only from courses importer
     *
     * @param string $restoreid
     */
    public function end_course_restore($restoreid) {
        unset(self::$activecourserestores[$restoreid]);
    }

    /**
     * Called from the core restore process to check if the migrations want to override file restore
     *
     * If this restore is marked as active, Workplace migration API will take care of the file restore
     * This allows us to avoid including the same file in several courses that are backed up as part
     * of the same migration export
     *
     * @param string $restoreid
     * @param array $filerecord
     * @param string $contenthash
     * @return bool|null
     */
    public static function process_course_restore_file($restoreid, $filerecord, $contenthash) {
        if ($manager = (self::$activecourserestores[$restoreid] ?? null)) {
            $filerecord['newcontextid'] = $filerecord['contextid']; // It is already mapped by course restore process.
            $filerecord['contenthash'] = $contenthash;
            return $manager->importmanager->import_file($filerecord, false);
        }
        return null;
    }

}
