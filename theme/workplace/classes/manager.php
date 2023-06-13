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

namespace theme_workplace;

use moodle_url;

/**
 * Manager class for theme workplace.
 *
 * @package    theme_workplace
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     Bas Brands <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {
    /**
     * Returns the actions for {@see core_userfeedback::print_reminder_block} adding the Workplace feedback
     *
     * @return array
     */
    public static function get_feedback_reminder_actions() {
        $params = [
            'utm_source' => 'workplace',
            'utm_medium' => 'platform',
            'utm_campaign' => 'name~WorkplaceReviews+cat~workplace+mp~no+type~landingpage+date~05-11-21'
        ];
        $wpfeedbackurl = new moodle_url('https://moodle.com/moodleworkplace-reviews/', $params);
        return [
            ['title' => get_string('calltofeedback_give'), 'url' => \core_userfeedback::make_link()->out(false),
                'data' => ['action' => 'give', 'record' => 1, 'hide' => 1], 'newwindow' => true],
            ['title' => get_string('shareyourexperience', 'theme_workplace'), 'url' => $wpfeedbackurl->out(false),
                'data' => ['action' => 'give'], 'newwindow' => true],
            ['title' => get_string('calltofeedback_remind'), 'url' => '#',
                'data' => ['action' => 'remind', 'record' => 1, 'hide' => 1]],
        ];
    }
}
