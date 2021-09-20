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
 * Class import_manager
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_wp\local\exportimport\csv\csv_import_reader;
use tool_wp\local\exportimport\forms\import_conflict_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class import_manager
 *
 * This class should not be used directly by exporters, importers and mappers, instead the
 * base classes should define the funcitons that would access methods of this class.
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_manager {

    /** @var int The type of import detail - notice */
    const DETAILTYPE_NOTICE = 5;
    /** @var int The type of import detail - success */
    const DETAILTYPE_SUCCESS = 2;
    /** @var int The type of import detail - success with notice */
    const DETAILTYPE_SUCCESS_WITH_NOTICES = 1;
    /** @var int The type of import detail - error */
    const DETAILTYPE_ERROR = 3;
    /** @var int The type of import detail - conflict */
    const DETAILTYPE_CONFLICT = 4;
    /** @var int The type of import detail - exception */
    const DETAILTYPE_EXCEPTION = 99;

    /** @var int import is in other stage */
    const STAGE_OTHER = 0;
    /** @var int import is in the stage of collecting errors */
    const STAGE_COLLECTING_ERRORS = 1;
    /** @var int import is in the actual import stage */
    const STAGE_IMPORT = 3;

    /** @var import_persistent */
    protected $importpersistent;
    /** @var importer_base[] used in {@see get_importers()} */
    protected $importers;
    /** @var export_import_mapper_base[] */
    protected $mappers;
    /** @var array */
    protected $mappings = [];
    /** @var \stored_file */
    protected $file = false;
    /** @var string */
    protected $unzipdir;
    /** @var csv_import_reader */
    protected $csvreader;
    /** @var import_detail_persistent */
    protected $importdetailpersistent = null;
    /** @var array */
    protected $collectederrors = null;
    /** @var int */
    protected $stage = self::STAGE_OTHER;
    /** @var array */
    protected $previousmappingerrors = [];
    /** @var bool */
    private $allowconflictresolution = false;
    /** @var importer_base[] caches all importers defined in the system, used in {@see get_all_system_importers()} */
    protected $allimporters = null;
    /** @var importer_base[][] caches all importers for given entry point, used in {@see find_importer_for()} */
    protected $chainedimporters = [];
    /** @var array list of instances that were or will be imported */
    protected $instanceslist = [];
    /** @var importer_base Stores the current importer if we are inside a chained import */
    protected $currentimporter = null;

    /**
     * import_manager constructor.
     *
     * @param int $importid
     * @param null|import_persistent $importpersistent
     */
    public function __construct(int $importid = 0, ?import_persistent $importpersistent = null) {
        if ($importpersistent) {
            $this->importpersistent = $importpersistent;
        } else {
            $this->importpersistent = new import_persistent($importid);
        }
    }

    /**
     * Guess the import tenant id before the importer was selected
     *
     * @return int|null
     */
    protected static function guess_import_tenant_id() {
        return \tool_tenant\permission::can_switch_tenant() ? null : \tool_tenant\tenancy::get_tenant_id();
    }

    /**
     * Create a new import from a draftfile, save in the database
     *
     * @param array $data
     * @param int $draftitemid
     * @return import_manager
     */
    public static function create_import_from_draftfile(array $data, int $draftitemid = 0): self {
        global $USER;
        $defaults = ['entrypoint' => '', 'entrypointid' => 0];
        $data += $defaults;
        $configdata = array_diff_key($data, $defaults);
        $persistent = new import_persistent(0, (object)[
            'createdby' => $USER->id,
            'importer' => '',
            'entrypoint' => $data['entrypoint'],
            'entrypointid' => $data['entrypointid'],
            'configdata' => json_encode($configdata),
            'reviewdata' => null,
            'status' => helper::STATUS_CREATED,
            'tenantid' => self::guess_import_tenant_id(),
        ]);
        $persistent->save();

        if ($draftitemid) {
            file_save_draft_area_files($draftitemid, \context_system::instance()->id,
                'tool_wp', 'import', $persistent->get('id'));
        }

        return new self($persistent->get('id'));
    }

    /**
     * Create a new import from a previous export, save in the database
     *
     * @param array $data
     * @param int $exportid
     * @return import_manager
     */
    public static function create_import_from_exportfile(array $data, int $exportid): self {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');
        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, \context_system::instance()->id, 'tool_wp', 'export', $exportid);
        return self::create_import_from_draftfile($data, $draftitemid);
    }

    /**
     * Returns all importers that are available and relevant for the current entry point and the uploaded file
     *
     * @return importer_base[]
     */
    public function get_importers(): array {
        if ($this->importers === null) {
            $this->importers = [];
            $entrypoint = $this->importpersistent->get('entrypoint');
            $entrypointid = $this->importpersistent->get('entrypointid');
            if ($importerclass = $this->importpersistent->get('importer')) {
                if ($importer = importer_base::create($importerclass, $entrypoint, $entrypointid, $this)) {
                    $this->importers[] = $importer;
                }
            } else {
                $allimporters = helper::get_all_importers($entrypoint, $entrypointid, $this);
                foreach ($allimporters as $importer) {
                    if ($importer->is_relevant($this->get_exporter(), $this->get_file_format())) {
                        $this->importers[] = $importer;
                    }
                }
            }
        }
        return $this->importers;
    }

    /**
     * Returns an importer used for this import (if known)
     *
     * @return importer_base|null
     */
    public function get_importer(): ?importer_base {
        $importers = $this->get_importers();
        return count($importers) == 1 ? reset($importers) : null;
    }

    /**
     * Returns all importers that are defined in the system, even those that are not available/relevant
     *
     * Used for:
     * - entity name lookup
     * - displaying contents of the export file
     *
     * @return importer_base[]
     */
    public function get_all_system_importers(): array {
        // TODO move to helper?
        if ($this->allimporters === null) {
            $this->allimporters = helper::get_all_importers('', 0, $this, false);
        }
        return $this->allimporters;
    }

    /**
     * Get all available mappers
     *
     * @return array
     */
    public function get_mappers(): array {
        if ($this->mappers === null) {
            $this->mappers = [];
            $allmappers = helper::get_all_mappers();
            foreach ($allmappers as $mapper) {
                $mapper->set_import_manager($this);
                $this->mappers[] = $mapper;
            }
        }
        return $this->mappers;
    }

    /**
     * Get an import file
     *
     * @return null|\stored_file
     */
    public function get_file(): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp', 'import',
            $this->get_import_id(), 'id', false);
        if ($file = reset($files)) {
            return $file;
        }
        return null;
    }

    /**
     * Prepare URL to download import file
     *
     * @param int $importid
     * @return \moodle_url|null
     */
    public static function get_import_file_url(int $importid): ?\moodle_url {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp', 'import',
            $importid, 'id', false);
        if ($file = reset($files)) {
            return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        }
        return null;
    }

    /**
     * Sets the general settings for the current import (called from the import wizard when user selects importer)
     *
     * @param array $data
     */
    public function save_general_settings(array $data) {
        if (array_key_exists('importer', $data)) {
            $importerclass = !empty($data['importer']) ? $data['importer'] : '';
            $this->importpersistent->set('importer', $importerclass);
            $this->importers = null; // Force to rebuild importers list.
        }

        $istenantrequired = false;
        if (!empty($importerclass) && ($importer = $this->get_importer())) {
            $istenantrequired = $importer->is_tenant_required();
        }
        if (!\tool_tenant\permission::can_switch_tenant()) {
            $tenantid = tenancy::get_tenant_id();
        } else if (!empty($data['tenantid'])) {
            $tenantid = $data['tenantid'];
        } else {
            $tenantid = $istenantrequired ? tenancy::get_tenant_id() : null;
        }
        $this->importpersistent->set('tenantid', $tenantid);
        $this->importpersistent->save();
    }

    /**
     * Save import settings
     *
     * @param array $settings
     */
    public function save_settings(array $settings) {
        if ($this->is_prepared()) {
            // TODO throw error?
            return;
        }

        $settings = array_filter($settings, function($key) {
            return !preg_match('/^mform_isexpanded_id_/', $key);
        }, ARRAY_FILTER_USE_KEY);

        $oldsettings = $this->get_settings();
        $this->importpersistent->set('configdata', json_encode($settings + $oldsettings));
        $this->importpersistent->save();

        // Changing any settings may result in the different collected errors and/or instances list.
        // Reset them so next time they are re-calculated.
        $this->collectederrors = null;
    }

    /**
     * Retrieves the data from the export file and saves it in the persistent for easier access later
     */
    public function retrieve_and_save_review_data() {
        if ($reviewdata = $this->get_raw_data_from_workplace_export_file('workplace', 0)) {
            $this->save_review_data($reviewdata);
        }
    }

    /**
     * Save review data
     *
     * @param array $data
     */
    protected function save_review_data(array $data) {
        $olddata = $this->get_review_data();
        $this->importpersistent->set('reviewdata', json_encode($data + $olddata));
        $this->importpersistent->save();
    }

    /**
     * Get import settings
     *
     * @return array
     */
    public function get_settings(): array {
        return @json_decode($this->importpersistent->get('configdata'), true) ?: [];
    }

    /**
     * All review data, to be used in the review
     *
     * @return array
     */
    public function get_review_data(): array {
        return @json_decode($this->importpersistent->get('reviewdata'), true) ?: [];
    }

    /**
     * Does this import have settings (has the user filled the settings form)
     *
     * @return bool
     */
    public function has_settings(): bool {
        // TODO more robust? Make sure that every setting has a value.
        // TODO WP-1480 must!
        return '' . $this->importpersistent->get('configdata') !== '';
    }

    /**
     * Does this import have general settings (has the user filled the general settings form)
     *
     * @return bool
     */
    public function has_general_settings(): bool {
        return !empty($this->importpersistent->get('importer'));
    }

    /**
     * Get import id
     *
     * @return int
     */
    public function get_import_id(): int {
        return (int)$this->importpersistent->get('id');
    }

    /**
     * Destination tenant for this import
     *
     * @return int|null
     */
    public function get_import_tenant_id(): ?int {
        if (!\tool_tenant\permission::can_switch_tenant()) {
            return tenancy::get_tenant_id();
        }
        $tenantid = (int)$this->importpersistent->get('tenantid');
        if (!$tenantid) {
            $importer = $this->currentimporter ?? $this->get_importer();
            if ($importer && $importer->is_tenant_required()) {
                return tenancy::get_tenant_id();
            }
        }
        return $tenantid ?: null;
    }

    /**
     * Get import status
     *
     * @return int
     */
    public function get_import_status(): int {
        return (int)$this->importpersistent->get('status');
    }

    /**
     * Does this import have conflicts, does it need to display a conflict resolution form?
     *
     * @return bool
     */
    public function has_conflicts(): bool {
        $collectederrors = $this->get_collected_errors();
        return !empty($collectederrors);
    }

    /**
     * Has the user input all the options for conflict resolutions?
     *
     * This is used by the import wizard to determine if user can go to the "Review" step or if they
     * need to go back to the "Conflict resolution" step
     *
     * @return bool
     */
    public function has_conflicts_settings(): bool {
        $collectederrors = $this->get_collected_errors();
        $settingskeys = array_keys($this->get_settings());
        foreach (array_keys($collectederrors) as $conflictkey) {
            foreach ($settingskeys as $settingkey) {
                if (strpos($settingkey, $conflictkey) === 0) {
                    continue 2;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Is this import finished (i.e. has status either "Done" or "Error")
     *
     * @return bool
     */
    public function is_completed() {
        $status = $this->importpersistent->get('status');
        return $status == helper::STATUS_DONE || $status == helper::STATUS_ERROR;
    }

    /**
     * Is this import already prepared
     *
     * No modifications to settings are allowed in this case
     * Only imports that are not yet prepared can be scheduled
     *
     * @return bool
     */
    public function is_prepared() {
        return $this->importpersistent->get('status') != helper::STATUS_CREATED;
    }

    /**
     * Schedule an ad-hoc task to execute this import
     *
     * @param bool $createadhoctask schedule ad-hoc task for the actual import
     *     can be set to false in unittests and CLI import
     */
    public function schedule_import(bool $createadhoctask = true) {
        if ($this->is_prepared()) {
            return;
        }

        $this->importpersistent->set('status', helper::STATUS_SCHEDULED);
        $this->importpersistent->save();

        // Create and schedule an adhoc task for this export.
        if ($createadhoctask) {
            $task = new \tool_wp\task\import_adhoc_task();
            $task->set_custom_data(['id' => $this->importpersistent->get('id')]);
            \core\task\manager::queue_adhoc_task($task);
        }
    }

    /**
     * Collect potential import errors
     */
    private function collect_errors() {
        $this->stage = self::STAGE_COLLECTING_ERRORS;
        // TODO respect dependencies.
        foreach ($this->get_importers() as $importer) {
            foreach ($importer->get_entities() as $entity) {
                $importer->collect_errors($entity);
            }
        }
        $this->stage = self::STAGE_OTHER;
        // Reset all mappings.
        $this->mappings = [];
    }

    /**
     * Current stage of the import (see self::STAGE_* constants)
     *
     * @return int
     */
    public function get_stage(): int {
        return $this->stage;
    }

    /**
     * Send message via helper method to notify user of status change in import
     *
     * @return mixed
     */
    protected function send_message_for_status_change() {
        $importurl = helper::import_url($this->importpersistent->get('id'));
        $strdate = userdate($this->importpersistent->get('timemodified'), get_string('strftimedatetime', 'langconfig'));
        $strstatus = helper::format_export_import_status($this->get_import_status());

        return helper::send_message($this->importpersistent->get('createdby'), 'importcomplete', $importurl,
            get_string('messagefullimportcomplete', 'tool_wp', [
                'status' => $strstatus,
                'date' => $strdate,
                'url' => $importurl->out(),
            ]));
    }

    /**
     * Execute the import
     *
     * @throws \moodle_exception
     */
    public function perform_import() {
        if (!$this->importpersistent->get('id') || $this->get_import_status() != helper::STATUS_SCHEDULED) {
            // Race condition, the import has different status.
            return;
        }

        $this->importpersistent->set('status', helper::STATUS_IN_PROGRESS);
        $this->importpersistent->save();
        $this->stage = self::STAGE_IMPORT;

        try {
            // TODO respect dependencies.
            $importer = $this->get_importer();
            foreach ($importer->get_entities() as $entity) {
                $importer->perform_import($entity);
            }

            $this->importpersistent->set('status', helper::STATUS_DONE);
        } catch (\Throwable $e) {
            $this->importpersistent->set('status', helper::STATUS_ERROR);
            $this->log_exception($e);
        }

        $this->importpersistent->save();
        $this->save_review_data(['instances' => $this->instanceslist]);
        $this->stage = self::STAGE_OTHER;

        // Inform user that the import has completed.
        $this->send_message_for_status_change();
    }

    /**
     * Set the mapping
     *
     * @param string $entity
     * @param int $oldid
     * @param int $newid
     */
    public function set_mapping(string $entity, int $oldid, ?int $newid) {
        $this->mappings += [$entity => []];
        $this->mappings[$entity][$oldid] = $newid;
    }

    /**
     * If there is a current import validation in progress - mark it as failed
     */
    protected function mark_validation_failed(): void {
        if ($this->get_current_import_detail_persistent()) {
            $this->get_current_import_detail_persistent()->mark_validation_failed();
        }
    }

    /**
     * Get the mapping
     *
     * @param string $entity
     * @param int $oldid
     * @param int $strictness IGNORE_MISSING/MUST_EXIST: log the error if the mapping can not be found
     * @return int|null
     */
    public function get_mapping(string $entity, int $oldid, int $strictness = MUST_EXIST): ?int {
        if (!$oldid) {
            return 0;
        }
        $allowdbwrite = $this->can_fully_apply_conflict_resolutions();
        if (isset($this->mappings[$entity]) && array_key_exists($oldid, $this->mappings[$entity]) &&
                !($allowdbwrite && $this->mappings[$entity][$oldid] == -1)) {
            $value = $this->mappings[$entity][$oldid];
            // Value=-1 means that there is an error but there is also a conflict resolution in place.
            // In this case we raise the error but do not mark as validation failed.
            if ((int)$value <= 0) {
                // Make sure this is reported as an error again.
                $this->ensure_mapping_error_added($entity, $oldid, $strictness);
            }
            if (!$value && $strictness == MUST_EXIST) {
                $this->mark_validation_failed();
            }
            return $value;
        }
        if (!$m = $this->get_raw_mapping_from_workplace_export_file($entity, $oldid)) {
            $m = ['id' => $oldid];
        }
        if ($fixedtenantid = $this->get_import_tenant_id()) {
            $m['tenantid'] = $fixedtenantid;
        }
        $id = $this->locate_mapping($entity, $m, $oldid, $strictness);
        // Always remember the mapping, even if it was not found.
        $this->set_mapping($entity, $oldid, $id);
        return $id;
    }

    /**
     * Locate the mapping for an entity
     *
     * @param string $entity
     * @param array $identifier something that is used to locate the entity, for example, idnumber or shortname
     *     this can also be an array/object with multiple known attributes, for example:
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID']
     * @param int $oldid
     * @param int $strictness IGNORE_MISSING/MUST_EXIST: log the error if the mapping can not be found
     * @return int|null
     */
    public function locate_mapping(string $entity, array $identifier, int $oldid = 0, int $strictness = MUST_EXIST): ?int {
        if ($mapper = helper::find_mapper_for_entity($entity, $this->get_mappers())) {
            $value = $mapper->locate_mapping($identifier);
            // Value=-1 means that there is an error but there is also a conflict resolution in place.
            // In this case we raise the error but do not mark as validation failed.
            if ((int)$value <= 0) {
                $this->add_mapping_error($mapper, $entity, $identifier, $oldid, $strictness);
            }
        } else {
            $value = null;
            $this->add_mapping_error(null, $entity, $identifier, $oldid, $strictness);
        }
        if (!$value && $strictness == MUST_EXIST) {
            $this->mark_validation_failed();
        }
        return $value;
    }

    /**
     * Add mapping error
     *
     * @param null|export_import_mapper_base $mapper
     * @param string $entityname
     * @param array $identifier
     * @param int $oldid
     * @param int $strictness
     */
    protected function add_mapping_error(?export_import_mapper_base $mapper, string $entityname,
                                         array $identifier, int $oldid, int $strictness = MUST_EXIST) {
        if ($strictness == MUST_EXIST) {
            // Mark as error straight away.
            $this->get_current_import_detail_persistent()->add_mapping_error($mapper, $entityname, $identifier);
        }
        // Remember error details so we can add it again for other similar errors.
        $key = $entityname . '-' . $oldid;
        $this->previousmappingerrors[$key] = [$mapper, $identifier];
    }

    /**
     * Make sure this is reported as an error
     *
     * @param string $entityname
     * @param int $oldid
     * @param int $strictness
     */
    protected function ensure_mapping_error_added(string $entityname, int $oldid, int $strictness = MUST_EXIST) {
        $key = $entityname . '-' . $oldid;
        if ($strictness == MUST_EXIST && array_key_exists($key, $this->previousmappingerrors)) {
            // We only try to find mapping once but the error about the missing mapping may happen several times.
            list($mapper, $identifier) = $this->previousmappingerrors[$key];
            $this->get_current_import_detail_persistent()->add_mapping_error($mapper, $entityname, $identifier);
        }
    }

    /**
     * Get a directory where we unzipped the import file (create if doesn't exit)
     *
     * @return string
     */
    protected function get_unzip_dir() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (!$this->is_zip()) {
            throw new \coding_exception('Method get_unzip_dir() can only be called for zip archives');
        }
        if ($this->unzipdir === null) {
            $this->unzipdir = make_request_directory();
            $zip = new \zip_packer();
            $this->get_file()->extract_to_pathname($zip, $this->unzipdir);
        }
        return $this->unzipdir;
    }

    /**
     * Returns a CSV reader for this import
     *
     * @return csv_import_reader
     * @throws \coding_exception
     */
    public function get_csv_reader(): csv_import_reader {
        if (!$this->is_csv()) {
            throw new \coding_exception('Method get_csv_reader() can only be called for CSV files');
        }
        if ($this->csvreader === null) {
            $this->csvreader = new csv_import_reader($this);
        }
        return $this->csvreader;
    }

    /**
     * Get raw data (without processing or mappings) about one entity from workplace export file
     *
     * @param string $entityname
     * @param int $id
     * @return array|null
     */
    public function get_raw_data_from_workplace_export_file(string $entityname, int $id): ?array {
        if (!$this->is_workplace_export()) {
            return [];
        }
        $path = $this->get_unzip_dir();
        if ($entityname == 'workplace') {
            $path .= '/workplace.xml';
        } else {
            $path .= '/data/'.$entityname.'/'.$id.'.xml';
        }
        return helper::xml_file_to_array($path);
    }

    /**
     * Get raw data about one mapping from the workplace export file
     *
     * @param string $entityname
     * @param int $id
     * @return array|null
     */
    public function get_raw_mapping_from_workplace_export_file(string $entityname, int $id): ?array {
        $path = $this->get_unzip_dir().'/mappings/'.$entityname.'/'.$id.'.xml';
        if (($mapping = helper::xml_file_to_array($path)) !== null) {
            return $mapping;
        }
        // Mapping file not found, maybe the entity itself was exported.
        return $this->get_raw_data_from_workplace_export_file($entityname, $id);
    }

    /**
     * Returns list of entities that are present in workplace export file
     *
     * @return array
     */
    public function get_entities_list_in_workplace_export_file(): array {
        if (!$this->is_workplace_export()) {
            return [];
        }
        $path = $this->get_unzip_dir().'/data';
        $files = get_directory_list($path, [], false, true, false);
        return $files;
    }

    /**
     * Returns the list of ids of the entities of the given type in the workplace export file
     *
     * @param string $entityname
     * @param callable|null $filter function that takes an array (entity) as parameter and returns boolean,
     *     if not specified all entities ids will be returned. Example:
     *     function($entity) use ($selectedids) { return in_array($entity['id'], $selectedids); }
     * @param callable|null $sorter function that takes an array (entity) as parameter and returns a field
     *     (or expression) that should be used for sorting of entities. Example:
     *     function($entity) use ($selectedids) { return $entity['pathlevel']; }
     *     By default list will be sorted by id.
     * @return array
     */
    public function get_entities_ids_in_workplace_export_file(string $entityname,
                                                              ?callable $filter = null, ?callable $sorter = null): array {
        if (!$this->is_workplace_export()) {
            return [];
        }
        $path = $this->get_unzip_dir().'/data/'.$entityname;
        $files = get_directory_list($path, [], false, false, true);
        $allids = array_map(function($path) {
            return basename($path, '.xml');
        }, $files);
        if (empty($sorter) && !$filter) {
            sort($allids);
            return array_values($allids);
        }

        // Perform sorting and filtering.
        $objs = [];
        foreach ($allids as $id) {
            $entity = helper::xml_file_to_array($path."/{$id}.xml");
            if ($filter && !$filter($entity)) {
                continue;
            }
            $objs[$id] = $sorter ? $sorter($entity) : $id;
        }
        if ($sorter) {
            asort($objs);
        } else {
            ksort($objs);
        }
        return array_keys($objs);
    }

    /**
     * Import a file - given a filerecord find it in the archive and add to the file storage
     *
     * @param array $filerecord record to be inserted in the 'files' table:
     *     if 'newcontextid' is specified it will be used as contextid for the record without modifications
     *     if 'newcontextid' is not specified, the value of 'contextid' will be mapped to the new context
     *     'contenthash' should be present and is used to find the actual content in the zip file
     * @param bool $skipfileexistscheck If set to true, checking whether the file already exists will be skipped and
     *      we'll try to create it always, which will cause an exception to be thrown when it does already exist
     * @return bool true if file was imported or already existed, false if file path was not found
     */
    public function import_file(array $filerecord, bool $skipfileexistscheck = true): bool {
        global $CFG;
        $filepath = $this->get_file_path($filerecord);
        if (!file_exists($filepath)) {
            return false;
        }
        // If newcontextid has been passed there is no need to map it.
        if (isset($filerecord['newcontextid'])) {
            $filerecord['contextid'] = $filerecord['newcontextid'];
            unset($filerecord['newcontextid']);
        } else {
            $filerecord['contextid'] = $this->get_mapping('context', $filerecord['contextid']);
        }

        unset($filerecord['id'], $filerecord['contenthash'], $filerecord['newcontextid']);

        // Prior to creating the new file, determine whether it already exists.
        $fs = get_file_storage();
        if ($skipfileexistscheck || !$fs->file_exists($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
                $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename'])) {

            if (($filerecord['license'] ?? null) == null) {
                $filerecord['license'] = $CFG->sitedefaultlicense;
            }
            $fs->create_file_from_pathname($filerecord, $filepath);
        }
        return true;
    }

    /**
     * Returns a path to the file attached to some entity (in the temporary directory)
     *
     * @param array $filerecord a records from the archive, only contenthash is used
     * @return string
     */
    public function get_file_path(array $filerecord) {
        $contenthash = $filerecord['contenthash'];
        $filepath = $this->get_unzip_dir() . '/files/' . substr($contenthash, 0, 2) . '/' .
            substr($contenthash, 2, 2) . '/' . $contenthash;
        return $filepath;
    }

    /**
     * Write to import log information about an exception
     *
     * @param \Throwable $exception
     */
    public function log_exception(\Throwable $exception) {
        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ];
        $this->log_raw(self::DETAILTYPE_EXCEPTION, $data);
    }

    /**
     * Write a "raw" record to the log
     *
     * @param int $type
     * @param array $data
     * @param importer_base|null $importer
     */
    public function log_raw(int $type, array $data, ?importer_base $importer = null) {
        $persistent = new import_detail_persistent(0, (object)[
            'importid' => $this->get_import_id(),
            'type' => $type
        ]);
        if ($importer) {
            $persistent->set('importer', get_class($importer));
        }
        $persistent->set('data', $data);
        $persistent->save();
    }

    /**
     * Is the current user the same user who created the import?
     *
     * @return bool
     */
    public function is_same_user() {
        global $USER;
        return $USER->id == $this->importpersistent->get('createdby');
    }

    /**
     * Sets the object responsible for storing logs about the current operation
     *
     * @param import_detail_persistent $persistent
     */
    public function set_current_import_detail_persistent(import_detail_persistent $persistent) {
        $persistent->set('importid', $this->get_import_id());
        $this->importdetailpersistent = $persistent;
    }

    /**
     * Called in the beginning of importing an entity to collect errors and notices about importing it
     *
     * @param importer_base $importer
     * @param string $entityname
     * @param int $originalid
     */
    public function importing_entity_start(importer_base $importer,
                                           string $entityname, int $originalid) {
        $importdetailpersistent = import_detail_persistent::prepare_from_importer($importer,
            $entityname, $originalid);
        $this->set_current_import_detail_persistent($importdetailpersistent);
    }

    /**
     * Called in the end of successful importing an entity to save collected notices
     *
     * @param int $id
     */
    public function importing_entity_finish(?int $id) {
        $p = $this->get_current_import_detail_persistent();
        if (!$id) {
            $p->set_error();
        } else {
            $p->set_success($id);
        }
        $this->store_instance_name_for_review($p->get_data_entity_name(), $p->get_importer_details(), $id);
        if ($this->stage != self::STAGE_COLLECTING_ERRORS) {
            $p->save();
        } else {
            $this->add_to_collected_errors($p->get_data_entity_name(), $p->get_data_mapping_errors());
            $this->add_to_collected_errors_importer($p->get('importer'), $p->get_data_entity_name(),
                $p->get_data_importer_errors(), $p->get_importer_details());
        }
        $this->allowconflictresolution = false;
    }

    /**
     * Called during importing an entity after successful validation
     *
     * This indicates that all mappers and importers can start actively applying conflict resolutions
     * that may include database modifications (creating non-existing entities)
     *
     * @throws \coding_exception
     */
    public function importing_entity_start_writing_to_database() {
        if ($this->get_stage() != self::STAGE_IMPORT) {
            throw new \coding_exception('Only allowed in the import stage');
        }
        $this->allowconflictresolution = true;
        // Clear all errors that might have been accumulated during validation. Since they did not actually
        // fail validation, they must have conflict resolutions and will not longer be errors.
        $p = $this->get_current_import_detail_persistent();
        $p->clear_errors();
    }

    /**
     * Is it allowed for mappers and importers to actively apply conflict resolutions
     *
     * @return bool
     */
    public function can_fully_apply_conflict_resolutions(): bool {
        return $this->allowconflictresolution;
    }

    /**
     * During error collection adds information about an error to the list of collected errors (used by mappers)
     *
     * @param string $importedentity
     * @param array $errors
     */
    protected function add_to_collected_errors(string $importedentity, array $errors) {
        foreach ($errors as $errordata) {
            if (isset($errordata['mapper'])) {
                $conflictkey = helper::get_setting_name_for_conflict_form($errordata['entityname'], '');
                $this->collectederrors += [$conflictkey => [
                    'entityname' => $errordata['entityname'],
                    'importedentities' => [],
                    'mapper' => $errordata['mapper'],
                    'identifiers' => []
                ]];
                $this->collectederrors[$conflictkey]['importedentities'][$importedentity] = $importedentity;
                // Add an identifier only if it was not added before.
                $identifierkey = md5(json_encode($errordata['identifier']));
                $this->collectederrors[$conflictkey]['identifiers'][$identifierkey] = $errordata['identifier'];
            }
        }
    }

    /**
     * During error collection adds information about an error to the list of collected errors (used by importers)
     *
     * @param string|null $importerclass
     * @param string $importedentity
     * @param array $errors
     * @param array $details
     */
    protected function add_to_collected_errors_importer(?string $importerclass, string $importedentity,
                                                        array $errors, array $details) {
        foreach ($errors as $code) {
            $conflictkey = helper::get_importer_setting_name_for_conflict_form($importedentity, $code, '');
            $this->collectederrors += [$conflictkey => [
                'importer' => $importerclass,
                'importedentity' => $importedentity,
                'errorcode' => $code,
                'details' => []
            ]];
            $this->collectederrors[$conflictkey]['details'][] = $details;
        }
    }

    /**
     * Retrieves and returns the list of collected errors
     *
     * @return array|null
     */
    public function get_collected_errors() {
        if ($this->collectederrors === null) {
            $this->collectederrors = [];
            $this->instanceslist = [];
            $this->collect_errors();
        }
        return $this->collectederrors;
    }

    /**
     * Adds elements to the conflict resolution form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     */
    public function conflict_form_definition(import_conflict_form $form) {
        $allerrors = $this->get_collected_errors();
        // Add conflict resolution form for each importedentity+mappedentity pairs.
        foreach ($allerrors as $conflictkey => $errordata) {
            if (!empty($errordata['mapper'])) {
                // Conflict resolution forms for mappers errors.
                if ($mapper = export_import_mapper_base::create($errordata['mapper'])) {
                    $mapper->set_import_manager($this);
                    $mapper->add_to_conflict_form($form, $errordata['importedentities'], $errordata['identifiers']);
                }
            } else if (!empty($errordata['importer'])) {
                // Conflict resolution forms for importer errors.
                if ($importer = importer_base::create($errordata['importer'],
                        $this->importpersistent->get('entrypoint'), $this->importpersistent->get('entrypointid'), $this)) {
                    $importer->add_to_conflict_form($form,
                        $errordata['importedentity'], $errordata['errorcode'], $errordata['details']);
                }
            }

            // Default behavior is to skip. TODO Add visual indicator.
            $mform = $form->get_quick_form();
            if (!$mform->elementExists($conflictkey . 'action')) {
                $mform->addElement('hidden', $conflictkey . 'action', 'skip');
                $mform->setType($conflictkey . 'action', PARAM_ALPHANUMEXT);
            }
        }
    }

    /**
     * Returns settings from the conflict resolution form
     *
     * @param string $mappedentity
     * @return array
     */
    public function get_conflict_settings_for(string $mappedentity) {
        $elementprefix = helper::get_setting_name_for_conflict_form($mappedentity, '');
        $allsettings = $this->get_settings();
        $settings = [];
        foreach ($allsettings as $key => $value) {
            if (strpos($key, $elementprefix) === 0) {
                $settings[substr($key, strlen($elementprefix))] = $value;
            }
        }
        return $settings;
    }

    /**
     * Returns settings from the conflict resolution form
     *
     * @param string $importedentity
     * @param string $errorcode
     * @return array
     */
    public function get_importer_conflict_settings_for(string $importedentity, string $errorcode) {
        $elementprefix = helper::get_importer_setting_name_for_conflict_form($importedentity, $errorcode, '');
        $allsettings = $this->get_settings();
        $settings = [];
        foreach ($allsettings as $key => $value) {
            if (strpos($key, $elementprefix) === 0) {
                $settings[substr($key, strlen($elementprefix))] = $value;
            }
        }
        return $settings;
    }

    /**
     * Gets the object responsible for storing logs about the current operation
     *
     * @return import_detail_persistent
     */
    public function get_current_import_detail_persistent(): ?import_detail_persistent {
        return $this->importdetailpersistent;
    }

    /**
     * Returns logs about the performed import
     *
     * @return array array of arrays where each element has keys: 'detail' (string), 'errors' (array of strings) and
     *     'notices' (array of strings)
     */
    public function get_import_logs() {
        /** @var import_detail_persistent[] $details */
        $details = import_detail_persistent::get_records(['importid' => $this->get_import_id()], 'id');
        if (!$details) {
            return [];
        }
        $rv = [];
        foreach ($details as $detail) {
            $importer = null;
            if (($importerclass = $detail->get('importer'))) {
                $importer = importer_base::create($importerclass, $this->importpersistent->get('entrypoint'),
                    $this->importpersistent->get('entrypointid'), $this);
                if ($importer) {
                    $importerdetail = $importer->display_log_detail($detail);
                } else {
                    $importerdetail = '??? ['.$importerclass.']'; // TODO generic string.
                }
            } else if ($detail->is_exception()) {
                $importerdetail = helper::format_logged_exception($detail->get('data'));
            } else {
                $importerdetail = '';
            }
            $errors = $detail->get_formatted_errors($importer);
            $notices = $detail->get_formatted_notices($importer);

            if (strlen($importerdetail) || !empty($errors) || !empty($notices)) {
                $rv[] = [
                    'detail' => $importerdetail,
                    'errors' => $errors,
                    'notices' => $notices
                ];
            }
        }
        return $rv;
    }

    /**
     * Is this a workplace export file
     *
     * @return bool
     */
    public function get_file_format() {
        $file = $this->get_file();
        $settings = $this->get_settings();
        return !empty($settings['fileformat']) ? $settings['fileformat'] : helper::detect_file_format($file);
    }

    /**
     * Is this a workplace export file
     *
     * @return bool
     */
    public function is_zip() {
        $format = $this->get_file_format();
        return ($format & helper::FORMAT_ZIP) == helper::FORMAT_ZIP;
    }

    /**
     * Is this a CSV export file
     *
     * @return bool
     */
    public function is_csv() {
        $format = $this->get_file_format();
        return ($format & helper::FORMAT_CSV) == helper::FORMAT_CSV;
    }

    /**
     * Is this a workplace export file
     *
     * @return bool
     */
    public function is_workplace_export() {
        if (!$this->is_zip()) {
            return false;
        }
        $filepath = $this->get_unzip_dir() . '/workplace.xml';
        if (!file_exists($filepath)) {
            return false;
        }
        return true;
    }

    /**
     * Return information about a Workplace export, from the contents of the root workplace.xml file
     *
     * @return array|null
     */
    protected function get_workplace_export_info(): ?array {
        return $this->get_raw_data_from_workplace_export_file('workplace', 0);
    }

    /**
     * Helper method to determine whether the export came from the current site
     *
     * Note that this is currently only supported in Workplace-format importers
     *
     * @param array $exportinfo If caller already has the export info, pass it to prevent it being re-loaded
     * @return bool
     */
    public function exported_from_same_site(array $exportinfo = []): bool {
        $exportinfo = $exportinfo ?: $this->get_workplace_export_info();

        return (strcmp($exportinfo['siteidentifier'] ?? '', md5(get_site_identifier())) === 0);
    }

    /**
     * Summary about a workplace export file (used in the import wizard)
     *
     * @return array
     */
    public function get_export_file_summary(): array {
        if (!$this->is_workplace_export()) {
            // TODO return summary for non-workplace files as well.
            return [];
        }

        $info = $this->get_workplace_export_info();
        $author = s($info['createdbyname']);
        $date = userdate($info['timecreated']);
        $site = s($info['wwwroot']);
        $version = s($info['release']) ?? '';
        // TODO WP-1846 Show summary and instances from file. Check how it looks in CLI import.
        $filecontent = $this->get_export_file_content_for_overview_page();

        // Append string to site URL if the export was created on the same site we are importing to.
        if ($this->exported_from_same_site($info)) {
            $site .= get_string('thissite', 'tool_wp');
        }

        return [
            'createdby' => ['label' => get_string('createdby', 'tool_wp'), 'value' => $author],
            'date' => ['label' => get_string('date'), 'value' => $date],
            'site' => ['label' => get_string('site'), 'value' => $site],
            'version' => ['label' => get_string('version'), 'value' => $version],
            'content' => ['label' => get_string('content', 'tool_wp'), 'value' => $filecontent],
        ];
    }

    /**
     * Summary about a workplace export file (used in the import wizard)
     *
     * @return string|null
     */
    public function get_export_file_summary_as_html() {
        if (!$summary = $this->get_export_file_summary()) {
            return null;
        }

        $table = new \html_table();
        $table->attributes['class'] = 'admintable generaltable';
        foreach ($summary as $key => $data) {
            $value = $data['value'];
            if ($key === 'content') {
                $value = '';
                foreach ($data['value'] as $idx => $c) {
                    $value .= \html_writer::div($c);
                }
            }
            $table->data[] = new \html_table_row([
                new \html_table_cell($data['label']),
                new \html_table_cell($value)
            ]);
        }
        return \html_writer::table($table);
    }

    /**
     * Shows preview of an export file
     *
     * @return string|null
     */
    public function get_export_file_preview_as_html(): ?string {
        global $OUTPUT;

        if (!$this->is_csv()) {
            // Currently only implemented for CSV exports.
            return null;
        }
        $data = $this->get_csv_reader()->get_preview(3);
        if (!$data) {
            $errorstring = $this->get_csv_reader()->get_error();
            return \html_writer::tag('div', $errorstring, ['class' => 'alert alert-danger']);
        }

        $table = new \html_table();
        $table->attributes['class'] = 'admintable generaltable';
        $table->head = $data['columns'];
        $table->data = $data['rows'];

        $content = \html_writer::table($table);
        $showmorecount = $data['total'] - count($data['rows']);

        return $OUTPUT->render(new export_file_preview($this->get_import_id(), $content, $showmorecount));
    }

    /**
     * Exporter that created this export file (if known)
     *
     * @return string|null name of the class of the exporter
     */
    protected function get_exporter(): ?string {
        if (!$this->is_workplace_export()) {
            return null;
        }
        $info = $this->get_workplace_export_info();
        if (!empty($info['exporter'])) {
            $exporter = (string)$info['exporter'];
            if (class_exists($exporter) && is_subclass_of($exporter, exporter_base::class)) {
                return $exporter;
            }
        }
        return null;
    }

    /**
     * Collects summary of entities included in the export file from relevant importers
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        $contentelements = [];
        foreach ($this->get_all_system_importers() as $importer) {
            $elements = array_values($importer->get_export_file_content_for_overview_page());
            $contentelements = array_merge($contentelements, $elements);
        }
        return $contentelements;
    }

    /**
     * Finds an importer available for importing an entity as chained entity
     *
     * @param string $entityname
     * @param importer_base $caller
     * @return importer_base|null
     */
    protected function find_importer_for(string $entityname, importer_base $caller): ?importer_base {
        $entrypoint = importer_base::ENTRY_POINT_CHAINED . get_class($caller);
        if (!array_key_exists($entrypoint, $this->chainedimporters)) {
            $this->chainedimporters[$entrypoint] = helper::get_all_importers($entrypoint, 0, $this);
        }
        foreach ($this->chainedimporters[$entrypoint] as $importer) {
            if (in_array($entityname, $importer->get_individual_entities_available_for_import())) {
                return $importer;
            }
        }
        return null;
    }

    /**
     * Is current user allowed to export some other entity (via another exporter) that can be exported as part of this entity
     *
     * For example, when exporting certifications we might want to export programs that are used in them
     *
     * @param string $entityname
     * @param importer_base $caller
     * @return bool
     */
    public function can_import_individual_entity(string $entityname, importer_base $caller): bool {
        if ($exporter = $this->find_importer_for($entityname, $caller)) {
            return true;
        }
        return false;
    }

    /**
     * Perform export of other entities that can be exported individually chained from the caller
     *
     * @param string $entityname
     * @param importer_base $caller
     * @param array $ids
     * @param array $overridesettings
     */
    public function process_chained_entities(string $entityname, importer_base $caller, array $ids, array $overridesettings = []) {
        if (!$importer = $this->find_importer_for($entityname, $caller)) {
            return;
        }
        // Settings that need to be passed to the found importer.
        $settings = $importer->get_registered_entity_property([$entityname, importer_base::ENTITY_INDIVIDUALIMPORT],
            [$ids, $overridesettings]);
        // Remember current settings and override them.
        $oldsettings = $this->importpersistent->get('configdata');
        $this->importpersistent->set('configdata', json_encode($settings + $this->get_settings()));

        // Set the "current importer" to the chained importer.
        $cachedvalue = $this->currentimporter;
        $this->currentimporter = $importer;
        // Depending on the stage either collect errors or perform import.
        if ($this->stage == self::STAGE_COLLECTING_ERRORS) {
            $importer->collect_errors($entityname);
        } else {
            // Perform import but ignore exceptions.
            try {
                $importer->perform_import($entityname);
            } catch (\Throwable $t) {
                // TODO log error.
                null;
            }
        }
        $this->currentimporter = $cachedvalue;

        // Restore old settings.
        $this->importpersistent->set('configdata', $oldsettings);
    }

    /**
     * Returns a human-readable entity name in the plural form
     *
     * To be used in conflict forms "It affects: ..."
     * and also for the chained entities "Import: ..."
     *
     * @param string $importedentity
     * @return string|null
     */
    final public function get_imported_entity_display_name_plural(string $importedentity): string {
        foreach ($this->get_all_system_importers() as $importer) {
            if (($name = $importer->get_registered_entity_property([$importedentity, importer_base::ENTITY_NAMEPLURAL])) !== null) {
                return $name;
            }
        }
        return '[[' . s($importedentity) . ']]';
    }

    /**
     * Get import progress
     *
     * @return int
     */
    public function get_import_progress(): int {
        // TODO: For the moment, return 0 or 100 depending on status.
        return $this->importpersistent->get('status') == helper::STATUS_DONE ? 100 : 0;
    }

    /**
     * Returns the conflicts resolutions summary for the review screen
     *
     * @param array $settings all settings from the importer
     * @param importer_base $caller the importer who called this method (only skipped in unittests)
     * @return array, each element is array [$problem, $solution]
     */
    public function get_conflicts_review(array $settings, ?importer_base $caller): array {
        $review = [];

        foreach (helper::parse_mapping_conflict_settings($settings) as $mapperconflict) {
            $mappedentity = $mapperconflict['entityname'];
            $mapper = helper::find_mapper_for_entity($mappedentity, $this->get_mappers());
            if ($mapper) {
                $solution = $mapper->get_conflict_solution($mapperconflict['settings'], false);
                if (isset($solution)) {
                    $review[] = [$mapper->get_conflict_header(), $solution];
                }
            }
        }

        foreach (helper::parse_importer_conflict_settings($settings) as $importerconflict) {
            $importedentity = $importerconflict['importedentity'];
            $errorcode = $importerconflict['errorcode'];
            $importer = null;
            if ($caller && in_array($importedentity, $caller->get_entities())) {
                $importer = $caller;
            } else if ($caller) {
                $importer = $this->find_importer_for($importedentity, $caller);
            }
            if ($importer) {
                $solution = $importer->get_conflict_solution($importedentity, $errorcode, $importerconflict['settings'], false);
                if (isset($solution)) {
                    $review[] = [$importer->get_conflict_header($importedentity, $errorcode), $solution];
                }
            }
        }

        return $review;
    }

    /**
     * Stores a name of the exported instance in the 'reviewdata' so it can be displayed on the export report screen
     *
     * @param string $importedentity
     * @param array $details
     * @param int $id
     */
    public function store_instance_name_for_review(string $importedentity, array $details, ?int $id) {
        if (!$importer = $this->get_importer()) {
            return;
        }
        if (($instancename = $importer->get_registered_entity_property(
                [$importedentity, importer_base::ENTITY_INSTANCENAME_FOR_REVIEW],
                [$details, $id ?? 0])) === null) {
            return;
        }
        $this->instanceslist[] = ['entityname' => $importedentity, 'instancename' => $instancename, 'id' => $id];
    }

    /**
     * Returns the list of instances that were or will be imported
     *
     * @return array each element is an array with properties: instancename, id, entityname
     *     instances with id==0 are shown as striketrhough and not counted towards total number of instances
     */
    public function get_instances(): array {
        $this->get_collected_errors();
        return $this->instanceslist;
    }
}
