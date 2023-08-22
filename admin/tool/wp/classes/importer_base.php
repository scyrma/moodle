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

namespace tool_wp;

use tool_tenant\tenancy;
use tool_wp\local\exportimport\csv\csv_import_reader;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_detail_persistent;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\wp_imported_entities;

/**
 * Class importer_base
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class importer_base {
    /** @var string */
    protected $entrypoint;
    /** @var int */
    protected $entrypointid;
    /** @var import_manager */
    private $importmanager;
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

    /** @var string used in $this->register_entity() */
    const ENTITY_NAMEPLURAL = 'nameplural';
    /** @var string used in $this->register_entity() */
    const ENTITY_DEPENDENCIES = 'dependencies';
    /** @var string used in $this->register_entity() */
    const ENTITY_LOGERROR = 'logerror';
    /** @var string used in $this->register_entity() */
    const ENTITY_LOGSUCCESS = 'logsuccess';
    /** @var string used in $this->register_entity() */
    const ENTITY_LOGNOTICE = 'lognotice';
    /** @var string used in $this->register_entity() */
    const ENTITY_INDIVIDUALIMPORT = 'individual';
    /** @var string used in {@see register_entity()} */
    const ENTITY_INSTANCENAME_FOR_REVIEW = 'reviewname';
    /** @var string used in {@see register_potential_error()} */
    const ENTITY_POTENTIALERRORS = 'potentialerrors';
    /** @var string used in {@see register_potential_notice()} */
    const ENTITY_POTENTIALNOTICES = 'potentialnotices';
    /** @var string used in {@see register_potential_error()} */
    const ERROR_CONFLICTHEADER = 'conflictheader';
    /** @var string used in {@see register_potential_error()} */
    const ERROR_CONFLICTSOLUTION = 'conflictsolutions';
    /** @var string used in {@see register_potential_error()} */
    const ERROR_LOG = 'log';
    /** @var string used in {@see register_potential_notice()} */
    const NOTICE_LOG = 'log';

    /** @var string */
    const ENTRY_POINT_CHAINED = 'chained:';

    /** @var string Must be used instead of the entity name for the CSV data export/import */
    const CSV_DATA = 'csvdata';

    /** @var string Prefix for CSV column mapping form element names */
    const SETTING_CSV_COLUMNS_MAPPING = 'csvmapping';
    /** @var string Prefix for CSV column default values form element names */
    const SETTING_CSV_COLUMNS_DEFAULT = 'csvdefault';

    /**
     * importer_base constructor. Can not be called directly, initialise using sef::create() method
     */
    final private function __construct() {
        null;
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities, potential errors and potential notices by calling
     * $this->register_entity()
     * $this->register_potential_errors()
     * $this->register_potential_notices()
     */
    abstract protected function initialise();

    /**
     * Initialise an importer instance
     *
     * @param string $importerclassname
     * @param string $entrypoint
     * @param int $entrypointid
     * @param import_manager $importmanager
     * @return null|self
     */
    final public static function create(string $importerclassname, string $entrypoint = '', int $entrypointid = 0,
                                        ?import_manager $importmanager = null): ?self {
        if ($importerclassname && class_exists($importerclassname) && is_subclass_of($importerclassname, self::class)) {
            try {
                /** @var self $importer */
                $importer = new $importerclassname();
                $importer->entrypoint = $entrypoint;
                $importer->entrypointid = $entrypointid;
                $importer->importmanager = $importmanager;
            } catch (\Exception $e) {
                return null;
            }
            $importer->initialise();
            // Raise PHP time limit and memory limit to avoid getting memory exhausted error.
            \core_php_time_limit::raise();
            raise_memory_limit(MEMORY_EXTRA);
            return $importer;
        }
        return null;
    }

    /**
     * Format of the import file. Use exporter_base::FORMAT_* constants
     *
     * @return int
     */
    abstract public function get_format(): int;

    /**
     * Checks if this importer format is $format
     *
     * @param int $format
     * @return bool
     */
    final public function is_format(int $format): bool {
        return ($this->get_format() & $format) == $format;
    }

    /**
     * Importer icon URL (without escaping).
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/default-file', 'theme')->out(false);
    }

    /**
     * Does the "entry point" for this import comes from another importer (as chained entity)
     *
     * @return bool
     */
    protected function is_chained_entrypoint(): bool {
        return preg_match('/^'.self::ENTRY_POINT_CHAINED.'/', $this->entrypoint);
    }

    /**
     * Allows to mark importer as not available, check entry point and capabilities.
     *
     * @return bool
     */
    abstract public function is_available(): bool;

    /**
     * Importer name to show in the list of available importers
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Importer description, required for importers in formats other than Workplace
     *
     * @return string|null
     */
    public function get_description(): ?string {
        if ($this->is_format(self::FORMAT_WORKPLACE)) {
            return null;
        } else {
            throw new \coding_exception('Class ' . get_class($this) . ' must override method ' . __FUNCTION__);
        }
    }

    /**
     * Returns the destination tenant of the import, where all entities must be imported to
     *
     * Can be null if the current importer does not require a tenant and no tenant was selected
     *
     * @return int|null
     */
    final public function get_import_tenant_id(): ?int {
        $tenantid = $this->importmanager->get_import_tenant_id();
        // If we are inside a nested importer, we may need to force the tenantid even if it is not set on the whole import.
        return ($tenantid || !$this->is_tenant_required()) ? $tenantid : tenancy::get_tenant_id();
    }

    /**
     * Helper method to determine whether the export came from the current site
     *
     * Note that this is currently only supported in Workplace-format importers
     *
     * @return bool
     */
    final public function exported_from_same_site(): bool {
        return $this->importmanager->exported_from_same_site();
    }

    /**
     * Does this importer "work" only within one tenant?
     *
     * For example, departments, programs, etc can only exist inside a tenant - return true,
     * but course categories, courses, cohorts can exist outside of tenants - return false.
     *
     * The export of tenants themselves should return false.
     *
     * Importers can override.
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return true;
    }

    /**
     * Does the importer require a destination tenant to be selected? Most importers will, with the exception of the
     * tenants importer where it doesn't make sense to import them into a "destination" tenant
     *
     * @return bool
     */
    public function is_destination_tenant_required(): bool {
        return true;
    }

    /**
     * Must be called from initialise() for every entity this importer can import
     *
     * $params: array with the following keys:
     *
     * self::ENTITY_DEPENDENCIES => List of entities that need to be imported before this one; array, f.e. ['tool_tenant'],
     * self::ENTITY_LOGSUCCESS => Message to display in the log after the entity was imported; string, lang_string or callback
     *    function(int $id, array $details): string
     * self::ENTITY_LOGERROR => Message to display in the log if the entity was not imported; string, lang_string or callback
     *    function(array $details): string
     * self::ENTITY_NAMEPLURAL => Name of the entity in the plural form, used for conflict form ("Affected:") and
     *    when importing chained entities
     * self::ENTITY_INDIVIDUALEXPORT => (optional, only for entities that support individual import) -
     *    Parameters to use when importing individual entity, callback
     *    function(array $ids, array $settings): array
     *    where $ids is the list of ids of entities that need to be imported and
     *    $settings is the list of settings that must override defaults
     * self::ENTITY_INSTANCENAME_FOR_REVIEW => Resolves the instance name for the "Imported" section of the review,
     *    only needs to be implemented for "main" entities (implement for programs but not for program allocations)
     *    function(array $logdetails, int $id): string
     *
     * Except for required parameters developers may add their own to use in their own importer.
     * To retrieve parameter call $this->get_registered_entity_property([$entityname, $paramname], [... callback args...])
     *
     * @param string $entityname
     * @param array $params
     * @throws \coding_exception
     */
    final protected function register_entity(string $entityname, array $params) {
        if ($this->is_format(self::FORMAT_CSV)) {
            if ($entityname !== self::CSV_DATA && !array_key_exists(self::CSV_DATA, $this->registeredelements)) {
                throw new \coding_exception(
                    'CSV importer must register an entity with the name self::CSV_DATA as the first entity');
            }
        }

        // Check that all required parameters are present.
        $requiredkeys = [
            self::ENTITY_DEPENDENCIES,
            self::ENTITY_LOGERROR,
            self::ENTITY_NAMEPLURAL,
            self::ENTITY_LOGSUCCESS
        ];
        foreach ($requiredkeys as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \coding_exception("Parameter '$key' is missing when registering entity '$entityname' in " .
                    get_class($this));
            }
        }

        // Add potential errors and notices one by one to make sure they are validated.
        $potentialerrors = !empty($params[self::ENTITY_POTENTIALERRORS]) ? $params[self::ENTITY_POTENTIALERRORS] : [];
        $potentialnotices = !empty($params[self::ENTITY_POTENTIALNOTICES]) ? $params[self::ENTITY_POTENTIALNOTICES] : [];
        $params[self::ENTITY_POTENTIALERRORS] = [];
        $params[self::ENTITY_POTENTIALNOTICES] = [];
        foreach ($potentialerrors as $errorcode => $errorparams) {
            $this->register_potential_error($entityname, $errorcode, $errorparams);
        }
        foreach ($potentialnotices as $noticecode => $noticeparams) {
            $this->register_potential_notice($entityname, $noticecode, $noticeparams);
        }

        $this->registeredelements[$entityname] = $params;
    }

    /**
     * Must be called from initialise() for every potential error the importer can throw in $this->add_error_to_log()
     *
     * $params: array with the following keys:
     *
     * self::ERROR_LOG => Message to display in the log if the entity was not imported; string, lang_string or callback
     *    Example "Entity with ID number {$a->idnumber} already exist";
     *    function(array $details): string
     *    where $details are all details logged by the add_details_to_log() before the error was raised
     * self::ERROR_CONFLICTHEADER => Error to display in the conflict resolution form with a generic wording.
     *    Example "Entities with the same ID number already exist";
     *    string, lang_string or callback
     *    function(): string
     * self::ERROR_CONFLICTSOLUTION => Message to display for a conflict resolution solution,
     *    this can be used in the add_to_conflict_form() ($forform=true) and is also used in conflicts review ($forform=false)
     *    callback
     *    function(string $importedentity, string $errorcode, array $settings, bool $forform): string
     *
     * @param string $entityname
     * @param string $errorcode
     * @param array $params
     * @throws \coding_exception
     */
    final protected function register_potential_error(string $entityname, string $errorcode, array $params) {
        foreach ([self::ERROR_LOG, self::ERROR_CONFLICTHEADER] as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \coding_exception("Parameter '$key' is missing when registering error '$errorcode' " .
                    "for entity '$entityname' in " . get_class($this));
            }
        }
        $this->registeredelements[$entityname][self::ENTITY_POTENTIALERRORS][$errorcode] = $params;
    }

    /**
     * Must be called from initialise() for every potential notice the importer can add in $this->add_notice_to_log()
     *
     * $params: array with the following keys:
     *
     * self::NOTICE_LOG => Message to display in the log for the entity that was imported;
     *    Example "Entity with ID number {$a->idnumber} already exist";
     *    string, lang_string or callback
     *    function(array $details, array $noticedetails): string
     *    where $details are all details logged by the add_details_to_log() before the error was raised
     *    and $noticedetails are details added to this particular notice in $this->add_notice_to_log()
     *
     * @param string $entityname
     * @param string $noticecode
     * @param array $params
     * @throws \coding_exception
     */
    final protected function register_potential_notice(string $entityname, string $noticecode, array $params) {
        foreach ([self::NOTICE_LOG] as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \coding_exception("Parameter '$key' is missing when registering notice '$noticecode' " .
                    "for entity '$entityname' in " . get_class($this));
            }
        }
        $this->registeredelements[$entityname][self::ENTITY_POTENTIALNOTICES][$noticecode] = $params;
    }

    /**
     * Returns information about an entity or potential errors/noticies
     *
     * @param array $keys array of keys/parameters, first element is entityname, second is the property name,
     *     for potential errors and notices there can be more elements
     * @param array $callbackargs arguments to pass to the callback if the value is callable
     * @return mixed - null if the property is not defined or the value of the property otherwise
     */
    final public function get_registered_entity_property(array $keys, array $callbackargs = []) {
        return helper::get_array_value_or_callback($this->registeredelements, $keys, $callbackargs);
    }

    /**
     * Add settings to the import form (Step 3. Options, "what to import")
     *
     * Importers must include headers and make set all headers as expanded
     *
     * Workplace importers can use $this->get_entities_in_workplace_export_file()
     * to get the list of entities present in the export file.
     *
     * To retrive QuickForm:
     * $mform = $form->get_quick_form()
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_settings_form $form
     */
    abstract public function add_to_options_form(import_settings_form $form): void;

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    abstract public function get_summary_for_review_step(bool $importiscompleted): string;

    /**
     * Is this importer relevant to the imported file
     *
     * For example, if the imported file does not contain any data about the organisation structure and this is an
     * organisation structure importer - return false
     *
     * @param string $exporterclass exporter who created the file, only known for Workplace exports
     * @param int $fileformat Format of the uploaded file (self::FORMAT_ZIP, self::FORMAT_CSV)
     * @return bool
     */
    public function is_relevant(?string $exporterclass, int $fileformat): bool {
        if ($this->is_format(self::FORMAT_CSV)) {
            // Default implementation for CSV formats is to check that file is in CSV format.
            return $fileformat == self::FORMAT_CSV && !$this->importmanager->get_csv_reader()->get_error();
        }

        if ($this->is_format(self::FORMAT_WORKPLACE)) {
            // If this is a workplace import, check that the imported file has entities from the
            // get_entities().
            if ($exporterclass && preg_match('|\\\\exporter\\\\|', $exporterclass)) {
                // If the exporter is known, only "paired" importer is relevant. Any other importer will be considered
                // as non-relevant, even if it can work with some entities included in the file.
                // For example, certifications may include programs information, however the programs importer
                // should never be "a match" for such exports.
                // The known drawback is that if user has capability to create programs but does not have capability
                // to create certifications, no matching importer will be displayed to them, even though they
                // could potentially import programs only using the programs importer.
                $importerclass = preg_replace('|\\\\exporter\\\\|', '\\importer\\', $exporterclass);
                if ($importerclass === get_class($this)) {
                    return true;
                } else if (class_exists($importerclass) && is_subclass_of($importerclass, self::class)) {
                    return false;
                }
            }
            $entities = $this->get_entities_list_in_workplace_export_file();
            return (bool)array_intersect($entities, $this->get_entities());
        }

        // Other formats are not yet implemented.
        return false;
    }

    /**
     * Perform the import, called from ad-hoc task
     *
     * @param string $entity
     * @return void
     */
    abstract public function perform_import(string $entity): void;

    /**
     * Collect errors for the conflict resolution form
     *
     * This function by default calls do_import(), however the stage in import_manager is different.
     *
     * @param string $entity
     */
    public function collect_errors(string $entity): void {
        $this->perform_import($entity);
    }

    /**
     * Returns a list of all entities this importer can handle
     *
     * @return array
     */
    final public function get_entities(): array {
        return array_keys($this->registeredelements);
    }

    /**
     * Returns a human-readable entity name in the plural form (to be used in conflict forms "It affects: ...)
     *
     * @param string $entityname name of the entity that was registered in initialise()
     * @return string|null
     */
    final public function get_entity_display_name_plural(string $entityname): ?string {
        if (($name = $this->get_registered_entity_property([$entityname, self::ENTITY_NAMEPLURAL])) !== null) {
            return $name;
        }
        return $this->importmanager->get_imported_entity_display_name_plural($entityname);
    }

    /**
     * Get import settings (to be used during the import)
     *
     * @return array
     */
    final protected function get_import_settings(): array {
        return $this->importmanager->get_settings();
    }

    /**
     * Get a single import setting (to be used during the import)
     *
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    final protected function get_import_setting(string $name, $default = null) {
        $settings = $this->get_import_settings();
        if (array_key_exists($name, $settings)) {
            return $settings[$name];
        }
        return $default;
    }

    /**
     * Returns list of entities that are present in workplace export file
     *
     * @return array
     */
    final public function get_entities_list_in_workplace_export_file(): array {
        return $this->importmanager->get_entities_list_in_workplace_export_file();
    }

    /**
     * Retrieve all entities of a given type in the workplace export file
     *
     * @param string $entityname
     * @param callable|null $filter function that takes an array (entity) as parameter and returns boolean,
     *     if not specified all entities ids will be returned. Example:
     *     function($entity) use ($selectedids) { return in_array($entity['id'], $selectedids); }
     * @param callable|null $sorter function that takes an array (entity) as parameter and returns a field
     *     (or expression) that should be used for sorting of entities. Example:
     *     function($entity) use ($selectedids) { return $entity['pathlevel']; }
     *     By default list will be sorted by id.
     * @return wp_imported_entities
     */
    final public function get_entities_in_workplace_export_file(string $entityname,
                                                   ?callable $filter = null, ?callable $sorter = null): wp_imported_entities {
        return new wp_imported_entities($this->importmanager, $entityname, $filter, $sorter);
    }

    /**
     * Add any details to the log that will be helpful when displaying logs, errors or notices
     *
     * It is recommended that add_details_to_log() is called with some additional data
     * using its own codes instead of strings.
     *
     * These details can be accessed by various callbacks when displaying the log (see initialise())
     *
     * In Workplace-format imports the best places to call add_details_to_log() are in
     * validation and import callbacks.
     *
     * Separate log entry is created for each imported entity (but not for nested entities)
     *
     * @param array $additionaldata
     */
    final public function add_details_to_log(array $additionaldata) {
        $this->importmanager->get_current_import_detail_persistent()->add_importer_details($additionaldata);
    }

    /**
     * When an entity can not be imported, record the reason why it can not be imported
     *
     * Any additional details can be added using add_details_to_log()
     *
     * This function should be only called from validation callback.
     *
     * Function add_to_conflict_form() can provide conflict resolution form for every type of error
     *
     * @param string $errorcode error code; must be registered in $this->initialise()
     * @param bool|null $willberesolved conflict resolution is specified for this error and it will
     *     be resolved, do not fail validation. If not specified the value will be calculated automatically as:
     *     "conflict resolution action for this entity and errorcode exists and is not equal to 'skip'"
     * @throws \coding_exception
     */
    final protected function add_error_to_log(string $errorcode, ?bool $willberesolved = null) {
        global $CFG;
        $detail = $this->importmanager->get_current_import_detail_persistent();
        if ($CFG->debugdeveloper &&
            !isset($this->registeredelements[$detail->get_data_entity_name()][self::ENTITY_POTENTIALERRORS][$errorcode])) {
            // Validation in development only! We don't use debugging here because they are suppressed in AJAX requests.
            throw new \coding_exception("Error $errorcode is not registered in importer ".get_class($this));
        }
        if ($willberesolved === null) {
            $action = $this->get_conflict_resolution_setting($detail->get_data_entity_name(), $errorcode, 'action', 'skip');
            $willberesolved = $action !== 'skip';
        }
        $detail->add_importer_error($errorcode, $willberesolved);
    }

    /**
     * Add notices/warnings about the entity import, for example, that idnumber was changed

     *
     * This function should be called from import callback
     *
     * @param string $noticecode notice code; must be registered in $this->initialise()
     * @param array $details if there can be multiple notices of the same type, it may be more convenient to pass respective
     *   details individually for each notice. The callback for displaying the notice can access both general import
     *   details added in add_details_to_log() and individual notice details
     * @throws \coding_exception
     */
    final protected function add_notice_to_log(string $noticecode, array $details = []) {
        global $CFG;
        $detail = $this->importmanager->get_current_import_detail_persistent();
        if ($CFG->debugdeveloper &&
            !isset($this->registeredelements[$detail->get_data_entity_name()][self::ENTITY_POTENTIALNOTICES][$noticecode])) {
            // Validation in development only! We don't use debugging here because they are suppressed in AJAX requests.
            throw new \coding_exception("Notice $noticecode is not registered in importer ".get_class($this));
        }
        $detail->add_importer_notice($noticecode, $details);
    }

    /**
     * Allows to add an independent message to the import log if we need to create entities that were not present in the import file
     *
     * @param string $entityname name of the pseudo entity, must be defined in the 'initialise' function
     * @param int|null $successid id of the created entity on success or null if there was an error
     * @param array $additionaldata additional data to pass to the success or error callback
     * @return void
     */
    protected function add_pseudo_entity_to_log(string $entityname, ?int $successid, array $additionaldata) {
        $this->importmanager->importing_entity_start($this, $entityname, 0);
        $this->add_details_to_log($additionaldata);
        $this->importmanager->importing_entity_finish($successid);
    }

    /**
     * Set the mapping
     *
     * When importing nested elements remembers the mapping of the "oldid" and "newid"
     *
     * wp_imported_entitiy::import() sets the mapping of the imported entity automatically, no need to call it
     *
     * @param string $entity name of the entity (usually name of the db table)
     * @param int $oldid id in the export file
     * @param int $newid new id in the current site after the import
     */
    final public function set_mapping(string $entity, int $oldid, int $newid) {
        $this->importmanager->set_mapping($entity, $oldid, $newid);
    }

    /**
     * Get the mapping
     *
     * @param string $entity name of the entity (usually name of the db table)
     * @param int $oldid id in the export file
     * @param int $strictness IGNORE_MISSING/MUST_EXIST: log the error if the mapping can not be found
     * @return int|null
     */
    final public function get_mapping(string $entity, int $oldid, int $strictness = MUST_EXIST): ?int {
        return $this->importmanager->get_mapping($entity, $oldid, $strictness);
    }

    /**
     * Human-readable representation of the import result that is displayed in the import log
     *
     * Importer can call add_details_to_log() to add any data necessary for this callback.
     *
     * It is recommended that add_details_to_log() is called with some additional data
     * using its own codes instead of strings.
     * The method display_log_detail() will convert the codes to human-readable string.
     *
     * In Workplace-format imports the best places to call add_details_to_log() are in
     * validation and import callbacks.
     *
     * @param import_detail_persistent $detailpersistent
     * @return null|string
     */
    final public function display_log_detail(import_detail_persistent $detailpersistent): ?string {
        $entityname = $detailpersistent->get_data_entity_name();
        $details = $detailpersistent->get_importer_details();
        if ($detailpersistent->is_error()) {
            $value = $this->get_registered_entity_property([$entityname, self::ENTITY_LOGERROR], [$details]);
        } else if ($detailpersistent->is_success()) {
            $value = $this->get_registered_entity_property([$entityname, self::ENTITY_LOGSUCCESS],
                [$detailpersistent->get_data_imported_id(), $details]);
        }
        return $value;
    }

    /**
     * Human-readable representation of the import error that is displayed in the import log
     *
     * See also add_error_to_log() and add_details_to_log()
     *
     * @param string $errorcode code of an error raised by calling add_error_to_log()
     * @param string $importedentity
     * @param array $details details logged by the add_details_to_log() before the error was raised
     * @return string
     * @throws \coding_exception
     */
    final public function display_log_error(string $errorcode, string $importedentity, array $details): string {
        return $this->get_registered_entity_property(
            [$importedentity, self::ENTITY_POTENTIALERRORS, $errorcode, self::ERROR_LOG],
            [$details]) ??
            get_string('importunknownerror', 'tool_wp', s($errorcode));
    }

    /**
     * Human-readable representation of the import notice that is displayed in the import log
     *
     * @param import_detail_persistent $detailpersistent
     * @param string $noticecode
     * @param array $details additional details about this particular notice that were set in add_notice_to_log()
     * @return string|null
     */
    final public function display_log_notice(import_detail_persistent $detailpersistent, string $noticecode,
                                       array $details = []): ?string {
        return $this->get_registered_entity_property(
            [$detailpersistent->get_data_entity_name(), self::ENTITY_POTENTIALNOTICES, $noticecode, self::NOTICE_LOG],
            [$detailpersistent->get_importer_details(), $details]);
    }

    /**
     * Makes sure that the current format is workplace format, otherwise throws an exception
     *
     * @throws \coding_exception
     */
    final public function require_workplace_format() {
        if (!$this->is_format(self::FORMAT_WORKPLACE)) {
            throw new \coding_exception('The importer should be in Workplace format in order to use this method');
        }
    }

    /**
     * Are we currently in the process of collecting errors
     *
     * In rare cases the validation callback may need to check it to change its behavior
     *
     * @return bool
     */
    final protected function is_collecting_errors() {
        return $this->importmanager->get_stage() == import_manager::STAGE_COLLECTING_ERRORS;
    }

    /**
     * Returns all settings for the conflict resolution form that user entered
     *
     * @param string $importedentity
     * @param string $errorcode
     * @return array
     */
    final protected function get_conflict_resolution_settings(string $importedentity, string $errorcode): array {
        return $this->importmanager->get_importer_conflict_settings_for($importedentity, $errorcode);
    }

    /**
     * Returns an individual setting in the conflict resolution form that user entered
     *
     * @param string $importedentity
     * @param string $errorcode
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    final protected function get_conflict_resolution_setting(string $importedentity, string $errorcode,
                                                             string $name, $default = null) {
        $settings = $this->get_conflict_resolution_settings($importedentity, $errorcode);
        return (array_key_exists($name, $settings)) ? $settings[$name] : $default;
    }

    /**
     * Generate name for a form element in the conflict resolution form
     *
     * @param string $importedentity
     * @param string $errorcode
     * @param string $settingname name of the setting, by default 'action'
     * @return string
     */
    final protected function get_conflict_form_element_name(string $importedentity, string $errorcode,
                                                            string $settingname = 'action') {
        return helper::get_importer_setting_name_for_conflict_form($importedentity, $errorcode, $settingname);
    }

    /**
     * Header in the conflict resolution form, for example "Some users do not exist"
     *
     * @param string $importedentity
     * @param string $errorcode
     * @return string
     */
    final public function get_conflict_header(string $importedentity, string $errorcode): string {
        return $this->get_registered_entity_property(
                [$importedentity, self::ENTITY_POTENTIALERRORS, $errorcode, self::ERROR_CONFLICTHEADER],
                []) ??
            get_string('importunknownerror', 'tool_wp', s($errorcode));
    }

    /**
     * Human-readable conflict solution
     *
     * This can be used in the add_to_conflict_form() ($forform=true) and is also used in conflicts review ($forform=false)
     *
     * @param string $importedentity
     * @param string $errorcode
     * @param array $values the settings, for example ['action' => 'skip'] or ['action' => 'create', 'frmid' => 123]
     * @param bool $forform whether it is called from the add_to_conflict_form() or from the static conflict review page
     * @return string|null
     */
    final public function get_conflict_solution(string $importedentity, string $errorcode, array $values,
                                                bool $forform = true): ?string {
        $solution = $this->get_registered_entity_property(
                [$importedentity, self::ENTITY_POTENTIALERRORS, $errorcode, self::ERROR_CONFLICTSOLUTION],
                [$values, $forform]);
        if (!isset($solution) && ($values['action'] ?? '') === 'skip') {
            // Skip action does not need to be defined by importers.
            return get_string('importconflictskip', 'tool_wp');
        }
        if (!isset($solution)) {
            debugging("Importer ".get_class($this)." does not implement ERROR_CONFLICTSOLUTION for entity '$importedentity', ".
                "errorcode '$errorcode' and action '{$values['action']}'", DEBUG_DEVELOPER);
            // TODO remove once all importers implement it, this is temporary.
            return "TODO, implement self::ERROR_CONFLICTSOLUTION for entity '$importedentity', ".
                "errorcode '$errorcode' and action '{$values['action']}'";
        }
        return $solution;
    }

    /**
     * Add the importer error to the conflict resolution form (Step 5. Conflicts)
     *
     * To retrieve QuickForm:
     * $mform = $form->get_quick_form();
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode code of an error that this importer raises using $this->add_error_to_log()
     * @param array $detailsarray array of arrays: for each occurrence of error stores the details that were logged
     *     in add_details_to_log()
     * @param bool $addskipaction  can be used by overridding methods when calling parent to prevent adding a default
     *     "skip" action
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity,
                                         string $errorcode, array $detailsarray, bool $addskipaction = true): void {
        // Default behavior is to add a single action "Do not import".
        $mform = $form->get_quick_form();

        // Header.
        $hdrname = $this->get_conflict_form_element_name($importedentity, $errorcode, 'hdr');
        $header = $this->get_conflict_header($importedentity, $errorcode);
        $header = get_string('importproblem', 'tool_wp', $header);
        $mform->addElement('header', $hdrname, $header);
        $mform->setExpanded($hdrname);

        // It affects.
        $elname = $this->get_conflict_form_element_name($importedentity, $errorcode, 'staticaffects');
        $mform->addElement('static', $elname, '',
            \html_writer::tag('strong', get_string('importproblemaffects', 'tool_wp')) .
            '<br>- ' . $this->get_entity_display_name_plural($importedentity));

        // Action selector.
        $elname = $this->get_conflict_form_element_name($importedentity, $errorcode, 'staticsolution');
        $mform->addElement('static', $elname, '',
            \html_writer::tag('strong', get_string('importsolution', 'tool_wp')));

        if ($addskipaction) {
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution($importedentity, $errorcode, ['action' => 'skip']), 'skip');
            $mform->setDefault($key, 'skip');
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }

    /**
     * Summary of entities included in the workplace file (human-readable), displayed in the "Step 2 General settings"
     *
     * Returns array of strings, where each string will be displayed as a separate 'static' element
     * in the form.
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        if ($this->is_format(self::FORMAT_WORKPLACE)) {
            throw new \coding_exception('Workplace importers must override ' . __FUNCTION__);
        }
        return [];
    }

    /**
     * Is current user allowed to export some other entity (via another exporter) that can be exported as part of this entity
     *
     * For example, when exporting certifications we might want to export programs that are used in them
     *
     * @param string $entityname
     * @return bool
     */
    final protected function can_import_chained_entity(string $entityname): bool {
        return $this->importmanager->can_import_individual_entity($entityname, $this);
    }

    /**
     * Returns the list of entities that this importer can import individually
     *
     * @return array
     */
    final public function get_individual_entities_available_for_import(): array {
        $entities = [];
        foreach ($this->registeredelements as $entityname => $params) {
            if (isset($params[self::ENTITY_INDIVIDUALIMPORT])) {
                $entities[] = $entityname;
            }
        }
        return $entities;
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
        $this->importmanager->process_chained_entities($entityname, $this, $ids, $settings);
    }

    /**
     * Returns the conflicts resolutions summary for the review screen
     *
     * @return array, each element is array [$problem, $solution]
     */
    final public function get_conflicts_review(): array {
        return $this->importmanager->get_conflicts_review($this->get_import_settings(), $this);
    }

    /**
     * Returns a CSV reader (for CSV importers)
     *
     * @return csv_import_reader
     */
    final public function get_csv_reader(): csv_import_reader {
        return $this->importmanager->get_csv_reader();
    }

    /**
     * Adds a group of form elements to map the CSV fields
     *
     * Can be used by CSV importers from add_to_options_form()
     * Inside the form validation the fields can be accessed as, for example:
     *     $data[self::SETTING_CSV_COLUMNS_MAPPING.':parentid']
     *     $data[self::SETTING_CSV_COLUMNS_DEFAULT.':descriptionformat']
     * the errors can be added to the group:
     *     $errors[self::SETTING_CSV_COLUMNS_MAPPING.'_group'] = 'There is an error with mapping';
     *
     * Example of $defaultcallback:
     *
     * function(string $key, string $elementnamedef) use ($form) {
     *    if ($key === 'descriptionformat') {
     *        $el = $form->get_quick_form()->createElement('select', $elementnamedef, null, format_text_menu());
     *        $form->get_quick_form()->setDefault($elementnamedef, FORMAT_HTML);
     *        return [$el];
     *    }
     *    return [];
     * }
     *
     * @param import_settings_form $form
     * @param array $targetfields
     * @param callable|null $defaultcallback what fields to add to the "Default value" column
     * @throws \coding_exception
     */
    protected function add_csv_mapping_element(import_settings_form $form, array $targetfields, ?callable $defaultcallback) {
        if (!$this->is_format(self::FORMAT_CSV)) {
            throw new \coding_exception('Method '.__FUNCTION__.' can only be used by CSV importers');
        }

        $mform = $form->get_quick_form();
        $columns = $this->get_csv_reader()->get_columns();

        $mform->addElement('header', 'mappingheader', get_string('csvfieldsmapping', 'tool_wp'));
        $mform->setExpanded('mappingheader');

        $objs = array();
        $objs[] = $mform->createElement('static', '_csvmappingheader', '',
            \html_writer::div(
            \html_writer::div(get_string('csvwpcolumn', 'tool_wp'), 'col-md-4').
            \html_writer::div(get_string('csvcolumn', 'tool_wp'), 'col-md-4').
            \html_writer::div(get_string('csvdefaultvalue', 'tool_wp'), 'col-md-4'),
            "row w-100 border-bottom font-weight-bold"));

        $defaults = [];
        foreach ($targetfields as $key => $displayname) {
            $options = ['' => get_string('csvmappingnotspecified', 'tool_wp')];
            $elementname = self::SETTING_CSV_COLUMNS_MAPPING.':'.$key;
            $elementnamedef = self::SETTING_CSV_COLUMNS_DEFAULT.':'.$key;
            $defaults[$elementname] = '';
            foreach ($columns as $column) {
                $options[$column] = $column;
                if (helper::strings_are_very_similar($key, $column) || helper::strings_are_very_similar($displayname, $column)) {
                    $defaults[$elementname] = $column;
                }
            }
            $objs[] = $mform->createElement('static', $elementname.'__prefix', '',
                \html_writer::start_div("row w-100") .
                \html_writer::div($displayname, "col-md-4") . \html_writer::start_div("col-md-4"));
            $objs[] = $el = $mform->createElement('select', $elementname, null, $options);
            $el->setLabel($displayname);
            $objs[] = $mform->createElement('static', $elementname.'__middle', '',
                \html_writer::end_div() . \html_writer::start_div("col-md-4"));

            if ($defaultcallback && ($els = $defaultcallback($key, $elementnamedef))) {
                foreach ($els as $el) {
                    $form->get_quick_form()->hideIf($elementnamedef, $elementname, 'noteq', '');
                    $objs[] = $el;
                }
            }
            $objs[] = $mform->createElement('static', $elementname.'__postfix', '',
                \html_writer::end_div() . \html_writer::end_div());
        }

        $mform->addElement('group', self::SETTING_CSV_COLUMNS_MAPPING.'_group', '', $objs, '', false);

        foreach ($defaults as $elementname => $value) {
            $mform->setDefault($elementname, $value);
        }
    }

    /**
     * Returns the name of the file used for the import
     */
    final protected function get_file_name(): string {
        return $this->importmanager->get_file()->get_filename();
    }

    /**
     * Allows to add anything to the import log
     *
     * Logging is called automatically when using imported_entity classes (wp_imported_entity, csv_imported_entity, etc)
     * However in rare cases we might need to log something additionally
     *
     * @param string $logtype one of: self::ENTITY_LOGSUCCESS, self::ENTITY_LOGERROR, self::ENTITY_LOGNOTICE
     * @param string $entityname name of the entity, it must be registered together with the log type display callback
     * @param array $details additional details to pass to the display callback
     * @param int|null $id id of the entity (only for $type==self::ENTITY_LOGSUCCESS)
     * @throws \coding_exception
     */
    final protected function add_to_log_raw(string $logtype, string $entityname, array $details = [], ?int $id = null) {
        $requireid = false;
        if ($logtype === self::ENTITY_LOGSUCCESS) {
            $type = import_manager::DETAILTYPE_SUCCESS;
            $requireid = true;
        } else if ($logtype === self::ENTITY_LOGERROR) {
            $type = import_manager::DETAILTYPE_ERROR;
        } else if ($logtype === self::ENTITY_LOGNOTICE) {
            $type = import_manager::DETAILTYPE_NOTICE;
        } else {
            throw new \coding_exception('Unrecognised log type '.$logtype);
        }

        if (!in_array($entityname, $this->get_entities())) {
            throw new \coding_exception('Unregistered entity '.$entityname);
        }

        if (!array_key_exists($logtype, $this->registeredelements[$entityname])) {
            throw new \coding_exception('Log type '.s($logtype).' is not registered for entity '.s($entityname));
        }

        if ((bool)$id != $requireid) {
            throw new \coding_exception(
                'Id must be specified for success log types and can not be specified for other log types');
        }

        $data = [
            'entityname' => $entityname,
            'importerdetails' => $details,
        ];
        if ($id) {
            $data['id'] = $id;
        }
        $this->importmanager->log_raw($type, $data, $this);
    }
}
