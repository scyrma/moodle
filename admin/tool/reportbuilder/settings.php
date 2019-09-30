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
 * Reportbuilder related settings.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($item = $ADMIN->locate('reports')->locate('reportbuilder')) {
    // The category was already added by datastore. Rename it to "Report builder".
    $item->visiblename = new lang_string('pluginname', 'tool_reportbuilder');
} else {
    // Add category "Report builder".
    $ADMIN->add('reports', new admin_category('reportbuilder', new lang_string('pluginname', 'tool_reportbuilder')));
}

if (\tool_reportbuilder\permission::can_create()) {
    // When user is able to manage reports add item "Site administration > Reports > Report builder > Manage reports".
    $ADMIN->add(
        'reportbuilder',
        new \tool_wp\admin_externalpage(
            'tool_reportbuilder',
            new lang_string('managereports', 'tool_reportbuilder'),
            new moodle_url('/admin/tool/reportbuilder/index.php'),
            [\tool_reportbuilder\permission::class, 'can_create']
        )
    );
} else {
    // When user is not able to manage reports add item "Site administration > Reports > Custom reports".
    $ADMIN->add(
        'reports',
        new \tool_wp\admin_externalpage(
            'tool_reportbuilder',
            new lang_string('viewreports', 'tool_reportbuilder'),
            new moodle_url('/admin/tool/reportbuilder/index.php'),
            [\tool_reportbuilder\permission::class, 'can_view_reports_list']
        )
    );
}
