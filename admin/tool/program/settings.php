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
 * Settings for programs.
 *
 * @var admin_category $ADMIN
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\permission;
use tool_wp\admin_externalpage;

defined('MOODLE_INTERNAL') || die();

// Programs custom fields.
$name = 'programscustomfields';
$str = new lang_string('programscustomfield', 'tool_program');
$url = "$CFG->wwwroot/$CFG->admin/tool/program/customfield.php";
$callback = [permission::class, 'has_configurecustomfields_capability'];
$adminexternalpage = new admin_externalpage($name, $str, $url, $callback);
// For consistency of UI make sure the "Programs" item is before "Certifications".
$certifications = $ADMIN->locate('certifications');
$ADMIN->add('courses', $adminexternalpage, $certifications ? 'certifications' : null);

// Programs.
$name = 'programs';
$str = new lang_string('programs', 'tool_program');
$url = "$CFG->wwwroot/$CFG->admin/tool/program/index.php";
$callback = [permission::class, 'check_access'];
$adminexternalpage = new admin_externalpage($name, $str, $url, $callback);
$ADMIN->add('courses', $adminexternalpage, 'programscustomfields');
