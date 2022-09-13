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
 * Upgrade scripts for custom pages.
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Method called from init WP instalation and upgrade script.
 */
function tool_custompage_create_myteams_page(): void {
    global $DB;
    $manageraudience = \tool_organisation\tool_custompage\audience\manager::class;
    if (!class_exists($manageraudience)) {
        return;
    }

    // Set My teams custom page object.
    $myteampage = new stdClass();
    $myteampage->name = get_string('myteamspagename', 'tool_custompage');
    $myteampage->weight = -10;
    $myteampage->global = 1;

    // Create My teams custom page.
    if ($custompageexists = $DB->get_record('tool_custompage', (array)$myteampage, 'id')) {
        $myteamscustompageid = $custompageexists->id;
    } else {
        $myteampage->tenantid = 0;
        $myteamscustompageid = \tool_custompage\local\helpers\page::create_page($myteampage)->get('id');
    }

    // Add manager audience to myteams page.
    $audiencedata = [
        'pageid' => $myteamscustompageid,
        'classname' => $manageraudience
    ];
    if (!$DB->record_exists('tool_custompage_audience', $audiencedata)) {
        $manageraudience::create($myteamscustompageid, ['permissions' => 'anymanager']);
    }
    // Add myteams block.
    $blockdata = [
        'blockname' => 'myteams',
        'pagetypepattern' => 'admin-tool-custompage',
        'subpagepattern' => $myteamscustompageid,
    ];

    if (!$DB->record_exists('block_instances', $blockdata)) {
        $page = new moodle_page();
        $page->set_context(context_system::instance());
        $page->blocks->add_region('content');
        $page->blocks->add_block($blockdata['blockname'], 'content', 0, false,
            $blockdata['pagetypepattern'], $blockdata['subpagepattern']);
    }
}
