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
 * Class to define a templatable export file preview
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Paul Holden <paulh@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use renderable;
use renderer_base;
use templatable;

/**
 * Templatable class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Paul Holden <paulh@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_file_preview implements renderable, templatable {

    /** @var int $importid */
    protected $importid;

    /** @var string $preview*/
    protected $preview;

    /** @var int $showmorecount */
    protected $showmorecount;

    /**
     * Class constructor
     *
     * @param int $importid
     * @param string $preview
     * @param int $showmorecount
     */
    public function __construct(int $importid, string $preview, int $showmorecount) {
        $this->importid = $importid;
        $this->preview = $preview;
        $this->showmorecount = $showmorecount;
    }

    /**
     * Export class data to be rendered in mustache template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        return [
            'importid' => $this->importid,
            'preview' => $this->preview,
            'showmore' => $this->showmorecount > 0,
            'showmorecount' => $this->showmorecount,
        ];
    }
}
