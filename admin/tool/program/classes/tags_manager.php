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
 * Tag manager for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program;

use context;
use context_helper;
use context_system;
use core_tag\output\tagindex;
use core_tag\output\tagfeed;
use core_tag_index_builder;
use core_tag_tag;
use html_writer;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tags_manager
 *
 * @package tool_program
 * @copyright 2018 Mitxel Moriana
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tags_manager {
    /**
     * Gets tagged programs.
     *
     * @param core_tag_tag $tag
     * @param bool $exclusivemode
     * @param int $fromctxid
     * @param int $ctxid
     * @param int $recursivectx
     * @param int $page
     * @return tagindex
     */
    public static function get_tagged_programs($tag, $exclusivemode = false, $fromctxid = 0, $ctxid = 0, $recursivectx = 1,
        $page = 0): tagindex {
        global $OUTPUT;

        $perpage = $exclusivemode ? 20 : 5;

        // Build select query.
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $query = "SELECT pr.id AS programid, $ctxselect
                FROM {tool_program} pr
                JOIN {tag_instance} tt
                  ON pr.id = tt.itemid
                JOIN {context} ctx
                  ON ctx.instanceid = 0
                 AND ctx.contextlevel = :programcontextlevel
               WHERE tt.itemtype = :itemtype
                 AND tt.tagid = :tagid
                 AND tt.component = :component
                 AND pr.id %ITEMFILTER% ";

        $params = [
            'itemtype' => 'tool_program',
            'tagid' => $tag->id,
            'component' => 'tool_program',
            'programcontextlevel' => CONTEXT_SYSTEM
        ];

        // Programs can only be displayed in system context.
        if ($ctxid && context_system::instance()->id !== (int) $ctxid) {
            return new tagindex($tag, 'tool_program', 'tool_program', '',
                $exclusivemode, $fromctxid, $ctxid, $recursivectx, $page, 0);
        }

        $query .= ' ORDER BY';
        if ($fromctxid) {
            // In order-clause specify that modules from inside "fromctx" context should be returned first.
            $fromcontext = context::instance_by_id($fromctxid);
            $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
            $params['fromcontextid'] = $fromcontext->id;
            $params['frompath'] = $fromcontext->path . '/%';
        }
        $query .= ' pr.id';

        $totalpages = $page + 1;

        // Use core_tag_index_builder to build and filter the list of items.
        // Request one item more than we need so we know if next page exists.
        $builder = new core_tag_index_builder('tool_program', 'tool_program', $query, $params, $page * $perpage, $perpage + 1);

        // Use core_tag_index_builder to build and filter the list of items.
        $context = context_system::instance();
        while ($item = $builder->has_item_that_needs_access_check()) {
            context_helper::preload_from_record($item);
            // Show programs only to users who have permission to edit details.
            $builder->walk(function($taggeditem) use ($builder, $context) {
                $program = new program($taggeditem->programid);
                $accessible = permission::can_edit_details($program, $context);
                $builder->set_accessible($taggeditem, $accessible);
            });
        }

        $items = $builder->get_items();
        if (count($items) > $perpage) {
            $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
            array_pop($items);
        }

        // Build the display contents.
        $content = '';
        if ($items) {
            $tagfeed = new tagfeed();
            $icon = $OUTPUT->pix_icon('menu/programs', '', 'theme');

            foreach ($items as $item) {
                context_helper::preload_from_record($item);
                $program = new program($item->programid);
                $url = new \moodle_url('/admin/tool/program/edit.php', ['id' => $item->programid]);
                $imgwithlink = html_writer::link($url, $icon);
                $tagfeed->add($imgwithlink, format_string($program->get('fullname')));
            }
            $content = $OUTPUT->render_from_template('core_tag/tagfeed', $tagfeed->export_for_template($OUTPUT));
        }

        return new tagindex($tag, 'tool_program', 'tool_program', $content, $exclusivemode, $fromctxid,
            $ctxid, $recursivectx, $page, $totalpages);
    }
}
