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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_dynamicrule\event;

use coding_exception;
use core\event\base;
use moodle_url;
use tool_dynamicrule\outcome;

/**
 * Action added to a rule event.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int ruleid: id of rule related to this condition.
 * }
 *
 * @package    tool_dynamicrule
 * @author     2023 David Matamoros <davidmc@moodle.com>
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class outcome_base extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param outcome $action The new outcome.
     * @return self
     */
    public static function create_from_outcome(outcome $action) {
        $params = [
            'contextid' => \context_system::instance()->id,
            'objectid' => $action->get('id'),
            'other' => [
                'ruleid' => $action->get('ruleid'),
            ],
        ];

        $event = static::create($params);
        $event->add_record_snapshot(outcome::TABLE, $action->to_record());
        return $event;
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $this->other['ruleid']]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (!$this->objectid) {
            throw new coding_exception('The outcome ID must be set.');
        }
        if (!$this->other['ruleid']) {
            throw new coding_exception('The rule ID must be set.');
        }
    }
}
