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
 * Class tab_form
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_wp\modal_form;

/**
 * Class tab_form
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class tab_form extends tab {

    /**
     * Name of the class that contains the form (must extend tool_wp\modal_form)
     *
     * @return string
     */
    abstract public function get_form_class(): string;

    /**
     * Export data for the template
     *
     * @param renderer_base $output
     * @return array|stdClass
     * @throws \coding_exception
     */
    public function export_for_template(renderer_base $output) {
        $classname = $this->get_form_class();
        if (!class_exists($classname) || !is_subclass_of($classname, modal_form::class)) {
            throw new \coding_exception('Form class does not exist or is invalid');
        }
        /** @var modal_form $form */
        $form = new $classname(null, null, 'post', '', [], true, $this->data);
        $form->set_data_for_modal();
        $data = $form->render();
        return [
            'form' => $data,
            'formclass' => $classname,
        ];
    }
}
