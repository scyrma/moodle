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
 * Class sessions_view
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\output;

use mod_appointment\local\reports\sessions_list;
use tool_reportbuilder\system_report_factory;

/**
 * Class sessions_view
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
     * @return array|\stdClass
     */
    public function export_for_template(\renderer_base $output): array {
        $params = ['appointmentid' => $this->appointment->id];
        // Check if tool_reportbuilder is installed.
        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            $report = system_report_factory::create(\mod_appointment\sessions_list::class, $params);
            $table = $report->output();
        } else {
            $str = get_string('reportbuildersessionslist', 'mod_appointment');
            $table = html_writer::tag('div', $str, ['class' => 'alert alert-warning']);
        }

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->appointment->course);

        $context = [
            'sessionslisttable' => $table,
            'intro' => format_module_intro('appointment', $this->appointment, $cm->id),
            'signupsessionid' => $this->signupsessionid,
        ];

        if ($this->signupsessionid) {
            $session = appointment_get_session($this->signupsessionid);
            $context['cansignup'] = \mod_appointment\permission::can_signup($session, \context_module::instance($cm->id));
        }

        if (\mod_appointment\permission::can_edit_sessions(\context_module::instance($cm->id))) {
            $actionmenu = new \action_menu();
            $actionmenu->set_action_label(get_string('add'));
            $actionmenu->set_menu_trigger(get_string('add'));
            $actionmenu->set_alignment(\action_menu::TL, \action_menu::TR);

            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                new \pix_icon('i/calendar', get_string('appointment', 'appointment')),
                get_string('appointment', 'appointment'),
                ['data-cmid' => $cm->id, 'data-action' => 'addsession']);
            $actionmenu->add($link);

            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                new \pix_icon('a/view_list_active', get_string('multipleappointments', 'appointment')),
                get_string('multipleappointments', 'appointment'),
                ['data-cmid' => $cm->id, 'data-action' => 'addmultiple']);
            $actionmenu->add($link);

            $context['actions'] = $actionmenu->export_for_template($output);
        }

        return $context;
    }
}
