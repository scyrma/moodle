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

declare(strict_types=1);

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use core_competency\api as competency_api;
use core_competency\plan;

/**
 * The backend class for the outcome for removing a learning plan.
 *
 * @package    tool_dynamicrule
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Odei Alba <odei.alba@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class learning_plan_remove extends learning_plan_base {

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomelearningplanunassign', 'tool_dynamicrule');
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $lptemplateid = $this->get_lptemplate()->get('id');
        $plan = plan::get_record(['userid' => (int)$user->id, 'templateid' => $lptemplateid]);
        if ($plan) {
            competency_api::delete_plan($plan->get('id'));
        }
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        $options = ['context' => \context_system::instance(), 'escape' => false];
        $shortname = format_string($this->get_lptemplate()->get('shortname'), true, $options);
        return get_string('outcomelearningplanunassigndescription', 'tool_dynamicrule', $shortname);
    }
}
