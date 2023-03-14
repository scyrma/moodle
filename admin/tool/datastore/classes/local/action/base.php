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

namespace tool_datastore\local\action;

use tool_datastore\local\models\action;
use tool_datastore\local\models\entity;

/**
 * Abstract class to be implemented for an action to be stored.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
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
        } catch (\Throwable $ex) {
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
        $stored = [];

        foreach ($relatedentities as $relatedentity => $typeofentity) {
            $id = $this->get_event_property($relatedentity);
            if (empty($stored[$typeofentity]) || !in_array($id, $stored[$typeofentity])) {
                $this->store_entity($typeofentity, $id);
                $stored[$typeofentity] = isset($stored[$typeofentity]) ? array_merge($stored[$typeofentity], [$id]) : [$id];
            }
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
     */
    protected function store_entity(string $type, int $originalid) {
        entity::create_entity($this->datastoreeventid, $originalid, $type);
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
