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

namespace tool_custompage\output\tabs;

use moodle_url;
use renderer_base;
use tool_custompage\permission;
use tool_custompage\local\helpers\page as helper;
use tool_custompage\local\models\page;
use tool_wp\output\tab;

/**
 * Content tab
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class content extends tab {

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        $page = new page($this->data['pageid']);

        return permission::can_preview_page($page);
    }

    /**
     * The label to be displayed for the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('content');
    }

    /**
     * Template to use to render tab
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_custompage/local/tabs/content';
    }

    /**
     * Export tab for use in a mustache template context
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $page = new page($this->data['pageid']);

        $editstridentifier = permission::can_edit_page($page) ? 'editpage' : 'previewpage';

        // Get the page blocks by region.
        $regionsblocks = [];
        foreach (helper::get_page_blocks($page, true) as $block) {
            $regionsblocks[$block->instance->region][] = $block->get_title();
        }

        // Generate the regions data for the template.
        $regions = [];
        foreach ($regionsblocks as $regionname => $blocks) {
            $regions[] = (object) [
                'name' => $regionname,
                'blocks' => $blocks
            ];
        }

        return [
            'editurl' => (new moodle_url('/admin/tool/custompage/edit.php', ['id' => $page->get('id')]))->out(),
            'editstr' => get_string($editstridentifier, 'tool_custompage'),
            'regions' => $regions,
        ];
    }
}
