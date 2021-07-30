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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * condition_instance renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_dynamicrule\api;
use tool_dynamicrule\permission;
use tool_dynamicrule\condition_base;

/**
 * condition_instance renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class condition_instance implements \renderable, \templatable {

    /** @var \tool_dynamicrule\condition_base */
    protected $condition;
    /** @var bool */
    protected $editmode;

    /**
     * Constructor.
     *
     * @param condition_base $condition
     * @param bool $editmode
     */
    public function __construct(condition_base $condition, bool $editmode) {
        $this->condition = $condition;
        $this->editmode = $editmode;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $explodedclass = explode('\\', get_class($this->condition));

        $configisvalid = api::is_condition_configuration_valid($this->condition);
        if (!$configisvalid && !$this->condition->is_broken()) {
            $this->condition->mark_as_broken();
        } else if ($configisvalid && $this->condition->is_broken()) {
            $this->condition->mark_as_not_broken();
        }

        if ($this->condition->is_broken()) {
            $description = $this->condition->get_broken_description();
        } else {
            $description = $this->condition->get_description();
        }

        $params = [
            'instanceclass' => $explodedclass[0] . ':' . $explodedclass[3],
            'instanceid' => $this->condition->get_id(),
            'description' => $description,
            'elementid' => random_string(),
            'title' => $this->condition->get_title(),
            'deletelabel' => get_string('deletecondition', 'tool_dynamicrule'),
            'canedit' => ($this->editmode && permission::can_edit_condition($this->condition)),
            'candelete' => ($this->editmode && permission::can_delete_condition($this->condition)),
            'editlabel' => get_string('editcondition', 'tool_dynamicrule'),
            'notsavedlabel' => get_string('conditionnotsaved', 'tool_dynamicrule'),
            'isbroken' => $this->condition->is_broken(),
            'brokenlabel' => get_string('conditionisbroken', 'tool_dynamicrule'),
            'isscheduledtask' => !$this->condition->get_event_subscription()
        ];
        return $params;
    }
}
