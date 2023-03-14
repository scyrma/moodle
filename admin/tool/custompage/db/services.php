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
 * Plugin external service definition
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_custompage_page_delete' => [
        'classname'   => tool_custompage\external\page\delete::class,
        'description' => 'Delete page',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'tool_custompage_page_duplicate' => [
        'classname'   => tool_custompage\external\page\duplicate::class,
        'description' => 'Duplicate page',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'tool_custompage_page_get' => [
        'classname'   => tool_custompage\external\page\get::class,
        'description' => 'Get page content',
        'type'        => 'read',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_custompage_page_listing' => [
        'classname'   => tool_custompage\external\page\listing::class,
        'description' => 'Retrieve list of pages for current user',
        'type'        => 'read',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_custompage_audience_delete' => [
        'classname'   => tool_custompage\external\audience\delete::class,
        'description' => 'Delete page audience',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
