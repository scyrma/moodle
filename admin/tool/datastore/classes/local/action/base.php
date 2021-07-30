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
 * Class containing the base abstract class for actions.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore\local\action;

use tool_datastore\local\models\action;
use tool_datastore\local\models\entity;

defined('MOODLE_INTERNAL') || die;

/**
 * Abstract class to be implemented for an action to be stored.
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class base {

    /** @var int Id of the row when the event is created */
    protected $datastoreeventid;
    /** @var \core\event\base Event object */
    private $event;
    /** @var string Event name */
    private $eventname;

    /**
     * base constructor.
     *
     * @param \core\event\base $event
     */
    public function __construct(\core\event\base $event) {
        $this->event = $event;
        $this->eventname = $event->target . '_' . $event->action;
    }

    /**
     * Store the event action.
     *
     * @return bool
     */
    public function save_action(): bool {
        try {
            $actionid = action::create_action(
                $this->eventname,
                $this->event->relateduserid,
                $this->event->courseid
            );
            $this->datastoreeventid = $actionid;
            $this->save_entities();
        } catch (\Exception $ex) {
            debugging('Error save_action: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }

        return true;

    }

    /**
     * For each entity related to the event store the data in the table.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    private function save_entities() {
        $relatedentities = $this->related_entities();

        foreach ($relatedentities as $relatedentity => $typeofentity) {
            $id = $this->get_event_property($relatedentity);
            $this->store_entity($typeofentity, $id);
        }

        return true;
    }


    /**
     * Get a property from the event object.
     *
     * @param string $property
     *
     * @return mixed
     */
    protected function get_event_property(string $property) {
        return $this->event->{$property};
    }

    /**
     * Store the entity if not exists.
     *
     * @param string $type
     * @param int $originalid
     *
     * @return bool
     */
    protected function store_entity(string $type, int $originalid) {
        try {

            // Check if the entity already exists.
            $exists = entity::record_exists_select("actionid = ? AND type = ? AND originalid = ?", array(
                'actionid' => $this->datastoreeventid,
                'type' => $type,
                'originalid' => $originalid)
            );

            if (!$exists) {
                entity::create_entity($this->datastoreeventid, $originalid, $type);
            }
        } catch (\Exception $ex) {
            debugging('Error store_entity: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }

        return true;
    }

    /**
     * Define an array of entities (table to search) and the field to get the main id in the event object.
     *
     * For example, for "user" table, "userid" is the field in the event object with the id of the "user" entity.
     *
     * @return mixed
     */
    abstract protected function related_entities();

    /**
     * Define the entity fields to index, indexed by the entity name => array of fields
     *
     * @return array
     */
    abstract protected static function get_fields_to_index(): array;
}
