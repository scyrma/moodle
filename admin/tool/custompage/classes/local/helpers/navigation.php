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

use core\navigation\views\primary;
use moodle_url;
use navigation_node;

/**
 * Custom pages navigation helper class
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class navigation {

    /**
     * Add custompage links to primary navigation
     * Implementation of callback called from {@see primary::initialise()}
     *
     * @param primary $primarynav
     */
    public static function add_primary_nodes(primary &$primarynav): void {
        $allowedpages = audience::user_pages_list();
        foreach ($allowedpages as $page) {
            $primarynav->add(
                $page->get_formatted_title(),
                new moodle_url('/admin/tool/custompage/view.php', ['id' => $page->get('id')]),
                navigation_node::TYPE_ROOTNODE,
                null,
                'tool_custompage-' . $page->get('id')
            );
        }
    }
}
