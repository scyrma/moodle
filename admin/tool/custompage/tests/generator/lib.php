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

use tool_custompage\local\audience\base;
use tool_custompage\local\helpers\page as helper;
use tool_custompage\local\models\page;

/**
 * Plugin test generator
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_custompage_generator extends component_generator_base {

    /**
     * Create page
     *
     * @param array|stdClass $record
     * @return page
     * @throws coding_exception
     */
    public function create_page($record): page {
        $record = (array) $record;

        if (!array_key_exists('name', $record)) {
            throw new coding_exception('Record must contain \'name\' property');
        }
        if (!array_key_exists('weight', $record)) {
            throw new coding_exception('Record must contain \'weight\' property');
        }

        return helper::create_page((object) $record);
    }

    /**
     * Create page block
     *
     * @param array|stdClass $record
     * @return page
     * @throws coding_exception
     */
    public function create_page_block($record): stdClass {
        $record = (array) $record;

        if (!array_key_exists('pageid', $record)) {
            throw new coding_exception('Record must contain \'pageid\' property');
        }
        if (!array_key_exists('blockname', $record)) {
            throw new coding_exception('Record must contain \'blockname\' property');
        }

        // Ensure any additional record data is preserved.
        $options = array_diff_key($record, array_flip(['pageid', 'blockname']));

        return $this->datagenerator->create_block($record['blockname'], [
            'pagetypepattern' => 'admin-tool-custompage',
            'subpagepattern' => $record['pageid'],
        ] + $options);
    }

    /**
     * Create page audience
     *
     * @param array|stdClass $record
     * @return base
     * @throws coding_exception
     */
    public function create_audience($record): base {
        $record = (array) $record;

        // Required properties.
        if (!array_key_exists('pageid', $record)) {
            throw new coding_exception('Record must contain \'pageid\' property');
        }
        if (!array_key_exists('configdata', $record)) {
            throw new coding_exception('Record must contain \'configdata\' property');
        }

        // Default to all users if not specified, for convenience.
        /** @var base $classname */
        $classname = $record['classname'] ??
            \tool_custompage\tool_custompage\audience\allusers::class;

        return $classname::create($record['pageid'], $record['configdata']);
    }
}
