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

use context_system;
use core\persistent;
use tool_custompage\event\{page_created, page_updated, page_deleted};

/**
 * Persistent class to represent a custom page
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class page extends persistent {

    /** @var string The table name. */
    public const TABLE = 'tool_custompage';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'tenantid' => [
                'type' => PARAM_INT,
            ],
            'global' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'weight' => [
                'type' => PARAM_INT,
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
     * Trigger created event
     */
    protected function after_create(): void {
        page_created::create_from_object($this)->trigger();
    }

    /**
     * Trigger updated event
     *
     * @param bool $result
     */
    protected function after_update($result): void {
        if ($result) {
            page_updated::create_from_object($this)->trigger();
        }
    }

    /**
     * Cascade page deletion, first deleting any linked audience
     */
    protected function before_delete(): void {
        foreach (audience::get_records(['pageid' => $this->get('id')]) as $audience) {
            $audience->delete();
        }
    }

    /**
     * Trigger deleted event
     *
     * @param bool $result
     */
    protected function after_delete($result): void {
        if ($result) {
            page_deleted::create_from_object($this)->trigger();
        }
    }

    /**
     * Return formatted page name
     *
     * @return string
     */
    public function get_formatted_name(): string {
        return format_string($this->raw_get('name'), true, ['context' => context_system::instance()]);
    }

    /**
     * Return formatted page title
     *
     * @return string
     */
    public function get_formatted_title(): string {
        return format_string($this->raw_get('title'), true, ['context' => context_system::instance()])
            ?: format_string($this->raw_get('name'), true, ['context' => context_system::instance()]);
    }
}
