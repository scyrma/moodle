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
 * Class export_import_mapper_base
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for the mappers for workplace export/import functionality
 *
 * Mappers allow to map and search for entities that are not included in the export file,
 * for example, users, courses, etc.
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class export_import_mapper_base {

    /** @var import_manager */
    private $importmanager;
    /** @var export_manager */
    private $exportmanager;
    /** @var array */
    private $registeredelements = [];
    /** @var string used in registeredelements, see method $this->register_potential_error() */
    const ENTITY_POTENTIALERRORS = 'potentialerrors';
    /** @var string used in registeredelements, see method $this->register_potential_notice() */
    const ENTITY_POTENTIALNOTICES = 'potentialnotices';
    /** @var string used in registeredelements, see method $this->register_potential_error() */
    const ERROR_CONFLICTHEADER = 'conflictheader';
    /** @var string used in registeredelements, see method $this->register_potential_error() */
    const ERROR_CONFLICTSOLUTION = 'conflictsolutions';
    /** @var string used in registeredelements, see method $this->register_potential_error() */
    const ERROR_LOG = 'log';
    /** @var string used in registeredelements, see method $this->register_potential_error() */
    const ERROR_IDENTIFIER = 'identifier';
    /** @var string used in registeredelements, see method $this->register_potential_notice() */
    const NOTICE_LOG = 'log';
    /** @var string code for the mandatory error "Entity not found" */
    const NOTFOUND = 'notfound';

    /**
     * Constructor. Can not be used directly, use static create() method
     */
    final protected function __construct() {
        $this->initialise();
        if (!isset($this->registeredelements[self::ENTITY_POTENTIALERRORS][self::NOTFOUND][self::ERROR_LOG]) ||
                !isset($this->registeredelements[self::ENTITY_POTENTIALERRORS][self::NOTFOUND][self::ERROR_CONFLICTHEADER])) {
            throw new \coding_exception('The error NOTFOUND must be registered in the initialiser of '.get_class($this));
        }
    }

    /**
     * Initialises mapper and registers all potential notices
     *
     * See also
     * $this->register_potential_notice()
     * $this->register_portential_error()
     *
     * Initialise must register potential error self::NOTFOUND
     *
     * @return void
     */
    abstract protected function initialise(): void;

    /**
     * Must be called from initialise() for every potential notice the importer can add in $this->log_mapping_notice()
     *
     * $params: array with the following keys:
     *
     * self::NOTICE_LOG => Message to display in the log for the mapped entity;
     *    Example "Entity with ID number {$a->idnumber} already exist";
     *    string, lang_string or callback
     *    function(array $identifiers, array $additionaldata): string
     *    where $identifiers are the identifiers used to locate an entity
     *    and $additionaldata are all details logged by the log_mapping_notice() before the error was raised
     *
     * @param string $noticecode
     * @param array $params
     * @throws \coding_exception
     */
    final protected function register_potential_notice(string $noticecode, array $params): void {
        foreach ([self::NOTICE_LOG] as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \coding_exception("Parameter '$key' is missing when registering notice '$noticecode' " .
                    "in " . get_class($this));
            }
        }
        $this->registeredelements += [self::ENTITY_POTENTIALNOTICES => []];
        $this->registeredelements[self::ENTITY_POTENTIALNOTICES][$noticecode] = $params;
    }

    /**
     * Must be called from initialise() for any potential error
     *
     * Currently only "not found" error is supported for mappers (self::NOTFOUND)
     *
     * $params: array with the following keys:
     *
     * self::ERROR_LOG => Message to display in the log if the entity was not imported; string, lang_string or callback
     *    Example "Entity with ID number {$a->idnumber} already exist";
     *    function(array $identifier): string
     *    where $identifiers are the identifiers used to locate an entity
     * self::ERROR_CONFLICTHEADER => Error to display in the conflict resolution form with a generic wording.
     *    Example "Some users do not exist";
     *    string, lang_string or callback
     *    function(): string
     * self::ERROR_CONFLICTSOLUTION => Message to display for a conflict resolution solution,
     *    this can be used in the add_to_conflict_form() ($forform=true) and is also used in conflicts review ($forform=false)
     *    callback
     *    function(array $values, bool $forform): string
     * self::IDENTIFIER => Human-readable description of the entity (normally used in error messages that it can not be found).
     *    In most cases mappers can just call {@see display_identifier_idnumber_name()}
     *    callback
     *    function(array $identifier): string
     *
     * @param string $errorcode
     * @param array $params
     * @throws \coding_exception
     */
    final protected function register_potential_error(string $errorcode, array $params): void {
        foreach ([self::ERROR_CONFLICTHEADER, self::ERROR_LOG, self::ERROR_IDENTIFIER] as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \coding_exception("Parameter '$key' is missing when registering error " .
                    "in " . get_class($this));
            }
        }
        $this->registeredelements += [self::ENTITY_POTENTIALERRORS => []];
        $this->registeredelements[self::ENTITY_POTENTIALERRORS][$errorcode] = $params;
    }

    /**
     * Create instance by name
     *
     * @param string $mapperclass
     * @return null|export_import_mapper_base
     */
    final public static function create(string $mapperclass): ?self {
        if ($mapperclass && class_exists($mapperclass) && is_subclass_of($mapperclass, self::class)) {
            try {
                return new $mapperclass();
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Allows to mark the mapper as not available
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Entity that is being mapped (normally db table name)
     *
     * @return string
     */
    public function get_entity(): string {
        $parts = preg_split('|\\\\|', get_class($this));
        $last = array_pop($parts);
        return $last;
    }

    /**
     * Returns the array of properties of an entity that can be used to find this entity during import
     *
     * This function is used when the entity itself is not included in the export
     *
     * @param int $id
     * @return array|null
     */
    abstract public function get_mapping_data_for_workplace_export(int $id): ?array;

    /**
     * Allows to locate the existing entity that is available to the current user by default identifier
     *
     * @param string $identifier the default identifier used by the entity (normally shortname/idnumber/name)
     * @param int $tenantid strictly inside the given tenant (for entities that can be inside tenants)
     * @return int|null the id of the entity or null if not found or not available
     */
    abstract public function locate_mapping_default(string $identifier, ?int $tenantid = null): ?int;

    /**
     * Allows to locate the existing entity that is available to the current user
     *
     * This method may log notices by calling $this->log_mapping_notice()
     * See also phpdocs for display_mapping_notice() for more details
     *
     * To retrieve conflict resolution rules, use function get_conflict_resolution_setting(), for example:
     *
     * if ($this->get_conflict_resolution_setting('action') === 'create') {
     *     if ($this->can_fully_apply_conflict_resolutions()) {
     *         $id = $DB->insert(...);
     *         $this->log_mapping_notice(...);
     *         return $id;
     *     } else {
     *         return -1;
     *     }
     * }
     *
     * @param array $identifier array or known entity's attributes, for example:
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID', 'tenantid' => 1]
     * @return int|null
     */
    abstract public function locate_mapping(array $identifier): ?int;

    /**
     * Log a notice to the import report
     *
     * See phpdocs to display_mapping_notice() for more details
     *
     * @param string $noticecode code of the notice that must be registered in the initialise() method
     * @param array $identifier identifier that was used to locate the entity (shortname, name, idnumber, etc)
     * @param array $additionaldata any additional data that should be stored for this notice, it can later be
     *     used when displaying the notice
     * @throws \coding_exception
     */
    final public function log_mapping_notice(string $noticecode, array $identifier, array $additionaldata = []) {
        global $CFG;
        if ($CFG->debugdeveloper && !isset($this->registeredelements[self::ENTITY_POTENTIALNOTICES][$noticecode])) {
            // Validation in development only! We don't use debugging here because they are suppressed in AJAX requests.
            throw new \coding_exception("Notice $noticecode is not registered in mapper ".get_class($this));
        }
        if ($persistent = $this->importmanager->get_current_import_detail_persistent()) {
            $persistent->add_mapping_notice($this,
                $this->get_entity(), $noticecode, $identifier, $additionaldata);
        }
    }

    /**
     * Set export manager
     *
     * @param export_manager $exportmanager
     */
    final public function set_export_manager(export_manager $exportmanager): void {
        $this->exportmanager = $exportmanager;
    }

    /**
     * Set import manager
     *
     * @param import_manager $importmanager
     */
    final public function set_import_manager(import_manager $importmanager): void {
        $this->importmanager = $importmanager;
    }

    /**
     * Human-readable description of a notice that occurred during mapping
     *
     * While implementing the locate_mapping() function the mapper may "throw" some notices
     * for example "Two matching entities were found, using the first one" or
     * "Name matching without idnumber is not recommended" etc.
     *
     * It is recommended that log_mapping_notice() is called with some additional data
     * explaining the reason but uses internal notice codes instead of strings.
     * The method display_mapping_notice() will convert the code to human-readable string.
     *
     * This method MUST be overridden if this mapper ever calls log_mapping_notice()
     *
     * @param string $noticecode
     * @param array $identifier
     * @param array $additionaldata
     * @return string
     */
    final public function display_mapping_notice(string $noticecode, array $identifier, array $additionaldata = []): string {
        return (string)helper::get_array_value_or_callback($this->registeredelements,
            [self::ENTITY_POTENTIALNOTICES, $noticecode, self::NOTICE_LOG],
            [$identifier, $additionaldata]);
    }

    /**
     * Human-readable description of an error that occurred during mapping
     *
     * When locate_mapping() is called by the API and it returns no results, the error is automatically
     * added to the log (unless it was called with $strictness=IGNORE_MISSING)
     *
     * Each mapper must implement this function to give a human-readable error description, for example:
     * "A course with short name 'ABCDEF' was not found"
     *
     * @param array $identifier identifier used when calling locate_mapping
     * @return string
     */
    final public function display_mapping_error(array $identifier): string {
        return (string)helper::get_array_value_or_callback($this->registeredelements,
            [self::ENTITY_POTENTIALERRORS, self::NOTFOUND, self::NOTICE_LOG],
            [$identifier]);
    }

    /**
     * Get the mapping
     *
     * @param string $entity
     * @param int $oldid
     * @param int $strictness IGNORE_MISSING/MUST_EXIST: log the error if the mapping can not be found
     * @return int|null
     */
    final public function get_mapping(string $entity, int $oldid, int $strictness = MUST_EXIST): ?int {
        return $this->importmanager->get_mapping($entity, $oldid, $strictness);
    }

    /**
     * Generate name for a form element in the conflict resolution form
     *
     * @param string $settingname
     * @return string
     */
    final protected function get_conflict_form_element_name(string $settingname = 'action'): string {
        return helper::get_setting_name_for_conflict_form($this->get_entity(), $settingname);
    }

    /**
     * Header in the conflict resolution form, for example "Some users do not exist"
     *
     * @return string
     */
    final public function get_conflict_header(): string {
        return (string)helper::get_array_value_or_callback($this->registeredelements,
            [self::ENTITY_POTENTIALERRORS, self::NOTFOUND, self::ERROR_CONFLICTHEADER]);
    }

    /**
     * Human-readable conflict solution
     *
     * This can be used in the add_to_conflict_form() ($forform=true) and is also used in conflicts review ($forform=false)
     *
     * @param array $values the settings, for example ['action' => 'skip'] or ['action' => 'create', 'frmid' => 123]
     * @param bool $forform whether it is called from the add_to_conflict_form() or from the static conflict review page
     * @return string|null
     */
    final public function get_conflict_solution(array $values, bool $forform = true): ?string {
        $solution = helper::get_array_value_or_callback($this->registeredelements,
            [self::ENTITY_POTENTIALERRORS, self::NOTFOUND, self::ERROR_CONFLICTSOLUTION], [$values, $forform]);
        if (!isset($solution) && $values['action'] === 'skip') {
            // Skip action does not need to be defined by mappers.
            return get_string('importconflictskip', 'tool_wp');
        }
        if (!isset($solution)) {
            debugging("Mapper ".get_class($this)." does not implement ERROR_CONFLICTSOLUTION ".
                "for action '{$values['action']}'", DEBUG_DEVELOPER);
            // TODO remove once all mappers implement it, this is temporary.
            return "TODO, implement self::ERROR_CONFLICTSOLUTION for action '{$values['action']}'";
        }
        return $solution;
    }

    /**
     * Human-readable description of the entity (normally used in error messages that it can not be found).
     *
     * @param array $identifier
     * @param bool $usequotes Add quotes around identifier fields
     * @return string
     */
    final protected function get_identifier_for_display(array $identifier, bool $usequotes = false): string {
        return helper::get_array_value_or_callback($this->registeredelements,
            [self::ENTITY_POTENTIALERRORS, self::NOTFOUND, self::ERROR_IDENTIFIER], [$identifier, $usequotes]);
    }

    /**
     * Typical implementation of self::ERROR_IDENTIFIER that displays formatted name and/or escaped idnumber field
     *
     * @param array $identifier
     * @param string $idnumberfield
     * @param string $namefield
     * @param bool $usequotes Add quotes around identifier fields
     * @return \lang_string|mixed|string
     */
    final protected static function display_identifier_idnumber_name(array $identifier, string $idnumberfield = 'idnumber',
                                                        string $namefield = 'name', bool $usequotes = false) {
        $values = [];
        if (!empty($identifier[$idnumberfield])) {
            $values['idnumber'] = s($identifier[$idnumberfield]);
        }
        if (!empty($identifier[$namefield])) {
            $values['name'] = format_string($identifier[$namefield]);
        }

        if (!$values) {
            return !empty($identifier['id']) ? ("[". $identifier['id'] . "]") : "?";
        } else if ($usequotes) {
            // Apply quoted lang string to each value.
            array_walk($values, function(&$value) {
                $value = get_string('quotedentity', 'tool_wp', $value);
            });
        }

        if (count($values) == 1) {
            return reset($values);
        }

        return get_string('entityidentifier', 'tool_wp', (object)$values);
    }

    /**
     * Add form elements to the conflict resolution form
     *
     * Use $this->get_conflict_form_element_name() to generate name for the form elements
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param array $importedentities list of entities that can not be imported because of this error (array of strings)
     * @param array $identifiers array of identifiers of the entities that can not be found (array or arrays),
     *     mapper may decide to list them in the conflict resolution form
     * @param bool $addskipaction  can be used by overridding methods when calling parent
     */
    public function add_to_conflict_form(import_conflict_form $form, array $importedentities,
                                         array $identifiers, bool $addskipaction = true): void {

        $mform = $form->get_quick_form();
        $hdrname = $this->get_conflict_form_element_name('hdr');
        $mform->addElement('header', $hdrname, $this->get_conflict_header());
        $mform->setExpanded($hdrname);

        // Instances list.
        $prefix = \html_writer::tag('strong', get_string('importconflictinstances', 'tool_wp', count($identifiers)));
        $instances = [];
        foreach ($identifiers as $identifier) {
            $instances[] = '- ' . $this->get_identifier_for_display($identifier);
        }
        $elname = $this->get_conflict_form_element_name('staticinstances');
        $mform->addElement('static', $elname, '', $prefix . '<br>' . join('<br>', $instances));

        // Affected imported entities types.
        $itaffects = \html_writer::tag('strong', get_string('importproblemaffects', 'tool_wp')) . '<br>';
        $entities = [];
        foreach ($importedentities as $importedentity) {
            $entities[] = '- ' . $this->get_imported_entity_display_name_plural($importedentity);
        }
        $elname = $this->get_conflict_form_element_name('staticaffects');
        $mform->addElement('static', $elname, '', $itaffects . join('<br>', $entities));

        // Action selector.
        $elname = $this->get_conflict_form_element_name('staticsolution');
        $mform->addElement('static', $elname, '',
            \html_writer::tag('strong', get_string('importsolution', 'tool_wp')));

        if ($addskipaction) {
            $key = $this->get_conflict_form_element_name();
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution(['action' => 'skip']), 'skip');
            $mform->setDefault($key, 'skip');
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }

    /**
     * Returns all settings for the conflict resolution form that user entered
     *
     * @return array
     */
    final protected function get_conflict_resolution_settings(): array {
        return $this->importmanager->get_conflict_settings_for($this->get_entity());
    }

    /**
     * Returns an individual setting in the conflict resolution form that user entered
     *
     * @param string $name
     * @param null $default
     * @return mixed|null
     */
    final protected function get_conflict_resolution_setting(string $name, $default = null) {
        $settings = $this->get_conflict_resolution_settings();
        return (array_key_exists($name, $settings)) ? $settings[$name] : $default;
    }

    /**
     * Check if it is allowed to actually make database changes (i.e. create missing entities)
     *
     * When the conflict resolution settings advise to create entities but this function returns false
     * this means we are still in the validation stage. The locate_mapping() method should return -1
     *
     * @return bool
     */
    final protected function can_fully_apply_conflict_resolutions(): bool {
        return $this->importmanager->can_fully_apply_conflict_resolutions();
    }

    /**
     * Returns a human-readable entity name in the plural form (to be used in conflict forms "It affects: ...)
     *
     * @param string $importedentity
     * @return string|null
     */
    final protected function get_imported_entity_display_name_plural(string $importedentity): string {
        foreach ($this->importmanager->get_importers() as $importer) {
            if ($name = $importer->get_entity_display_name_plural($importedentity)) {
                return $name;
            }
        }
        return '';
    }

    /**
     * Destination tenant for this import
     *
     * @return int|null
     */
    final protected function get_import_tenant_id() {
        return $this->importmanager->get_import_tenant_id();
    }
}
