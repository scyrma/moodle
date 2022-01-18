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
 * Class session_signup
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
 * session_signup renderable class.
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_signup implements \renderable, \templatable {

    /**
     * @var \stdClass $session
     */
    private $session;

    /**
     * Constructor
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
        global $PAGE;
        $bookaction = '';
        $bookactiontitle = '';
        $iscancelling = false;

        if ($this->session->usersubmission) {
            // User is already signed up, display "Cancel".
            // TODO: check config allow cancelling.
            $bookaction = 'cancelsignup';
            $bookactiontitle = get_string('cancel', 'mod_appointment');
            $iscancelling = true;
        } else if (\mod_appointment\permission::can_signup($this->session, $PAGE->context)) {
            $signupcount = appointment_get_num_attendees($this->session->id, MOD_APPOINTMENT_STATUS_APPROVED);
            if ($signupcount >= $this->session->capacity) {
                $bookaction = 'joinwaitlist';
                $bookactiontitle = get_string('joinwaitlist', 'mod_appointment');
            } else {
                $bookaction = 'signup';
                $bookactiontitle = get_string('book', 'mod_appointment');
            }
        }

        return [
            'bookaction' => $bookaction,
            'bookactiontitle' => $bookactiontitle,
            'iscancelling' => $iscancelling,
            'sessionid' => $this->session->id,
            'detailstitle' => get_string('details', 'mod_appointment'),
        ];
    }
}
