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

use context;

/**
 * Allows to work with one entity when preparing it for workplace-format export
 *
 * To initiate call exporter_base::prepare_data_for_workplace_export()
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class wp_exported_entity {
    /** @var export_manager */
    protected $exportmanager;
    /** @var string */
    protected $entityname;
    /** @var array */
    protected $entitydata;
    /** @var int */
    protected $entityid;
    /** @var array */
    protected $mapping = []; // TODO deprecated, remove.
    /** @var array */
    protected $mappings = [];
    /** @var array */
    protected $nestedmappings = [];
    /** @var \stored_file[] */
    protected $files = [];
    /** @var \stored_file[] */
    protected $sharedfiles = [];
    /** @var bool */
    protected $isexported = false;
    /** @var self[] */
    static protected $activecoursebackup = [];

    /**
     * wp_exported_entity constructor.
     *
     * To initiate call exporter_base::prepare_data_for_workplace_export()
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
     * Add nested entities to the export
     *
     * @param string $entityname
     * @param array $entities
     * @param string[] $excludefields
     * @return wp_exported_entity
     */
    public function add_nested_entities(string $entityname, array $entities, array $excludefields = []): wp_exported_entity {
        $this->ensure_not_exported();

        // Nested entity names must take plural form.
        if (count($entities) > 0) {
            $nestedentityname = helper::pluralize_entityname($entityname);
            if (!empty($excludefields)) {
                foreach ($entities as &$entity) {
                    // Remove excluded fields entries from the entity.
                    $entity = array_diff_key($entity, array_flip($excludefields));
                }
            }
            $this->entitydata += [$nestedentityname => $entities];
        }

        return $this;
    }

    /**
     * Add mappings for the nested entities
     *
     * @param string $entityname name of the nested entity (for example 'linkedcourse')
     * @param string $fieldname name of the field in the nested entity (for example 'courseid')
     * @param string $mapper name of the mapper (for example 'course')
     * @return wp_exported_entity
     */
    public function add_mappings_for_nested_entities(string $entityname, string $fieldname, string $mapper): wp_exported_entity {
        $this->ensure_not_exported();

        $nestedentityname = helper::pluralize_entityname($entityname);
        $this->nestedmappings[] = [$nestedentityname, $fieldname, $mapper];

        return $this;
    }

    /**
     * Annotate fields that do not need to be exported
     *
     * Usually ['timecreated', 'timemodified']
     *
     * @param array $fields
     * @return wp_exported_entity
     */
    public function exclude_fields(array $fields): wp_exported_entity {
        $this->ensure_not_exported();
        foreach ($fields as $key) {
            unset($this->entitydata[$key]);
        }
        return $this;
    }

    /**
     * Annotate one mapping for an entity to the workplace export
     *
     * Can be called from the exporter when some data is refererred to but not included in the export
     * For example, when we export dynamic rule outcome that enrols into a course we do not export the course
     * but we would like to add some minimum information about the course (like shortname and idnumber) to
     * be able to find it in the site where we import it to
     *
     * @param string $fieldname name of the field from the data record that contains the value that needs to be mapped
     * @param string $mappedentityname name of the mapped entity
     */
    public function add_mappings(string $fieldname, string $mappedentityname): wp_exported_entity {
        $this->ensure_not_exported();
        $this->mappings[] = [$fieldname, $mappedentityname];
        return $this;
    }

    /**
     * Annotate files embedded into the textarea that need to be exported
     *
     * @param null|string $text
     * @param context $context
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @return wp_exported_entity
     */
    final public function add_files_from_text(?string $text, \context $context, string $component,
                                              string $filearea, int $itemid): wp_exported_entity {
        $this->ensure_not_exported();
        if (strpos($text, '@@PLUGINFILE@@/') === false) {
            // Quick exit.
            return $this;
        }

        $allareafiles = get_file_storage()->get_directory_files($context->id, $component, $filearea, $itemid, '/', true, false);
        // TODO find only used files somehow?

        foreach ($allareafiles as $file) {
            $this->add_file($file);
        }
        return $this;
    }

    /**
     * Annotate files in a filearea relevant to this entity
     *
     * @param context $context
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @return wp_exported_entity
     */
    public function add_area_files(\context $context, string $component, string $filearea, int $itemid): wp_exported_entity {
        $this->ensure_not_exported();
        $files = get_file_storage()->get_directory_files($context->id,
            $component, $filearea, $itemid, '/', true, false);
        foreach ($files as $file) {
            $this->add_file($file);
        }
        return $this;
    }

    /**
     * Annotate files relevant to this entity
     *
     * @param \stored_file $file
     * @return wp_exported_entity
     */
    public function add_file(\stored_file $file): wp_exported_entity {
        $this->ensure_not_exported();
        $this->files[$file->get_pathnamehash()] = $file;

        return $this;
    }

    /**
     * Prepare file record
     *
     * @param \stored_file $file
     * @return array
     */
    protected static function prepare_file(\stored_file $file) {
        return [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
            'author' => $file->get_author(),
            'license' => $file->get_license(),
            'sortorder' => $file->get_sortorder(),
            'contenthash' => $file->get_contenthash()
        ];
    }

    /**
     * Actually do export
     */
    public function export() {
        $this->ensure_not_exported();
        if ($this->files) {
            $this->entitydata['_files'] = [];
            foreach ($this->files as $file) {
                $this->entitydata['_files'][] = self::prepare_file($file);
            }
            $this->add_mappings_for_nested_entities('_files', 'contextid', 'context');
        }

        $this->exportmanager->add_data_to_workplace_export($this->entityname, $this->entityid, $this->entitydata);
        if ($this->files) {
            foreach ($this->files as $file) {
                $this->exportmanager->add_file_to_workplace_export($file);
            }
        }
        foreach ($this->sharedfiles as $file) {
            $this->exportmanager->add_file_to_workplace_export($file);
        }

        foreach ($this->mapping as $mapping) {
            // TODO deprecated, remove.
            $this->exportmanager->add_entity_mapping_to_export($mapping[0], $mapping[1]);
        }

        foreach ($this->mappings as $mapping) {
            list($fieldname, $mappedentityname) = $mapping;
            $this->exportmanager->add_entity_mapping_to_export($mappedentityname, $this->entitydata[$fieldname]);
        }

        foreach ($this->nestedmappings as $nestedmapping) {
            list($nestedentity, $fieldname, $mapper) = $nestedmapping;
            if (empty($this->entitydata[$nestedentity])) {
                continue;
            }
            foreach ($this->entitydata[$nestedentity] as $record) {
                $this->exportmanager->add_entity_mapping_to_export($mapper, $record[$fieldname]);
            }
        }

        $this->exportmanager->store_instance_name_for_review($this->entityname, $this->entitydata);
        $this->isexported = true;
    }

    /**
     * Make sure the entity has not been exported yet (check for developers)
     *
     * @throws \coding_exception
     */
    protected function ensure_not_exported() {
        if ($this->isexported) {
            throw new \coding_exception('This function can not be called after export');
        }
    }

    /**
     * Start course backup process
     *
     * To be used only from courses exporter
     *
     * @param string $backupid
     */
    public function start_course_backup($backupid) {
        self::$activecoursebackup[$backupid] = $this;
    }

    /**
     * End course backup process
     *
     * To be used only from courses exporter
     *
     * @param string $backupid
     */
    public function end_course_backup($backupid) {
        unset(self::$activecoursebackup[$backupid]);
    }

    /**
     * Called from the core backup process to check if the migrations want to override file backup
     *
     * If this backup is marked as active, Workplace migration API will take care of the file backup
     * This allows us to avoid including the same file in several courses that are backed up as part
     * of the same migration export
     *
     * @param string $backupid
     * @param \stored_file $file
     * @return string|null
     */
    public static function process_course_backup_file($backupid, \stored_file $file): ?string {
        if ($manager = (self::$activecoursebackup[$backupid] ?? null)) {
            $manager->sharedfiles[$file->get_contenthash()] = $file;
            return true;
        }
        return false;
    }

}
