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

use renderer_base;

/**
 * Class sessions_list_item
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions_list_item implements \templatable, \renderable {

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
     * @param \stdClass|false $usersubmission
     * @param \context_module $context
     */
    public function __construct(\stdClass $session, $usersubmission, \context_module $context) {
        $this->session = $session;
        $this->session->usersubmission = false;
        // Embed booking submission data if matching the session.
        if ($usersubmission && $this->session->id == $usersubmission->sessionid) {
            $this->session->usersubmission = $usersubmission;
        }
        $this->context = $context;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {

        $context = [
            'sessionid' => $this->session->id,
        ];

        // Add date and time.
        $datetime = new session_datetime($this->session);
        $context += $datetime->export_for_template($output);

        // Add capacity.
        $capacitycontext = new session_capacity($this->session, $this->context);
        $context += $capacitycontext->export_for_template($output);

        // Add status.
        $sessioncontext = new session_status($this->session);
        $context += $sessioncontext->export_for_template($output);

        // Add signup.
        $signupcontext = new session_signup($this->session, $this->context);
        $context += $signupcontext->export_for_template($output);

        // Add details.
        $detailscontext = new session_details($this->session, $this->context);
        $context += $detailscontext->export_for_template($output);

        // Add customfield.
        $customfieldcontext = new session_customfields($this->session);
        $context += $customfieldcontext->export_for_template($output);

        // Add actions.
        $actionmenu = $this->action_menu();
        $context['actions'] = $actionmenu->export_for_template($output);
        $context['hasmenu'] = !$actionmenu->is_empty();

        return $context;
    }

    /**
     * Adds an action menu.
     *
     * @return \action_menu
     */
    final protected function action_menu() {
        // Actions.
        $actionmenu = new \action_menu();
        $actionmenu->set_action_label(get_string('actions'));
        $actionmenu->set_constraint('[data-region=report-table]');

        if (\mod_appointment\permission::can_edit_sessions($this->context)) {
            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                new \pix_icon('t/edit', get_string('settings', 'appointment')),
                get_string('settings', 'appointment'),
                ['data-id' => $this->session->id, 'data-action' => 'editsession']);
            $actionmenu->add($link);
        }

        if (\mod_appointment\permission::can_view_attendees($this->context)) {
            $link = new \action_menu_link_secondary(
                new \moodle_url('attendees.php', ['s' => $this->session->id, 'backtoallsessions' => $this->session->appointment]),
                new \pix_icon('t/user', get_string('attendees', 'appointment')), get_string('attendees', 'appointment'));
            $actionmenu->add($link);
        }

        if (\mod_appointment\permission::can_edit_sessions($this->context)) {
            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                new \pix_icon('t/copy', get_string('duplicate', 'appointment')),
                get_string('duplicate', 'appointment'),
                ['data-id' => $this->session->id, 'data-action' => 'duplicatesession']);
            $actionmenu->add($link);

            $link = new \action_menu_link_secondary(
                new \moodle_url('#'),
                new \pix_icon('t/delete', get_string('delete', 'appointment')),
                get_string('delete', 'appointment'),
                ['data-id' => $this->session->id, 'data-action' => 'deletesession']);
            $actionmenu->add($link);
        }

        return $actionmenu;
    }
}
