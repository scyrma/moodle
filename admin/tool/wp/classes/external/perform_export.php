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
 * External class for performing an export
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper as exportimport_helper;
use tool_tenant\tenancy;

/**
 * External class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class perform_export extends \external_api {
    /**
     * Parameters for performing export
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'exporter' => new external_value(PARAM_RAW, 'Full name of the exporter class'),
            'settings' => new external_value(PARAM_RAW, 'JSON encoded list of settings for the exporter', VALUE_DEFAULT, '{}'),
            'tenant' => new external_value(PARAM_ALPHANUM, 'Database ID or non-numeric idnumber
                of the tenant where the export should be made', VALUE_DEFAULT, 0),
            'dryrun' => new external_value(PARAM_BOOL,
                'Only validate input and show the actual settings, do not actually schedule export',
                VALUE_DEFAULT, false),
            'notenant' => new external_value(PARAM_BOOL,
                'Perform export without the tenant (only for exporters that support this).',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Performing (schedules) export and displays information about it
     *
     * @param string $exporter
     * @param string $settings
     * @param string|null $tenant
     * @param bool $dryrun
     * @param bool $notenant
     * @return array
     */
    public static function execute(string $exporter, string $settings, string $tenant,
            bool $dryrun, bool $notenant): array {
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'exporter' => $exporter,
            'settings' => $settings,
            'tenant' => $tenant,
            'dryrun' => $dryrun,
            'notenant' => $notenant,
        ]);
        $settings = json_decode($params['settings'], true);
        if (is_numeric($params['settings']) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \invalid_parameter_exception(
                get_string('exportersettingsinvalid', 'tool_wp'));
        }

        $context = context_system::instance();
        self::validate_context($context);
        \tool_wp\permission::require_can_use_export_import();

        // Exporter.
        $exporters = exportimport_helper::get_all_exporters();
        $exporterinstance = null;
        $exporters = array_values($exporters);
        $availableexporters = [];
        foreach ($exporters as $exp) {
            if (get_class($exp) === $params['exporter']) {
                $exporterinstance = $exp;
                break;
            }
            $availableexporters[] = get_class($exp);
        }
        if (!$exporterinstance) {
            throw new \invalid_parameter_exception(
                get_string('exporternotfound', 'tool_wp', $params['exporter']) .
                "\n". get_string('migrationschoosefrom', 'tool_wp') .
                ': '. join(', ', $availableexporters)
            );
        }

        // Tenant.
        if ($params['tenant'] && $params['notenant']) {
            throw new \invalid_parameter_exception(get_string('migrationnotenanterror', 'tool_wp'));
        }
        if ($params['notenant'] && $exporterinstance->is_tenant_required()) {
            throw new \invalid_parameter_exception(get_string('exporterrequirestenant', 'tool_wp', $exporterinstance->get_name()));
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
                    throw new \invalid_parameter_exception(get_string('migrationcannotswitchtenant', 'tool_wp', $params['tenant']));
                }
            }
        }

        // Settings.
        $formdata = [
            'exporter' => get_class($exporterinstance),
            'exportertenant' => $usertenant->id ?? 0,
            'entrypoint' => '',
            'entrypointid' => 0,
        ] + $settings;
        $form = export_settings_form::mock_form_submission($formdata);
        $defaultsettings = $form->get_elements_defaults_for_cli();

        // Check settings validation errors.
        if (!$form->is_validated()) {
            $errors = $form->get_quick_form()->_errors;
            $errordescr = [];
            foreach ($errors as $key => $value) {
                $errordescr[] = $key.' - '.$value;
            }
            throw new \invalid_parameter_exception(
                get_string('exportersettingsvalidationfailed', 'tool_wp', implode(';', $errordescr)));
        }

        // Export.
        $alldata = $form->get_data();
        $exporterclass = get_class($exporterinstance);
        $exporter = export_manager::create_exporter($exporterclass, '', 0, (array)$alldata,
            $usertenant->id ?? 0);
        $instances = export_manager::get_instances_for_review_form($exporter);
        $instanceswithids = array_filter($instances, function ($instance) {
            return $instance['id'] != 0;
        });
        $exportid = $instanceswithids && !$params['dryrun'] ?
            export_manager::schedule_export((array)$alldata, true) : 0;
        return [
            'id' => $exportid,
            'settings' => json_encode(array_intersect_key((array)$alldata, $defaultsettings)),
        ];
    }

    /**
     * Return for performing export.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'Export ID or 0 if nothing to export'),
                'settings' => new external_value(PARAM_RAW,
                    'JSON encoded list of actual settings the export will be made with', VALUE_DEFAULT, ''),
            ]
        );
    }
}
