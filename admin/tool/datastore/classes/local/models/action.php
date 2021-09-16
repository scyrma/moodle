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
 * Class to define a stored action
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore\local\models;

use core\persistent;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Action class
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class action extends persistent {

    /** Table name for the persistent. */
    const TABLE = 'tool_datastore_action';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'action' => array(
                'type' => PARAM_RAW
            ),
            'relateduserid'  => array(
                'type' => PARAM_INT
            ),
            'originalcourseid'  => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'originalprogramid'  => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'tenantid'  => array(
                'type' => PARAM_INT
            ),
        );
    }

    /**
     * Store a new action.
     *
     * @param string $eventname
     * @param string $relateduserid
     * @param string|null $originalcourseid
     * @param string|null $originalprogramid
     * @return mixed
     */
    public static function create_action($eventname, $relateduserid, $originalcourseid = null, $originalprogramid = null) {
        try {
            $data = new \stdClass();
            $data->action = $eventname;
            $data->relateduserid = $relateduserid;
            $data->originalcourseid = $originalcourseid;
            $data->originalprogramid = $originalprogramid;
            $data->tenantid = tenancy::get_tenant_id($relateduserid);
            $persistent = new action(0, $data);
            $result = $persistent->create();
            return $result->get('id');
        } catch (\Exception $ex) {
            debugging('Error create_action: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }
    }
}
