<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

/**
 * Class session_datetime
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * session_datetime renderable class.
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_datetime implements \renderable, \templatable {

    /**
     * Constructor.
     *
     * @param \stdClass $session
     */
    public function __construct(\stdClass $session) {
        $this->session = $session;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {

        $sessiontimes = [];

        // Add session times list.
        if (empty($this->session->sessiondates)) {
            $sessiontimes[] = [
                'date' => get_string('notset', 'mod_appointment'),
                'time' => get_string('notset', 'mod_appointment'),
            ];
        } else {
            foreach ($this->session->sessiondates as $sessiondate) {
                $date = userdate($sessiondate->timestart, get_string('strftimedaydate', 'langconfig'));
                $timestart = userdate($sessiondate->timestart, get_string('strftimetime', 'langconfig'));
                $timefinish = userdate($sessiondate->timefinish, get_string('strftimetime', 'langconfig'));
                $sessiontimes[] = [
                    'date' => $date,
                    'time' => $timestart . ' - ' . $timefinish,
                ];
            }
        }
        return ['sessiontimes' => $sessiontimes];
    }
}
