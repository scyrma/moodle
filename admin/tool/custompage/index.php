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
 * Custom pages listing
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use tool_custompage\permission;
use tool_custompage\reportbuilder\local\systemreports\pages;
use tool_tenant\system_report_factory;

// Workplace validates login and access in function \tool_wp\admin_externalpage::setup_page(), not supported in codechecker.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../config.php');

\tool_wp\admin_externalpage::setup_page('custompages');

$PAGE->requires->js_call_amd('tool_custompage/manage', 'init');

// Remove secondary navigation.
$PAGE->set_secondary_navigation(false);

// If current user is able to create both global/tenant pages, they should be allowed to select as appropriate.
if (permission::can_create_global_page()) {
    $actions = new action_menu();
    $actions->set_menu_trigger(get_string('newpage', 'tool_custompage'), 'btn btn-primary');
    $actions->add(new action_menu_link(
        new moodle_url('#'),
        null,
        new lang_string('newpageglobal', 'tool_custompage'),
        false,
        ['data-action' => 'page-create', 'data-page-global' => 1],
    ));
    $actions->add(new action_menu_link(
        new moodle_url('#'),
        null,
        new lang_string('newpagetenant', 'tool_custompage'),
        false,
        ['data-action' => 'page-create'],
    ));
    $button = $OUTPUT->render($actions);
} else {
    $button = html_writer::tag('button', get_string('newpage', 'tool_custompage'), [
        'class' => 'btn btn-primary my-auto',
        'data-action' => 'page-create',
    ]);
}

$PAGE->add_header_action($button);
echo $OUTPUT->header();

$report = system_report_factory::create(pages::class);
echo $report->output();

echo $OUTPUT->footer();
