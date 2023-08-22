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

declare(strict_types=1);

namespace tool_custompage\external\page;

use context_system;
use external_api;
use external_files;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use tool_custompage\local\models\page;
use tool_custompage\permission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * External method for retrieving page info
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get extends external_api {

    /**
     * External method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
        ]);
    }

    /**
     * External method execution
     *
     * @see core_block_external::get_all_current_page_blocks
     *
     * @param int $pageid
     * @return array[]
     */
    public static function execute(int $pageid): array {
        global $PAGE, $OUTPUT;

        [
            'pageid' => $pageid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'pageid' => $pageid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        $page = new page($pageid);
        permission::require_can_view_page($page);

        // Avoid debugging.
        $PAGE->set_url('/');

        // Define pagetype and subpage to show the correct blocks.
        $PAGE->set_pagetype('admin-tool-custompage');
        $PAGE->set_subpage($page->get('id'));

        // Add content blocks region.
        $PAGE->blocks->add_region('content');
        $PAGE->blocks->add_region('side-pre');

        // Load the block instances for all the regions.
        $PAGE->blocks->load_blocks(false);
        $PAGE->blocks->create_all_block_instances();

        $pageblocks = [];

        $blocks = $PAGE->blocks->get_content_for_all_regions($OUTPUT);
        foreach ($blocks as $region => $regionblocks) {
            $regioninstances = $PAGE->blocks->get_blocks_for_region($region);

            // Index block instances to retrieve required info.
            $blockregioninstances = [];
            foreach ($regioninstances as $regioninstance) {
                $blockregioninstances[$regioninstance->instance->id] = $regioninstance;
            }

            foreach ($regionblocks as $regionblock) {
                /** @var \block_base $blockregioninstance */
                $blockregioninstance = $blockregioninstances[$regionblock->blockinstanceid];

                $pageblockdata = [
                    'instanceid' => $regionblock->blockinstanceid,
                    'name' => $blockregioninstance->instance->blockname,
                    'region' => $region,
                    'positionid' => $regionblock->blockpositionid,
                    'collapsible' => (bool) $regionblock->collapsible,
                    'dockable' => (bool) $regionblock->dockable,
                    'weight' => $blockregioninstance->instance->weight,
                    'visible' => $blockregioninstance->instance->visible,
                    'contents' => (array) $blockregioninstance->get_content_for_external($OUTPUT),
                ];

                $configs = (array) $blockregioninstance->get_config_for_external();
                foreach ($configs as $type => $data) {
                    foreach ((array) $data as $name => $value) {
                        $pageblockdata['configs'][] = [
                            'name' => $name,
                            'value' => json_encode($value),
                            'type' => $type,
                        ];
                    }
                }

                $pageblocks[] = $pageblockdata;
            }
        }

        return [
            'name' => external_format_string($page->get('name'), $context),
            'title' => external_format_string($page->get('title'), $context),
            'blocks' => $pageblocks,
        ];
    }

    /**
     * External method return value
     *
     * @see core_block_external::get_block_structure()
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Page name'),
            'title' => new external_value(PARAM_TEXT, 'Page title (optional)'),
            'blocks' => new external_multiple_structure(
                new external_single_structure([
                    'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
                    'name' => new external_value(PARAM_PLUGIN, 'Block name'),
                    'region' => new external_value(PARAM_ALPHANUMEXT, 'Block region'),
                    'positionid' => new external_value(PARAM_INT, 'Position ID'),
                    'collapsible' => new external_value(PARAM_BOOL, 'Whether the block is collapsible'),
                    'dockable' => new external_value(PARAM_BOOL, 'Whether the block is dockable'),
                    'weight' => new external_value(PARAM_INT, 'Used to order blocks within a region', VALUE_OPTIONAL),
                    'visible' => new external_value(PARAM_BOOL, 'Whether the block is visible', VALUE_OPTIONAL),
                    'contents' => new external_single_structure([
                        'title' => new external_value(PARAM_RAW, 'Block title'),
                        'content' => new external_value(PARAM_RAW, 'Block contents'),
                        'contentformat' => new external_format_value('content'),
                        'footer' => new external_value(PARAM_RAW, 'Block footer'),
                        'files' => new external_files('Block files'),
                    ], 'Block contents (if required)', VALUE_OPTIONAL),
                    'configs' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_RAW, 'Name'),
                            'value' => new external_value(PARAM_RAW, 'JSON encoded representation of the config value'),
                            'type' => new external_value(PARAM_ALPHA, 'Type (instance or plugin)'),
                        ]
                    ), 'Block instance and plugin configuration settings', VALUE_OPTIONAL),
                ])
            )
        ], 'Page blocks');
    }
}
