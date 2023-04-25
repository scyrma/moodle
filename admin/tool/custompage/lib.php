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
 * Plugin callbacks
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

declare(strict_types=1);

use core\output\inplace_editable;
use tool_custompage\local\models\page;
use tool_custompage\permission;
use tool_custompage\form\audience;
use tool_custompage\output\{name_editable, weight_editable};

/**
 * Plugin inplace editable implementation
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return inplace_editable|null
 */
function tool_custompage_inplace_editable(string $itemtype, int $itemid, string $newvalue): ?inplace_editable {
    switch ($itemtype) {
        case 'name':
            return name_editable::update($itemid, $newvalue);
        case 'weight':
            return weight_editable::update($itemid, $newvalue);
    }

    return null;
}

/**
 * Return the audience form fragment
 *
 * @param array $params
 * @return string
 */
function tool_custompage_output_fragment_audience_form(array $params): string {
    global $PAGE;

    $audienceform = new audience(null, null, 'post', '', [], true, [
        'pageid' => $params['pageid'],
        'classname' => $params['classname'],
    ]);
    $audienceform->set_data_for_dynamic_submission();

    $context = [
        'instanceid' => 0,
        'heading' => $params['title'],
        'headingeditable' => $params['title'],
        'form' => $audienceform->render(),
        'canedit' => true,
        'candelete' => true,
        'showormessage' => $params['showormessage'],
    ];

    $renderer = $PAGE->get_renderer('core_reportbuilder');
    return $renderer->render_from_template('core_reportbuilder/local/audience/form', $context);
}

/**
 * Define menu items to be added to the Workplace launcher
 *
 * @return array[]
 */
function tool_custompage_theme_workplace_menu_items(): array {
    global $OUTPUT;

    $menuitems = [];

    if (permission::can_view_pages_list()) {
        $menuitems[] = [
            'url' => new moodle_url('/admin/tool/custompage/index.php'),
            'name' => get_string('pluginname', 'tool_custompage'),
            'imageurl' => $OUTPUT->image_url('icon', 'tool_custompage')->out(false),
            'isglobal' => permission::can_create_global_page(),
        ];
    }

    return $menuitems;
}

/**
 * Font awesome icon mapping
 *
 * @return string[]
 */
function tool_custompage_get_fontawesome_icon_map(): array {
    return [
        'tool_custompage:t/globe' => 'fa-globe',
    ];
}

/**
 * Callback for the tool_wp_potential_users_selector web service
 *
 * @param string $area 'data-area' attribute sent by manual audience.
 * @param int $itemid page id used to retrieve users data.
 * @return array|null used to search users based on custom page type.
 */
function tool_custompage_potential_users_selector(string $area, int $itemid): ?array {

    if ($area !== 'custompage' || !$itemid) {
        return null;
    }

    $page = new page($itemid);
    permission::require_can_edit_page($page);

    // If custom page is global, return dummy where to retrieve users in whole site.
    if ($page->get('global')) {
        return ['', '1=1', []];
    }

    return \tool_tenant\tenancy::get_users_sql('u');
}
