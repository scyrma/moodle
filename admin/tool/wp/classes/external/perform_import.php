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
 * External class for performing an import
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/externallib.php');

use context_system;
use external_function_parameters;
use external_single_structure;
use external_value;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\helper as exportimport_helper;
use tool_tenant\tenancy;

/**
 * External class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class perform_import extends \external_api {

    /**
     * Parameters for performing import
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'exportid' => new external_value(PARAM_INT, 'Export id', VALUE_DEFAULT, 0),
            'file' => new external_value(PARAM_INT, 'File to import. The itemid of a file in a user draft area.', VALUE_DEFAULT, 0),
            'importer' => new external_value(PARAM_RAW, 'Full name of the importer class', VALUE_DEFAULT, ''),
            'settings' => new external_value(PARAM_RAW, 'JSON encoded list of settings for the importer', VALUE_DEFAULT, '{}'),
            'tenant' => new external_value(PARAM_ALPHANUM, 'Database ID or non-numeric idnumber
                of the tenant where the import should be made', VALUE_DEFAULT, 0),
            'dryrun' => new external_value(PARAM_BOOL,
                'Only validate input and show the actual settings, do not actually schedule import',
                VALUE_DEFAULT, false),
            'notenant' => new external_value(PARAM_BOOL,
                'Perform import without the tenant (only for importers that support this).',
                VALUE_DEFAULT, false)
        ]);
    }

    /**
     * Performing (schedules) import.
     *
     * @param int|null $exportid
     * @param int|null $file
     * @param string $importer
     * @param string  $settings
     * @param string|null $tenant
     * @param bool $dryrun
     * @param bool $notenant
     * @return array
     */
    public static function execute(int $exportid, int $file, string $importer, string $settings, string $tenant,
            bool $dryrun, bool $notenant): array {
        global $USER;
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'exportid' => $exportid,
            'file' => $file,
            'importer' => $importer,
            'settings' => $settings,
            'tenant' => $tenant,
            'dryrun' => $dryrun,
            'notenant' => $notenant,
        ]);
        $settings = json_decode($params['settings'], true);
        if (is_numeric($params['settings']) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \invalid_parameter_exception(
                get_string('importersettingsinvalid', 'tool_wp'));
        }

        $context = context_system::instance();
        self::validate_context($context);
        \tool_wp\permission::require_can_use_export_import();

        // File.
        if ($params['exportid'] && $params['file']) {
            throw new \invalid_parameter_exception(get_string('importeitherexportidorfile', 'tool_wp'));
        }
        if ($params['exportid']) {
            \tool_wp\permission::require_can_view_export($params['exportid']);
            if (!export_manager::get_export_file_url($params['exportid'])) {
                throw new \invalid_parameter_exception(get_string('exportnotfoundornotready', 'tool_wp'));
            }
            $importmanager = import_manager::create_import_from_exportfile([], $params['exportid']);
        } else if ($params['file']) {
            $usercontext = \context_user::instance($USER->id);
            $draftfiles = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $params['file'], 'id');
            if (empty($draftfiles)) {
                throw new \invalid_parameter_exception(get_string('cantlocatefileindraftarea', 'tool_wp'));
            }
            $importmanager = import_manager::create_import_from_draftfile([], $params['file']);
        }

        // Importer.
        $importers = $importmanager->get_importers();
        if (!$importers) {
            throw new \invalid_parameter_exception(get_string('noavailableimporters', 'tool_wp'));
        }
        if (count($importers) == 1 && empty($params['importer'])) {
            $importer = reset($importers);
            $importmanager->save_general_settings(['importer' => get_class($importer)]);
        } else {
            $importers = array_values($importers);
            $found = false;
            $availableimporters = [];
            foreach ($importers as $imp) {
                if (get_class($imp) === $params['importer']) {
                    $importmanager->save_general_settings(['importer' => get_class($imp)]);
                    $found = true;
                    break;
                }
                $availableimporters[] = get_class($imp);
            }
            if (!$found) {
                if ($params['importer']) {
                    $error = get_string('importernotfound', 'tool_wp', $params['importer']);
                } else {
                    $error = get_string('importerrequired', 'tool_wp');
                }
                throw new \invalid_parameter_exception($error . "\n" . get_string('migrationschoosefrom', 'tool_wp') .
                    ': ' . join(', ', $availableimporters)
                );
            }
        }

        if (!$importmanager->get_importer()) {
            throw new \invalid_parameter_exception(get_string('importernotfound', 'tool_wp', $params['importer']));
        }

        // Tenant.
        if ($params['tenant'] && $params['notenant']) {
            throw new \invalid_parameter_exception(get_string('migrationnotenanterror', 'tool_wp'));
        }
        if ($params['notenant'] && $importmanager->get_importer()->is_tenant_required()) {
            throw new \invalid_parameter_exception(
                get_string('importerrequirestenant', 'tool_wp', $importmanager->get_importer()->get_name()));
        }

        if ($params['notenant']) {
            $usertenant = null;
        } else {
            $tenants = tenancy::get_tenants();
            $usertenant = $tenants[tenancy::get_tenant_id()];
            if ($params['tenant']) {
                if ($usertenant = exportimport_helper::locate_tenant($params['tenant'])) {
                    if ($usertenant->id != tenancy::get_tenant_id() && !\tool_tenant\permission::can_switch_tenant()) {
                        throw new \invalid_parameter_exception(
                            get_string('migrationcannotswitchtenant', 'tool_wp', $params['tenant']));
                    }
                    // Switch tenant.
                    tenancy::set_switched_tenant_id($usertenant->id);
                } else {
                    throw new \invalid_parameter_exception(
                        get_string('migrationcannotswitchtenant', 'tool_wp', $params['tenant']));
                }
            }
        }

        $importmanager->save_general_settings(['tenantid' => $usertenant->id ?? 0]);
        $importmanager->retrieve_and_save_review_data();

        // Settings.
        $formdata = [
            'importid' => $importmanager->get_import_id(),
        ] + $settings;
        $form = import_settings_form::mock_form_submission($formdata);

        // Check settings validation errors.
        if (!$form->is_validated()) {
            $errors = $form->get_quick_form()->_errors;
            $errordescr = [];
            foreach ($errors as $key => $value) {
                $errordescr[] = $key.' - '.$value;
            }
            throw new \invalid_parameter_exception(
                get_string('importersettingsvalidationfailed', 'tool_wp', implode(';', $errordescr)));
        }

        $settings = array_diff_key((array)$form->get_data(), ['importid' => 1, 'stage' => 1]);
        $importmanager->save_settings($settings);

        $defaults = $form->get_elements_defaults_for_cli();
        $formsettings = array_intersect_key((array)$form->get_data(), $form->get_elements_defaults_for_cli()) + $defaults;

        // Conflict resolution.
        if ($importmanager->has_conflicts()) {
            $formdata = [
                'importid' => $importmanager->get_import_id(),
            ] + $settings;
            $form = import_conflict_form::mock_form_submission($formdata);

            if (!$form->is_validated()) {
                $errors = $form->get_quick_form()->_errors;
                $errordescr = [];
                foreach ($errors as $key => $value) {
                    $errordescr[] = $key.' - '.$value;
                }
                throw new \invalid_parameter_exception(
                    get_string('importhasconflits', 'tool_wp', implode(';', $importmanager->get_collected_errors())) .
                    get_string('importersettingsvalidationfailed', 'tool_wp', implode(';', $errordescr)));
            }

            $settings = array_diff_key((array)$form->get_data(), ['importid' => 1, 'stage' => 1]);
            $importmanager->save_settings($settings);

            $conflictformsettings = array_intersect_key((array)$form->get_data(), $form->get_elements_defaults_for_cli());
            $formsettings += $conflictformsettings;
        }

        // Import.
        $instances = array_column(array_filter($importmanager->get_instances(), function($instance) {
            return $instance['id'] != 0;
        }), 'instancename');
        if (!$instances || $params['dryrun']) {
            return ['id' => 0, 'settings' => json_encode($formsettings)];
        }

        $importid = $importmanager->get_import_id();
        $importmanager->schedule_import();
        return [
            'id' => $importid,
            'settings' => json_encode($formsettings),
        ];
    }

    /**
     * Return for performing import.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'Import ID or 0 if nothing to import'),
                'settings' => new external_value(PARAM_RAW,
                    'JSON encoded list of actual settings the import will be made with', VALUE_DEFAULT, ''),
            ]
        );
    }
}
