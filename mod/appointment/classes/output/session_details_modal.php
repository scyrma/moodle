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
 * Class session_details_modal
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Class session_details_modal
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_details_modal implements \templatable, \renderable {

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
    public function export_for_template(\renderer_base $output): array {
        global $USER;

        $data = [];

        $datetime = new session_datetime($this->session);
        $data += $datetime->export_for_template($output);

        $capacity = new session_capacity($this->session, $this->context);
        $data += $capacity->export_for_template($output);

        if ($submissions = appointment_get_user_submissions($this->session->appointment, $USER->id)) {
            foreach ($submissions as $s) {
                if ($s->sessionid == $this->session->id) {
                    $this->session->usersubmission = $s;
                }
            }
        } else {
            $this->session->usersubmission = null;
        }

        $status = new session_status($this->session);
        $data += $status->export_for_template($output);

        $details = new session_details($this->session, $this->context);
        $data += $details->export_for_template($output);

        $customfields = new session_customfields($this->session);
        $data += $customfields->export_for_template($output);

        return $data;
    }
}
