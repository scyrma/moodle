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
 * Class export_manager
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
use tool_wp\local\exportimport\csv\csv_export_writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Class export_manager
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_manager {

    /** @var export_import_mapper_base[] */
    protected $mappers;
    /** @var export_persistent */
    protected $exportpersistent;
    /** @var string */
    private $directory;
    /** @var csv_export_writer */
    private $csvwriter;
    /** @var array */
    protected $files = [];
    /** @var exporter_base[][] caches all importers for given entry point, used in {@see find_exporter_for()} */
    protected $chainedexporters = [];
    /** @var exporter_base */
    protected $mainexporter;
    /** @var exporter_base Stores the current exporter if we are inside a chained export */
    protected $currentexporter = null;

    /**
     * export_manager constructor.
     *
     * @param int $exportid
     * @param null|export_persistent $exportpersistent
     */
    public function __construct(int $exportid = 0, ?export_persistent $exportpersistent = null) {
        if ($exportpersistent) {
            $this->exportpersistent = $exportpersistent;
        } else {
            $this->exportpersistent = new export_persistent($exportid);
        }
        if ($this->exportpersistent->get('id')) {
            $this->directory = make_request_directory();
        }
    }

    /**
     * Create and schedule export as ad-hoc task
     *
     * @param array $data
     * @param bool $createadhoctask schedule ad-hoc task for the actual export
     *     can be set to false in unittests and CLI export
     * @return int|mixed
     */
    public static function schedule_export(array $data, bool $createadhoctask = true) {
        global $USER, $CFG;
        $defaults = ['exporter' => '', 'exportertenant' => 0, 'entrypoint' => '', 'entrypointid' => 0];
        $data += $defaults;
        $manager = new self(0);
        $exporter = helper::get_available_exporter($data['exporter'], $data['entrypoint'], $data['entrypointid'], $manager);
        $configdata = array_diff_key($data, $defaults);
        $plugin = \core_plugin_manager::instance()->get_plugins_of_type('tool')['wp'];
        $reviewdata = [
            'wwwroot' => $CFG->wwwroot,
            'siteidentifier' => md5(get_site_identifier()),
            'version' => get_config('tool_wp', 'version'),
            'release' => !empty($plugin->release) ? $plugin->release : $plugin->versiondb,
        ];
        $persistent = new export_persistent(0, (object)[
            'createdby' => $USER->id,
            'exporter' => get_class($exporter),
            'entrypoint' => $data['entrypoint'],
            'entrypointid' => $data['entrypointid'],
            'configdata' => json_encode($configdata),
            'reviewdata' => json_encode($reviewdata),
            'status' => helper::STATUS_SCHEDULED,
            // If exporter requires a tenant/user can't switch tenants, use the current tenant. Otherwise use submitted value.
            'tenantid' => ($exporter->is_tenant_required() || !\tool_tenant\permission::can_switch_tenant())
                ? tenancy::get_tenant_id()
                : ($data['exportertenant'] ?: null)
        ]);
        $persistent->save();

        // Create and schedule an adhoc task for this export.
        if ($createadhoctask) {
            $task = new \tool_wp\task\export_adhoc_task();
            $task->set_custom_data(['id' => $persistent->get('id')]);
            \core\task\manager::queue_adhoc_task($task);
        }

        return $persistent->get('id');
    }

    /**
     * Creates an instance of an exporter
     *
     * @param string $exporterclass
     * @param string $entrypoint
     * @param int $entrypointid
     * @param array $configdata
     * @param int|null $exportertenant For users who are able to switch tenants, when creating an export for an exporter that
     *      doesn't require a tenant, allow overriding the tenant used for the export.
     * @return exporter_base
     */
    public static function create_exporter(string $exporterclass, string $entrypoint, int $entrypointid = 0,
           array $configdata = [], ?int $exportertenant = null): exporter_base {

        global $USER, $CFG;
        $defaults = ['exporter' => '', 'exportertenant' => 0, 'entrypoint' => '', 'entrypointid' => 0];
        $configdata = array_diff_key($configdata, $defaults);
        $plugin = \core_plugin_manager::instance()->get_plugins_of_type('tool')['wp'];
        $reviewdata = [
            'wwwroot' => $CFG->wwwroot,
            'siteidentifier' => md5(get_site_identifier()),
            'version' => get_config('tool_wp', 'version'),
            'release' => !empty($plugin->release) ? $plugin->release : $plugin->versiondb,
        ];
        $persistent = new export_persistent(0, (object)[
            'createdby' => $USER->id,
            'exporter' => $exporterclass,
            'entrypoint' => $entrypoint,
            'entrypointid' => $entrypointid,
            'configdata' => json_encode($configdata),
            'reviewdata' => json_encode($reviewdata),
            'status' => helper::STATUS_SCHEDULED,
        ]);
        $manager = new self(0, $persistent);
        $exporter = helper::get_available_exporter($exporterclass, $entrypoint, $entrypointid, $manager);

        // Calculate tenant; if required or user can't switch then use current, otherwise use that passed as param.
        $tenantid = ($exporter->is_tenant_required() || !\tool_tenant\permission::can_switch_tenant())
            ? tenancy::get_tenant_id()
            : ($exportertenant ?: null);

        $manager->exportpersistent->set('tenantid', $tenantid);

        return $exporter;
    }

    /**
     * Get export id
     *
     * @return int
     */
    public function get_export_id(): int {
        return (int)$this->exportpersistent->get('id');
    }

    /**
     * Get export status
     *
     * @return int
     */
    public function get_export_status(): int {
        return (int)$this->exportpersistent->get('status');
    }

    /**
     * Get export tenant id
     *
     * @return int|null
     */
    public function get_export_tenant_id(): ?int {
        if (!\tool_tenant\permission::can_switch_tenant()) {
            return tenancy::get_tenant_id();
        }
        $tenantid = (int)$this->exportpersistent->get('tenantid');
        if (!$tenantid) {
            $exporter = $this->currentexporter ?? $this->mainexporter;
            if ($exporter && $exporter->is_tenant_required()) {
                return tenancy::get_tenant_id();
            }
        }
        return $tenantid ?: null;
    }

    /**
     * Send message via helper method to notify user of status change in export
     *
     * @return mixed
     */
    protected function send_message_for_status_change() {
        $exporturl = helper::export_url($this->exportpersistent->get('id'));
        $strdate = userdate($this->exportpersistent->get('timemodified'), get_string('strftimedatetime', 'langconfig'));
        $strstatus = helper::format_export_import_status($this->get_export_status());
        if ($errors = helper::format_export_errors($this->get_export_errors())) {
            $strstatus .= "\n" . $errors;
        }

        return helper::send_message($this->exportpersistent->get('createdby'), 'exportcomplete', $exporturl,
            get_string('messagefullexportcomplete', 'tool_wp', [
                'status' => $strstatus,
                'date' => $strdate,
                'url' => $exporturl->out(),
            ]));
    }

    /**
     * All settings recorded by wizard, to be used during the export
     *
     * @return array
     */
    public function get_export_settings(): array {
        $persistent = $this->exportpersistent;
        return @json_decode($persistent->get('configdata'), true) ?: [];
    }

    /**
     * All review data, to be used in the review
     *
     * @return array
     */
    public function get_review_data(): array {
        return @json_decode($this->exportpersistent->get('reviewdata'), true) ?: [];
    }

    /**
     * Perform the export (called from the ad-hoc task)
     *
     * @throws \moodle_exception
     */
    public function perform_export() {
        $persistent = $this->exportpersistent;
        if (!$persistent->get('id') || $this->get_export_status() != helper::STATUS_SCHEDULED) {
            // Some race condition, the status of the export is no longer "Scheduled".
            return;
        }

        try {
            $this->mainexporter = $exporter = helper::get_available_exporter($persistent->get('exporter'),
                $persistent->get('entrypoint'), $persistent->get('entrypointid'), $this);
        } catch (\moodle_exception $e) {
            $persistent->set('status', helper::STATUS_ERROR);
            $this->store_exception_in_review_data($e);
            $persistent->save();
            $this->send_message_for_status_change();
            return;
        }

        $persistent->set('status', helper::STATUS_IN_PROGRESS);
        $persistent->save();

        if ($exporter->is_format(exporter_base::FORMAT_CSV)) {
             $this->csvwriter = new csv_export_writer($this);
        }

        try {
            $exporter->perform_export();
        } catch (\Throwable $e) {
            $persistent->set('status', helper::STATUS_ERROR);
            $this->store_exception_in_review_data($e);
            $persistent->save();
            $this->send_message_for_status_change();
            return;
        }

        $exportername = str_replace(' ', '-', $exporter->get_name());
        $backupdateformat = str_replace(' ', '-', get_string('backupnameformat', 'langconfig'));
        $date = userdate($persistent->get('timemodified'), $backupdateformat, 99, false);
        $filename = clean_filename(\core_text::strtolower($exportername . '-export-' . $date)) .
            $exporter->get_export_file_extension();

        if ($exporter->is_format(exporter_base::FORMAT_ZIP)) {
            if ($this->create_archive(new \zip_packer(), $persistent, $filename)) {
                $persistent->set('status', helper::STATUS_DONE);
            } else {
                $persistent->set('status', helper::STATUS_ERROR);
            }
        } else if ($exporter->is_format(exporter_base::FORMAT_CSV)) {
            if ($this->save_csv_file($persistent, $filename)) {
                $persistent->set('status', helper::STATUS_DONE);
            } else {
                $persistent->set('status', helper::STATUS_ERROR);
            }
        } else {
            $persistent->set('status', helper::STATUS_ERROR);
            $errormessage = 'Exporter format is not supported'; // No need for translation, this is a message for developers.
            $this->store_error_in_review_data(['message' => $errormessage]);
            // TODO implement other export formats.
        }

        $persistent->save();

        // Inform user that the export has completed.
        $this->send_message_for_status_change();

        return;
    }

    /**
     * For workplace-format exports, add the entity to the export directory
     *
     * @param string $entityname
     * @param int $id
     * @param array $data
     * @throws \coding_exception
     */
    public function add_data_to_workplace_export(string $entityname, int $id, array $data) {
        if ($entityname !== strtolower(clean_param($entityname, PARAM_ALPHANUMEXT)) || empty($entityname)) {
            throw new \coding_exception('Entity name must be simple and lowercase');
        }
        $filepath = 'data/'.$entityname.'/'.$id.'.xml';
        $fullfilepath = $this->directory.'/'.$filepath;
        helper::array_to_xml_file($fullfilepath, $entityname, $data);
        $this->files[$filepath] = $fullfilepath;

        $mappedfilename = 'mapped/'.$entityname.'/'.$id.'.xml';
        if (!empty($this->files[$mappedfilename])) {
            // When we export actual data no need to add mapping.
            unset($this->files[$mappedfilename]);
        }
    }

    /**
     * Writes content to a CSV export
     *
     * @param array $row
     * @throws \coding_exception
     */
    public function write_to_csv_file(array $row) {
        if (!$this->mainexporter || !$this->mainexporter->is_format(exporter_base::FORMAT_CSV)) {
            throw new \coding_exception('Function ' . __FUNCTION__ . ' can only be used in CSV exporters');
        }
        $this->csvwriter->add_data($row);
    }

    /**
     * Get list of all mappers
     *
     * @return array
     */
    public function get_mappers(): array {
        if ($this->mappers === null) {
            $this->mappers = [];
            $allmappers = helper::get_all_mappers();
            foreach ($allmappers as $mapper) {
                $mapper->set_export_manager($this);
                $this->mappers[] = $mapper;
            }
        }
        return $this->mappers;
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
    public function add_entity_mapping_to_export(string $entityname, int $id) {
        if (!$id) {
            return;
        }

        if (!$mapper = helper::find_mapper_for_entity($entityname, $this->get_mappers())) {
            return;
        }

        if (!empty($this->files['data/'.$entityname.'/'.$id.'.xml'])) {
            // We already exported data, no need to map.
            return;
        }

        $filepath = 'mappings/'.$entityname.'/'.$id.'.xml';
        if (!empty($this->files[$filepath])) {
            // We already exported this mapping, no need to do it again.
            return;
        }

        if (!$data = $mapper->get_mapping_data_for_workplace_export($id)) {
            return;
        }

        $fullfilepath = $this->directory.'/'.$filepath;
        helper::array_to_xml_file($fullfilepath, $entityname, $data);
        $this->files[$filepath] = $fullfilepath;

    }

    /**
     * Add a file to a workplace export
     *
     * @param \stored_file $file
     */
    public function add_file_to_workplace_export(\stored_file $file) {
        $contenthash = $file->get_contenthash();
        $filepath = 'files/' . substr($contenthash, 0, 2) . '/' .
            substr($contenthash, 2, 2) . '/' . $contenthash;
        if (!empty($this->files[$filepath])) {
            // We already exported this file, no need to do it again.
            return;
        }
        $fullfilepath = $this->directory.'/'.$filepath;
        make_writable_directory(dirname($fullfilepath));
        $file->copy_content_to($fullfilepath);
        $this->files[$filepath] = $fullfilepath;
    }

    /**
     * Add general information to the workplace export
     *
     * @param export_persistent $export
     */
    protected function add_workplace_exporter_info(export_persistent $export) {
        $filepath = 'workplace.xml';
        $fullfilepath = $this->directory.'/'.$filepath;
        $reviewdata = $this->get_review_data();
        helper::array_to_xml_file($fullfilepath, 'workplace', [
            'createdby' => $export->get('createdby'),
            'createdbyname' => fullname(\core_user::get_user($export->get('createdby'))),
            'exporter' => $export->get('exporter'),
            'timecreated' => $export->get('timecreated'),
            'wwwroot' => $reviewdata['wwwroot'],
            'siteidentifier' => $reviewdata['siteidentifier'],
            'version' => $reviewdata['version'],
            'release' => $reviewdata['release'],
        ]);
        $this->files[$filepath] = $fullfilepath;

    }

    /**
     * Create an archive from the export directory (for ZIP exports)
     *
     * @param \file_packer $zipper
     * @param export_persistent $export
     * @param string $filename
     * @return \stored_file
     */
    protected function create_archive(\file_packer $zipper, export_persistent $export, string $filename): ?\stored_file {
        global $USER;
        $this->add_workplace_exporter_info($export);

        $contextid = \context_system::instance()->id;
        $component = 'tool_wp';
        $filearea = 'export';
        $itemid = (int)$export->get('id');
        $filepath = '/';
        try {
            if ($newfile = $zipper->archive_to_storage($this->files, $contextid, $component, $filearea, $itemid,
                    $filepath, $filename, $USER->id)) {
                return $newfile;
            }
            $errormessage = get_string('errorcreatingfile', 'error', basename($filename));
            $this->store_error_in_review_data(['message' => $errormessage]);
        } catch (\Throwable $t) {
            $this->store_exception_in_review_data($t);
        }
        return null;
    }

    /**
     * Save exported single file (for CSV, JSON, text, etc. exports)
     *
     * @param export_persistent $export
     * @param string $filename
     * @return \stored_file
     */
    protected function save_csv_file(export_persistent $export, string $filename): ?\stored_file {
        $contextid = \context_system::instance()->id;
        $component = 'tool_wp';
        $filearea = 'export';
        $itemid = (int)$export->get('id');
        $filepath = '/';
        try {
            return $this->csvwriter->csv_to_storage($contextid, $component, $filearea, $itemid, $filepath, $filename);
        } catch (\Throwable $t) {
            $this->store_exception_in_review_data($t);
            return null;
        }
    }

    /**
     * Is this export completed (i.e. has status either "Done" or "Error")
     *
     * @return bool
     */
    public function is_completed() {
        $status = $this->exportpersistent->get('status');
        return $status == helper::STATUS_DONE || $status == helper::STATUS_ERROR;
    }

    /**
     * Finds an exporter available for exporting an entity as chained entity
     *
     * @param string $entityname
     * @param exporter_base $caller
     * @return exporter_base|null
     */
    protected function find_exporter_for(string $entityname, exporter_base $caller): ?exporter_base {
        $entrypoint = exporter_base::ENTRY_POINT_CHAINED . get_class($caller);
        if (!array_key_exists($entrypoint, $this->chainedexporters)) {
            $this->chainedexporters[$entrypoint] = helper::get_all_exporters($entrypoint, 0, $this);
        }
        foreach ($this->chainedexporters[$entrypoint] as $exporter) {
            if (in_array($entityname, $exporter->get_individual_entities_available_for_export())) {
                return $exporter;
            }
        }
        return null;
    }

    /**
     * Perform export of entities that can be exported individually (chained from somewhere else)
     *
     * @param string $entityname
     * @param exporter_base $caller
     * @param array $ids
     * @param array $overridesettings
     */
    public function process_chained_entities(string $entityname, exporter_base $caller, array $ids, array $overridesettings = []) {
        if (!$exporter = $this->find_exporter_for($entityname, $caller)) {
            return;
        }
        // Settings that need to be passed to the found exporter.
        $settings = $exporter->get_registered_entity_property([$entityname, exporter_base::ENTITY_INDIVIDUALEXPORT],
            [$ids, $overridesettings]);
        // Remember curent settings and override them.
        $oldsettings = $this->exportpersistent->get('configdata');
        $this->exportpersistent->set('configdata', json_encode($settings + $this->get_export_settings()));

        // Set the "current exporter" to the chained importer.
        $cachedvalue = $this->currentexporter;
        $this->currentexporter = $exporter;
        // Perform export but ignore exceptions.
        try {
            $exporter->perform_export();
        } catch (\Throwable $t) {
            $this->store_exception_in_review_data($t);
        }
        $this->currentexporter = $cachedvalue;

        // Restore old settings.
        $this->exportpersistent->set('configdata', $oldsettings);
    }

    /**
     * Is current user allowed to export some other entity (via another exporter) that can be exported as part of this entity
     *
     * For example, when exporting certifications we might want to export programs that are used in them
     *
     * @param string $entityname
     * @param exporter_base $caller
     * @return bool
     */
    public function can_export_individual_entity(string $entityname, exporter_base $caller): bool {
        $exporter = $this->find_exporter_for($entityname, $caller);
        return !empty($exporter);
    }

    /**
     * Get export progress
     *
     * @return int
     */
    public function get_export_progress(): int {
        // TODO: For the moment, return 0 or 100 depending on status.
        return $this->exportpersistent->get('status') == helper::STATUS_DONE ? 100 : 0;
    }

    /**
     * Stores a name of the exported instance in the 'reviewdata' so it can be displayed on the export report screen
     *
     * @param string $exportedentity
     * @param array $record
     */
    public function store_instance_name_for_review(string $exportedentity, array $record) {
        if (($instancename = $this->mainexporter->get_registered_entity_property(
                [$exportedentity, exporter_base::ENTITY_INSTANCENAME_FOR_REVIEW], [$record])) === null) {
            return;
        }
        $reviewdata = @json_decode($this->exportpersistent->get('reviewdata'), true);
        $reviewdata += ['instances' => []];
        $reviewdata['instances'][] = ['entityname' => $exportedentity, 'instancename' => $instancename, 'id' => $record['id']];
        $this->exportpersistent->set('reviewdata', json_encode($reviewdata));
    }

    /**
     * Stores export exception in the review data
     *
     * @param \Throwable $e
     */
    protected function store_exception_in_review_data(\Throwable $e) {
        $error = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ];
        $this->store_error_in_review_data($error);
    }

    /**
     * Stores export error in the review data
     *
     * @param array $errordetails details of an error, must include 'message'
     */
    protected function store_error_in_review_data(array $errordetails) {
        $reviewdata = @json_decode($this->exportpersistent->get('reviewdata'), true);
        $reviewdata += ['errors' => []];
        $reviewdata['errors'][] = $errordetails;
        $this->exportpersistent->set('reviewdata', json_encode($reviewdata));
    }

    /**
     * Returns errors logged during the export
     *
     * @return array each element is an array with at least has 'message' key
     */
    public function get_export_errors(): array {
        $reviewdata = @json_decode($this->exportpersistent->get('reviewdata'), true);
        if (!empty($reviewdata['errors'])) {
            return $reviewdata['errors'];
        }
        return [];
    }

    /**
     * Returns list of instances that will be/were imported/exported
     *
     * @param exporter_base $exporter
     * @return array
     */
    public static function get_instances_for_review_form(exporter_base $exporter): array {
        $instanceslist = [];
        foreach ($exporter->get_entities() as $entityname) {
            foreach ($exporter->get_instances_for_review_step($entityname) as $record) {
                $record = (array)$record;
                if (($instancename = $exporter->get_registered_entity_property(
                        [$entityname, exporter_base::ENTITY_INSTANCENAME_FOR_REVIEW],
                        [$record])) !== null) {
                    $instanceslist[] = ['entityname' => $entityname, 'instancename' => $instancename, 'id' => $record['id']];
                }
            }
        }
        return $instanceslist;
    }

    /**
     * Returns URL to download the export file
     *
     * @param int $exportid
     * @return \moodle_url|null
     */
    public static function get_export_file_url(int $exportid): ?\moodle_url {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, 'id', false);
        if ($file = reset($files)) {
            return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        }
        return null;
    }
}
