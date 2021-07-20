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
 * CLI export
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define('CLI_SCRIPT', true);

use tool_wp\local\exportimport\cli_helper;

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

if (moodle_needs_upgrading()) {
    cli_error("Moodle upgrade pending, export execution suspended.");
}

$clihelper = new cli_helper(cli_helper::EXPORT);
$clihelper->set_language();

if ($clihelper->get_cli_option('help')) {
    $clihelper->print_export_help();
    die();
}

$user = $clihelper->choose_user();
cron_setup_user($user);
$clihelper->set_language(); // Set language again because session was changed after setting the user.
$clihelper->choose_exporter();
$tenant = $clihelper->choose_tenant();
$clihelper->apply_general_settings($tenant);
$clihelper->print_export_summary($tenant);
$clihelper->perform_export($tenant);
