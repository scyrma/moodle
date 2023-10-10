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

namespace tool_custompage\output;

use context_system;
use core_external;
use html_writer;
use moodle_url;
use core\output\inplace_editable;
use tool_custompage\permission;
use tool_custompage\local\models\page;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("{$CFG->libdir}/external/externallib.php");

/**
 * Page name editable component
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class name_editable extends inplace_editable {

    /**
     * Class constructor
     *
     * @param page $page
     */
    public function __construct(page $page) {
        $editable = permission::can_edit_page($page);

        $displayvalue = html_writer::link(new moodle_url('/admin/tool/custompage/manage.php', ['id' => $page->get('id')]),
            $page->get_formatted_name());

        parent::__construct('tool_custompage', 'name', $page->get('id'), $editable, $displayvalue, $page->get('name'),
            get_string('editname', 'tool_custompage'));
    }

    /**
     * Update page persistent and return self, called from inplace_editable callback
     *
     * @param int $pageid
     * @param string $value
     * @return self
     */
    public static function update(int $pageid, string $value): self {
        core_external::validate_context(context_system::instance());

        $page = new page($pageid);
        permission::require_can_edit_page($page);

        $value = trim(clean_param($value, PARAM_TEXT));
        if ($value !== '') {
            $page
                ->set('name', $value)
                ->update();
        }

        return new self($page);
    }
}
