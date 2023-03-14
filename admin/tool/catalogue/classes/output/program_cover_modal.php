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

namespace tool_catalogue\output;

use renderable;
use renderer_base;
use templatable;
use tool_program\persistent\program;

/**
 * Class to prepare the program cover modal to be displayed in the user progress overview.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_cover_modal implements templatable, renderable {

    /** @var program The program instance */
    private $program;

    /** @var int The user id */
    private $userid;

    /**
     * Constructor.
     *
     * @param program $program
     * @param int|null $userid
     */
    public function __construct(program $program, ?int $userid = null) {
        global $USER;

        $this->program = $program;
        $this->userid = $userid ?? (int) $USER->id;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        $programview = new program_cover($this->program, $this->userid);
        $data = $programview->export_for_template($output);

        // Do not show help box in the program cover modal.
        $data->hashelp = false;
        // Do not show about section in the program cover modal.
        $data->pagesectionabout = false;
        // Do not show buttons in the program cover modal.
        $data->hasbuttons = false;
        // Show all sections expanded.
        if (!empty($data->pagesectiondates)) {
            $data->pagesectiondates = (object) [];
            $data->pagesectiondates->collapse = false;
        }
        if (!empty($data->certifications)) {
            $data->pagesectioncertifications = (object) [];
            $data->pagesectioncertifications->collapse = false;
        }
        if (!empty($data->pagesectionstructure)) {
            $data->pagesectionstructure = (object)[];
            $data->pagesectionstructure->collapse = false;
        }

        return $data;
    }
}
