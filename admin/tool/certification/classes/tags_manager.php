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
 * Tag manager for tool_certification.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use context_system;
use core_tag\output\tagindex;
use core_tag\output\tagfeed;
use core_tag_index_builder;
use core_tag_tag;
use tool_tenant\tenancy;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tags_manager
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tags_manager {
    /**
     * Gets tagged certifications.
     * @param core_tag_tag $tag
     * @param bool $exclusivemode
     * @param int $fromctxid
     * @param int $ctxid
     * @param int $recursivectx
     * @param int $page
     * @return tagindex
     */
    public static function get_tagged_certifications($tag, $exclusivemode = false, $fromctxid = 0, $ctxid = 0, $recursivectx = 1,
                                               $page = 0): tagindex {
        global $OUTPUT;

        // Show certifications only to users who have permission to edit details or allocate users.
        // Certifications can only be displayed in system context.
        $canview = permission::can_view_list(context_system::instance());
        if (!$canview || ($ctxid && context_system::instance()->id !== (int) $ctxid)) {
            return new tagindex($tag, 'tool_certification', 'tool_certification', '', $exclusivemode,
                $fromctxid, $ctxid, $recursivectx, $page, 0);
        }

        $perpage = $exclusivemode ? 20 : 5;

        // Build select query.
        $query = "SELECT cer.id AS certificationid
                FROM {tool_certification} cer
                JOIN {tag_instance} tt
                  ON cer.id = tt.itemid
                JOIN {context} ctx
                  ON ctx.instanceid = 0
                 AND ctx.contextlevel = :certificationcontextlevel
               WHERE tt.itemtype = :itemtype
                 AND tt.tagid = :tagid
                 AND tt.component = :component
                 AND cer.id %ITEMFILTER%
                 AND cer.archived = 0
                 AND cer.tenantid = :tenantid";

        $params = [
            'itemtype' => 'tool_certification',
            'tagid' => $tag->id,
            'component' => 'tool_certification',
            'certificationcontextlevel' => CONTEXT_SYSTEM,
            'tenantid' => tenancy::get_tenant_id()
        ];

        $query .= ' ORDER BY cer.id';
        $totalpages = $page + 1;

        // Use core_tag_index_builder to build and filter the list of items.
        // Request one item more than we need so we know if next page exists.
        $builder = new core_tag_index_builder('tool_certification', 'tool_certification', $query, $params,
            $page * $perpage, $perpage + 1);

        // Use core_tag_index_builder to build and filter the list of items.
        while ($item = $builder->has_item_that_needs_access_check()) {
            $builder->set_accessible($item, true);
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
            $icon = $OUTPUT->pix_icon('menu/certifications', '', 'theme');
            foreach ($items as $item) {
                $certification = new certification($item->certificationid);
                $url = new moodle_url('/admin/tool/certification/edit.php', ['id' => $item->certificationid]);
                $imgwithlink = html_writer::link($url, $icon);
                $namewithlink = html_writer::link($url, format_string($certification->get('fullname')));
                $tagfeed->add($imgwithlink, $namewithlink);
            }
            $content = $OUTPUT->render_from_template('core_tag/tagfeed', $tagfeed->export_for_template($OUTPUT));
        }

        return new tagindex($tag, 'tool_certification', 'tool_certification', $content, $exclusivemode, $fromctxid,
            $ctxid, $recursivectx, $page, $totalpages);
    }
}