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
 * Class job
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\output;

use core\external\persistent_exporter;
use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\position;

defined('MOODLE_INTERNAL') || die();

/**
 * Class job
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job extends persistent_exporter {

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return \tool_organisation\job::class;
    }

    /**
     * Returns a list of objects that are related to this persistent.
     *
     * Only objects listed here can be cached in this object.
     *
     * The class name can be suffixed:
     * - with [] to indicate an array of values.
     * - with ? to indicate that 'null' is allowed.
     *
     * @return array of 'propertyname' => array('type' => classname, 'required' => true)
     */
    public static function define_related() {
        return [
            'position' => position::class . '?',
            'department' => department::class . '?',
        ];
    }

    /**
     * Get job position
     *
     * @return position
     */
    public function get_position() : position {
        if (empty($this->related['position'])) {
            $this->related['position'] = new position($this->persistent->get('positionid'));
        }
        return $this->related['position'];
    }

    /**
     * Get job department
     *
     * @return department
     */
    public function get_department() : department {
        if (empty($this->related['department'])) {
            $this->related['department'] = new department($this->persistent->get('departmentid'));
        }
        return $this->related['department'];
    }

    /**
     * Wrapper for a persistent getter, allows to get any property of a job
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key) {
        return $this->persistent->get($key);
    }

    /**
     * Is this job relevant to the manager's job
     *
     * Basically, does this job makes the user a subordinate of a user with a job $managerjob
     *
     * @param job $managerjob
     * @return bool
     */
    public function is_job_relevant(job $managerjob) {
        if ($managerjob->get_position()->is_global_manager()) {
            $mpath = $managerjob->get_position()->get('path');
            $path = $this->get_position()->get('path');
            if (strpos($path, $mpath . '/') === 0) {
                return true;
            }
        }
        if ($managerjob->get_position()->is_department_manager()) {
            $mpath = $managerjob->get_department()->get('path');
            $path = $this->get_department()->get('path');
            if (strpos($path, $mpath . '/') === 0 || $path === $mpath) {
                return true;
            }
        }
        return false;
    }

    /**
     * Other properties definition
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'positionname' => ['type' => PARAM_NOTAGS],
            'departmentname' => ['type' => PARAM_NOTAGS],
            'dates' => ['type' => PARAM_NOTAGS],
            'ended' => ['type' => PARAM_BOOL],
        ];
    }

    /**
     * Values for other properties
     * @param \renderer_base $output
     * @return array
     */
    protected function get_other_values(\renderer_base $output) {
        global $CFG;
        $format = get_string('strftimedatefullshort');
        if ($this->get('startdate') && $this->get('enddate')) {
            $a = new \stdClass();
            $a->from = \tool_organisation\local\helpers\format::jobdate($this->get('startdate'));
            $a->to = \tool_organisation\local\helpers\format::jobdate($this->get('enddate'));
            $dates = get_string('jobfromto', 'tool_organisation', $a);
        } else {
            $a = userdate($this->get('startdate'), $format, $CFG->timezone);
            $dates = get_string('jobfrom', 'tool_organisation', $a);
        }
        return [
            'positionname' => $this->get_position()->get_formatted_name(),
            'departmentname' => $this->get_department()->get_formatted_name(),
            'dates' => $dates,
            'ended' => $this->get('enddate') && $this->get('enddate') < helper::round_time(time()),
        ];
    }
}
