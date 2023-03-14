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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use tool_custompage\local\models\page;

/**
 * Plugin Behat step definitions
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_custompage extends behat_base {

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | type   | identifier | description        |
     * | Manage | Page name  | Custom page manage |
     * | Edit   | Page name  | Custom page edit   |
     * | View   | Page name  | Custom page view   |
     *
     * @param string $type
     * @param string $identifier
     * @return moodle_url
     * @throws Exception for unrecognised page or type
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        if (!$page = page::get_record(['name' => $identifier])) {
            throw new Exception("Unknown page '{$identifier}'");
        }

        switch ($type) {
            case 'Manage':
                return new moodle_url('/admin/tool/custompage/manage.php', ['id' => $page->get('id')]);
            case 'Edit':
                return new moodle_url('/admin/tool/custompage/edit.php', ['id' => $page->get('id')]);
            case 'View':
                return new moodle_url('/admin/tool/custompage/view.php', ['id' => $page->get('id')]);
            default:
                throw new Exception("Unrecognised page type '{$type}'");
        }
    }
}
