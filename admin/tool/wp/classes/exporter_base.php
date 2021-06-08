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
 * Class exporter_base
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_exported_entity;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for all exporters
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class exporter_base {
    /** @var string  */
    protected $entrypoint;
    /** @var int|null  */
    protected $entrypointid;
    /** @var export_manager */
    private $exportmanager;
    /** @var array */
    private $registeredelements = [];

    /** @var int */
    const FORMAT_UNKNOWN = helper::FORMAT_UNKNOWN;
    /** @var int */
    const FORMAT_ISDIRECTORY = helper::FORMAT_ISDIRECTORY;
    /** @var int */
    const FORMAT_ISSINGLEFILE = helper::FORMAT_ISSINGLEFILE;
    /** @var int */
    const FORMAT_ZIP = helper::FORMAT_ZIP;
    /** @var int */
    const FORMAT_CSV = helper::FORMAT_CSV;
    /** @var int */
    const FORMAT_JSON = helper::FORMAT_JSON;
    /** @var int */
    const FORMAT_WORKPLACE = helper::FORMAT_WORKPLACE;

    /** @var string */
    const ENTRY_POINT_CHAINED = 'chained:';

    /** @var string used in {@see register_entity()} */
    const ENTITY_INDIVIDUALEXPORT = 'individual';
    /** @var string used in {@see register_entity()} */
    const ENTITY_INSTANCENAME_FOR_REVIEW = 'reviewname';

    /**
     * importer_base constructor. Can not be called directly, initialise using sef::create() method
     */
    final protected function __construct() {
        null;
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities that can be called from other exporter as chained exports, calling {@see register_entity()}
     */
    abstract protected function initialise();

    /**
     * Initialise an exporter instance
     *
     * @param string $exporterclassname
     * @param string $entrypoint
     * @param int $entrypointid
     * @param export_manager $exportmanager
     * @return null|self
     */
    final public static function create(string $exporterclassname, string $entrypoint = '', int $entrypointid = 0,
                                  ?export_manager $exportmanager = null): ?self {
        if ($exporterclassname && class_exists($exporterclassname) && is_subclass_of($exporterclassname, self::class)) {
            try {
                /** @var self $exporter */
                $exporter = new $exporterclassname();
                $exporter->entrypoint = $entrypoint;
                $exporter->entrypointid = $entrypointid;
                $exporter->exportmanager = $exportmanager;
                $exporter->initialise();
                return $exporter;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Format of this exporter. Use constants self::FORMAT_*
     *
     * @return int
     */
    abstract public function get_format(): int;

    /**
     * Checks if this exporter format is $format
     *
     * @param int $format
     * @return bool
     */
    final public function is_format(int $format): bool {
        return ($this->get_format() & $format) == $format;
    }

    /**
     * Exporter icon URL (without escaping).
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/default-file', 'theme')->out(false);
    }

    /**
     * Does the "entry point" for this export comes from another exporter (as chained entity)
     *
     * @return bool
     */
    protected function is_chained_entrypoint(): bool {
        return preg_match('/^'.self::ENTRY_POINT_CHAINED.'/', $this->entrypoint);
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * Normally all plugins will allow empty entry point or entry point that starts with self::ENTRY_POINT_CHAINED
     *
     * @return bool
     */
    abstract public function is_available(): bool;

    /**
     * Exporter name to show in the list of available exporters
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Add export settings to the export form (Step 2. Options)
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param export_settings_form $form
     */
    abstract public function add_to_options_form(export_settings_form $form): void;

    /**
     * Does this exporter "work" only within one tenant?
     *
     * For example, departments, programs, etc can only exist inside a tenant - return true,
     * but course categories, courses, cohorts can exist outside of tenants - return false.
     *
     * The export of tenants themselves should return false.
     *
     * Exporters can override.
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return true;
    }

    /**
     * Get export tenant id
     *
     * Can be null if the current importer does not require a tenant and no tenant was selected
     *
     * @return int|null
     */
    final public function get_export_tenant_id(): ?int {
        $tenantid = $this->exportmanager->get_export_tenant_id();
        // If we are inside a nested exporter, we may need to force the tenantid even if it is not set on the whole export.
        return ($tenantid || !$this->is_tenant_required()) ? $tenantid : tenancy::get_tenant_id();
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * Sub-classes must also implement {@see get_instances_for_review_step()}
     *
     * @param bool $exportcompleted
     * @return string
     */
    abstract public function get_summary_for_review_step(bool $exportcompleted): string;

    /**
     * Returns the list of entities that will be exported
     *
     * Sub-classes must also implement {@see get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    abstract public function get_instances_for_review_step(string $entityname): array;

    /**
     * Adds exported entities in a temporary directory that export manager created.
     *
     * To retrieve settings use $this->get_export_setting() and $this->get_export_settings()
     *
     * @return void
     */
    abstract public function perform_export(): void;

    /**
     * Helper method for exporting of the workplace entities, to be used in do_export()
     *
     * Example of usage:
     *
     * $record = $DB->get_record('tool_myplugin_entity', ...);
     * $this->prepare_data_for_workplace_export('tool_myplugin_entity', (array)$record)
     *   ->exclude_fields(['timecreated', 'timemodified'])
     *   ->add_mapping('user', $record->userid)
     *   ->add_files_from_text(...)
     *   ->add_area_files(...)
     *   ->export();
     *
     * @param string $entityname
     * @param array $data
     * @return wp_exported_entity
     */
    final public function prepare_data_for_workplace_export(string $entityname, array $data): wp_exported_entity {
        $this->require_workplace_format();
        return new wp_exported_entity($this->exportmanager, $entityname, $data);
    }

    /**
     * Makes sure that the current format is workplace format, otherwise throws an exception
     *
     * @throws \coding_exception
     */
    final public function require_workplace_format() {
        if (!$this->is_format(self::FORMAT_WORKPLACE)) {
            throw new \coding_exception('The exporter should be in Workplace format in order to use this method');
        }
    }

    /**
     * Add a mapping to the workplace export
     *
     * Can be called from the exporter when some data is refererred to but not included in the export
     * For example, when we export dynamic rule outcome that enrols into a course we do not export the course
     * but we would like to add some minimum information about the course (like shortname and idnumber) to
     * be able to find it in the site where we import it to
     *
     * @param string $entityname
     * @param int $id
     */
    final public function add_mapping(string $entityname, int $id) {
        $this->require_workplace_format();
        $this->exportmanager->add_entity_mapping_to_export($entityname, $id);
    }

    /**
     * Get export settings (to be used during the export)
     *
     * @return array
     */
    final protected function get_export_settings(): array {
        return $this->exportmanager->get_export_settings();
    }

    /**
     * Get a single export setting (to be used during the export)
     *
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    final protected function get_export_setting(string $name, $default = null) {
        $settings = $this->get_export_settings();
        if (array_key_exists($name, $settings)) {
            return $settings[$name];
        }
        return $default;
    }

    /**
     * Returns the list of entities that this exporter can export individually
     *
     * @return array
     */
    final public function get_individual_entities_available_for_export(): array {
        $entities = [];
        foreach ($this->registeredelements as $entityname => $params) {
            if (isset($params[self::ENTITY_INDIVIDUALEXPORT])) {
                $entities[] = $entityname;
            }
        }
        return $entities;
    }

    /**
     * Is current user allowed to export some other entity (via another exporter) that can be exported as part of this entity
     *
     * For example, when exporting certifications we might want to export programs that are used in them
     *
     * @param string $entityname
     * @return bool
     */
    final protected function can_export_chained_entity(string $entityname): bool {
        return $this->exportmanager->can_export_individual_entity($entityname, $this);
    }

    /**
     * Perform export of other entities (done by another exporter) that can be exported as part of this entity
     *
     * @param string $entityname
     * @param array $ids list of ids
     * @param array $settings settings to override defaults, the settings keys from another exporter must be used.
     * @return void
     */
    final protected function process_chained_entities(string $entityname, array $ids, array $settings = []) {
        $this->exportmanager->process_chained_entities($entityname, $this, $ids, $settings);
    }

    /**
     * Can be called from initialise() for every entity this exporter can export individually (not required to call otherwise)
     *
     * "Individual" export means that other exporters can ask this exporter to export one entity, for example,
     * when exporting certifications we can also export programs
     *
     * $params: array with the following keys:
     *
     * self::ENTITY_INDIVIDUALEXPORT => Parameters to use when exporting individual entity, callback
     *    function(array $ids, array $settings): array
     *    where $ids is the list of ids of entities that need to be exported and
     *    $settings is the list of settings that must override defaults
     * self::ENTITY_NAMEPLURAL => Name of the entity in the plural form, to be used in the forms referring to chained entities
     * self::ENTITY_INSTANCENAME_FOR_REVIEW => Resolves the instance name for the "Exported" section of the review,
     *    only needs to be implemented for "main" entities (implement for programs but not for program allocations)
     *    function(array $record): string
     *
     * @param string $entityname
     * @param array $params
     * @throws \coding_exception
     */
    final protected function register_entity(string $entityname, array $params) {
        $this->registeredelements[$entityname] = $params;
    }

    /**
     * Returns a list of all entities this exporter can handle
     *
     * @return array
     */
    final public function get_entities(): array {
        return array_keys($this->registeredelements);
    }

    /**
     * Returns information about an entity
     *
     * @param array $keys array of keys/parameters, first element is entityname, second is the property name,
     * @param array $callbackargs arguments to pass to the callback if the value is callable
     * @return mixed - null if the property is not defined or the value of the property otherwise
     */
    final public function get_registered_entity_property(array $keys, array $callbackargs = []) {
        return helper::get_array_value_or_callback($this->registeredelements, $keys, $callbackargs);
    }

    /**
     * Returns the extension for export files
     *
     * Usually does not need to be overridden, unless there is some very specific file format
     *
     * @return string
     */
    public function get_export_file_extension(): string {
        if ($this->is_format(self::FORMAT_WORKPLACE)) {
            return '.zip';
        }
        if ($this->is_format(self::FORMAT_CSV)) {
            return '.csv';
        }
        if ($this->is_format(self::FORMAT_JSON)) {
            return '.json';
        }
        return '';
    }

    /**
     * Writes content to a CSV export, may only be used by exporters that have self::FORMAT_CSV format
     *
     * @param array $row associative array with the data
     * @param string|null $entityname name of the entity being exported (can be omitted if the exporter only registers one entity)
     * @throws \coding_exception
     */
    final public function write_to_csv_file(array $row, ?string $entityname = null) {
        $entities = $this->get_entities();
        if (empty($entityname)) {
            if (count($entities) != 1) {
                throw new \coding_exception('Second parameter ($entityname) is required when calling write_to_csv_file() in '.
                    get_class($this));
            }
            $entityname = reset($entities);
        } else if (!in_array($entityname, $entities)) {
            throw new \coding_exception('Entity '.s($entityname).' is not registered in initialise() method in '.
                get_class($this));
        }

        $this->exportmanager->write_to_csv_file($row);
        $this->exportmanager->store_instance_name_for_review($entityname, $row);
    }
}
