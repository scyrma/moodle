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

namespace tool_custompage\local\models;

use lang_string;
use core\persistent;

/**
 * Persistent class to represent a custom page audience
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience extends persistent {

    /** @var string The table name. */
    public const TABLE = 'tool_custompage_audience';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'pageid' => [
                'type' => PARAM_INT,
            ],
            'classname' => [
                'type' => PARAM_TEXT,
            ],
            'configdata' => [
                'type' => PARAM_RAW,
                'default' => '{}',
            ],
            'usercreated' => [
                'type' => PARAM_INT,
                'default' => static function(): int {
                    global $USER;

                    return (int) $USER->id;
                },
            ],
        ];
    }

    /**
     * Validate pageid property
     *
     * @param int $pageid
     * @return bool|lang_string
     */
    protected function validate_pageid(int $pageid) {
        if (!page::record_exists($pageid)) {
            return new lang_string('invaliddata', 'error');
        }

        return true;
    }

    /**
     * Return the page this audience belongs to
     *
     * @return page
     */
    public function get_page(): page {
        return new page($this->get('pageid'));
    }
}
