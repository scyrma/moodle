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

namespace tool_custompage\local\helpers;

use block_base;
use core_text;
use moodle_page;
use moodle_url;
use stdClass;
use tool_custompage\local\models\{audience, page as model};
use tool_custompage\permission;

/**
 * Custom pages helper class
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class page {

    /**
     * Create new page
     *
     * @param stdClass $data
     * @return model
     */
    public static function create_page(stdClass $data): model {

        // We need to account for the page being created during installation, before the tenant tool has been installed.
        if (!isset($data->tenantid) ||
                (!during_initial_install() && !\tool_tenant\permission::can_access_tenant((int) $data->tenantid))) {

            $data->tenantid = \tool_tenant\tenancy::get_tenant_id();
        }

        return (new model(0, $data))->create();
    }

    /**
     * Update existing page
     *
     * @param int $pageid
     * @param stdClass $data
     * @return model
     */
    public static function update_page(int $pageid, stdClass $data): model {
        $page = new model($pageid);
        $page->set_many((array) $data)->update();

        return $page;
    }

    /**
     * Delete page
     *
     * @param model $page
     * @return bool
     */
    public static function delete_page(model $page): bool {

        // Delete all the page blocks first.
        $blocks = static::get_page_blocks($page);
        foreach ($blocks as $block) {
            blocks_delete_instance($block);
        }

        return $page->delete();
    }

    /**
     * Duplicate page
     *
     * @param model $page
     * @param bool|null $global Whether to set global state of duplicated page (null equals preserve that of original page)
     * @return model
     */
    public static function duplicate_page(model $page, ?bool $global = null): model {
        global $DB;

        // Copy the original page, removing properties to be re-created when duplicating.
        $pagerecord = $page->to_record();
        unset($pagerecord->id, $pagerecord->usercreated, $pagerecord->tenantid);

        // Update some properties of the original page record, ready for duplicating.
        $duplicatepagename = get_string('duplicatepagepostfix', 'tool_custompage', $pagerecord->name);
        $pagerecord->name = core_text::substr($duplicatepagename, 0, 255);

        if ($global !== null) {
            $pagerecord->global = $global;
        }

        $newpage = static::create_page($pagerecord);

        // Copy the audiences of the original page (if preserving global/tenant state).
        if ($global === null) {
            $audiences = audience::get_records(['pageid' => $page->get('id')]);
            foreach ($audiences as $audience) {
                $audiencecopy = $audience->to_record();
                unset($audiencecopy->id, $audiencecopy->usercreated);

                (new audience(0, $audiencecopy))
                    ->set('pageid', $newpage->get('id'))
                    ->create();
            }
        }

        // Copy the blocks of the original page.
        $blocks = static::get_page_blocks($page);
        foreach ($blocks as $block) {

            // Store the original block instance ID for later.
            $blockinstanceid = $block->id;

            $blockinstance = block_instance($block->blockname, $block);
            if (!$blockinstance->user_can_addto($blockinstance->page) &&
                !permission::can_duplicate_block_without_validation()) {
                if ($validblock = self::validate_block_configuration($blockinstance)) {
                    $block = $validblock;
                } else {
                    continue;
                }
            }

            // Create our copy of the original block.
            $blockcopy = clone($block);
            $blockcopy->subpagepattern = $newpage->get('id');
            $blockcopy->timecreated = $blockcopy->timemodified = time();
            $blockcopy->id = $DB->insert_record('block_instances', $blockcopy);

            $blockcopy = block_instance($blockcopy->blockname, $blockcopy);
            $blockcopy->instance_copy($blockinstanceid);

            // Copy any block position override.
            $position = $DB->get_record('block_positions', ['blockinstanceid' => $blockinstanceid]);
            if ($position) {
                $position->blockinstanceid = $blockcopy->instance->id;
                $position->subpage = $newpage->get('id');
                $DB->insert_record('block_positions', $position);
            }
        }

        return $newpage;
    }

    /**
     * Return list of block instance records added to page
     *
     * Specific block position data is also accounted for in returned data, with additional "region" property containing
     * calculated/actual value in addition to the "weight" value being used for sorting
     *
     * @param model $page
     * @param bool $returnblockinstance
     * @return stdClass[]|block_base[]
     */
    public static function get_page_blocks(model $page, bool $returnblockinstance = false): array {
        global $DB;

        $sql = "SELECT bi.*, COALESCE(bp.region, bi.defaultregion) AS region
                  FROM {block_instances} bi
             LEFT JOIN {block_positions} bp ON bp.blockinstanceid = bi.id
                 WHERE bi.pagetypepattern = :pagetypepattern AND bi.subpagepattern = :subpagepattern
              ORDER BY COALESCE(bp.weight, bi.defaultweight)";

        $params = [
            'pagetypepattern' => 'admin-tool-custompage',
            'subpagepattern' => $page->get('id'),
        ];

        $instances = $DB->get_records_sql($sql, $params);
        if ($returnblockinstance) {
            $instances = static::get_block_instances($instances);
        }

        return $instances;
    }

    /**
     * Transform array of block instance records into block class instances
     *
     * @param stdClass[] $instances
     * @return block_base[]
     */
    public static function get_block_instances(array $instances): array {
        return array_map(static function(stdClass $instance): block_base {
            return block_instance($instance->blockname, $instance);
        }, $instances);
    }

    /**
     * Validate block configuration
     *
     * When users can't add blocks and doesn't have 'skipblockvalidation' capability
     * we need to check if block has a valid config to allow duplicate it.
     * @param block_base $blockinstance
     * @return object|null
     */
    private static function validate_block_configuration(block_base $blockinstance): ?object {
        global $CFG;
        require_once("{$CFG->dirroot}/blocks/edit_form.php");

        $formfile = $CFG->dirroot . '/blocks/' . $blockinstance->name() . '/edit_form.php';

        if (is_readable($formfile)) {
            require_once($formfile);
            $classname = 'block_' . $blockinstance->name() . '_edit_form';
            if (!class_exists($classname)) {
                $classname = 'block_edit_form';
            }
        } else {
            $classname = 'block_edit_form';
        }

        // We need te create a dummy page.
        $duplicatepage = new moodle_page();
        $duplicatepage->set_pagelayout('admin-tool-custompage');
        $duplicatepage->set_context($blockinstance->page->context);
        $duplicatepage->set_url(new moodle_url('/admin/tool/custompage/edit.php'));

        // Complete data object necessary to instantiate edit form class.
        $blockinstance->instance->weight = $blockinstance->instance->defaultweight;
        $blockinstance->instance->visible = 1;

        $mform = new $classname($duplicatepage, $blockinstance, $blockinstance->page);
        $mform->set_data($blockinstance->instance);
        $validationerrors = $mform->validation($blockinstance->instance, null);
        return (empty($validationerrors) && has_capability('moodle/block:edit', $blockinstance->page->context))
            ? $blockinstance->instance : null;
    }
}
