<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     tool_program
 * @copyright   2018 Mitxel Moriana
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// This plugin is part of Moodle Workplace product.
$plugin->component    = 'tool_program';
$plugin->release      = '3.7.2';
$plugin->version      = 2019091700;
$plugin->requires     = 2019052002.00;
$plugin->maturity     = MATURITY_STABLE;
$plugin->dependencies = [
    'enrol_program'   => 2019090900,
    'tool_wp'         => 2019090900,
    'tool_tenant'     => 2019090900,
];