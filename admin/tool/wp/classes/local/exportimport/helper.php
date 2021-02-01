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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class helper
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

defined('MOODLE_INTERNAL') || die();

use DOMDocument;
use DOMElement;
use core_user;
use moodle_url;
use core\message\message;
use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Various methods used by exporter, importer and mapper
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class helper {

    /** @var int Export/import created */
    const STATUS_CREATED = 0;
    /** @var int Export/import completed without errors */
    const STATUS_DONE = 1;
    /** @var int Export/import created and scheduled */
    const STATUS_SCHEDULED = 2;
    /** @var int Export/import in progress */
    const STATUS_IN_PROGRESS = 3;
    /** @var int Export/import completed but an error occurred in the process */
    const STATUS_ERROR = 4;

    /** @var int */
    const FORMAT_UNKNOWN = 0;
    /** @var int */
    const FORMAT_ISDIRECTORY = 1;
    /** @var int */
    const FORMAT_ISSINGLEFILE = 2;
    /** @var int */
    const FORMAT_ZIP = 4 | self::FORMAT_ISDIRECTORY;
    /** @var int */
    const FORMAT_CSV = 8 | self::FORMAT_ISSINGLEFILE;
    /** @var int */
    const FORMAT_JSON = 16 | self::FORMAT_ISSINGLEFILE;
    /** @var int */
    const FORMAT_WORKPLACE = 1024 | self::FORMAT_ZIP;

    /**
     * Convert array to XML
     *
     * @param string $entityname
     * @param array $data
     * @return string
     */
    public static function array_to_xml(string $entityname, array $data): string {
        $domdocument = new DOMDocument();
        $domdocument->formatOutput = true;
        self::xml_encode([$entityname => $data], $domdocument, $domdocument);
        return $domdocument->saveXML($domdocument->documentElement);
    }

    /**
     * Convert array to XML and write to a file
     *
     * @param string $filepath
     * @param string $entityname
     * @param array $data
     */
    public static function array_to_xml_file(string $filepath, string $entityname, array $data) {
        make_writable_directory(dirname($filepath));
        file_put_contents($filepath, self::array_to_xml($entityname, $data));
    }

    /**
     * Converts an object/array to XML, called recursively
     *
     * @param \stdClass|array $mixed
     * @param DOMDocument|DOMElement $domelement
     * @param DOMDocument $domdocument
     */
    protected static function xml_encode($mixed, $domelement, $domdocument) {
        if (is_object($mixed)) {
            $mixed = get_object_vars($mixed);
        }
        if (is_array($mixed)) {
            $isplainarray = self::is_nonassoc_array($mixed);
            foreach ($mixed as $index => $mixedelement) {
                if ($isplainarray) {
                    if ($index === 0) {
                        $node = $domelement;
                    } else {
                        $node = $domdocument->createElement($domelement->tagName);
                        $domelement->parentNode->appendChild($node);
                    }
                } else {
                    $plural = $domdocument->createElement($index);
                    $domelement->appendChild($plural);
                    $node = $plural;
                    if (rtrim($index, 's') !== $index && self::is_nonassoc_array($mixedelement)) {
                        $singular = $domdocument->createElement(rtrim($index, 's'));
                        $plural->appendChild($singular);
                        $node = $singular;
                    }
                }

                self::xml_encode($mixedelement, $node, $domdocument);
            }
        } else {
            if (is_null($mixed)) {
                $mixed = '$@NULL@$';
            } else {
                $mixed = is_bool($mixed) ? ($mixed ? '1' : '0') : $mixed;
            }
            $domelement->appendChild($domdocument->createTextNode($mixed));
        }
    }

    /**
     * Is the given variable a non-associative array?
     *
     * @param mixed $mixed
     * @return bool
     */
    protected static function is_nonassoc_array($mixed) {
        return is_array($mixed) && array_keys($mixed) === range(0, count($mixed) - 1);
    }

    /**
     * Convert XML to array
     *
     * @param string $xml
     * @return array
     */
    public static function xml_to_array(string $xml): array {
        $obj = simplexml_load_string($xml, json_xml_element::class);
        // See https://stackoverflow.com/questions/15092338/php-simplexmlelement-to-array-null-value .
        $str = str_replace(':{}', ':null', json_encode($obj));
        return json_decode($str, true);
    }

    /**
     * Read XML from a file and convert to array
     *
     * @param string $filepath
     * @return array|null
     */
    public static function xml_file_to_array(string $filepath): ?array {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return null;
        }
        return self::xml_to_array(file_get_contents($filepath));
    }

    /**
     * Search for mappers defined in all plugins
     *
     * @return export_import_mapper_base[]
     */
    public static function get_all_mappers() {
        $instances = [];
        // Go through all plugins and find out which of them got mapper class instances defined.
        $componentinstances = \core_component::get_component_classes_in_namespace(null,
            'tool_wp\\mapper');
        if (!empty($componentinstances)) {
            // Found component with exporter class instance, add instance to the list.
            foreach (array_keys($componentinstances) as $mapperclass) {
                if (($instance = export_import_mapper_base::create($mapperclass)) && $instance->is_available()) {
                    $instances[] = $instance;
                }
            }
        }
        return $instances;
    }

    /**
     * Among the given list of mappers find a mapper for a given entity
     *
     * @param string $entity
     * @param array $allmappers list of all mappers. This method is used from import/export_manager and they
     *     cache the list of their mappers with the instance of manager set in them
     * @return null|export_import_mapper_base
     */
    public static function find_mapper_for_entity(string $entity, array $allmappers): ?export_import_mapper_base {
        foreach ($allmappers as $mapper) {
            if ($mapper->get_entity() === $entity) {
                return $mapper;
            }
        }
        return null;
    }

    /**
     * Search for all importers defined in all plugins and return available ones
     *
     * @param string $entrypoint
     * @param int|null $entrypointid
     * @param import_manager $importmanager
     * @param bool $availableonly
     * @return importer_base[]
     */
    public static function get_all_importers(string $entrypoint = '', int $entrypointid = 0,
                                             ?import_manager $importmanager = null, bool $availableonly = true): array {
        $instances = [];
        // Go through all plugins and find out which of them got exporter class instances defined.
        $componentinstances = \core_component::get_component_classes_in_namespace(null,
            'tool_wp\\importer');
        if (!empty($componentinstances)) {
            // Found component with importer class instance, add instance to the list.
            foreach (array_keys($componentinstances) as $importer) {
                if (($instance = importer_base::create($importer, $entrypoint, $entrypointid, $importmanager))
                        && (!$availableonly || $instance->is_available())) {
                    $instances[] = $instance;
                }
            }
        }
        return $instances;
    }

    /**
     * Search for all exporters defined in all plugins and return available ones
     *
     * @param string $entrypoint
     * @param int|null $entrypointid
     * @param export_manager|null $exportmanager
     * @param bool $onlyavailable
     * @return exporter_base[]
     */
    public static function get_all_exporters(string $entrypoint = '', int $entrypointid = 0,
                                             ?export_manager $exportmanager = null, bool $onlyavailable = true): array {
        $instances = [];
        // Go through all plugins and find out which of them got exporter class instances defined.
        $componentinstances = \core_component::get_component_classes_in_namespace(null,
            'tool_wp\\exporter');
        if (!empty($componentinstances)) {
            // Found component with exporter class instance, add instance to the list.
            foreach (array_keys($componentinstances) as $exporter) {
                if (($instance = exporter_base::create($exporter, $entrypoint, $entrypointid, $exportmanager)) &&
                        (!$onlyavailable || $instance->is_available())) {
                    $instances[] = $instance;
                }
            }
        }
        return $instances;
    }

    /**
     * Returns an instance of exporter (if available) or throws an exception
     *
     * @param string $exporterclassname
     * @param string $entrypoint
     * @param int $entrypointid
     * @param export_manager|null $exportmanager
     * @return exporter_base
     * @throws \moodle_exception
     */
    public static function get_available_exporter(string $exporterclassname, string $entrypoint = '', int $entrypointid = 0,
                                                  ?export_manager $exportmanager = null): exporter_base {
        if (!$exporter = exporter_base::create($exporterclassname, $entrypoint, $entrypointid, $exportmanager)) {
            throw new \moodle_exception('exporternotfound', 'tool_wp', '', s($exporterclassname));
        }
        if (!$exporter->is_available()) {
            throw new \moodle_exception('exporternotavailable', 'tool_wp', '',
                format_string($exporter->get_name()));
        }
        return $exporter;
    }

    /**
     * Returns a file used in a given export
     *
     * @param int $exportid
     * @return null|\stored_file
     */
    public static function get_export_file(int $exportid): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp', 'export', $exportid,
            '', false);
        return $files ? reset($files) : null;
    }

    /**
     * Returns a file used in a given import
     *
     * @param int $importid
     * @return null|\stored_file
     */
    public static function get_import_file(int $importid): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp', 'import', $importid,
            '', false);
        return $files ? reset($files) : null;
    }

    /**
     * Pluralize the name of an entity, typically when we want to add/get nested entities
     *
     * @param string $entityname
     * @return string
     */
    public static function pluralize_entityname(string $entityname): string {
        if (strcasecmp(\core_text::substr($entityname, -1), 's') !== 0) {
            $entityname .= 's';
        }

        return $entityname;
    }

    /**
     * Transform an array of persistent instances to array of records
     *
     * @param \core\persistent[] $persistents
     * @return array[]
     */
    public static function persistents_to_array(array $persistents): array {
        return array_map(function(\core\persistent $persistent) {
            return (array) $persistent->to_record();
        }, array_values($persistents));
    }

    /**
     * Send message to specified user
     *
     * @param int $userid
     * @param string $provider
     * @param moodle_url $url
     * @param string $fullmessage Message content, will be converted to HTML and sent with FORMAT_HTML
     * @return mixed
     */
    public static function send_message(int $userid, string $provider, moodle_url $url, string $fullmessage) {
        $message = new message();
        $message->courseid = SITEID;
        $message->component = 'tool_wp';
        $message->name = $provider;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = core_user::get_user($userid);
        $message->notification = 1;
        $message->subject = get_string('messageprovider:' . $provider, 'tool_wp');
        $message->fullmessagehtml = text_to_html($fullmessage, false, false);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage = html_to_text($message->fullmessagehtml);
        $message->contexturl = $url;
        $message->contexturlname = $message->subject;

        return message_send($message);
    }

    /**
     * Delete all data for a tenant (used in tenant_deleted event observer)
     *
     * @param int $tenantid
     */
    public static function delete_all_for_tenant(int $tenantid) {
        $exports = export_persistent::get_records_select('tenantid=:t', ['t' => $tenantid]);
        foreach ($exports as $export) {
            $export->delete();
        }
        $imports = import_persistent::get_records_select('tenantid=:t', ['t' => $tenantid]);
        foreach ($imports as $import) {
            $import->delete();
        }
    }

    /**
     * Delete import instance
     *
     * @param int $id
     * @return bool
     */
    public static function delete_import(int $id) {
        return (new \tool_wp\local\exportimport\import_persistent($id))->delete();
    }

    /**
     * Delete export instance
     *
     * @param int $id
     * @return bool
     */
    public static function delete_export(int $id) {
        return (new \tool_wp\local\exportimport\export_persistent($id))->delete();
    }

    /**
     * Clean up abandoned imports after 24h with the status CREATED
     */
    public static function cleanup_abandoned_imports(): void {
        $params = ['onedayago' => strtotime('-1 day'), 'status' => self::STATUS_CREATED];
        $persistents = import_persistent::get_records_select('timecreated < :onedayago AND status = :status', $params);
        foreach ($persistents as $persistent) {
            $persistent->delete();
        }
    }

    /**
     * Clean up imports and exports if expiry date has been reached.
     *
     * NOTE: $CFG->wpexportimportexpiry is set in SECONDS.
     * @throws \coding_exception
     */
    public static function cleanup_expired_exports_imports(): void {
        global $CFG;

        if (isset($CFG->wpexportimportexpiry)) {
            $exportimportexpiry = $CFG->wpexportimportexpiry;
        } else {
            $exportimportexpiry = 28 * DAYSECS;
        }

        $params = ['exportimportexpiry' => time() - $exportimportexpiry];
        $persistents = import_persistent::get_records_select('timecreated < :exportimportexpiry', $params);
        foreach ($persistents as $persistent) {
            $persistent->delete();
        }
        $persistents = export_persistent::get_records_select('timecreated < :exportimportexpiry', $params);
        foreach ($persistents as $persistent) {
            $persistent->delete();
        }
    }

    /**
     * Formatter for the 'status' column
     *
     * @param int $value
     * @return string
     */
    public static function format_export_import_status(int $value): string {
        if ($value == self::STATUS_CREATED) {
            // This status is never set for the exports, only used in imports.
            return get_string('exportimportstatuscreated', 'tool_wp');
        } else if ($value == self::STATUS_SCHEDULED) {
            return get_string('exportimportstatusscheduled', 'tool_wp');
        } else if ($value == self::STATUS_DONE) {
            return get_string('exportimportstatuscompleted', 'tool_wp');
        } else if ($value == self::STATUS_IN_PROGRESS) {
            return get_string('exportimportstatusinprogress', 'tool_wp');
        } else if ($value == self::STATUS_ERROR) {
            return get_string('exportimportstatuserror', 'tool_wp');
        }
        return s($value);
    }

    /**
     * Returns html pill for export/import status.
     *
     * @param string $statusstr
     * @return string
     */
    public static function get_export_import_status_formatted(string $statusstr): string {
        $statusclass = str_replace(' ', '', strtolower($statusstr));
        return \html_writer::span($statusstr, "tool_wp_status_$statusclass");
    }

    /**
     * Formats the export errors
     *
     * @param array $errordetails
     * @param bool $ashtml TODO not implemented yet, can be used to show debuginfo
     * @return string
     */
    public static function format_export_errors(array $errordetails = [], bool $ashtml = false): string {
        $errors = array_map(function($error) use ($ashtml) {
            return self::format_logged_exception($error, $ashtml);
        }, $errordetails);
        $separator = $ashtml ? '<br>' : "\n";
        return ($errors ? join($separator, $errors) : '');
    }

    /**
     * Formatter for the 'tenant' column
     *
     * @param mixed $value
     * @return string
     */
    public static function format_tenantid($value) {
        if (!$value) {
            return '';
        }
        return tenancy::get_tenant_name_from_id($value);
    }

    /**
     * Formatter for the 'exporter' column
     *
     * @param string $value
     * @param \stdClass $data
     * @return string
     */
    public static function format_exporter($value, $data) {
        if (strlen($value) && ($exporter = exporter_base::create($value, (string)$data->entrypoint, (int)$data->entrypointid))) {
            return $exporter->get_name();
        }
        return s($value);
    }

    /**
     * Formatter for the 'importer' column
     *
     * @param string $value
     * @param \stdClass $data
     * @return string
     */
    public static function format_importer($value, $data) {
        if (strlen($value) && ($importer = importer_base::create($value, (string)$data->entrypoint, (int)$data->entrypointid))) {
            return $importer->get_name();
        }
        return s($value);
    }

    /**
     * Return a list of status fields as an array [$value => $name]
     *
     * @return array
     */
    public static function get_status_list(): array {
        return [
            self::STATUS_SCHEDULED => self::format_export_import_status(self::STATUS_SCHEDULED),
            self::STATUS_IN_PROGRESS => self::format_export_import_status(self::STATUS_IN_PROGRESS),
            self::STATUS_DONE => self::format_export_import_status(self::STATUS_DONE),
            self::STATUS_ERROR => self::format_export_import_status(self::STATUS_ERROR),
        ];
    }

    /**
     * Generate name for a form element in the conflict resolution form (used by mappers)
     *
     * @param string $mappedentity
     * @param string $settingname
     * @return string
     */
    public static function get_setting_name_for_conflict_form(string $mappedentity, string $settingname) {
        return 'ifnotfound:' . $mappedentity . ':' . $settingname;
    }

    /**
     * Finds mapping conflict settings in the import settings and groups them by entity
     *
     * @param array $settings
     * @return array where each element is ['entityname' => '...', 'settings' => []]
     *     where settings are all settings for this entity without prefixes, for example,
     *     ['action' => 'skip'] or ['action' => 'create', 'frmid' => 123]
     */
    public static function parse_mapping_conflict_settings(array $settings) {
        $mapperconflicts = [];
        foreach ($settings as $setting => $value) {
            if (preg_match('/^ifnotfound:(.+?):(.+)/', $setting, $matches)) {
                $mappedentity = $matches[1];
                $settingname = $matches[2];
                if (!isset($mapperconflicts[$mappedentity])) {
                    $mapperconflicts[$mappedentity] = [
                        'entityname' => $mappedentity,
                        'settings' => []
                    ];
                }
                $mapperconflicts[$mappedentity]['settings'][$settingname] = $value;
            }
        }
        return array_values($mapperconflicts);
    }

    /**
     * Generate name for a form element in the conflict resolution form (used by importers)
     *
     * @param string $importedentity
     * @param string $errorcode
     * @param string $settingname
     * @return string
     */
    public static function get_importer_setting_name_for_conflict_form(string $importedentity,
                                                                       string $errorcode, string $settingname) {
        return 'conflict:' . $importedentity . ':' . $errorcode . ':' . $settingname;
    }

    /**
     * Finds importer conflict settings in the import settings and groups them by entity and errorcode
     *
     * @param array $settings
     * @return array where each element is ['importedentity' => '...', 'errorcode' => '...', 'settings' => []]
     *     where settings are all settings for this entity and errorcode without prefixes, for example,
     *     ['action' => 'skip'] or ['action' => 'create', 'frmid' => 123]
     */
    public static function parse_importer_conflict_settings(array $settings): array {
        $importerconflicts = [];

        foreach ($settings as $setting => $value) {
            if (preg_match('/^conflict:(.+?):(.+?):(.+)/', $setting, $matches)) {
                $importedentity = $matches[1];
                $errorcode = $matches[2];
                $settingname = $matches[3];
                if (!isset($importerconflicts["$importedentity:$errorcode"])) {
                    $importerconflicts["$importedentity:$errorcode"] = [
                        'importedentity' => $importedentity,
                        'errorcode' => $errorcode,
                        'settings' => []
                    ];
                }
                $importerconflicts["$importedentity:$errorcode"]['settings'][$settingname] = $value;
            }
        }
        return array_values($importerconflicts);
    }

    /**
     * Generate URL for the "View import details" page
     *
     * @param int $importid id of the import, usually a number but can be a template such as ":id",
     *     if empty the entrypoint will be used in the URL
     * @param string $entrypoint if id is not specified the entry point
     * @param int $entrypointid
     * @return moodle_url
     */
    public static function import_url($importid, string $entrypoint = '', int $entrypointid = 0) {
        if (!empty($importid)) {
            return new moodle_url('/admin/tool/wp/import.php', ['importid' => $importid]);
        } else {
            return new moodle_url('/admin/tool/wp/import.php', ['entrypoint' => $entrypoint, 'entrypointid' => $entrypointid]);
        }
    }

    /**
     * Generate URL for the "View export details" page
     *
     * @param int $exportid id of the export, usually a number but can be a template such as ":id",
     *     if empty the entrypoint will be used in the URL
     * @param string $entrypoint
     * @param int $entrypointid
     * @return moodle_url
     */
    public static function export_url($exportid, string $entrypoint = '', int $entrypointid = 0) {
        if (!empty($exportid)) {
            return new moodle_url('/admin/tool/wp/export.php', ['exportid' => $exportid]);
        } else {
            return new moodle_url('/admin/tool/wp/export.php', ['entrypoint' => $entrypoint, 'entrypointid' => $entrypointid]);
        }
    }


    /**
     * Retrieves array value or callback
     *
     * For example, to return $array[$p1][$p2]
     * use helper::get_array_value_or_callback($array, [$p1, $p2])
     *
     * @param array $array
     * @param array $keys array of array keys
     * @param array $callbackargs arguments to pass to the callback if the value is callable
     * @return mixed - null if the property is not defined or the value of the property otherwise
     */
    public static function get_array_value_or_callback(array &$array, array $keys, array $callbackargs = []) {
        $value = &$array;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = &$value[$key];
        }
        if (is_callable($value)) {
            return call_user_func_array($value, $callbackargs);
        }
        return $value;
    }

    /**
     * Finds an available unique value for a field (such as idnumber or shortname)
     *
     * @param string $originalvalue
     * @param callable $lookup checks if value is present in the DB. Example:
     *     function(string $value): bool {
     *         global $DB;
     *         return $DB->record_exists('course', ['shortname' => $value]);
     *     }
     * @param bool $useincrement - if the value is not unique - increment (false => set to empty)
     * @return string either original value if it is already unique or modified value
     */
    public static function find_unique_value_for_field(?string $originalvalue, callable $lookup,
                                                       bool $useincrement = true): ?string {
        if (!strlen($originalvalue) || !$lookup($originalvalue)) {
            // Original value is empty or is already unique - return it.
            return $originalvalue;
        }

        if (!$useincrement) {
            // Conflict resolution is to set the value to empty.
            return '';
        }

        // Conflict resolution rule is to increment existing value.
        if (preg_match('/^(.*?)(\d+)$/', $originalvalue, $matches)) {
            $base = $matches[1];
            $postfix = ((int)$matches[2]) + 1;
        } else {
            $base = $originalvalue;
            $postfix = 2;
        }
        while ($lookup($base . $postfix)) {
            $postfix++;
        }
        return $base . $postfix;
    }

    /**
     * Displays list of instances that will be/were imported/exported
     *
     * @param array $instances each element is an array with properties: instancename, id, entityname
     *     instances with id==0 are shown as striketrhough and not counted towards total number of instances
     * @return string
     */
    public static function display_instances_list(array $instances): string {
        global $OUTPUT;

        if ($instances) {
            $instanceswithids = array_filter($instances, function($instance) {
                return $instance['id'] != 0;
            });
            $instancescount = count($instanceswithids);
            $list = [];
            foreach ($instances as $instance) {
                $list[] = [
                    'name' => format_string($instance['instancename'], true, ['escape' => false]),
                    'value' => $instance['id'] != 0,
                ];
            }

            // Ensure instance list is consistently sorted.
            \core_collator::asort_array_of_arrays_by_key($list, 'name');

            $instanceslist = [
                'count' => $instancescount,
                'list' => array_values($list),
                'morecount' => $instancescount > 3 ? $instancescount - 3 : 0,
            ];
        }
        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_instances',
            ['instances' => $instanceslist ?? []]
        );
    }

    /**
     * Get list of instances for CLI export/import
     *
     * @param array $instances
     * @return array
     */
    public static function display_instances_list_cli(array $instances): array {
        if ($instances) {
            $instanceswithids = array_filter($instances, function ($instance) {
                return $instance['id'] != 0;
            });
            return array_column($instanceswithids, 'instancename');
        }
        return [];
    }

    /**
     * Format logged exception data as a string
     *
     * @param array $data Logged exception data, containing 'class', 'message', 'code', 'trace' elements
     * @param bool $ashtml
     * @return string
     */
    public static function format_logged_exception(array $data, bool $ashtml = true): string {
        $message = get_string('importlogexception', 'tool_wp', $data['message']);

        // Include stacktrace for developers.
        if (debugging('', DEBUG_DEVELOPER)) {
            $message .= $ashtml ? \html_writer::tag('pre', $data['trace']) : "\n{$data['trace']}\n";
        }

        return $message;
    }

    /**
     * Detects file format
     *
     * @param \stored_file $file
     * @return int
     */
    public static function detect_file_format(\stored_file $file) {
        if ($file->get_mimetype() === 'text/csv') {
            return self::FORMAT_CSV;
        } else if ($file->get_mimetype() === 'application/zip') {
            return self::FORMAT_ZIP;
        }
        return 0;
    }

    /**
     * Compares two strings kind of a "smart" way
     *
     * @param string $str1
     * @param string $str2
     * @return bool
     */
    public static function strings_are_very_similar(string $str1, string $str2): bool {
        $str1 = \core_text::strtolower(preg_replace('/[_\s]/', '', $str1));
        $str2 = \core_text::strtolower(preg_replace('/[_\s]/', '', $str2));
        // TODO: If something ends with "id" but is not "id" - strip id.
        return $str1 === $str2;
    }

    /**
     * Locate a tenant by numeric id or non-numeric ID number
     *
     * @param int|string $codeorid
     * @return \stdClass|null
     */
    public static function locate_tenant($codeorid): ?\stdClass {
        $tenants = tenancy::get_tenants();
        if (is_number($codeorid)) {
            if (array_key_exists($codeorid, $tenants)) {
                return $tenants[$codeorid];
            }
        } else {
            $found = array_filter($tenants, function($t) use ($codeorid) {
                return $t->idnumber === $codeorid;
            });
            if ($tenant = reset($found)) {
                return $tenant;
            }
        }
        return null;
    }
}
