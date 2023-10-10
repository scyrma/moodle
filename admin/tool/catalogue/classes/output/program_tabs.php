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

/**
 * Class to prepare the program tabs for display.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands <bas@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_tabs implements templatable, renderable {

    /** @var string The rendered cover html */
    private $coverhtml;

    /** @var string The program content html */
    private $contenthtml;

    /** @var array The program alert */
    private $alert;

    /**
     * Constructor.
     *
     * @param string $coverhtml The rendered cover html.
     * @param string $contenthtml
     * @param array $alert
     */
    public function __construct(string $coverhtml, string $contenthtml, array $alert) {
        $this->coverhtml = $coverhtml;
        $this->contenthtml = $contenthtml;
        $this->alert = $alert;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        $data = (object)[];
        $data->coverhtml = $this->coverhtml;
        $data->contenthtml = $this->contenthtml;
        $data->alert = $this->alert;
        return $data;
    }
}
