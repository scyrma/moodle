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

namespace tool_certification\output;

use action_menu_link;
use lang_string;
use moodle_url;
use pix_icon;
use plugin_renderer_base;
use tool_certification\certification;
use tool_certification\permission;

/**
 * Class renderer
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class renderer extends plugin_renderer_base {

    /**
     * Get action menu links for certification edit page.
     *
     * @param certification $certification
     * @return action_menu_link[]
     */
    public function get_action_menu_links(certification $certification): array {
        $actionmenulinks = [];
        // Action to duplicate certification.
        if (permission::can_duplicate($certification)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url('#'),
                new pix_icon('e/manage_files', '', 'core'),
                new lang_string('duplicate', 'tool_certification'),
                null,
                [
                    'data-action' => 'duplicate',
                    'data-certificationid' => $certification->get('id'),
                ]
            );
        }

        // Action to archive certification.
        if (permission::can_archive($certification)) {
            $actionmenulinks[] = new action_menu_link(
                new moodle_url(
                    '/admin/tool/certification/index.php',
                    null,
                    'certification_manager_list_archived_tab'
                ),
                new pix_icon('archive', '', 'tool_wp'),
                new lang_string('archive', 'tool_certification'),
                null,
                [
                    'data-action' => 'archive',
                    'data-certificationid' => $certification->get('id'),
                    'data-name' => $certification->get_formatted_name(),
                ]
            );
        }

        return $actionmenulinks;
    }
}
