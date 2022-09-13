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
use external_function_parameters;
use external_value;
use tool_custompage\permission;
use tool_custompage\local\helpers\page as helper;
use tool_custompage\local\models\page;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * External method for duplicating pages
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class duplicate extends external_api {

    /**
     * External method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
            'amendglobal' => new external_value(PARAM_BOOL, 'Amend duplicated page global state'),
            'newglobal' => new external_value(PARAM_BOOL, 'New global state of duplicated page', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * External method execution
     *
     * @param int $pageid
     * @param bool $amendglobal
     * @param bool $newglobal
     * @return int
     */
    public static function execute(int $pageid, bool $amendglobal, bool $newglobal = false): int {
        [
            'pageid' => $pageid,
            'amendglobal' => $amendglobal,
            'newglobal' => $newglobal,
        ] = self::validate_parameters(self::execute_parameters(), [
            'pageid' => $pageid,
            'amendglobal' => $amendglobal,
            'newglobal' => $newglobal,
        ]);

        self::validate_context(context_system::instance());

        // User must be able to preview the original page, and create a new one.
        $page = new page($pageid);
        permission::require_can_preview_page($page);

        if ((!$amendglobal && $page->get('global')) || ($amendglobal && $newglobal)) {
            permission::require_can_create_global_page();
        }

        if ($amendglobal && ($page->get('global') !== $newglobal)) {
            $newpage = helper::duplicate_page($page, $newglobal);
        } else {
            $newpage = helper::duplicate_page($page);
        }

        return $newpage->get('id');
    }

    /**
     * External method return value
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_INT, 'ID of the new page');
    }
}
