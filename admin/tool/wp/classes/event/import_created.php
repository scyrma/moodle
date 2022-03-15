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
 * Event definition for creation of imports
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\event;

use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * The event that's triggered when an import is created
 *
 * @since       Moodle 3.8
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_created extends import_base {

    /**
     * Set the event data properties
     *
     * @return void
     */
    protected function init(): void {
        parent::init();

        $this->data['crud'] = 'c';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventimportcreated', 'tool_wp');
    }

    /**
     * Return non-localised event description
     *
     * @uses \tool_wp\importer_base::get_name()
     *
     * @return string
     */
    public function get_description(): string {
        $description = "The user with id '{$this->relateduserid}' created the import with id '{$this->objectid}'";

        $importerclass = $this->other['importer'];
        if (class_exists($importerclass) && is_subclass_of($importerclass, importer_base::class)) {
            $description .= ' using the \'' . $importerclass . '\' importer';
        }

        return $description;
    }
}
