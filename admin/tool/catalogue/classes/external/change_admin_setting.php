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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_catalogue\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_value;
use tool_catalogue\configuration;
use tool_catalogue\permission;

/**
 * Implementation of web service tool_catalogue_change_admin_setting
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class change_admin_setting extends external_api {

    /**
     * Describes the parameters for tool_catalogue_change_admin_setting
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action (toggle, move, etc)', VALUE_REQUIRED),
            'setting' => new external_value(PARAM_RAW, 'The name of the setting', VALUE_REQUIRED),
            'field' => new external_value(PARAM_RAW, 'The name of the field', VALUE_REQUIRED),
            'newstate' => new external_value(PARAM_RAW,
                'The target state (new visibility, direction of moving, etc)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Implementation of web service tool_catalogue_change_admin_setting
     *
     * @param string $action Action (toggle, move, etc)
     * @param string $setting The name of the setting
     * @param string $field The name of the field
     * @param string $newstate The target state (new visibility, direction of moving, etc)
     * @return array
     */
    public static function execute($action, $setting, $field, $newstate = ''): array {
        // Parameter validation.
        ['action' => $action, 'setting' => $setting, 'field' => $field, 'newstate' => $newstate] = self::validate_parameters(
            self::execute_parameters(),
            ['action' => $action, 'setting' => $setting, 'field' => $field, 'newstate' => $newstate]
        );

        // From web services we don't call require_login(), but rather validate_context.
        $context = \context_system::instance();
        self::validate_context($context);
        permission::require_can_configure_catalogue();

        // Execute action.
        configuration::change_admin_setting($action, $setting, $field, $newstate);
        return [];
    }

    /**
     * Describe the return structure for tool_catalogue_change_admin_setting
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}
