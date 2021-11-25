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
 * Class import_detail_persistent
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use core\persistent;
use tool_wp\export_import_mapper_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class import_detail_persistent
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_detail_persistent extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_wp_import_details';

    /** @var bool */
    protected $isvalidated = true;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'importid' => [
                'type' => PARAM_INT,
            ],
            'type' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 0,
            ],
            'importer' => [
                'type' => PARAM_RAW_TRIMMED,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'mapper' => [ // TODO not used currently.
                'type' => PARAM_RAW_TRIMMED,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'conflict' => [ // TODO not used currently.
                'type' => PARAM_ALPHANUMEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'data' => [
                'type' => PARAM_TEXT,
                'optional' => true,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Magic method for persistent call $this->set('data', $data)
     *
     * @param array $data
     */
    protected function set_data(array $data): void {
        $this->raw_set('data', json_encode($data));
    }

    /**
     * Magic method for persistent call $this->get('data')
     *
     * @return array
     */
    protected function get_data(): array {
        return @json_decode($this->raw_get('data'), true) ?: [];
    }

    /**
     * Add mapping notice
     *
     * @param export_import_mapper_base $mapper
     * @param string $entityname
     * @param string $noticecode
     * @param array $identifier
     * @param array $additionaldata
     */
    public function add_mapping_notice(export_import_mapper_base $mapper, string $entityname,
                                       string $noticecode, array $identifier, array $additionaldata = []) {
        $data = $this->get('data') + ['mappingnotices' => []];
        $data['mappingnotices'][] = ['mapper' => get_class($mapper), 'entityname' => $entityname,
            'noticecode' => $noticecode, 'identifier' => $identifier, 'additionaldata' => $additionaldata];
        $this->set('data', $data);
    }

    /**
     * Get all mapping notices
     *
     * @return mixed
     */
    public function get_data_mapping_notices() {
        $data = $this->get('data') + ['mappingnotices' => []];
        return $data['mappingnotices'];
    }

    /**
     * Add mapping error
     *
     * @param null|export_import_mapper_base $mapper
     * @param string $entityname
     * @param array $identifier
     */
    public function add_mapping_error(?export_import_mapper_base $mapper, string $entityname, array $identifier) {
        $data = $this->get('data') + ['mappingerrors' => []];
        $data['mappingerrors'][] = ['mapper' => $mapper ? get_class($mapper) : null,
                'entityname' => $entityname, 'identifier' => $identifier];
        $this->set('data', $data);
    }

    /**
     * Get all mapping errors
     *
     * @return mixed
     */
    public function get_data_mapping_errors() {
        $data = $this->get('data') + ['mappingerrors' => []];
        return $data['mappingerrors'];
    }

    /**
     * Add details about the imported object
     *
     * @param array $additionaldata
     */
    public function add_importer_details(array $additionaldata) {
        $data = $this->get('data') + ['importerdetails' => []];
        $data['importerdetails'] = $additionaldata + $data['importerdetails'];
        $this->set('data', $data);
    }

    /**
     * Add an error raised by the importer
     *
     * @param string $errorcode
     * @param bool $willberesolved
     */
    public function add_importer_error(string $errorcode, bool $willberesolved = false) {
        $data = $this->get('data') + ['importererrors' => []];
        $data['importererrors'][] = $errorcode;
        $this->set('data', $data);
        if (!$willberesolved) {
            $this->mark_validation_failed();
        }
    }

    /**
     * Clear all collected errors
     *
     * This is called after successful validation before the import. Some errors may have been
     * collected during validation, however if the validation did not fail, this means that there
     * must be some conflict resolution rules.
     */
    public function clear_errors(): void {
        $data = $this->get('data');
        $data['mappingerrors'] = [];
        $data['importererrors'] = [];
        $this->set('data', $data);
    }

    /**
     * Get all mapping errors
     *
     * @return mixed
     */
    public function get_data_importer_errors() {
        $data = $this->get('data') + ['importererrors' => []];
        return $data['importererrors'];
    }

    /**
     * Mark this detail as failing validation
     */
    public function mark_validation_failed() {
        $this->isvalidated = false;
    }

    /**
     * Is this detail valid (does not have any errors)
     *
     * @return bool
     */
    public function is_validated(): bool {
        return $this->isvalidated;
    }

    /**
     * Add a notice raised by the importer
     *
     * @param string $noticecode
     * @param array $details additional details for this notice only
     */
    public function add_importer_notice(string $noticecode, array $details = []) {
        $data = $this->get('data') + ['importernotices' => []];
        $data['importernotices'][] = [$noticecode, $details];
        $this->set('data', $data);
    }

    /**
     * Prepare an instance from an importer
     *
     * @param importer_base $importer
     * @param string $entityname
     * @param int $originalid
     * @return import_detail_persistent
     */
    public static function prepare_from_importer(importer_base $importer,
                                                 string $entityname, int $originalid): import_detail_persistent {
        $instance = new self(0, (object)['importer' => get_class($importer)]);
        $instance->set('data', ['entityname' => $entityname, 'originalid' => $originalid]);
        return $instance;
    }

    /**
     * Set the type to success and record the new entity id in the logged data
     *
     * @param int $id
     * @return import_detail_persistent
     */
    public function set_success(int $id): import_detail_persistent {
        // TODO import notices and errors?
        if (!empty($data['mappingerrors']) || !empty($data['mappingnotices'])) {
            $this->set('type', import_manager::DETAILTYPE_SUCCESS_WITH_NOTICES);
        } else {
            $this->set('type', import_manager::DETAILTYPE_SUCCESS);
        }
        $data = $this->get('data');
        $data['id'] = $id;
        $this->set('data', $data);
        return $this;
    }

    /**
     * Set the type to error
     *
     * @return import_detail_persistent
     */
    public function set_error(): import_detail_persistent {
        $this->set('type', import_manager::DETAILTYPE_ERROR);
        return $this;
    }

    /**
     * Check if the log detail has a type exception
     *
     * @return bool
     */
    public function is_exception(): bool {
        return $this->get('type') == import_manager::DETAILTYPE_EXCEPTION;
    }

    /**
     * Check if the log detail has a type success
     *
     * @return bool
     */
    public function is_success(): bool {
        return $this->get('type') == import_manager::DETAILTYPE_SUCCESS ||
            $this->get('type') == import_manager::DETAILTYPE_SUCCESS_WITH_NOTICES;
    }

    /**
     * Check if the log detail has a type error
     *
     * @return bool
     */
    public function is_error(): bool {
        return $this->get('type') == import_manager::DETAILTYPE_ERROR;
    }

    /**
     * Get entity name from logged data
     *
     * @return null|string
     */
    public function get_data_entity_name(): ?string {
        $data = $this->get('data');
        return !empty($data['entityname']) ? $data['entityname'] : null;
    }

    /**
     * Get entity original id from logged data
     *
     * @return null|int
     */
    public function get_data_original_id(): ?int {
        $data = $this->get('data');
        return !empty($data['originalid']) ? (int)$data['originalid'] : null;
    }

    /**
     * Get entity new id from logged data
     *
     * @return int|null
     */
    public function get_data_imported_id(): ?int {
        $data = $this->get('data');
        return !empty($data['id']) ? (int)$data['id'] : null;
    }

    /**
     * Get all importer details
     *
     * @return array
     */
    public function get_importer_details(): array {
        $data = $this->get('data') + ['importerdetails' => []];
        return $data['importerdetails'];
    }

    /**
     * Returns errors formatted for display
     *
     * @param importer_base|null $importer
     * @return array
     */
    public function get_formatted_errors(?importer_base $importer): array {
        $errors = [];
        $data = $this->get('data');
        if ($importer && !empty($data['importererrors'])) {
            foreach ($data['importererrors'] as $error) {
                $errors[] = $importer->display_log_error($error, $this->get_data_entity_name(), $this->get_importer_details());
            }
        }
        foreach ($this->get_data_mapping_errors() as $errordata) {
            $mapperclass = $errordata['mapper'];
            $entityname = $errordata['entityname'];
            $identifier = $errordata['identifier'];
            if (!$mapperclass) {
                $errors[] = 'Not possible to map '.$entityname.', no mapper available'; // TODO string, more details.
            } else if ($mapper = export_import_mapper_base::create($mapperclass)) {
                $errors[] = $mapper->display_mapping_error($identifier);
            } else {
                $errors[] = '??? ['.$mapper.']'; // TODO generic string.
            }
        }
        return $errors;
    }

    /**
     * Returns notices formatted for display
     *
     * @param importer_base|null $importer
     * @return array
     */
    public function get_formatted_notices(?importer_base $importer): array {
        $notices = [];
        $data = $this->get('data');
        if ($importer && !empty($data['importernotices'])) {
            foreach ($data['importernotices'] as $notice) {
                $notices[] = $importer->display_log_notice($this, $notice[0], $notice[1]);
            }
        }
        foreach ($this->get_data_mapping_notices() as $noticedata) {
            $mapperclass = $noticedata['mapper'];
            $entityname = $noticedata['entityname'];
            $noticecode = $noticedata['noticecode'];
            $identifier = $noticedata['identifier'];
            $additionaldata = $noticedata['additionaldata'];
            if (!$mapperclass) {
                $notices[] = 'Not possible to map '.$entityname.', no mapper available'; // TODO string, more details.
            } else if ($mapper = export_import_mapper_base::create($mapperclass)) {
                $notices[] = $mapper->display_mapping_notice($noticecode, $identifier, $additionaldata);
            } else {
                $notices[] = '??? ['.$mapper.']'; // TODO generic string.
            }
        }
        return $notices;
    }
}
