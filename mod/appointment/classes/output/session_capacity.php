<?php
// This file is part of Moodle - http://moodle.org/
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
 * Class session_capacity
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
 * session_capacity renderable class.
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_capacity implements \renderable, \templatable {

    /**
     * @var \stdClass $session
     */
    private $session;

    /**
     * @var \context_module $context
     */
    private $context;

    /**
     * Constructor
     *
     * @param \stdClass $session
     * @param \context_module $context
     */
    public function __construct(\stdClass $session, \context_module $context) {
        $this->session = $session;
        $this->context = $context;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $signupcount = appointment_get_num_attendees($this->session->id, MOD_APPOINTMENT_STATUS_APPROVED);
        $stats = $this->session->capacity - $signupcount;
        if (\mod_appointment\permission::can_view_attendees($this->context)) {
            $stats = $signupcount . ' / ' . $this->session->capacity;
            $title = get_string('capacity', 'appointment');
        } else {
            $stats = max(0, $stats);
            $title = get_string('seatsavailable', 'appointment');
        }
        return [
            'capacity' => $stats,
            'capacitytitle' => $title,
        ];
    }
}
