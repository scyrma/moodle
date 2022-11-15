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

namespace tool_datastore\local\models;

use core\persistent;
use tool_datastore\action_factory;

/**
 * Class to define a stored entity
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class entity extends persistent {

    /** Table name for the persistent. */
    const TABLE = 'tool_datastore_entity';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'actionid' => array(
                'type' => PARAM_INT
            ),
            'type' => array(
                'type' => PARAM_TEXT
            ),
            'originalid' => array(
                'type' => PARAM_INT
            )
        );
    }

    /**
     * Create an entity.
     *
     * @param int $datastoreeventid
     * @param int $originalid
     * @param string $type
     *
     * @return int id of the created entity or 0 if no entity was created
     */
    public static function create_entity(int $datastoreeventid, int $originalid, string $type) {
        if (($type === 'course' || $type === 'user')
                && 0 === $originalid) {

            return 0;
        }
        try {
            $data = new \stdClass();
            $data->actionid = $datastoreeventid;
            $data->originalid = $originalid;
            $data->type = $type;
            $persistent = new entity(0, $data);
            $result = $persistent->create();
            return $result->get('id');
        } catch (\Throwable $ex) {
            debugging('Error create_entity: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
            return 0;
        }
    }

    /**
     * When a new entity is created We need to store the entity snapshot and the fields.
     */
    protected function after_create() {
        $function = '\tool_datastore\api::get_' . $this->get('type');
        $datatosnapshot = $function($this->get('originalid'));
        $this->store_indexed_fields($this->get('type'), $datatosnapshot);
        $this->make_snapshot($datatosnapshot);
    }

    /**
     * Store the fields from the entity.
     *
     * @param string $typeofentity
     * @param array $datatosnapshot
     */
    protected function store_indexed_fields(string $typeofentity, array $datatosnapshot): void {
        try {
            $fields = $this->get_fields_to_index($typeofentity);
            foreach ($fields as $field) {
                if (is_array($datatosnapshot[$field])) {
                    foreach ($datatosnapshot[$field] as $keysubfield => $subfield) {
                        $this->store_field(
                            $this->get('id'),
                            "$field:$keysubfield",
                            $subfield
                        );
                    }
                } else {
                    $this->store_field(
                        $this->get('id'),
                        $field,
                        $datatosnapshot[$field]
                    );
                }
            }
        } catch (\Throwable $ex) {
            debugging('Error store_indexed_fields: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }
    }

    /**
     * Store a field.
     *
     * @param int $entityid
     * @param string $field
     * @param string|null $value
     */
    protected function store_field(int $entityid, string $field, ?string $value): void {
        $data = new \stdClass();
        $data->entityid = $entityid;
        $data->name = $field;
        $data->value = $value;
        $persistent = new field(0, $data);
        $persistent->save();
    }

    /**
     * Store the snapshot of the entity
     *
     * @param array $datatosnapshot
     * @return int
     */
    protected function make_snapshot(array $datatosnapshot): int {
        try {
            $data = new \stdClass();
            $data->entityid = $this->get('id');
            $encodedata = json_encode($datatosnapshot);
            $data->data = $encodedata;
            $data->hash = md5($encodedata);
            $persistent = new snapshot(0, $data);
            return $persistent->create()->get('id');
        } catch (\Throwable $ex) {
            debugging('Error make_snapshot: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
            return 0;
        }
    }

    /**
     * Get the fields from entity to index.
     *
     * @param string $entity
     * @return array
     */
    protected function get_fields_to_index(string $entity): array {
        $action = (new action($this->get('actionid')))->get('action');
        $classname = action_factory::get_action_class($action);

        return $classname::get_fields_to_index()[$entity];
    }
}
