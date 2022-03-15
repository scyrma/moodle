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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * outcome_instance renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_dynamicrule\api;
use tool_dynamicrule\permission;
use tool_dynamicrule\outcome_base;

/**
 * outcome_instance renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcome_instance implements \renderable, \templatable {

    /** @var \tool_dynamicrule\outcome_base */
    protected $outcome;

    /**
     * Constructor.
     *
     * @param outcome_base $outcome
     */
    public function __construct(outcome_base $outcome) {
        $this->outcome = $outcome;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $explodedclass = explode('\\', get_class($this->outcome));

        $configisvalid = api::is_outcome_configuration_valid($this->outcome);
        if (!$configisvalid && !$this->outcome->is_broken()) {
            $this->outcome->mark_as_broken();
        } else if ($configisvalid && $this->outcome->is_broken()) {
            $this->outcome->mark_as_not_broken();
        }

        if ($this->outcome->is_broken()) {
            $description = $this->outcome->get_broken_description();
        } else {
            $description = $this->outcome->get_description();
        }

        $params = [
            'instanceclass' => $explodedclass[0] . ':' . $explodedclass[3],
            'instanceid' => $this->outcome->get_id(),
            'description' => $description,
            'elementid' => random_string(),
            'title' => $this->outcome->get_title(),
            'deletelabel' => get_string('deleteoutcome', 'tool_dynamicrule'),
            'canedit' => permission::can_edit_outcome($this->outcome),
            'candelete' => permission::can_delete_outcome($this->outcome),
            'editlabel' => get_string('editoutcome', 'tool_dynamicrule'),
            'notsavedlabel' => get_string('outcomenotsaved', 'tool_dynamicrule'),
            'isbroken' => $this->outcome->is_broken(),
            'brokenlabel' => get_string('outcomeisbroken', 'tool_dynamicrule'),
        ];
        return $params;
    }
}
