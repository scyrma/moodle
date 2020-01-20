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
 * Class session_customfields
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * session_customfields renderable class.
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_customfields implements \renderable, \templatable {

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
        $handler = \mod_appointment\customfield\appointment_handler::create();
        $fields = [];
        foreach ($handler->export_instance_data($this->session->id) as $fielddata) {
            if (!empty($fielddata->get_value())) {
                $fields[] = ['name' => $fielddata->get_name(), 'value' => $fielddata->get_value()];
            }
        }
        return ['fields' => $fields];
    }
}
