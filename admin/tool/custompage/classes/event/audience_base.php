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

namespace tool_custompage\event;

use coding_exception;
use context_system;
use core\event\base;
use moodle_url;
use tool_custompage\local\models\audience;

/**
 * Audience base event
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int    pageid:      The ID of the page
 * }
 */
abstract class audience_base extends base {

    /**
     * Creates an instance from given audience
     *
     * @param audience $audience
     * @return self
     */
    final public static function create_from_object(audience $audience): parent {
        $event = self::create([
            'context' => context_system::instance(),
            'objectid' => $audience->get('id'),
            'other' => [
                'pageid' => $audience->get('pageid'),
            ],
        ]);
        $event->add_record_snapshot($event->objecttable, $audience->to_record());
        return $event;
    }

    /**
     * Event data validation
     *
     * @throws coding_exception
     */
    final protected function validate_data(): void {
        parent::validate_data();

        if (!isset($this->objectid)) {
            throw new coding_exception('The \'objectid\' must be set');
        }

        if (!isset($this->other['pageid'])) {
            throw new coding_exception('The \'pageid\' must be set in other');
        }
    }

    /**
     * Returns relevant URL
     *
     * @return moodle_url
     */
    final public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/custompage/view.php', ['id' => $this->other['pageid']]);
    }
}
