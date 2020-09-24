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
 * Class department
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

defined('MOODLE_INTERNAL') || die();

use core\output\inplace_editable;

/**
 * Class department
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class department extends hierarchy {

    /** The table name. */
    const TABLE = 'tool_organisation_department';

    /**
     * Generates inplace_editable object for the name
     *
     * @return inplace_editable
     */
    public function get_editable_name() : inplace_editable {
        return new \core\output\inplace_editable(
            'tool_organisation',
            'department_name',
            $this->get('id'),
            permission::can_create_department($this),
            $this->get_formatted_name(),
            $this->get('name'),
            get_string('editdepartmentname', 'tool_organisation'),
            get_string('newnamefor', 'tool_organisation', $this->get_formatted_name())
        );
    }
}

