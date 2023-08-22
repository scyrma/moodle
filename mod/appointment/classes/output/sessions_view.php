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

namespace mod_appointment\output;

use context_module;
use renderer_base;
use core_reportbuilder\system_report_factory;
use mod_appointment\reportbuilder\local\systemreports\sessions;

/**
 * Renderable for showing sessions of a given appointment activity
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions_view implements \templatable, \renderable {
    /**
     * @var \stdClass $appointment
     */
    private $appointment;

    /**
     * @var int $signupsessionid
     */
    private $signupsessionid;

    /**
     * Constructor
     *
     * @param \stdClass $appointment
     * @param int $signupsessionid Session to show sign up modal on page load.
     */
    public function __construct(\stdClass $appointment, int $signupsessionid = 0) {
        $this->appointment = $appointment;
        $this->signupsessionid = $signupsessionid;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->appointment->course);
        $contextmodule = context_module::instance($cm->id);

        // Create our report instance in the course module context.
        $report = system_report_factory::create(sessions::class, $contextmodule, '', '', 0, [
            'appointmentid' => $this->appointment->id,
        ]);

        $context = [
            'sessionslisttable' => $report->output(),
            'signupsessionid' => $this->signupsessionid,
        ];

        if ($this->signupsessionid) {
            $session = appointment_get_session($this->signupsessionid);
            $context['cansignup'] = \mod_appointment\permission::can_signup($session, \context_module::instance($cm->id));
        }

        if (\mod_appointment\permission::can_edit_sessions(\context_module::instance($cm->id))) {
            $actionmenu = new \action_menu();
            $actionmenu->set_action_label(get_string('adddots'));
            $actionmenu->set_menu_trigger(get_string('adddots'), 'btn btn-primary align-items-center mb-2');

            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                null,
                get_string('appointment', 'appointment'),
                ['data-cmid' => $cm->id, 'data-action' => 'addsession']);
            $actionmenu->add($link);

            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                null,
                get_string('multipleappointments', 'appointment'),
                ['data-cmid' => $cm->id, 'data-action' => 'addmultiple']);
            $actionmenu->add($link);

            $context['actions'] = $actionmenu->export_for_template($output);
        }

        return $context;
    }
}
