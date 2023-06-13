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

namespace block_myinprogress\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for block_myinprogress.
 *
 * @package    block_myinprogress
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements \core_privacy\local\metadata\provider, user_preference_provider {

    /**
     * Returns meta-data information for tool block_myinprogress.
     *
     * @param collection $collection A collection of meta-data.
     * @return collection Return the collection of meta-data.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('block_myinprogress_hidden_courses', 'privacy:metadata:hiddencourses');
        return $collection;
    }

    /**
     * Export the user preferences for block_myinprogress.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preference = get_user_preferences('block_myinprogress_hidden_courses', null, $userid);
        if (isset($preference)) {
            writer::export_user_preference(
                'block_myinprogress',
                'block_myinprogress_hidden_courses',
                $preference,
                get_string('privacy:metadata:hiddencourses', 'block_myinprogress')
            );
        }
        $preference = get_user_preferences('block_myinprogress_show_hidden_cards', false, $userid);
        if (isset($preference)) {
            writer::export_user_preference(
                'block_myinprogress',
                'block_myinprogress_show_hidden_cards',
                transform::yesno($preference),
                get_string('privacy:metadata:showhiddencards', 'block_myinprogress')
            );
        }
    }
}
